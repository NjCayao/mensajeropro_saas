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
    $stmt = $pdo->prepare("SELECT * FROM bot_templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch();
    
    if (!$template) {
        echo json_encode(['success' => false, 'message' => 'Template no encontrado']);
        exit;
    }
    
    // Parsear JSON si existe
    if ($template['configuracion_adicional']) {
        $template['configuracion_adicional'] = json_decode($template['configuracion_adicional'], true);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $template
    ]);
    
} catch (Exception $e) {
    error_log("Error obteniendo template: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener template']);
}