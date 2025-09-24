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

$nombre = sanitize($_POST['nombre'] ?? '');
$mensaje = sanitize($_POST['mensaje'] ?? '');

if (empty($nombre) || empty($mensaje)) {
    jsonResponse(false, 'Nombre y mensaje son requeridos');
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO plantillas_mensajes (nombre, mensaje, uso_general, empresa_id) VALUES (?, ?, 1, ?)"
    );
    $stmt->execute([$nombre, $mensaje, getEmpresaActual()]);

    jsonResponse(true, 'Plantilla creada exitosamente');
} catch (Exception $e) {
    jsonResponse(false, 'Error al crear plantilla');
}
?>