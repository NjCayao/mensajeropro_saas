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
        SELECT * FROM servicios_disponibles 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$id, $empresa_id]);
    $servicio = $stmt->fetch();
    
    if (!$servicio) {
        throw new Exception('Servicio no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $servicio
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}