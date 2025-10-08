<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Log para depuración (comentar en producción)
error_log("POST recibido en crear-template: " . print_r($_POST, true));

// Validaciones
$tipo_negocio = trim($_POST['tipo_negocio'] ?? '');
$tipo_bot = $_POST['tipo_bot'] ?? 'ventas';
$nombre_template = trim($_POST['nombre_template'] ?? '');
$prompt_template = trim($_POST['prompt_template'] ?? '');

if (!$tipo_negocio || !$nombre_template || !$prompt_template) {
    echo json_encode([
        'success' => false, 
        'message' => 'Datos incompletos. Verifica: tipo negocio, nombre y prompt.'
    ]);
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
        INSERT INTO bot_templates (
            tipo_negocio,
            tipo_bot,
            nombre_template,
            personalidad_bot,
            instrucciones_ventas,
            instrucciones_citas,
            informacion_negocio_ejemplo,
            configuracion_adicional,
            mensaje_notificacion_escalamiento,
            mensaje_notificacion_ventas,
            mensaje_notificacion_citas,
            activo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $tipo_negocio,
        $tipo_bot,
        $nombre_template,
        $prompt_template, // Se guarda en columna personalidad_bot
        emptyToNull($_POST['instrucciones_ventas'] ?? null),
        emptyToNull($_POST['instrucciones_citas'] ?? null),
        emptyToNull($_POST['informacion_negocio_ejemplo'] ?? null),
        $configuracion_adicional, // Ya procesado arriba
        emptyToNull($_POST['mensaje_notificacion_escalamiento'] ?? null),
        emptyToNull($_POST['mensaje_notificacion_ventas'] ?? null),
        emptyToNull($_POST['mensaje_notificacion_citas'] ?? null),
        isset($_POST['activo']) ? 1 : 0
    ]);
    
    if (!$result) {
        throw new Exception('Error al ejecutar INSERT: ' . print_r($stmt->errorInfo(), true));
    }
    
    $template_id = $pdo->lastInsertId();
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', 'crear_bot_template', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], "Template '$nombre_template' creado"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Template creado correctamente',
        'id' => $template_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error creando template: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al crear template: ' . $e->getMessage()
    ]);
}