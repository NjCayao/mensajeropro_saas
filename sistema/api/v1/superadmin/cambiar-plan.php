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

$empresa_id = $_POST['empresa_id'] ?? 0;
$plan_id = $_POST['plan_id'] ?? 0;
$motivo = $_POST['motivo'] ?? '';

if (!$empresa_id || !$plan_id) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Obtener plan actual
    $stmt = $pdo->prepare("SELECT plan_id FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $plan_anterior = $stmt->fetchColumn();
    
    // Actualizar plan
    $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ? WHERE id = ?");
    $stmt->execute([$plan_id, $empresa_id]);
    
    // Registrar cambio
    $stmt = $pdo->prepare("
        INSERT INTO cambios_plan (empresa_id, plan_anterior_id, plan_nuevo_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$empresa_id, $plan_anterior, $plan_id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (empresa_id, usuario_id, modulo, accion, descripcion)
        VALUES (?, ?, 'superadmin', 'cambiar_plan', ?)
    ");
    $stmt->execute([$empresa_id, $_SESSION['user_id'], "Plan cambiado: $motivo"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Plan actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error cambiando plan: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cambiar plan']);
}