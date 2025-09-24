<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    // Verificar que el mensaje es del usuario y está pendiente
    $stmt = $pdo->prepare("
        SELECT titulo FROM mensajes_programados 
        WHERE id = ? AND usuario_id = ? AND estado = 'pendiente' AND empresa_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id'], getEmpresaActual()]);
    $mensaje = $stmt->fetch();
    
    if (!$mensaje) {
        jsonResponse(false, 'No se puede cancelar este mensaje');
    }
    
    // Actualizar estado a cancelado
    $stmt = $pdo->prepare("
        UPDATE mensajes_programados 
        SET estado = 'cancelado'
        WHERE id = ? AND usuario_id = ? AND estado = 'pendiente' AND empresa_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id'], getEmpresaActual()]);

    if ($stmt->rowCount() > 0) {
        logActivity($pdo, 'programados', 'cancelar', "Mensaje programado cancelado: {$mensaje['titulo']}");
        jsonResponse(true, 'Mensaje cancelado exitosamente');
    } else {
        jsonResponse(false, 'No se pudo cancelar el mensaje');
    }
    
} catch (Exception $e) {
    error_log("Error al cancelar mensaje: " . $e->getMessage());
    jsonResponse(false, 'Error al cancelar mensaje');
}
?>