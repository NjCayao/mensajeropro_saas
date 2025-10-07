<?php
// sistema/api/v1/cliente/pagos/crear-suscripcion.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../../config/app.php';
require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../../../../includes/auth.php';
require_once __DIR__ . '/../../../../../includes/multi_tenant.php';

// Verificar autenticación
if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$plan_id = $data['plan_id'] ?? 0;
$tipo_pago = $data['tipo_pago'] ?? 'mensual';
$metodo = $data['metodo'] ?? 'mercadopago';

// Validar plan
$stmt = $pdo->prepare("SELECT * FROM planes WHERE id = ? AND activo = 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'Plan no válido']);
    exit;
}

// Obtener datos de la empresa
$empresa = getDatosEmpresa();

// Calcular monto
$monto = ($tipo_pago === 'anual') ? $plan['precio_anual'] : $plan['precio_mensual'];

if ($monto <= 0) {
    echo json_encode(['success' => false, 'message' => 'Este plan no requiere pago']);
    exit;
}

// ✅ Leer configuración desde BD
function getPaymentConfig($clave) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : '';
}

try {
    if ($metodo === 'mercadopago') {
        $response = crearSuscripcionMercadoPago($empresa, $plan, $tipo_pago, $monto);
    } else {
        $response = crearSuscripcionPayPal($empresa, $plan, $tipo_pago, $monto);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error creando suscripción: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el pago: ' . $e->getMessage()
    ]);
}

/**
 * Crear suscripción en MercadoPago
 */
function crearSuscripcionMercadoPago($empresa, $plan, $tipo_pago, $monto) {
    global $pdo;
    
    // ✅ Leer desde BD
    $access_token = getPaymentConfig('mercadopago_access_token');
    
    if (empty($access_token)) {
        throw new Exception("MercadoPago no está configurado");
    }
    
    // ✅ URLs correctas
    $success_url = url('cliente/pago-exitoso');
    $failure_url = url('cliente/pago-fallido');
    $pending_url = url('cliente/pago-pendiente');
    
    $plan_data = [
        "reason" => $plan['nombre'] . " - " . ucfirst($tipo_pago),
        "auto_recurring" => [
            "frequency" => ($tipo_pago === 'anual') ? 12 : 1,
            "frequency_type" => "months",
            "transaction_amount" => (float)$monto,
            "currency_id" => "PEN" // ✅ Perú
        ],
        "back_url" => $success_url,
        "payer" => [
            "email" => $empresa['email']
        ],
        "external_reference" => "empresa_" . $empresa['id'] . "_plan_" . $plan['id']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($plan_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201) {
        throw new Exception("Error en MercadoPago (HTTP $http_code): " . $response);
    }
    
    $result = json_decode($response, true);
    
    // Guardar intento
    $stmt = $pdo->prepare("
        INSERT INTO pagos (empresa_id, plan_id, monto, metodo, referencia_externa, estado, respuesta_gateway)
        VALUES (?, ?, ?, 'mercadopago', ?, 'pendiente', ?)
    ");
    $stmt->execute([
        $empresa['id'],
        $plan['id'],
        $monto,
        $result['id'],
        json_encode($result)
    ]);
    
    return [
        'success' => true,
        'init_point' => $result['init_point'],
        'subscription_id' => $result['id']
    ];
}

/**
 * Crear suscripción en PayPal
 */
function crearSuscripcionPayPal($empresa, $plan, $tipo_pago, $monto) {
    global $pdo;
    
    // ✅ Leer desde BD
    $client_id = getPaymentConfig('paypal_client_id');
    $secret = getPaymentConfig('paypal_secret');
    $mode = getPaymentConfig('paypal_mode') ?: 'sandbox';
    
    if (empty($client_id) || empty($secret)) {
        throw new Exception("PayPal no está configurado");
    }
    
    $base_url = ($mode === 'sandbox') 
        ? "https://api-m.sandbox.paypal.com" 
        : "https://api-m.paypal.com";
    
    // ✅ URLs correctas
    $success_url = url('cliente/pago-exitoso');
    $cancel_url = url('cliente/pago-fallido');
    
    // 1. Obtener access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $secret);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $auth = json_decode($response, true);
    
    if (!isset($auth['access_token'])) {
        throw new Exception("Error obteniendo token de PayPal");
    }
    
    $access_token = $auth['access_token'];
    
    // 2. Crear producto
    $product_data = [
        "name" => APP_NAME . " - " . $plan['nombre'],
        "type" => "SERVICE",
        "category" => "SOFTWARE"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/catalogs/products");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $product = json_decode($response, true);
    
    if (!isset($product['id'])) {
        throw new Exception("Error creando producto en PayPal");
    }
    
    // 3. Crear plan de suscripción
    $billing_cycles = [
        [
            "frequency" => [
                "interval_unit" => ($tipo_pago === 'anual') ? "YEAR" : "MONTH",
                "interval_count" => 1
            ],
            "tenure_type" => "REGULAR",
            "sequence" => 1,
            "total_cycles" => 0,
            "pricing_scheme" => [
                "fixed_price" => [
                    "value" => number_format($monto, 2, '.', ''),
                    "currency_code" => "USD"
                ]
            ]
        ]
    ];
    
    $plan_data = [
        "product_id" => $product['id'],
        "name" => $plan['nombre'] . " - " . ucfirst($tipo_pago),
        "billing_cycles" => $billing_cycles,
        "payment_preferences" => [
            "auto_bill_outstanding" => true,
            "setup_fee_failure_action" => "CONTINUE",
            "payment_failure_threshold" => 3
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/billing/plans");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($plan_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $billing_plan = json_decode($response, true);
    
    if (!isset($billing_plan['id'])) {
        throw new Exception("Error creando plan en PayPal");
    }
    
    // 4. Crear suscripción
    $subscription_data = [
        "plan_id" => $billing_plan['id'],
        "application_context" => [
            "brand_name" => APP_NAME,
            "return_url" => $success_url,
            "cancel_url" => $cancel_url
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/billing/subscriptions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subscription_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201) {
        throw new Exception("Error creando suscripción en PayPal (HTTP $http_code): " . $response);
    }
    
    $subscription = json_decode($response, true);
    
    // Guardar intento
    $stmt = $pdo->prepare("
        INSERT INTO pagos (empresa_id, plan_id, monto, metodo, referencia_externa, estado, respuesta_gateway)
        VALUES (?, ?, ?, 'paypal', ?, 'pendiente', ?)
    ");
    $stmt->execute([
        $empresa['id'],
        $plan['id'],
        $monto,
        $subscription['id'],
        json_encode($subscription)
    ]);
    
    // Buscar link de aprobación
    $approval_link = '';
    foreach ($subscription['links'] as $link) {
        if ($link['rel'] === 'approve') {
            $approval_link = $link['href'];
            break;
        }
    }
    
    return [
        'success' => true,
        'approval_url' => $approval_link,
        'subscription_id' => $subscription['id']
    ];
}