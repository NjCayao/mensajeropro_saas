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

$plan_id = $_POST['plan_id'] ?? 0;
$nombre = $_POST['nombre'] ?? '';
$precio_mensual = $_POST['precio_mensual'] ?? 0;
$precio_anual = $_POST['precio_anual'] ?? 0;
$limite_contactos = $_POST['limite_contactos'] ?? 0;
$limite_mensajes_mes = $_POST['limite_mensajes_mes'] ?? 0;
$bot_ia = isset($_POST['bot_ia']) ? 1 : 0;
$soporte_prioritario = isset($_POST['soporte_prioritario']) ? 1 : 0;
$caracteristicas_json = $_POST['caracteristicas_json'] ?? '{}';

if (!$plan_id || !$nombre) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    json_decode($caracteristicas_json);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'El JSON de características no es válido']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE planes SET
            nombre = ?,
            precio_mensual = ?,
            precio_anual = ?,
            limite_contactos = ?,
            limite_mensajes_mes = ?,
            bot_ia = ?,
            soporte_prioritario = ?,
            caracteristicas_json = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $nombre,
        $precio_mensual,
        $precio_anual,
        $limite_contactos,
        $limite_mensajes_mes,
        $bot_ia,
        $soporte_prioritario,
        $caracteristicas_json,
        $plan_id
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion)
        VALUES (?, 'superadmin', 'guardar_plan', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], "Plan $nombre actualizado"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Plan actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error guardando plan: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar plan']);
}