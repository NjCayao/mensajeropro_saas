<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$seccion = $_POST['seccion'] ?? '';

function guardarConfig($clave, $valor, $tipo = 'texto', $descripcion = '')
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO configuracion_plataforma (clave, valor, tipo, descripcion)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)
    ");

    return $stmt->execute([$clave, $valor, $tipo, $descripcion]);
}

try {
    $pdo->beginTransaction();

    switch ($seccion) {
        case 'openai':
            guardarConfig('openai_api_key', $_POST['openai_api_key'] ?? '', 'texto', 'API Key de OpenAI');
            guardarConfig('openai_modelo', $_POST['openai_modelo'] ?? 'gpt-3.5-turbo', 'texto', 'Modelo de IA');
            guardarConfig('openai_temperatura', $_POST['openai_temperatura'] ?? '0.7', 'numero', 'Temperatura');
            guardarConfig('openai_max_tokens', $_POST['openai_max_tokens'] ?? '150', 'numero', 'Tokens máximos');
            break;

        case 'pagos':
            guardarConfig('mercadopago_access_token', $_POST['mercadopago_access_token'] ?? '', 'texto', 'MercadoPago Token');
            guardarConfig('mercadopago_public_key', $_POST['mercadopago_public_key'] ?? '', 'texto', 'MercadoPago Public Key');
            guardarConfig('paypal_client_id', $_POST['paypal_client_id'] ?? '', 'texto', 'PayPal Client ID');
            guardarConfig('paypal_secret', $_POST['paypal_secret'] ?? '', 'texto', 'PayPal Secret');
            guardarConfig('paypal_mode', $_POST['paypal_mode'] ?? 'sandbox', 'texto', 'PayPal Mode');
            break;

        case 'email':
            guardarConfig('email_remitente', $_POST['email_remitente'] ?? '', 'texto', 'Email remitente');
            guardarConfig('email_nombre', $_POST['email_nombre'] ?? 'MensajeroPro', 'texto', 'Nombre remitente');
            break;

        case 'google':
            guardarConfig('google_client_id', $_POST['google_client_id'] ?? '', 'texto', 'Google Client ID');
            guardarConfig('google_client_secret', $_POST['google_client_secret'] ?? '', 'texto', 'Google Client Secret');
            guardarConfig('google_oauth_activo', isset($_POST['google_oauth_activo']) ? '1' : '0', 'boolean', 'Google OAuth Activo');
            break;

        case 'sistema':
            guardarConfig('trial_dias', $_POST['trial_dias'] ?? '30', 'numero', 'Días de trial');
            guardarConfig('whatsapp_soporte', $_POST['whatsapp_soporte'] ?? '', 'texto', 'WhatsApp soporte');
            break;

        case 'seguridad':
            guardarConfig('recaptcha_site_key', $_POST['recaptcha_site_key'] ?? '');
            guardarConfig('recaptcha_secret_key', $_POST['recaptcha_secret_key'] ?? '');
            guardarConfig('recaptcha_activo', isset($_POST['recaptcha_activo']) ? '1' : '0');
            guardarConfig('honeypot_activo', isset($_POST['honeypot_activo']) ? '1' : '0');
            guardarConfig('bloquear_emails_temporales', isset($_POST['bloquear_emails_temporales']) ? '1' : '0');

            // Convertir líneas a comas
            $dominios = $_POST['dominios_temporales'] ?? '';
            $dominios = str_replace(["\r\n", "\n", "\r"], ',', trim($dominios));
            $dominios = implode(',', array_filter(array_map('trim', explode(',', $dominios))));
            guardarConfig('dominios_temporales', $dominios);

            guardarConfig('verificacion_email_obligatoria', isset($_POST['verificacion_email_obligatoria']) ? '1' : '0');
            break;

        default:
            throw new Exception('Sección inválida');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada correctamente'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error guardando configuración: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la configuración'
    ]);
}
