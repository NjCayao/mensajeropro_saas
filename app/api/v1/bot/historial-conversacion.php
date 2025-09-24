<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

try {
    $numero = $_GET['numero'] ?? '';
    
    if (!$numero) {
        Response::error('Número no especificado');
    }
    
    // Obtener últimas conversaciones
    $stmt = $pdo->prepare("
        SELECT mensaje_cliente, respuesta_bot, fecha_hora
        FROM conversaciones_bot
        WHERE numero_cliente = ? AND empresa_id = ?
        ORDER BY fecha_hora DESC
        LIMIT 50
    ");
    
    $stmt->execute([$numero, getEmpresaActual()]);
    $conversaciones = array_reverse($stmt->fetchAll());
    
    Response::success($conversaciones);
    
} catch (Exception $e) {
    error_log("Error en historial-conversacion: " . $e->getMessage());
    Response::error('Error obteniendo historial');
}