<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        Response::error('ID no proporcionado');
    }
    
    $sql = "UPDATE bot_templates SET
            tipo_negocio = ?,
            tipo_bot = ?,
            nombre_template = ?,
            personalidad_bot = ?,
            instrucciones_ventas = ?,
            instrucciones_citas = ?,
            informacion_negocio_ejemplo = ?,
            mensaje_notificacion_escalamiento = ?,
            mensaje_notificacion_ventas = ?,
            mensaje_notificacion_citas = ?,
            configuracion_adicional = ?,
            activo = ?
        WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $_POST['tipo_negocio'] ?? '',
        $_POST['tipo_bot'] ?? 'ventas',
        $_POST['nombre_template'] ?? '',
        $_POST['personalidad_bot'] ?? '',
        $_POST['instrucciones_ventas'] ?? null,
        $_POST['instrucciones_citas'] ?? null,
        $_POST['informacion_negocio_ejemplo'] ?? '',
        $_POST['mensaje_notificacion_escalamiento'] ?? null,
        $_POST['mensaje_notificacion_ventas'] ?? null,
        $_POST['mensaje_notificacion_citas'] ?? null,
        $_POST['configuracion_adicional'] ?? null,
        isset($_POST['activo']) ? 1 : 0,
        $id
    ]);
    
    if ($result) {
        Response::success(null, 'Template actualizado correctamente');
    } else {
        Response::error('Error al actualizar template');
    }
    
} catch (Exception $e) {
    error_log("Error actualizando template: " . $e->getMessage());
    Response::error('Error en el servidor');
}