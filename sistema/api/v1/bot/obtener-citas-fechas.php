<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $empresa_id = getEmpresaActual();
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT * FROM citas_bot 
        WHERE empresa_id = ? AND fecha_cita = ?
        ORDER BY hora_cita
    ");
    $stmt->execute([$empresa_id, $fecha]);
    $citas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $citas
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}