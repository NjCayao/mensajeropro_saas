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

$id = $_POST['id'] ?? 0;
$codigo = $_POST['codigo'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$asunto = $_POST['asunto'] ?? '';
$contenido_html = $_POST['contenido_html'] ?? '';
$variables = $_POST['variables'] ?? '[]';
$descripcion = $_POST['descripcion'] ?? '';
$activa = isset($_POST['activa']) ? 1 : 0;

if (!$id || !$asunto || !$contenido_html) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
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
        UPDATE plantillas_email SET
            categoria = ?,
            asunto = ?,
            contenido_html = ?,
            variables = ?,
            descripcion = ?,
            activa = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $categoria,
        $asunto,
        $contenido_html,
        $variables,
        $descripcion,
        $activa,
        $id
    ]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', 'editar_plantilla_email', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], "Plantilla ID $id actualizada"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Plantilla actualizada correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error actualizando plantilla: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar plantilla']);
}