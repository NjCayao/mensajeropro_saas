<?php
// app/api/v1/cliente/pagos/cambiar-plan.php
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

$data = json_decode(file_get_contents('php://input'), true);
$nuevo_plan_id = $data['plan_id'] ?? 0;
$tipo_pago = $data['tipo_pago'] ?? 'mensual';

try {
    // Obtener plan actual y nuevo plan
    $empresa = getDatosEmpresa();
    $plan_actual_id = $empresa['plan_id'];
    
    // Verificar suscripción activa
    $stmt = $pdo->prepare("
        SELECT sp.*, p.precio_mensual, p.precio_anual, s.fecha_inicio, s.fecha_fin
        FROM suscripciones_pago sp
        JOIN suscripciones s ON s.empresa_id = sp.empresa_id AND s.estado = 'activa'
        JOIN planes p ON p.id = s.plan_id
        WHERE sp.empresa_id = ? AND sp.estado = 'activa'
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
    
    // Calcular el prorrateo
    $hoy = new DateTime();
    $fecha_fin = new DateTime($suscripcion_actual['fecha_fin']);
    $dias_restantes = $hoy->diff($fecha_fin)->days;
    
    // Precio por día del plan actual
    $precio_actual_mensual = $suscripcion_actual['precio_mensual'];
    $dias_mes = 30; // Simplificado
    $precio_dia_actual = $precio_actual_mensual / $dias_mes;
    
    // Crédito por días no usados
    $credito = $precio_dia_actual * $dias_restantes;
    
    // Precio del nuevo plan
    $precio_nuevo = ($tipo_pago === 'anual') ? $nuevo_plan['precio_anual'] : $nuevo_plan['precio_mensual'];
    
    // Monto a pagar = Precio nuevo - Crédito
    $monto_a_pagar = max(0, $precio_nuevo - $credito);
    
    // Si es downgrade (plan más barato), el crédito se guarda para el próximo mes
    $tiene_credito = ($credito > $precio_nuevo);
    
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
            'credito_restante' => $tiene_credito ? round($credito - $precio_nuevo, 2) : 0
        ]
    ];
    
    // Si no hay que pagar (downgrade con crédito suficiente)
    if ($monto_a_pagar == 0) {
        // Actualizar directamente
        $pdo->beginTransaction();
        
        try {
            // Cancelar suscripción actual en la pasarela
            cancelarSuscripcionPasarela($suscripcion_actual);
            
            // Actualizar plan
            $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ? WHERE id = ?");
            $stmt->execute([$nuevo_plan_id, $empresa['id']]);
            
            // Actualizar suscripción
            $stmt = $pdo->prepare("
                UPDATE suscripciones 
                SET plan_id = ?, 
                    credito_disponible = ?
                WHERE empresa_id = ? AND estado = 'activa'
            ");
            $stmt->execute([$nuevo_plan_id, $credito_restante, $empresa['id']]);
            
            // Registrar el cambio
            $stmt = $pdo->prepare("
                INSERT INTO cambios_plan 
                (empresa_id, plan_anterior_id, plan_nuevo_id, credito_aplicado, monto_pagado, fecha_cambio)
                VALUES (?, ?, ?, ?, 0, NOW())
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
        // Necesita pagar - crear nueva suscripción con el monto prorrateado
        if ($data['confirmar_pago'] ?? false) {
            // Procesar el pago
            $response['pago_requerido'] = true;
            $response['url_pago'] = crearPagoProrrateado($empresa, $nuevo_plan, $monto_a_pagar, $credito);
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error cambiando plan: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar el cambio']);
}

/**
 * Cancelar suscripción en la pasarela
 */
function cancelarSuscripcionPasarela($suscripcion) {
    if ($suscripcion['metodo'] === 'mercadopago') {
        // Cancelar en MercadoPago
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval/" . $suscripcion['suscripcion_externa_id']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'cancelled']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . MP_ACCESS_TOKEN,
            "Content-Type: application/json"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    // Similar para PayPal
}

/**
 * Crear pago prorrateado
 */
function crearPagoProrrateado($empresa, $nuevo_plan, $monto, $credito) {
    // Aquí creas una preferencia de pago único para la diferencia
    // y al confirmarse, creas la nueva suscripción recurrente
    
    // Por simplicidad, retornamos URL de ejemplo
    return url('cliente/pagar-cambio-plan?monto=' . $monto);
}
?>