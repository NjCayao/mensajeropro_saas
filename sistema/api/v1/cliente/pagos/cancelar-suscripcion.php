<?php
// app/api/v1/cliente/pagos/cancelar-suscripcion.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../../config/app.php';
require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../../../../config/payments.php';
require_once __DIR__ . '/../../../../../includes/auth.php';
require_once __DIR__ . '/../../../../../includes/multi_tenant.php';

if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener suscripción activa
    $stmt = $pdo->prepare("
        SELECT * FROM suscripciones_pago 
        WHERE empresa_id = ? AND estado = 'activa'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['empresa_id']]);
    $suscripcion = $stmt->fetch();
    
    if (!$suscripcion) {
        echo json_encode(['success' => false, 'message' => 'No tienes una suscripción activa']);
        exit;
    }
    
    // Cancelar en la pasarela de pago
    if ($suscripcion['metodo'] === 'mercadopago') {
        cancelarMercadoPago($suscripcion['suscripcion_externa_id']);
    } else if ($suscripcion['metodo'] === 'paypal') {
        cancelarPayPal($suscripcion['suscripcion_externa_id']);
    }
    
    // Actualizar en base de datos
    $pdo->beginTransaction();
    
    // Marcar suscripción de pago como cancelada
    $stmt = $pdo->prepare("
        UPDATE suscripciones_pago 
        SET estado = 'cancelada',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$suscripcion['id']]);
    
    // La suscripción sigue activa hasta fecha_fin
    $stmt = $pdo->prepare("
        UPDATE suscripciones 
        SET auto_renovar = 0
        WHERE empresa_id = ? AND estado = 'activa'
    ");
    $stmt->execute([$_SESSION['empresa_id']]);
    
    // Registrar la cancelación
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (empresa_id, usuario_id, modulo, accion, descripcion)
        VALUES (?, ?, 'pagos', 'cancelar_suscripcion', ?)
    ");
    $stmt->execute([
        $_SESSION['empresa_id'],
        $_SESSION['user_id'],
        'Suscripción cancelada: ' . $suscripcion['suscripcion_externa_id']
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Suscripción cancelada. Seguirás teniendo acceso hasta el final del periodo actual.'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error cancelando suscripción: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cancelar la suscripción']);
}

function cancelarMercadoPago($subscription_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval/" . $subscription_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'cancelled']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . MP_ACCESS_TOKEN,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Error cancelando en MercadoPago");
    }
}

function cancelarPayPal($subscription_id) {
    // Obtener token
    $ch = curl_init();
    $base_url = (PAYPAL_MODE === 'sandbox') 
        ? "https://api-m.sandbox.paypal.com" 
        : "https://api-m.paypal.com";
        
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_SECRET);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $auth = json_decode($response, true);
    $access_token = $auth['access_token'];
    
    // Cancelar suscripción
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/billing/subscriptions/" . $subscription_id . "/cancel");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reason' => 'Customer requested']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 204) {
        throw new Exception("Error cancelando en PayPal");
    }
}
?>