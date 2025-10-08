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

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

// Log para depuración
error_log("Intentando eliminar template ID: $id");

try {
    $pdo->beginTransaction();
    
    // Obtener nombre del template antes de eliminar
    $stmt = $pdo->prepare("SELECT nombre_template FROM bot_templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch();
    
    if (!$template) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Template no encontrado']);
        exit;
    }
    
    // Eliminar template
    $stmt = $pdo->prepare("DELETE FROM bot_templates WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if (!$result) {
        throw new Exception('Error al ejecutar DELETE: ' . print_r($stmt->errorInfo(), true));
    }
    
    // Verificar que se eliminó
    if ($stmt->rowCount() === 0) {
        throw new Exception('No se eliminó ninguna fila (posible problema de permisos o ID inexistente)');
    }
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', 'eliminar_bot_template', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], "Template '{$template['nombre_template']}' eliminado"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Template eliminado correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error eliminando template: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al eliminar template: ' . $e->getMessage()
    ]);
}