<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $empresa_id = getEmpresaActual();
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? null;
    
    $estados_validos = ['agendada', 'confirmada', 'cancelada', 'completada'];
    
    if (!$id || !in_array($estado, $estados_validos)) {
        throw new Exception('Datos inválidos');
    }
    
    $stmt = $pdo->prepare("
        UPDATE citas_bot 
        SET estado = ? 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$estado, $id, $empresa_id]);
    
    echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}