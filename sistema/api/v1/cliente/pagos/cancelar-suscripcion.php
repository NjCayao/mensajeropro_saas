<?php
// sistema/api/v1/cliente/pagos/cancelar-suscripcion.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../../config/app.php';
require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../../../../includes/auth.php';
require_once __DIR__ . '/../../../../../includes/multi_tenant.php';

if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// ✅ Función para leer config
function getPaymentConfig($clave) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : '';
}

try {
    // ✅ Obtener suscripción activa (tabla correcta)
    $stmt = $pdo->prepare("
        SELECT * FROM suscripciones 
        WHERE empresa_id = ? AND estado = 'activa'
        ORDER BY fecha_fin DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['empresa_id']]);
    $suscripcion = $stmt->fetch();
    
    if (!$suscripcion) {
        echo json_encode(['success' => false, 'message' => 'No tienes una suscripción activa']);
        exit;
    }
    
    // Cancelar en la pasarela
    if (!empty($suscripcion['suscripcion_externa_id'])) {
        if ($suscripcion['metodo_pago'] === 'mercadopago') {
            cancelarMercadoPago($suscripcion['suscripcion_externa_id']);
        } elseif ($suscripcion['metodo_pago'] === 'paypal') {
            cancelarPayPal($suscripcion['suscripcion_externa_id']);
        }
    }
    
    // Actualizar BD
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE suscripciones 
        SET auto_renovar = 0, estado = 'cancelada'
        WHERE id = ?
    ");
    $stmt->execute([$suscripcion['id']]);
    
    // Registrar en logs
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (empresa_id, usuario_id, modulo, accion, descripcion)
        VALUES (?, ?, 'pagos', 'cancelar_suscripcion', ?)
    ");
    $stmt->execute([
        $_SESSION['empresa_id'],
        $_SESSION['user_id'] ?? null,
        'Suscripción cancelada: ' . ($suscripcion['suscripcion_externa_id'] ?? 'N/A')
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Suscripción cancelada. Tendrás acceso hasta: ' . date('d/m/Y', strtotime($suscripcion['fecha_fin']))
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error cancelando suscripción: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function cancelarMercadoPago($subscription_id) {
    $access_token = getPaymentConfig('mercadopago_access_token');
    
    if (empty($access_token)) {
        throw new Exception("MercadoPago no configurado");
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval/" . $subscription_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'cancelled']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Error cancelando en MercadoPago (HTTP $http_code)");
    }
}

function cancelarPayPal($subscription_id) {
    $client_id = getPaymentConfig('paypal_client_id');
    $secret = getPaymentConfig('paypal_secret');
    $mode = getPaymentConfig('paypal_mode') ?: 'sandbox';
    
    if (empty($client_id) || empty($secret)) {
        throw new Exception("PayPal no configurado");
    }
    
    $base_url = ($mode === 'sandbox') 
        ? "https://api-m.sandbox.paypal.com" 
        : "https://api-m.paypal.com";
    
    // Obtener token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $secret);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $auth = json_decode($response, true);
    
    if (!isset($auth['access_token'])) {
        throw new Exception("Error obteniendo token PayPal");
    }
    
    // Cancelar suscripción
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/billing/subscriptions/" . $subscription_id . "/cancel");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reason' => 'Customer requested cancellation']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $auth['access_token'],
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 204) {
        throw new Exception("Error cancelando en PayPal (HTTP $http_code)");
    }
}