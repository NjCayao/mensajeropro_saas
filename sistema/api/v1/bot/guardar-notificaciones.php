<?php
// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    $empresa_id = getEmpresaActual();
    
    // CRÍTICO: Obtener el tipo de bot actual
    $stmt = $pdo->prepare("SELECT tipo_bot FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $tipo_bot = $stmt->fetchColumn();
    
    if (!$tipo_bot) {
        Response::error('No se encontró configuración de bot');
    }
    
    // Obtener datos del POST
    $numeros_notificacion = $_POST['numeros_notificacion'] ?? '';
    
    // Escalamiento SIEMPRE se guarda (todos los tipos lo usan)
    $notificar_escalamiento = isset($_POST['notificar_escalamiento']) ? 1 : 0;
    $mensaje_escalamiento = $_POST['mensaje_escalamiento'] ?? '';
    
    // Ventas: Solo para tipo "ventas" o "soporte"
    if ($tipo_bot === 'ventas' || $tipo_bot === 'soporte') {
        $notificar_ventas = isset($_POST['notificar_ventas']) ? 1 : 0;
        $mensaje_ventas = $_POST['mensaje_ventas'] ?? '';
    } else {
        // Si NO es bot de ventas, FORZAR a 0
        $notificar_ventas = 0;
        $mensaje_ventas = null;
    }
    
    // Citas: Solo para tipo "citas" o "soporte"
    if ($tipo_bot === 'citas' || $tipo_bot === 'soporte') {
        $notificar_citas = isset($_POST['notificar_citas']) ? 1 : 0;
        $mensaje_citas = $_POST['mensaje_citas'] ?? '';
    } else {
        // Si NO es bot de citas, FORZAR a 0
        $notificar_citas = 0;
        $mensaje_citas = null;
    }
    
    // Convertir números a array JSON
    $numeros_array = [];
    if (!empty($numeros_notificacion)) {
        $numeros_array = array_map('trim', explode(',', $numeros_notificacion));
        $numeros_array = array_filter($numeros_array);
    }
    
    // Verificar si existe registro
    $stmt = $pdo->prepare("SELECT id FROM notificaciones_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        // UPDATE
        $sql = "UPDATE notificaciones_bot SET
                numeros_notificacion = ?,
                notificar_escalamiento = ?,
                mensaje_escalamiento = ?,
                notificar_ventas = ?,
                mensaje_ventas = ?,
                notificar_citas = ?,
                mensaje_citas = ?,
                updated_at = NOW()
            WHERE empresa_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            json_encode($numeros_array),
            $notificar_escalamiento,
            $mensaje_escalamiento,
            $notificar_ventas,
            $mensaje_ventas,
            $notificar_citas,
            $mensaje_citas,
            $empresa_id
        ]);
    } else {
        // INSERT
        $sql = "INSERT INTO notificaciones_bot 
                (empresa_id, numeros_notificacion, notificar_escalamiento, mensaje_escalamiento,
                 notificar_ventas, mensaje_ventas, notificar_citas, mensaje_citas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $empresa_id,
            json_encode($numeros_array),
            $notificar_escalamiento,
            $mensaje_escalamiento,
            $notificar_ventas,
            $mensaje_ventas,
            $notificar_citas,
            $mensaje_citas
        ]);
    }
    
    if ($result) {
        Response::success(null, 'Notificaciones guardadas correctamente');
    } else {
        Response::error('Error al guardar las notificaciones');
    }
    
} catch (Exception $e) {
    error_log("Error guardando notificaciones: " . $e->getMessage());
    Response::error('Error en el servidor: ' . $e->getMessage());
}