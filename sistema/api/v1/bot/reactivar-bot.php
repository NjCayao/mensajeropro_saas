<?php
// sistema/api/v1/bot/reactivar-bot.php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    $numero = $_POST['numero'] ?? '';
    
    if (!$numero) {
        Response::error('Número no especificado');
    }
    
    $empresaId = getEmpresaActual();
    
    // Opción 1: Reactivar intervención humana (si existe)
    $stmt = $pdo->prepare("
        UPDATE intervencion_humana 
        SET estado = 'bot_activo' 
        WHERE numero_cliente = ? AND empresa_id = ?
    ");
    $stmt->execute([$numero, $empresaId]);
    
    // Opción 2: Resolver escalamiento (si existe)
    $stmt = $pdo->prepare("
        UPDATE estados_conversacion 
        SET estado = 'resuelto',
            fecha_resuelto = NOW(),
            resuelto_por = ?
        WHERE numero_cliente = ? 
        AND estado = 'escalado_humano' 
        AND empresa_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $numero, $empresaId]);
    
    Response::success(['message' => 'Bot reactivado correctamente']);
    
} catch (Exception $e) {
    error_log("Error en reactivar-bot: " . $e->getMessage());
    Response::error('Error reactivando el bot');
}