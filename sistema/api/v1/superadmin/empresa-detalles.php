<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            p.nombre as plan_nombre,
            (SELECT COUNT(*) FROM contactos WHERE empresa_id = e.id) as total_contactos,
            (SELECT COUNT(*) FROM usuarios WHERE empresa_id = e.id) as total_usuarios,
            (SELECT COUNT(*) FROM historial_mensajes WHERE empresa_id = e.id AND MONTH(fecha) = MONTH(CURRENT_DATE())) as mensajes_mes
        FROM empresas e
        LEFT JOIN planes p ON e.plan_id = p.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        echo json_encode(['success' => false, 'message' => 'Empresa no encontrada']);
        exit;
    }
    
    $empresa['fecha_registro'] = date('d/m/Y H:i', strtotime($empresa['fecha_registro']));
    $empresa['ultimo_acceso'] = $empresa['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($empresa['ultimo_acceso'])) : null;
    
    echo json_encode([
        'success' => true,
        'data' => $empresa
    ]);
    
} catch (Exception $e) {
    error_log("Error en empresa-detalles: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener detalles']);
}