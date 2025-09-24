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

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);
    $contacto = $stmt->fetch();
    
    if ($contacto) {
        jsonResponse(true, 'Contacto encontrado', $contacto);
    } else {
        jsonResponse(false, 'Contacto no encontrado');
    }
    
} catch (Exception $e) {
    error_log("Error al obtener contacto: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener el contacto');
}
?>