<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

$id = intval($_POST['id'] ?? 0);
$nombre = sanitize($_POST['nombre'] ?? '');
$mensaje = sanitize($_POST['mensaje'] ?? '');
$categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
$uso_general = intval($_POST['uso_general'] ?? 0);

// Validaciones
if ($id <= 0 || empty($nombre) || empty($mensaje)) {
    jsonResponse(false, 'Datos incompletos');
}

if (strlen($nombre) > 100) {
    jsonResponse(false, 'El nombre es demasiado largo (máximo 100 caracteres)');
}

try {
    // Verificar que existe
    $stmt = $pdo->prepare("SELECT id FROM plantillas_mensajes WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);

    if (!$stmt->fetch()) {
        jsonResponse(false, 'Plantilla no encontrada');
    }

    // Verificar nombre único (excepto la misma plantilla)
    $stmt = $pdo->prepare("SELECT id FROM plantillas_mensajes WHERE nombre = ? AND id != ? AND empresa_id = ?");
    $stmt->execute([$nombre, $id, getEmpresaActual()]);

    if ($stmt->fetch()) {
        jsonResponse(false, 'Ya existe otra plantilla con ese nombre');
    }

    // Si tiene categoría, verificar que existe
    if ($categoria_id) {
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND activo = 1 AND empresa_id = ?");
        $stmt->execute([$categoria_id, getEmpresaActual()]);

        if (!$stmt->fetch()) {
            jsonResponse(false, 'La categoría seleccionada no existe');
        }
    }

    // Detectar variables en el mensaje
    $variables = [];
    if (strpos($mensaje, '{{nombre}}') !== false) $variables[] = 'nombre';
    if (strpos($mensaje, '{{categoria}}') !== false) $variables[] = 'categoria';
    if (strpos($mensaje, '{{precio}}') !== false) $variables[] = 'precio';
    if (strpos($mensaje, '{{fecha}}') !== false) $variables[] = 'fecha';
    if (strpos($mensaje, '{{hora}}') !== false) $variables[] = 'hora';

    // Actualizar plantilla
    $stmt = $pdo->prepare("
        UPDATE plantillas_mensajes 
        SET nombre = ?, mensaje = ?, variables = ?, categoria_id = ?, uso_general = ?
        WHERE id = ? AND empresa_id = ?
    ");

    $stmt->execute([
        $nombre,
        $mensaje,
        json_encode($variables),
        $categoria_id,
        $uso_general,
        $id,
        getEmpresaActual()
    ]);

    logActivity($pdo, 'plantillas', 'editar', "Plantilla editada: $nombre");

    jsonResponse(true, 'Plantilla actualizada exitosamente');
} catch (Exception $e) {
    error_log("Error al editar plantilla: " . $e->getMessage());
    jsonResponse(false, 'Error al actualizar plantilla');
}
