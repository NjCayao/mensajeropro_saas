<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../../../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$email_destino = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email_destino) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    $resultado = enviarEmailSimple(
        $email_destino,
        'Prueba de Email - ' . APP_NAME,
        '<h1>Email de Prueba</h1>
        <p>Si ves este mensaje, tu configuración SMTP está correcta.</p>
        <hr>
        <small>Fecha: ' . date('d/m/Y H:i:s') . '</small>'
    );

    echo json_encode([
        'success' => $resultado,
        'message' => $resultado ? 'Email enviado correctamente' : 'Error al enviar. Revisa los logs.'
    ]);
} catch (Exception $e) {
    error_log("Error test email: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}