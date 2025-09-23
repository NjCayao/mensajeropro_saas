<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

try {
    // Mensajes de hoy
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM historial_mensajes 
        WHERE DATE(fecha_creacion) = CURDATE()
    ");
    $mensajes_hoy = $stmt->fetchColumn();

    // Enviados
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM historial_mensajes 
        WHERE estado = 'enviado'
    ");
    $enviados = $stmt->fetchColumn();

    // Pendientes
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM cola_mensajes 
        WHERE estado = 'pendiente'
    ");
    $pendientes = $stmt->fetchColumn();

    // Errores
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM cola_mensajes 
        WHERE estado = 'error'
    ");
    $errores = $stmt->fetchColumn();

    jsonResponse(true, 'Estadísticas obtenidas', [
        'mensajes_hoy' => $mensajes_hoy,
        'enviados' => $enviados,
        'pendientes' => $pendientes,
        'errores' => $errores
    ]);

} catch (Exception $e) {
    jsonResponse(false, 'Error al obtener estadísticas');
}
?>