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

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    jsonResponse(false, 'Token de seguridad inválido');
}

// Obtener datos
$nombre = sanitize($_POST['nombre'] ?? '');
$descripcion = sanitize($_POST['descripcion'] ?? '');
$precio = floatval($_POST['precio'] ?? 0);
$color = sanitize($_POST['color'] ?? '#17a2b8');
$activo = intval($_POST['activo'] ?? 1);

// Validaciones
if (empty($nombre)) {
    jsonResponse(false, 'El nombre es requerido');
}

if (strlen($nombre) > 50) {
    jsonResponse(false, 'El nombre no puede tener más de 50 caracteres');
}

// Validar color hexadecimal
if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
    $color = '#17a2b8';
}

try {
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ? AND empresa_id = ?");
    $stmt->execute([$nombre, getEmpresaActual()]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Ya existe una categoría con ese nombre');
    }

    // Insertar
    $stmt = $pdo->prepare("
        INSERT INTO categorias (nombre, descripcion, precio, color, activo, empresa_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nombre, $descripcion, $precio, $color, $activo, getEmpresaActual()]);

    // Log
    logActivity($pdo, 'categorias', 'crear', "Categoría creada: $nombre");

    jsonResponse(true, 'Categoría creada exitosamente', ['id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log("Error al crear categoría: " . $e->getMessage());
    jsonResponse(false, 'Error al crear la categoría');
}
