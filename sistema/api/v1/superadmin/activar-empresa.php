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
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE empresas SET activo = 1 WHERE id = ?");
    $stmt->execute([$id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (empresa_id, usuario_id, modulo, accion, descripcion)
        VALUES (?, ?, 'superadmin', 'activar_empresa', 'Empresa activada por administrador')
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Empresa activada correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error activando empresa: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al activar empresa']);
}