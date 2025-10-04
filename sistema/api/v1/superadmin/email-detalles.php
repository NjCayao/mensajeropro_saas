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
    $stmt = $pdo->prepare("SELECT * FROM plantillas_email WHERE id = ?");
    $stmt->execute([$id]);
    $plantilla = $stmt->fetch();
    
    if (!$plantilla) {
        echo json_encode(['success' => false, 'message' => 'Plantilla no encontrada']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $plantilla
    ]);
    
} catch (Exception $e) {
    error_log("Error en email-detalles: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener plantilla']);
}