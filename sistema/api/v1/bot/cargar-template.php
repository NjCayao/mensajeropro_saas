<?php
// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

try {
    $template_id = intval($_GET['id'] ?? 0);
    
    if (!$template_id) {
        Response::error('ID de template no especificado');
    }
    
    // Obtener template
    $stmt = $pdo->prepare("
        SELECT * FROM bot_templates 
        WHERE id = ? AND activo = 1
    ");
    
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();
    
    if (!$template) {
        Response::error('Template no encontrado');
    }
    
    // Parsear JSONs
    $template['respuestas_rapidas_template'] = json_decode($template['respuestas_rapidas_template'], true);
    $template['configuracion_adicional'] = json_decode($template['configuracion_adicional'], true);
    
    Response::success($template);
    
} catch (Exception $e) {
    error_log("Error cargando template: " . $e->getMessage());
    Response::error('Error obteniendo template');
}