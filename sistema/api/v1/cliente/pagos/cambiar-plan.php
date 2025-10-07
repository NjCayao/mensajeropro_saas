<?php
// sistema/api/v1/cliente/pagos/cambiar-plan.php
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

// ✅ Función para leer config desde BD
function getPaymentConfig($clave) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : '';
}

$data = json_decode(file_get_contents('php://input'), true);
$nuevo_plan_id = $data['plan_id'] ?? 0;
$tipo_pago = $data['tipo_pago'] ?? 'mensual';

try {
    $empresa = getDatosEmpresa();
    $plan_actual_id = $empresa['plan_id'];
    
    // ✅ Verificar suscripción activa (tabla correcta)
    $stmt = $pdo->prepare("
        SELECT s.*, p.precio_mensual, p.precio_anual, p.nombre as plan_nombre
        FROM suscripciones s
        JOIN planes p ON p.id = s.plan_id
        WHERE s.empresa_id = ? AND s.estado = 'activa'
        ORDER BY s.fecha_fin DESC
        LIMIT 1
    ");
    $stmt->execute([$empresa['id']]);
    $suscripcion_actual = $stmt->fetch();
    
    if (!$suscripcion_actual) {
        echo json_encode(['success' => false, 'message' => 'No tienes una suscripción activa']);
        exit;
    }
    
    // Obtener nuevo plan
    $stmt = $pdo->prepare("SELECT * FROM planes WHERE id = ? AND activo = 1");
    $stmt->execute([$nuevo_plan_id]);
    $nuevo_plan = $stmt->fetch();
    
    if (!$nuevo_plan) {
        echo json_encode(['success' => false, 'message' => 'Plan no válido']);
        exit;
    }
    
    // Calcular prorrateo
    $hoy = new DateTime();
    $fecha_fin = new DateTime($suscripcion_actual['fecha_fin']);
    $dias_restantes = max(0, $hoy->diff($fecha_fin)->days);
    
    $precio_actual_mensual = $suscripcion_actual['precio_mensual'];
    $dias_mes = 30;
    $precio_dia_actual = $precio_actual_mensual / $dias_mes;
    
    $credito = $precio_dia_actual * $dias_restantes;
    $precio_nuevo = ($tipo_pago === 'anual') ? $nuevo_plan['precio_anual'] : $nuevo_plan['precio_mensual'];
    $monto_a_pagar = max(0, $precio_nuevo - $credito);
    $tiene_credito = ($credito > $precio_nuevo);
    $credito_restante = $tiene_credito ? ($credito - $precio_nuevo) : 0;
    
    $response = [
        'success' => true,
        'detalles' => [
            'plan_actual' => $suscripcion_actual['plan_id'],
            'plan_nuevo' => $nuevo_plan_id,
            'dias_restantes' => $dias_restantes,
            'credito' => round($credito, 2),
            'precio_nuevo_plan' => $precio_nuevo,
            'monto_a_pagar' => round($monto_a_pagar, 2),
            'tiene_credito' => $tiene_credito,
            'credito_restante' => round($credito_restante, 2)
        ]
    ];
    
    // Si no hay que pagar (downgrade con crédito)
    if ($monto_a_pagar == 0) {
        $pdo->beginTransaction();
        
        try {
            // Cancelar suscripción en pasarela
            if (!empty($suscripcion_actual['suscripcion_externa_id'])) {
                cancelarSuscripcionPasarela($suscripcion_actual);
            }
            
            // Actualizar plan
            $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ? WHERE id = ?");
            $stmt->execute([$nuevo_plan_id, $empresa['id']]);
            
            // Actualizar suscripción
            $stmt = $pdo->prepare("
                UPDATE suscripciones 
                SET plan_id = ?, credito_disponible = ?
                WHERE empresa_id = ? AND estado = 'activa'
            ");
            $stmt->execute([$nuevo_plan_id, $credito_restante, $empresa['id']]);
            
            // Registrar cambio
            $stmt = $pdo->prepare("
                INSERT INTO cambios_plan 
                (empresa_id, plan_anterior_id, plan_nuevo_id, credito_aplicado, monto_pagado)
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([$empresa['id'], $plan_actual_id, $nuevo_plan_id, $credito]);
            
            $pdo->commit();
            
            $response['cambio_inmediato'] = true;
            $response['message'] = 'Plan cambiado exitosamente. Tu crédito cubrió el costo.';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        $response['pago_requerido'] = true;
        $response['message'] = 'Necesitas pagar la diferencia para cambiar de plan';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error cambiando plan: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function cancelarSuscripcionPasarela($suscripcion) {
    if ($suscripcion['metodo_pago'] === 'mercadopago') {
        $access_token = getPaymentConfig('mercadopago_access_token');
        if (empty($access_token)) return;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval/" . $suscripcion['suscripcion_externa_id']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'cancelled']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token,
            "Content-Type: application/json"
        ]);
        curl_exec($ch);
        curl_close($ch);
        
    } elseif ($suscripcion['metodo_pago'] === 'paypal') {
        $client_id = getPaymentConfig('paypal_client_id');
        $secret = getPaymentConfig('paypal_secret');
        $mode = getPaymentConfig('paypal_mode') ?: 'sandbox';
        
        if (empty($client_id) || empty($secret)) return;
        
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
        if (!isset($auth['access_token'])) return;
        
        // Cancelar
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . "/v1/billing/subscriptions/" . $suscripcion['suscripcion_externa_id'] . "/cancel");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reason' => 'Plan change']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $auth['access_token'],
            "Content-Type: application/json"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}