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

$codigo = $_POST['codigo'] ?? '';
$categoria = $_POST['categoria'] ?? 'notificacion';
$asunto = $_POST['asunto'] ?? '';
$contenido_html = $_POST['contenido_html'] ?? '';
$variables = $_POST['variables'] ?? '[]';
$descripcion = $_POST['descripcion'] ?? '';
$activa = isset($_POST['activa']) ? 1 : 0;

// Validaciones
if (!$codigo || !$asunto || !$contenido_html) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Validar que el código sea único
$stmt = $pdo->prepare("SELECT id FROM plantillas_email WHERE codigo = ?");
$stmt->execute([$codigo]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'El código ya existe']);
    exit;
}

// Validar JSON de variables
if ($variables) {
    json_decode($variables);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Variables debe ser JSON válido']);
        exit;
    }
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO plantillas_email 
        (codigo, categoria, asunto, contenido_html, variables, descripcion, activa, editable)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([
        $codigo,
        $categoria,
        $asunto,
        $contenido_html,
        $variables,
        $descripcion,
        $activa
    ]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', 'crear_plantilla_email', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], "Plantilla $codigo creada"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Plantilla creada correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error creando plantilla: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al crear plantilla']);
}