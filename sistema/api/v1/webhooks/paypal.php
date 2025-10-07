<?php
// sistema/api/v1/webhooks/paypal.php
// Webhook para procesar notificaciones de PayPal

// No requiere sesión
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';

// Log de entrada para debug
$raw_input = file_get_contents('php://input');
error_log("Webhook PayPal recibido: " . $raw_input);

// Obtener configuración de PayPal desde BD
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

// Verificar firma del webhook (seguridad)
function verificarWebhookPayPal($headers, $body) {
    $config = getPayPalConfig();
    
    // Obtener token de acceso
    $base_url = ($config['mode'] === 'sandbox') 
        ? 'https://api-m.sandbox.paypal.com' 
        : 'https://api-m.paypal.com';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ":" . $config['secret']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $auth = json_decode($response, true);
    
    if (!isset($auth['access_token'])) {
        error_log("PayPal: No se pudo obtener token de acceso");
        return false;
    }
    
    // Verificar webhook usando API de PayPal
    $webhook_id = $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
    
    $verify_data = [
        'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
        'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
        'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'webhook_id' => $webhook_id, // Deberías tener esto guardado
        'webhook_event' => json_decode($body, true)
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/notifications/verify-webhook-signature");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verify_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $auth['access_token'],
        "Content-Type: application/json"
    ]);
    
    $verify_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($verify_response, true);
    
    return ($http_code === 200 && isset($result['verification_status']) && $result['verification_status'] === 'SUCCESS');
}

// Obtener datos del webhook
$data = json_decode($raw_input, true);

if (!$data || !isset($data['event_type'])) {
    http_response_code(400);
    exit;
}

// Headers para verificación
$headers = getallheaders();

try {
    // Procesar según tipo de evento
    switch ($data['event_type']) {
        case 'BILLING.SUBSCRIPTION.ACTIVATED':
            procesarSuscripcionActivada($data);
            break;
            
        case 'BILLING.SUBSCRIPTION.CANCELLED':
        case 'BILLING.SUBSCRIPTION.SUSPENDED':
            procesarSuscripcionCancelada($data);
            break;
            
        case 'PAYMENT.SALE.COMPLETED':
            procesarPagoRecurrente($data);
            break;
            
        default:
            error_log("PayPal: Evento no manejado: " . $data['event_type']);
    }
    
    // Responder 200 OK
    http_response_code(200);
    echo "OK";
    
} catch (Exception $e) {
    error_log("Error en webhook PayPal: " . $e->getMessage());
    http_response_code(500);
}

/**
 * Procesar suscripción activada
 */
