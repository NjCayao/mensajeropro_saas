<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'No autorizado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die(json_encode(['success' => false, 'error' => 'Token de seguridad inválido']));
}

$contacto_id = intval($_POST['contacto_id'] ?? 0);
$mensaje = sanitize($_POST['mensaje'] ?? '');

if ($contacto_id <= 0 || empty($mensaje)) {
    die(json_encode(['success' => false, 'error' => 'Datos inválidos']));
}

try {
    // Guardar en historial_mensajes
    $stmt = $pdo->prepare("
        INSERT INTO historial_mensajes 
        (contacto_id, mensaje, tipo, estado, fecha, empresa_id) 
        VALUES (?, ?, 'saliente', 'enviado', NOW(), ?)
    ");

    $stmt->execute([
        $contacto_id,
        $mensaje,
        getEmpresaActual()
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log("Error guardando mensaje: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}