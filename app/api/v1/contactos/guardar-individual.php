<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'No autorizado']));
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Guardar en historial_mensajes
    $stmt = $pdo->prepare("
        INSERT INTO historial_mensajes 
        (contacto_id, mensaje, tipo, estado, fecha) 
        VALUES (?, ?, 'saliente', 'enviado', NOW())
    ");
    
    $stmt->execute([
        $data['contacto_id'],
        $data['mensaje']
    ]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    
} catch (Exception $e) {
    error_log("Error guardando mensaje: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>