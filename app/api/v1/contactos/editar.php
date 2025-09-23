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
$numero = sanitize($_POST['numero'] ?? '');
$categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
$notas = sanitize($_POST['notas'] ?? '');
$activo = intval($_POST['activo'] ?? 1);

// Validaciones
if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

if (empty($nombre)) {
    jsonResponse(false, 'El nombre es requerido');
}

if (empty($numero)) {
    jsonResponse(false, 'El número es requerido');
}

// Validar formato del número
if (!validatePhone($numero)) {
    jsonResponse(false, 'El formato del número no es válido');
}

// Formatear número
$numero = formatPhone($numero);

try {
    // Verificar que el contacto existe
    $stmt = $pdo->prepare("SELECT numero FROM contactos WHERE id = ?");
    $stmt->execute([$id]);
    $oldContacto = $stmt->fetch();
    
    if (!$oldContacto) {
        jsonResponse(false, 'Contacto no encontrado');
    }
    
    // Verificar si el número ya existe en otro contacto
    $stmt = $pdo->prepare("SELECT id FROM contactos WHERE numero = ? AND id != ?");
    $stmt->execute([$numero, $id]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'El número ya está registrado en otro contacto');
    }
    
    // Si se especificó categoría, verificar que existe
    if ($categoria_id) {
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND activo = 1");
        $stmt->execute([$categoria_id]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'La categoría seleccionada no existe o está inactiva');
        }
    }
    
    // Actualizar contacto
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET nombre = ?, numero = ?, categoria_id = ?, notas = ?, activo = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$nombre, $numero, $categoria_id, $notas, $activo, $id]);
    
    // Log
    logActivity($pdo, 'contactos', 'editar', "Contacto editado: $nombre");
    
    jsonResponse(true, 'Contacto actualizado exitosamente');
    
} catch (Exception $e) {
    error_log("Error al editar contacto: " . $e->getMessage());
    jsonResponse(false, 'Error al actualizar el contacto');
}
?>