<?php
// sistema/api/v1/webhooks/mercadopago.php
// Webhook para procesar notificaciones de MercadoPago

// No requiere sesión
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';

// Log de entrada para debug
error_log("Webhook MercadoPago recibido: " . file_get_contents('php://input'));

// Obtener datos del webhook
$data = json_decode(file_get_contents('php://input'), true);

// Validar que sea una notificación válida
if (!isset($data['type']) || !isset($data['data']['id'])) {
    http_response_code(400);
    exit;
}

try {
    switch ($data['type']) {
        case 'subscription_preapproval':
            procesarSuscripcion($data['data']['id']);
            break;
            
        case 'subscription_authorized_payment':
            procesarPagoRecurrente($data['data']['id']);
            break;
    }
    
    // Responder 200 OK
    http_response_code(200);
    echo "OK";
    
} catch (Exception $e) {
    error_log("Error en webhook MercadoPago: " . $e->getMessage());
    http_response_code(500);
}

/**
 * Procesar nueva suscripción o actualización
 */
function procesarSuscripcion($preapproval_id) {
    global $pdo;
    
    // Obtener token de MercadoPago desde BD
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'mercadopago_access_token'");
    $stmt->execute();
    $mp_token = $stmt->fetchColumn();
    
    // Obtener detalles de la suscripción desde MP
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval/" . $preapproval_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $mp_token
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Error obteniendo suscripción de MP");
    }
    
    $subscription = json_decode($response, true);
    
    // Buscar el pago pendiente con esta referencia
    $stmt = $pdo->prepare("
        SELECT p.*, e.email 
        FROM pagos p 
        JOIN empresas e ON p.empresa_id = e.id 
        WHERE p.referencia_externa = ?
    ");
    $stmt->execute([$preapproval_id]);
    $pago = $stmt->fetch();
    
    if (!$pago) {
        error_log("No se encontró pago para preapproval_id: " . $preapproval_id);
        return;
    }
    
    // Actualizar según estado
    if ($subscription['status'] === 'authorized') {
        // Suscripción aprobada
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
            $stmt->execute([json_encode($subscription), $pago['id']]);
            
            // ✅ CORREGIDO: Crear o actualizar en tabla suscripciones (no suscripciones_pago)
            $tipo = ($subscription['auto_recurring']['frequency'] == 12) ? 'anual' : 'mensual';
            $fecha_fin = ($tipo == 'anual') 
                ? date('Y-m-d', strtotime('+1 year'))
                : date('Y-m-d', strtotime('+1 month'));
            
            $fecha_proximo = date('Y-m-d', strtotime($subscription['next_payment_date']));
            $monto = $subscription['auto_recurring']['transaction_amount'];
            
            $stmt = $pdo->prepare("
                INSERT INTO suscripciones 
                (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, auto_renovar, metodo_pago, referencia_externa, suscripcion_externa_id, fecha_proximo_pago, monto, metadata)
                VALUES (?, ?, ?, CURDATE(), ?, 'activa', 1, 'mercadopago', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    estado = 'activa',
                    fecha_fin = VALUES(fecha_fin),
                    fecha_proximo_pago = VALUES(fecha_proximo_pago),
                    monto = VALUES(monto),
                    metadata = VALUES(metadata)
            ");
            
            $stmt->execute([
                $pago['empresa_id'],
                $pago['plan_id'],
                $tipo,
                $fecha_fin,
                $preapproval_id,
                $preapproval_id,
                $fecha_proximo,
                $monto,
                json_encode($subscription)
            ]);
            
            // Actualizar empresa con nuevo plan
            $stmt = $pdo->prepare("
                UPDATE empresas 
                SET plan_id = ?, activo = 1
                WHERE id = ?
            ");
            $stmt->execute([$pago['plan_id'], $pago['empresa_id']]);
            
            $pdo->commit();
            
            // Enviar email de confirmación
            enviarEmailSuscripcion($pago['email'], 'aprobada', $subscription);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif (in_array($subscription['status'], ['cancelled', 'paused'])) {
        // ✅ CORREGIDO: Actualizar en tabla suscripciones
        $nuevo_estado = ($subscription['status'] === 'cancelled') ? 'cancelada' : 'activa';
        $auto_renovar = ($subscription['status'] === 'cancelled') ? 0 : 1;
        
        $stmt = $pdo->prepare("
            UPDATE suscripciones 
            SET estado = ?, auto_renovar = ?
            WHERE suscripcion_externa_id = ?
        ");
        $stmt->execute([$nuevo_estado, $auto_renovar, $preapproval_id]);
    }
}

/**
 * Procesar pago recurrente
 */
function procesarPagoRecurrente($payment_id) {
    global $pdo;
    
    // Obtener token de MercadoPago desde BD
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'mercadopago_access_token'");
    $stmt->execute();
    $mp_token = $stmt->fetchColumn();
    
    // Obtener detalles del pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/authorized_payments/" . $payment_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $mp_token
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $payment = json_decode($response, true);
    
    if ($payment['status'] === 'approved') {
        // Registrar pago exitoso
        $preapproval_id = $payment['preapproval_id'];
        
        // ✅ CORREGIDO: Obtener desde tabla suscripciones
        $stmt = $pdo->prepare("
            SELECT * FROM suscripciones 
            WHERE suscripcion_externa_id = ?
        ");
        $stmt->execute([$preapproval_id]);
        $suscripcion = $stmt->fetch();
        
        if ($suscripcion) {
            $pdo->beginTransaction();
            
            try {
                // Registrar pago
                $stmt = $pdo->prepare("
                    INSERT INTO pagos 
                    (empresa_id, suscripcion_id, plan_id, monto, metodo, referencia_externa, estado, fecha_pago, respuesta_gateway)
                    VALUES (?, ?, ?, ?, 'mercadopago', ?, 'aprobado', NOW(), ?)
                ");
                
                $stmt->execute([
                    $suscripcion['empresa_id'],
                    $suscripcion['id'],
                    $suscripcion['plan_id'],
                    $payment['transaction_amount'],
                    $payment_id,
                    json_encode($payment)
                ]);
                
                // Extender suscripción según tipo
                if ($suscripcion['tipo'] === 'anual') {
                    $nuevo_fecha_fin = date('Y-m-d', strtotime($suscripcion['fecha_fin'] . ' +1 year'));
                } else {
                    $nuevo_fecha_fin = date('Y-m-d', strtotime($suscripcion['fecha_fin'] . ' +1 month'));
                }
                
                $fecha_proximo = date('Y-m-d', strtotime($nuevo_fecha_fin));
                
                $stmt = $pdo->prepare("
                    UPDATE suscripciones 
                    SET fecha_fin = ?,
                        fecha_proximo_pago = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nuevo_fecha_fin, $fecha_proximo, $suscripcion['id']]);
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}

/**
 * Enviar email de confirmación
 */
function enviarEmailSuscripcion($email, $estado, $datos) {
    // Implementar envío de email
    // Por ahora solo log
    error_log("Email de suscripción $estado enviado a $email");
}