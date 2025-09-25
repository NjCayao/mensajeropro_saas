<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

try {
    $empresa_id = getEmpresaActual();
    
    // Usar prepare() en lugar de query() cuando tienes parámetros
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contactos WHERE activo = 1 AND empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $result = $stmt->fetch();
    
    jsonResponse(true, 'Total obtenido', [
        'total' => intval($result['total'])
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener total de contactos: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener total de contactos');
}
?>