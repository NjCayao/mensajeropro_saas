<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

$empresa_id = getEmpresaActual();
$numero = $_POST['numero'] ?? '';
$mensaje = $_POST['mensaje'] ?? '';
$imagen_path = $_POST['imagen_path'] ?? null;

// Validaciones
if (empty($numero) || empty($mensaje)) {
    jsonResponse(false, 'Número y mensaje son requeridos');
}

// Verificar que WhatsApp esté conectado
$stmt = $pdo->prepare("
    SELECT estado FROM whatsapp_sesiones_empresa 
    WHERE empresa_id = ?
");
$stmt->execute([$empresa_id]);
$sesion = $stmt->fetch();

if (!$sesion || $sesion['estado'] !== 'conectado') {
    jsonResponse(false, 'WhatsApp no está conectado');
}

try {
    // Enviar directamente sin cola
    $resultado = enviarWhatsApp($empresa_id, $numero, $mensaje, $imagen_path);
    
    if ($resultado['success']) {
        // Registrar en historial
        $stmt = $pdo->prepare("
            INSERT INTO historial_mensajes 
            (empresa_id, contacto_id, mensaje, tipo, estado, fecha)
            SELECT ?, id, ?, 'saliente', 'enviado', NOW()
            FROM contactos
            WHERE empresa_id = ? AND numero = ?
            LIMIT 1
        ");
        $stmt->execute([$empresa_id, $mensaje, $empresa_id, $numero]);
        
        jsonResponse(true, 'Mensaje enviado correctamente');
    } else {
        jsonResponse(false, $resultado['error'] ?? 'Error al enviar mensaje');
    }
} catch (Exception $e) {
    error_log("Error enviando WhatsApp: " . $e->getMessage());
    jsonResponse(false, 'Error del sistema al enviar mensaje');
}