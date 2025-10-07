<?php
// sistema/api/v1/superadmin/extender-trial.php
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
$dias = $_POST['dias'] ?? 0;

if (!$empresa_id || !$dias) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // ✅ CORREGIDO: Extender trial en tabla suscripciones
    // Buscar suscripción trial activa
    $stmt = $pdo->prepare("
        SELECT * FROM suscripciones 
        WHERE empresa_id = ? AND tipo = 'trial' AND estado = 'activa'
        ORDER BY fecha_fin DESC
        LIMIT 1
    ");
    $stmt->execute([$empresa_id]);
    $trial = $stmt->fetch();
    
    if ($trial) {
        // Extender trial existente
        $stmt = $pdo->prepare("
            UPDATE suscripciones 
            SET fecha_fin = DATE_ADD(fecha_fin, INTERVAL ? DAY)
            WHERE id = ?
        ");
        $stmt->execute([$dias, $trial['id']]);
    } else {
        // Crear nuevo trial si no existe
        $stmt = $pdo->prepare("
            INSERT INTO suscripciones 
            (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, auto_renovar)
            VALUES (?, 1, 'trial', CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'activa', 0)
        ");
        $stmt->execute([$empresa_id, $dias]);
    }
    
    // Asegurar que la empresa esté activa
    $stmt = $pdo->prepare("UPDATE empresas SET activo = 1 WHERE id = ?");
    $stmt->execute([$empresa_id]);
    
    // Registrar en logs
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (empresa_id, usuario_id, modulo, accion, descripcion)
        VALUES (?, ?, 'superadmin', 'extender_trial', ?)
    ");
    $stmt->execute([$empresa_id, $_SESSION['user_id'], "Trial extendido $dias días"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Trial extendido $dias días exitosamente"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error extendiendo trial: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al extender trial']);
}