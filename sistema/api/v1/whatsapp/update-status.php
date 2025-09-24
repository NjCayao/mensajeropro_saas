<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

$estado = $_POST['estado'] ?? '';
$numero = $_POST['numero'] ?? null;

try {
    $stmt = $pdo->prepare(
        "UPDATE whatsapp_sesiones_empresa 
        SET estado = ?, numero_conectado = ?, ultima_actualizacion = NOW() 
        WHERE empresa_id = ?"
    );

    $stmt->execute([$estado, $numero, getEmpresaActual()]);

    jsonResponse(true, 'Estado actualizado');
    
} catch (Exception $e) {
    jsonResponse(false, 'Error al actualizar estado');
}
?>