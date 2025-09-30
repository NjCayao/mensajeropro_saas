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
$dias = $_POST['dias'] ?? 0;

if (!$id || !$dias) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE empresas 
        SET fecha_expiracion_trial = DATE_ADD(COALESCE(fecha_expiracion_trial, NOW()), INTERVAL ? DAY)
        WHERE id = ?
    ");
    $stmt->execute([$dias, $id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (empresa_id, usuario_id, modulo, accion, descripcion)
        VALUES (?, ?, 'superadmin', 'extender_trial', ?)
    ");
    $stmt->execute([$id, $_SESSION['user_id'], "Trial extendido $dias días"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Trial extendido $dias días"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error extendiendo trial: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al extender trial']);
}