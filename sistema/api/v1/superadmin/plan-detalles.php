<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM planes WHERE id = ?");
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'Plan no encontrado']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $plan
    ]);
    
} catch (Exception $e) {
    error_log("Error en plan-detalles: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener detalles']);
}