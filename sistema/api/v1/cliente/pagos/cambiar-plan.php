<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

header('Content-Type: application/json');

$empresa_id = $_POST['empresa_id'] ?? null;
$plan_id = $_POST['plan_id'] ?? null;
$motivo = $_POST['motivo'] ?? '';

if (!$empresa_id || !$plan_id) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // ✅ 1. Actualizar plan en tabla empresas
    $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ? WHERE id = ?");
    $stmt->execute([$plan_id, $empresa_id]);
    
    // ✅ 2. Actualizar suscripción activa (SINCRONIZAR)
    $stmt = $pdo->prepare("
        UPDATE suscripciones 
        SET plan_id = ? 
        WHERE empresa_id = ? AND estado = 'activa'
    ");
    $resultado = $stmt->execute([$plan_id, $empresa_id]);
    
    if ($stmt->rowCount() === 0) {
        // No hay suscripción activa, crear una básica
        $stmt = $pdo->prepare("
            INSERT INTO suscripciones 
            (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, auto_renovar)
            VALUES (?, ?, 'mensual', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'activa', 0)
        ");
        $stmt->execute([$empresa_id, $plan_id]);
    }
    
    // ✅ 3. Log de auditoría
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (empresa_id, usuario_id, modulo, accion, descripcion) 
        VALUES (?, ?, 'superadmin', 'cambio_plan', ?)
    ");
    $stmt->execute([
        $empresa_id, 
        $_SESSION['superadmin_id'], 
        "Plan cambiado por SuperAdmin. Motivo: {$motivo}"
    ]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Plan actualizado correctamente en ambas tablas']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}