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
    // Verificar que existe
    $stmt = $pdo->prepare("SELECT nombre, numero FROM contactos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);
    $contacto = $stmt->fetch();

    if (!$contacto) {
        jsonResponse(false, 'Contacto no encontrado');
    }

    // Eliminar contacto
    $stmt = $pdo->prepare("DELETE FROM contactos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);

    // Log
    logActivity($pdo, 'contactos', 'eliminar', "Contacto eliminado: {$contacto['nombre']} ({$contacto['numero']})");

    jsonResponse(true, 'Contacto eliminado exitosamente');
} catch (Exception $e) {
    error_log("Error al eliminar contacto: " . $e->getMessage());
    jsonResponse(false, 'Error al eliminar el contacto');
}
