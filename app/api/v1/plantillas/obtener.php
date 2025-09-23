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

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre as categoria_nombre 
        FROM plantillas_mensajes p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $plantilla = $stmt->fetch();
    
    if ($plantilla) {
        // Incrementar contador de uso solo si se obtiene para usar (no para editar)
        $para_usar = isset($_GET['usar']) && $_GET['usar'] == '1';
        
        if ($para_usar) {
            $pdo->prepare("UPDATE plantillas_mensajes SET veces_usado = veces_usado + 1 WHERE id = ?")
                ->execute([$id]);
        }
        
        jsonResponse(true, 'Plantilla encontrada', $plantilla);
    } else {
        jsonResponse(false, 'Plantilla no encontrada');
    }
    
} catch (Exception $e) {
    error_log("Error al obtener plantilla: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener plantilla');
}
?>