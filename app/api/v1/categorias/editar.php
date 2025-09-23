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

// Obtener datos
$id = intval($_POST['id'] ?? 0);
$nombre = sanitize($_POST['nombre'] ?? '');
$descripcion = sanitize($_POST['descripcion'] ?? '');
$precio = floatval($_POST['precio'] ?? 0);
$color = sanitize($_POST['color'] ?? '#17a2b8');
$activo = intval($_POST['activo'] ?? 1);

// Validaciones
if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

if (empty($nombre)) {
    jsonResponse(false, 'El nombre es requerido');
}

// Validar color hexadecimal
if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
    $color = '#17a2b8';
}

try {
    // Verificar que existe
    $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $oldCategoria = $stmt->fetch();
    
    if (!$oldCategoria) {
        jsonResponse(false, 'Categoría no encontrada');
    }
    
    // Verificar nombre único (excepto la misma categoría)
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ? AND id != ?");
    $stmt->execute([$nombre, $id]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Ya existe otra categoría con ese nombre');
    }
    
    // Actualizar
    $stmt = $pdo->prepare("
        UPDATE categorias 
        SET nombre = ?, descripcion = ?, precio = ?, color = ?, activo = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$nombre, $descripcion, $precio, $color, $activo, $id]);
    
    // Log
    logActivity($pdo, 'categorias', 'editar', "Categoría editada: {$oldCategoria['nombre']} → $nombre");
    
    jsonResponse(true, 'Categoría actualizada exitosamente');
    
} catch (Exception $e) {
    error_log("Error al editar categoría: " . $e->getMessage());
    jsonResponse(false, 'Error al actualizar la categoría');
}
?>