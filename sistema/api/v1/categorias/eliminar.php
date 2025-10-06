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

$id = intval($_POST['id'] ?? 0);

// Validaciones
if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    // Verificar que existe y obtener datos
    $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);
    $categoria = $stmt->fetch();

    if (!$categoria) {
        jsonResponse(false, 'Categoría no encontrada');
    }

    // ✅ CORREGIDO: Proteger categoría "General" por nombre, no por ID
    if (strtolower($categoria['nombre']) === 'general') {
        jsonResponse(false, 'No se puede eliminar la categoría General');
    }

    // Verificar que no tenga contactos asignados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);
    $contactCount = $stmt->fetchColumn();

    if ($contactCount > 0) {
        jsonResponse(false, "No se puede eliminar. La categoría tiene $contactCount contacto(s) asignado(s)");
    }

    // Eliminar
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);

    // Log
    logActivity($pdo, 'categorias', 'eliminar', "Categoría eliminada: {$categoria['nombre']}");

    jsonResponse(true, 'Categoría eliminada exitosamente');
} catch (Exception $e) {
    error_log("Error al eliminar categoría: " . $e->getMessage());
    jsonResponse(false, 'Error al eliminar la categoría');
}