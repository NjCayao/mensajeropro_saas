<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

$estado = $_POST['estado'] ?? '';
$numero = $_POST['numero'] ?? null;

try {
    $stmt = $pdo->prepare(
        "UPDATE whatsapp_sesion 
         SET estado = ?, numero_conectado = ?, ultima_actualizacion = NOW() 
         WHERE id = 1"
    );
    
    $stmt->execute([$estado, $numero]);
    
    jsonResponse(true, 'Estado actualizado');
    
} catch (Exception $e) {
    jsonResponse(false, 'Error al actualizar estado');
}
?>