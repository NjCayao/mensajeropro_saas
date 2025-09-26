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
    $empresa_id = getEmpresaActual();
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $duracion = $_POST['duracion'] ?? 30;
    $preparacion = $_POST['preparacion'] ?? '';
    $activo = $_POST['activo'] ?? 1;
    
    if (empty($nombre)) {
        throw new Exception('El nombre del servicio es requerido');
    }
    
    if ($id) {
        // Actualizar
        $sql = "UPDATE servicios_disponibles SET 
                nombre_servicio = ?, duracion_minutos = ?, 
                requiere_preparacion = ?, activo = ?
                WHERE id = ? AND empresa_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $duracion, $preparacion, $activo, $id, $empresa_id]);
    } else {
        // Insertar
        $sql = "INSERT INTO servicios_disponibles 
                (empresa_id, nombre_servicio, duracion_minutos, requiere_preparacion, activo)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$empresa_id, $nombre, $duracion, $preparacion, $activo]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Servicio guardado correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}