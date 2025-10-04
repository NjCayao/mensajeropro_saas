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

try {
    // Verificar que sea editable
    $stmt = $pdo->prepare("SELECT editable, codigo FROM plantillas_email WHERE id = ?");
    $stmt->execute([$id]);
    $plantilla = $stmt->fetch();
    
    if (!$plantilla) {
        echo json_encode(['success' => false, 'message' => 'Plantilla no encontrada']);
        exit;
    }
    
    if (!$plantilla['editable']) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar plantillas del sistema']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("DELETE FROM plantillas_email WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', 'eliminar_plantilla_email', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], "Plantilla {$plantilla['codigo']} eliminada"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Plantilla eliminada correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error eliminando plantilla: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar plantilla']);
}