<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../../../includes/plan-limits.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    jsonResponse(false, 'Token de seguridad inválido');
}

// Verificar archivo
if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Error al cargar el archivo');
}

$archivo = $_FILES['archivo_csv'];
$categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
$actualizar_existentes = isset($_POST['actualizar_existentes']);

// Verificar extensión
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    jsonResponse(false, 'Solo se permiten archivos CSV');
}

// Verificar categoría si se especificó
if ($categoria_id) {
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND activo = 1 AND empresa_id = ?");
    $stmt->execute([$categoria_id, getEmpresaActual()]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'La categoría seleccionada no existe o está inactiva');
    }
}

try {
    $pdo->beginTransaction();

    // Leer archivo CSV
    $handle = fopen($archivo['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('No se pudo leer el archivo');
    }

    // Contar primero cuántos contactos se van a importar
    $temp_handle = fopen($archivo['tmp_name'], 'r');
    $total_lineas = 0;
    $es_encabezado_temp = false;

    if ($temp_handle) {
        $primera = fgetcsv($temp_handle);
        $es_encabezado_temp = (
            strtolower($primera[0] ?? '') === 'nombre' ||
            strtolower($primera[0] ?? '') === 'name'
        );

        while (($data = fgetcsv($temp_handle)) !== false) {
            if (count($data) >= 2 && !empty(trim($data[0])) && !empty(trim($data[1]))) {
                $total_lineas++;
            }
        }
        fclose($temp_handle);
    }

    // Verificar límite antes de procesar
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE empresa_id = ?");
    $stmt_count->execute([getEmpresaActual()]);
    $contactos_actuales = $stmt_count->fetchColumn();

    $limite = obtenerLimite('contactos');

    if ($limite != PHP_INT_MAX) {
        $disponibles = max(0, $limite - $contactos_actuales);

        if ($total_lineas > $disponibles) {
            $pdo->rollBack();
            fclose($handle);

            jsonResponse(
                false,
                "Tu plan permite hasta " . number_format($limite) . " contactos. " .
                    "Actualmente tienes " . number_format($contactos_actuales) . ". " .
                    "El archivo contiene " . number_format($total_lineas) . " contactos válidos. " .
                    "Solo puedes agregar " . number_format($disponibles) . " más. " .
                    '<a href="' . url('cliente/mi-plan') . '" target="_blank">Actualizar plan</a>',
                ['limite_alcanzado' => true]
            );
        }
    }

    // Saltar primera línea si es encabezado
    $primera_linea = fgetcsv($handle);
    $es_encabezado = (
        strtolower($primera_linea[0] ?? '') === 'nombre' ||
        strtolower($primera_linea[0] ?? '') === 'name'
    );

    if (!$es_encabezado) {
        // Si no es encabezado, volver al inicio
        rewind($handle);
    }

    $importados = 0;
    $actualizados = 0;
    $errores = 0;
    $linea_num = $es_encabezado ? 2 : 1;

    // Preparar consultas
    $stmtCheck = $pdo->prepare("SELECT id FROM contactos WHERE numero = ? AND empresa_id = ?");
    $stmtInsert = $pdo->prepare("
        INSERT INTO contactos (nombre, numero, categoria_id, notas, activo, empresa_id) 
        VALUES (?, ?, ?, ?, 1, ?)
    ");
    $stmtUpdate = $pdo->prepare("
        UPDATE contactos 
        SET nombre = ?, categoria_id = ?, notas = ? 
        WHERE numero = ? AND empresa_id = ?
    ");

    // Procesar cada línea
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 2) {
            $errores++;
            $linea_num++;
            continue;
        }

        $nombre = trim($data[0] ?? '');
        $numero = trim($data[1] ?? '');
        $notas = trim($data[2] ?? '');

        // Validar datos
        if (empty($nombre) || empty($numero)) {
            $errores++;
            $linea_num++;
            continue;
        }

        // Validar y formatear número
        if (!validatePhone($numero)) {
            $errores++;
            $linea_num++;
            continue;
        }

        $numero = formatPhone($numero);

        try {
            // Verificar si existe PRIMERO
            $stmtCheck->execute([$numero, getEmpresaActual()]);
            $existe = $stmtCheck->fetch();

            // DESPUÉS verificar límite (solo si no existe el contacto)
            if (!$existe && $limite != PHP_INT_MAX && ($contactos_actuales + $importados) >= $limite) {
                $errores++;
                $linea_num++;
                continue; // Saltar esta línea si ya se alcanzó el límite
            }

            if ($existe && $actualizar_existentes) {
                // Actualizar
                $stmtUpdate->execute([$nombre, $categoria_id, $notas, $numero, getEmpresaActual()]);
                $actualizados++;
            } elseif (!$existe) {
                // Insertar nuevo
                $stmtInsert->execute([$nombre, $numero, $categoria_id, $notas, getEmpresaActual()]);
                $importados++;
            }
        } catch (Exception $e) {
            $errores++;
        }

        $linea_num++;
    }

    fclose($handle);
    $pdo->commit();

    // Log
    logActivity(
        $pdo,
        'contactos',
        'importar',
        "CSV importado: $importados nuevos, $actualizados actualizados, $errores errores"
    );

    $mensaje = "<strong>Resumen de importación:</strong><br>";
    $mensaje .= "• Contactos nuevos: $importados<br>";
    if ($actualizar_existentes) {
        $mensaje .= "• Contactos actualizados: $actualizados<br>";
    }
    if ($errores > 0) {
        $mensaje .= "• Líneas con errores: $errores<br>";
    }

    jsonResponse(true, $mensaje);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al importar CSV: " . $e->getMessage());
    jsonResponse(false, 'Error al procesar el archivo: ' . $e->getMessage());
}
