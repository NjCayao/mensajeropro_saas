<?php
// sistema/api/v1/superadmin/guardar-configuracion.php
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

/**
 * Guardar o actualizar configuración
 */
function guardarConfig($clave, $valor, $tipo = 'texto', $descripcion = '') {
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
            guardarConfig('openai_temperatura', $_POST['openai_temperatura'] ?? '0.7', 'numero', 'Temperatura del modelo');
            guardarConfig('openai_max_tokens', $_POST['openai_max_tokens'] ?? '150', 'numero', 'Tokens máximos');
            break;
            
        case 'pagos':
            guardarConfig('mercadopago_access_token', $_POST['mercadopago_access_token'] ?? '', 'texto', 'Access Token MercadoPago');
            guardarConfig('mercadopago_public_key', $_POST['mercadopago_public_key'] ?? '', 'texto', 'Public Key MercadoPago');
            guardarConfig('paypal_client_id', $_POST['paypal_client_id'] ?? '', 'texto', 'PayPal Client ID');
            guardarConfig('paypal_secret', $_POST['paypal_secret'] ?? '', 'texto', 'PayPal Secret');
            guardarConfig('paypal_mode', $_POST['paypal_mode'] ?? 'sandbox', 'texto', 'Modo PayPal');
            break;
            
        case 'email':
            guardarConfig('email_remitente', $_POST['email_remitente'] ?? '', 'texto', 'Email remitente');
            guardarConfig('email_nombre', $_POST['email_nombre'] ?? 'MensajeroPro', 'texto', 'Nombre remitente');
            break;
            
        case 'sistema':
            guardarConfig('trial_dias', $_POST['trial_dias'] ?? '2', 'numero', 'Días de trial');
            guardarConfig('whatsapp_soporte', $_POST['whatsapp_soporte'] ?? '', 'texto', 'WhatsApp de soporte');
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