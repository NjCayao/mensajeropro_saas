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
$activo = $_POST['activo'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE planes SET activo = ? WHERE id = ?");
    $stmt->execute([$activo, $id]);
    
    $accion = $activo ? 'activar_plan' : 'desactivar_plan';
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $accion, "Plan ID $id " . ($activo ? 'activado' : 'desactivado')]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Plan ' . ($activo ? 'activado' : 'desactivado') . ' correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error toggle plan: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cambiar estado del plan']);
}