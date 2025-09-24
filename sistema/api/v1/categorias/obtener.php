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
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaActual()]);
    $categoria = $stmt->fetch();

    if ($categoria) {
        jsonResponse(true, 'Categoría encontrada', $categoria);
    } else {
        jsonResponse(false, 'Categoría no encontrada');
    }
} catch (Exception $e) {
    error_log("Error al obtener categoría: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener la categoría');
}
