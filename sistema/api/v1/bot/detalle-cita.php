<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;
    $empresa_id = getEmpresaActual();
    
    if (!$id) {
        throw new Exception('ID no proporcionado');
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM citas_bot 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$id, $empresa_id]);
    $cita = $stmt->fetch();
    
    if (!$cita) {
        throw new Exception('Cita no encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $cita
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}