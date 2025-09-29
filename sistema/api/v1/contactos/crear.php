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

// Obtener datos
$nombre = sanitize($_POST['nombre'] ?? '');
$numero = sanitize($_POST['numero'] ?? '');
$categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
$notas = sanitize($_POST['notas'] ?? '');
$activo = intval($_POST['activo'] ?? 1);

// Validaciones
if (empty($nombre)) {
    jsonResponse(false, 'El nombre es requerido');
}

if (empty($numero)) {
    jsonResponse(false, 'El número es requerido');
}

// Validar formato del número
if (!validatePhone($numero)) {
    jsonResponse(false, 'El formato del número no es válido. Use formato internacional: +51999999999');
}

// Formatear número
$numero = formatPhone($numero);

try {
    if (verificarLimiteAlcanzado('contactos')) {
        echo json_encode([
            'success' => false,
            'message' => mostrarMensajeLimite('contactos'),
            'limite_alcanzado' => true
        ]);
        exit;
    }
    // Verificar si el número ya existe
    $stmt = $pdo->prepare("SELECT id, nombre FROM contactos WHERE numero = ? AND empresa_id = ?");
    $stmt->execute([$numero, getEmpresaActual()]);
    if ($existente = $stmt->fetch()) {
        jsonResponse(false, "El número ya existe, registrado como: {$existente['nombre']}");
    }

    // Si se especificó categoría, verificar que existe
    if ($categoria_id) {
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND activo = 1 AND empresa_id = ?");
        $stmt->execute([$categoria_id, getEmpresaActual()]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'La categoría seleccionada no existe o está inactiva');
        }
    }

    // Insertar contacto
    $stmt = $pdo->prepare("
        INSERT INTO contactos (nombre, numero, categoria_id, notas, activo, empresa_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$nombre, $numero, $categoria_id, $notas, $activo, getEmpresaActual()]);

    // Log
    logActivity($pdo, 'contactos', 'crear', "Contacto creado: $nombre ($numero)");

    jsonResponse(true, 'Contacto creado exitosamente', ['id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log("Error al crear contacto: " . $e->getMessage());
    jsonResponse(false, 'Error al crear el contacto');
}
