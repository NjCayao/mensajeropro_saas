<?php
// config/payments.php
// Configuración de pasarelas de pago para suscripciones

// MercadoPago
define('MP_ACCESS_TOKEN', ''); // Tu Access Token de producción
define('MP_PUBLIC_KEY', '');   // Tu Public Key
define('MP_SANDBOX', true);    // true para pruebas, false para producción

// PayPal
define('PAYPAL_CLIENT_ID', '');
define('PAYPAL_SECRET', '');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' o 'live'

// Configuración general
define('PAYMENT_CURRENCY', 'USD'); // Moneda principal
define('PAYMENT_CURRENCY_SYMBOL', '$'); // Símbolo de moneda
define('TAX_RATE', 0.18); // IGV 18%

// URLs de retorno - Se construyen dinámicamente
if (!defined('APP_URL')) {
    require_once __DIR__ . '/app.php';
}

define('PAYMENT_SUCCESS_URL', APP_URL . '/cliente/pago-exitoso');
define('PAYMENT_FAILURE_URL', APP_URL . '/cliente/pago-fallido');
define('PAYMENT_PENDING_URL', APP_URL . '/cliente/pago-pendiente');

// Webhooks
define('MP_WEBHOOK_URL', APP_URL . '/api/v1/webhooks/mercadopago');
define('PAYPAL_WEBHOOK_URL', APP_URL . '/api/v1/webhooks/paypal');

// Función para inicializar MercadoPago SDK (si usas Composer)
function initMercadoPago() {
    if (class_exists('MercadoPago\SDK')) {
        MercadoPago\SDK::setAccessToken(MP_ACCESS_TOKEN);
        return true;
    }
    return false;
}

// Función para obtener cliente PayPal (si usas SDK)
function getPayPalClient() {
    // Solo si tienes el SDK de PayPal instalado
    if (!class_exists('\PayPalCheckoutSdk\Core\PayPalHttpClient')) {
        return null;
    }
    
    $clientId = PAYPAL_CLIENT_ID;
    $clientSecret = PAYPAL_SECRET;
    
    if (PAYPAL_MODE === 'live') {
        $environment = new \PayPalCheckoutSdk\Core\ProductionEnvironment($clientId, $clientSecret);
    } else {
        $environment = new \PayPalCheckoutSdk\Core\SandboxEnvironment($clientId, $clientSecret);
    }
        
    return new \PayPalCheckoutSdk\Core\PayPalHttpClient($environment);
}

// Configuración para MercadoPago sin SDK (usando cURL)
function getMercadoPagoHeaders() {
    return [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . uniqid()
    ];
}

// URL base de MercadoPago según el modo
function getMercadoPagoApiUrl() {
    return 'https://api.mercadopago.com';
}

// Configuración para PayPal sin SDK (usando cURL)
function getPayPalApiUrl() {
    return (PAYPAL_MODE === 'sandbox') 
        ? 'https://api-m.sandbox.paypal.com' 
        : 'https://api-m.paypal.com';
}

// Obtener token de PayPal
function getPayPalAccessToken() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, getPayPalApiUrl() . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_SECRET);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $auth = json_decode($response, true);
        return $auth['access_token'];
    }
    
    return false;
}
?>