<?php
// config/payments.php
// Configuración de pasarelas de pago - Solo constantes fijas
// ⚠️ Las credenciales se obtienen desde la BD (configuracion_plataforma)

// ✅ Solo URLs y constantes que NO cambian

// Configuración general
define('PAYMENT_CURRENCY', 'USD');
define('PAYMENT_CURRENCY_SYMBOL', '$');
define('TAX_RATE', 0.18); // IGV 18%

// URLs de retorno - Se construyen dinámicamente
if (!defined('APP_URL')) {
    require_once __DIR__ . '/app.php';
}

define('PAYMENT_SUCCESS_URL', APP_URL . '/cliente/pago-exitoso');
define('PAYMENT_FAILURE_URL', APP_URL . '/cliente/pago-fallido');
define('PAYMENT_PENDING_URL', APP_URL . '/cliente/pago-pendiente');

// Webhooks URLs
define('MP_WEBHOOK_URL', APP_URL . '/api/v1/webhooks/mercadopago');
define('PAYPAL_WEBHOOK_URL', APP_URL . '/api/v1/webhooks/paypal');

/**
 * Obtener configuración de MercadoPago desde BD
 * @return array
 */
function getMercadoPagoConfig() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT clave, valor 
        FROM configuracion_plataforma 
        WHERE clave IN ('mercadopago_access_token', 'mercadopago_public_key')
    ");
    $stmt->execute();
    
    $config = [
        'access_token' => '',
        'public_key' => ''
    ];
    
    while ($row = $stmt->fetch()) {
        switch ($row['clave']) {
            case 'mercadopago_access_token': $config['access_token'] = $row['valor']; break;
            case 'mercadopago_public_key': $config['public_key'] = $row['valor']; break;
        }
    }
    
    return $config;
}

/**
 * Obtener configuración de PayPal desde BD
 * @return array
 */
function getPayPalConfig() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT clave, valor 
        FROM configuracion_plataforma 
        WHERE clave IN ('paypal_client_id', 'paypal_secret', 'paypal_mode')
    ");
    $stmt->execute();
    
    $config = [
        'client_id' => '',
        'secret' => '',
        'mode' => 'sandbox'
    ];
    
    while ($row = $stmt->fetch()) {
        switch ($row['clave']) {
            case 'paypal_client_id': $config['client_id'] = $row['valor']; break;
            case 'paypal_secret': $config['secret'] = $row['valor']; break;
            case 'paypal_mode': $config['mode'] = $row['valor']; break;
        }
    }
    
    return $config;
}

/**
 * Obtener URL base de MercadoPago
 * @return string
 */
function getMercadoPagoApiUrl() {
    return 'https://api.mercadopago.com';
}

/**
 * Obtener URL base de PayPal según modo
 * @return string
 */
function getPayPalApiUrl() {
    $config = getPayPalConfig();
    return ($config['mode'] === 'sandbox') 
        ? 'https://api-m.sandbox.paypal.com' 
        : 'https://api-m.paypal.com';
}

/**
 * Obtener token de acceso de PayPal
 * @return string|false
 */
function getPayPalAccessToken() {
    $config = getPayPalConfig();
    
    if (!$config['client_id'] || !$config['secret']) {
        return false;
    }
    
    $base_url = getPayPalApiUrl();
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ":" . $config['secret']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $auth = json_decode($response, true);
        return $auth['access_token'];
    }
    
    return false;
}