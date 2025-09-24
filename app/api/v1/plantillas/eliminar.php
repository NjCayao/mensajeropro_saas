<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    // Obtener información de la plantilla
    $stmt = $pdo->prepare("SELECT nombre FROM plantillas_mensajes WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);
    $plantilla = $stmt->fetch();

    if (!$plantilla) {
        jsonResponse(false, 'Plantilla no encontrada');
    }

    // Eliminar plantilla
    $stmt = $pdo->prepare("DELETE FROM plantillas_mensajes WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);

    logActivity($pdo, 'plantillas', 'eliminar', "Plantilla eliminada: {$plantilla['nombre']}");

    jsonResponse(true, 'Plantilla eliminada exitosamente');
} catch (Exception $e) {
    error_log("Error al eliminar plantilla: " . $e->getMessage());
    jsonResponse(false, 'Error al eliminar plantilla');
}
