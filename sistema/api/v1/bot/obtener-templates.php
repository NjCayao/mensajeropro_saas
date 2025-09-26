<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

try {
    $tipo_bot = $_GET['tipo_bot'] ?? null;
    
    $sql = "SELECT * FROM bot_templates WHERE activo = 1";
    $params = [];
    
    if ($tipo_bot) {
        $sql .= " AND tipo_bot = ?";
        $params[] = $tipo_bot;
    }
    
    $sql .= " ORDER BY tipo_negocio, nombre_template";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $templates = $stmt->fetchAll();
    
    Response::success($templates);
    
} catch (Exception $e) {
    error_log("Error obteniendo templates: " . $e->getMessage());
    Response::error('Error obteniendo templates');
}