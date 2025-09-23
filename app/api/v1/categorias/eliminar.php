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

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

$id = intval($_POST['id'] ?? 0);

// Validaciones
if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

// No permitir eliminar la categoría "General" (ID = 1)
if ($id == 1) {
    jsonResponse(false, 'No se puede eliminar la categoría General');
}

try {
    // Verificar que existe
    $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        jsonResponse(false, 'Categoría no encontrada');
    }
    
    // Verificar que no tenga contactos asignados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id = ?");
    $stmt->execute([$id]);
    $contactCount = $stmt->fetchColumn();
    
    if ($contactCount > 0) {
        jsonResponse(false, "No se puede eliminar. La categoría tiene $contactCount contacto(s) asignado(s)");
    }
    
    // Eliminar
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log
    logActivity($pdo, 'categorias', 'eliminar', "Categoría eliminada: {$categoria['nombre']}");
    
    jsonResponse(true, 'Categoría eliminada exitosamente');
    
} catch (Exception $e) {
    error_log("Error al eliminar categoría: " . $e->getMessage());
    jsonResponse(false, 'Error al eliminar la categoría');
}
?>