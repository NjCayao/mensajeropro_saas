<?php
// ✅ CORREGIDO: Usar superadmin_session_check
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    // ✅ HELPER: Convertir strings vacíos a NULL
    function emptyToNull($value) {
        return (isset($value) && trim($value) !== '') ? trim($value) : null;
    }
    
    // Procesar configuracion_adicional
    $configuracion_adicional = emptyToNull($_POST['configuracion_adicional'] ?? null);
    
    // Validar JSON si existe
    if ($configuracion_adicional !== null) {
        json_decode($configuracion_adicional);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'success' => false, 
                'message' => 'Configuración adicional debe ser JSON válido: ' . json_last_error_msg()
            ]);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE bot_templates SET
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
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $_POST['tipo_negocio'] ?? '',
        $_POST['tipo_bot'] ?? 'ventas',
        $_POST['nombre_template'] ?? '',
        $_POST['prompt_template'] ?? '', // ✅ prompt_template se guarda en personalidad_bot
        emptyToNull($_POST['instrucciones_ventas'] ?? null),
        emptyToNull($_POST['instrucciones_citas'] ?? null),
        emptyToNull($_POST['informacion_negocio_ejemplo'] ?? null),
        emptyToNull($_POST['mensaje_notificacion_escalamiento'] ?? null),
        emptyToNull($_POST['mensaje_notificacion_ventas'] ?? null),
        emptyToNull($_POST['mensaje_notificacion_citas'] ?? null),
        $configuracion_adicional, // Ya procesado arriba
        isset($_POST['activo']) ? 1 : 0,
        $id
    ]);
    
    if ($result) {
        // Log
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
            VALUES (?, 'superadmin', 'actualizar_bot_template', ?)
        ");
        $stmt->execute([$_SESSION['user_id'], "Template ID $id actualizado"]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template actualizado correctamente'
        ]);
    } else {
        throw new Exception('Error al ejecutar UPDATE');
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error actualizando template: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar template']);
}