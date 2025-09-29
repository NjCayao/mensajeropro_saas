<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $empresa_id = getEmpresaActual();
    
    if (!$id) {
        throw new Exception('ID no proporcionado');
    }
    
    // Verificar que no haya citas con este servicio
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM citas_bot 
        WHERE tipo_servicio = (SELECT nombre_servicio FROM servicios_disponibles WHERE id = ?)
        AND empresa_id = ?
    ");
    $stmt->execute([$id, $empresa_id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        throw new Exception('No se puede eliminar, hay citas registradas con este servicio');
    }
    
    // Eliminar servicio
    $stmt = $pdo->prepare("
        DELETE FROM servicios_disponibles 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$id, $empresa_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Servicio eliminado correctamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}