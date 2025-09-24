<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    $stmt = $pdo->prepare("
        SELECT mp.*, c.nombre as categoria_nombre
        FROM mensajes_programados mp
        LEFT JOIN categorias c ON mp.categoria_id = c.id AND c.empresa_id = mp.empresa_id
        WHERE mp.id = ? AND mp.usuario_id = ? AND mp.empresa_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id'], getEmpresaActual()]);
    $mensaje = $stmt->fetch();
    
    if ($mensaje) {
        // Verificar si es un mensaje individual
        if (!$mensaje['enviar_a_todos'] && !$mensaje['categoria_id']) {
            $stmt = $pdo->prepare("
                SELECT contacto_id 
                FROM mensajes_programados_individuales 
                WHERE mensaje_programado_id = ?
            ");
            $stmt->execute([$id]);
            $individual = $stmt->fetch();
            
            if ($individual) {
                $mensaje['tipo_envio'] = 'individual';
                $mensaje['contacto_id'] = $individual['contacto_id'];
            }
        } else if ($mensaje['enviar_a_todos']) {
            $mensaje['tipo_envio'] = 'todos';
        } else if ($mensaje['categoria_id']) {
            $mensaje['tipo_envio'] = 'categoria';
        }
        
        jsonResponse(true, 'Mensaje encontrado', $mensaje);
    } else {
        jsonResponse(false, 'Mensaje no encontrado');
    }
    
} catch (Exception $e) {
    error_log("Error al obtener mensaje: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener mensaje');
}
?>