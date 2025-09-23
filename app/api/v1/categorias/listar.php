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

try {
    $activo = isset($_GET['activo']) ? intval($_GET['activo']) : null;
    
    $sql = "SELECT id, nombre, color, precio FROM categorias";
    $params = [];
    
    if ($activo !== null) {
        $sql .= " WHERE activo = ?";
        $params[] = $activo;
    }
    
    $sql .= " ORDER BY nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categorias = $stmt->fetchAll();
    
    jsonResponse(true, 'Categorías obtenidas', $categorias);
    
} catch (Exception $e) {
    error_log("Error al listar categorías: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener las categorías');
}
?>