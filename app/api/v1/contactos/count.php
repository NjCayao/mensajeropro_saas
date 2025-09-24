<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contactos WHERE activo = 1 AND empresa_id = ?");
    $stmt->execute([getEmpresaActual()]);
    $result = $stmt->fetch();
    
    jsonResponse(true, 'Total obtenido', [
        'total' => $result['total']
    ]);
    
} catch (Exception $e) {
    jsonResponse(false, 'Error al obtener total de contactos');
}
//aca termina el archivo count.php
?>