function procesarSuscripcionActivada($data) {
    global $pdo;
    
    $subscription_id = $data['resource']['id'];
    $status = $data['resource']['status'];
    
    // Buscar el pago pendiente con esta referencia
    $stmt = $pdo->prepare("
        SELECT p.*, e.email 
        FROM pagos p 
        JOIN empresas e ON p.empresa_id = e.id 
        WHERE p.referencia_externa = ?
    ");
    $stmt->execute([$subscription_id]);
    $pago = $stmt->fetch();
    
    if (!$pago) {
        error_log("PayPal: No se encontró pago para subscription_id: " . $subscription_id);
        return;
    }
    
    if ($status === 'ACTIVE') {
        $pdo->beginTransaction();
        
        try {
            // Actualizar pago
            $stmt = $pdo->prepare("
                UPDATE pagos 
                SET estado = 'aprobado', 
                    fecha_pago = NOW(),
                    respuesta_gateway = ?
                WHERE id = ?
            ");
            $stmt->execute([json_encode($data), $pago['id']]);
            
            // Determinar tipo y fecha fin
            $plan_data = $data['resource']['plan_id'] ?? '';
            $billing_info = $data['resource']['billing_info'] ?? [];
            
            $tipo = 'mensual'; // Por defecto
            $fecha_fin = date('Y-m-d', strtotime('+1 month'));
            
            // Detectar si es anual
            if (isset($billing_info['cycle_executions'])) {
                foreach ($billing_info['cycle_executions'] as $cycle) {
                    if (isset($cycle['tenure_type']) && $cycle['tenure_type'] === 'REGULAR') {
                        $interval = $cycle['frequency']['interval_unit'] ?? 'MONTH';
                        if ($interval === 'YEAR') {
                            $tipo = 'anual';
                            $fecha_fin = date('Y-m-d', strtotime('+1 year'));
                        }
                    }
                }
            }
            
            // Obtener próximo pago
            $next_billing_time = $data['resource']['billing_info']['next_billing_time'] ?? null;
            $fecha_proximo = $next_billing_time ? date('Y-m-d', strtotime($next_billing_time)) : $fecha_fin;
            
            // Crear o actualizar suscripción
            $stmt = $pdo->prepare("
                INSERT INTO suscripciones 
                (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, auto_renovar, metodo_pago, referencia_externa, suscripcion_externa_id, fecha_proximo_pago, monto, metadata)
                VALUES (?, ?, ?, CURDATE(), ?, 'activa', 1, 'paypal', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    estado = 'activa',
                    fecha_fin = VALUES(fecha_fin),
                    fecha_proximo_pago = VALUES(fecha_proximo_pago),
                    metadata = VALUES(metadata)
            ");
            
            $monto = 0;
            if (isset($data['resource']['billing_info']['last_payment']['amount']['value'])) {
                $monto = (float)$data['resource']['billing_info']['last_payment']['amount']['value'];
            }
            
            $stmt->execute([
                $pago['empresa_id'],
                $pago['plan_id'],
                $tipo,
                $fecha_fin,
                $subscription_id,
                $subscription_id,
                $fecha_proximo,
                $monto,
                json_encode($data)
            ]);
            
            // Actualizar empresa con nuevo plan
            $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ?, activo = 1 WHERE id = ?");
            $stmt->execute([$pago['plan_id'], $pago['empresa_id']]);
            
            $pdo->commit();
            
            error_log("PayPal: Suscripción activada para empresa_id: " . $pago['empresa_id']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

/**
 * Procesar suscripción cancelada
 */
function procesarSuscripcionCancelada($data) {
    global $pdo;
    
    $subscription_id = $data['resource']['id'];
    
    $stmt = $pdo->prepare("
        UPDATE suscripciones 
        SET estado = 'cancelada', auto_renovar = 0
        WHERE suscripcion_externa_id = ?
    ");
    $stmt->execute([$subscription_id]);
    
    error_log("PayPal: Suscripción cancelada: " . $subscription_id);
}

/**
 * Procesar pago recurrente
 */
function procesarPagoRecurrente($data) {
    global $pdo;
    
    $sale_id = $data['resource']['id'];
    $billing_agreement_id = $data['resource']['billing_agreement_id'] ?? null;
    
    if (!$billing_agreement_id) {
        error_log("PayPal: Pago sin billing_agreement_id");
        return;
    }
    
    // Obtener suscripción
    $stmt = $pdo->prepare("
        SELECT * FROM suscripciones 
        WHERE suscripcion_externa_id = ?
    ");
    $stmt->execute([$billing_agreement_id]);
    $suscripcion = $stmt->fetch();
    
    if (!$suscripcion) {
        error_log("PayPal: No se encontró suscripción para billing_agreement_id: " . $billing_agreement_id);
        return;
    }
    
    // Verificar si el pago está completado
    if ($data['resource']['state'] === 'completed') {
        $pdo->beginTransaction();
        
        try {
            // Registrar pago
            $monto = (float)$data['resource']['amount']['total'];
            
            $stmt = $pdo->prepare("
                INSERT INTO pagos 
                (empresa_id, suscripcion_id, plan_id, monto, metodo, referencia_externa, estado, fecha_pago, respuesta_gateway)
                VALUES (?, ?, ?, ?, 'paypal', ?, 'aprobado', NOW(), ?)
            ");
            
            $stmt->execute([
                $suscripcion['empresa_id'],
                $suscripcion['id'],
                $suscripcion['plan_id'],
                $monto,
                $sale_id,
                json_encode($data)
            ]);
            
            // Extender suscripción según tipo
            if ($suscripcion['tipo'] === 'anual') {
                $nuevo_fecha_fin = date('Y-m-d', strtotime($suscripcion['fecha_fin'] . ' +1 year'));
            } else {
                $nuevo_fecha_fin = date('Y-m-d', strtotime($suscripcion['fecha_fin'] . ' +1 month'));
            }
            
            $stmt = $pdo->prepare("
                UPDATE suscripciones 
                SET fecha_fin = ?,
                    fecha_proximo_pago = ?
                WHERE id = ?
            ");
            
            $fecha_proximo = date('Y-m-d', strtotime($nuevo_fecha_fin));
            $stmt->execute([$nuevo_fecha_fin, $fecha_proximo, $suscripcion['id']]);
            
            $pdo->commit();
            
            error_log("PayPal: Pago recurrente procesado para empresa_id: " . $suscripcion['empresa_id']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}