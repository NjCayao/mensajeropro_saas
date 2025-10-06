<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    jsonResponse(false, 'Token de seguridad inválido');
}

$nombre = sanitize($_POST['nombre'] ?? '');
$mensaje = sanitize($_POST['mensaje'] ?? '');

if (empty($nombre) || empty($mensaje)) {
    jsonResponse(false, 'Nombre y mensaje son requeridos');
}

try {
    $variables = [];
    if (strpos($mensaje, '{{nombre}}') !== false) $variables[] = 'nombre';
    if (strpos($mensaje, '{{nombreWhatsApp}}') !== false) $variables[] = 'nombreWhatsApp';
    if (strpos($mensaje, '{{whatsapp}}') !== false) $variables[] = 'whatsapp';
    if (strpos($mensaje, '{{categoria}}') !== false) $variables[] = 'categoria';
    if (strpos($mensaje, '{{precio}}') !== false) $variables[] = 'precio';
    if (strpos($mensaje, '{{fecha}}') !== false) $variables[] = 'fecha';
    if (strpos($mensaje, '{{hora}}') !== false) $variables[] = 'hora';

    $stmt = $pdo->prepare(
        "INSERT INTO plantillas_mensajes (nombre, mensaje, variables, uso_general, empresa_id) VALUES (?, ?, ?, 1, ?)"
    );
    $stmt->execute([$nombre, $mensaje, json_encode($variables), getEmpresaActual()]);

    jsonResponse(true, 'Plantilla creada exitosamente');
} catch (Exception $e) {
    jsonResponse(false, 'Error al crear plantilla');
}
