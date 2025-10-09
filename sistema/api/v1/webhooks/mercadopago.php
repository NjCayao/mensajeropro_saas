<?php
// sistema/api/v1/webhooks/mercadopago.php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/email.php';

$raw_input = file_get_contents('php://input');
error_log("Webhook MercadoPago recibido: " . $raw_input);

$data = json_decode($raw_input, true);

if (!isset($data['type']) || !isset($data['data']['id'])) {
    http_response_code(400);
    exit;
}

try {
    switch ($data['type']) {
        case 'payment':
            procesarPagoUnico($data['data']['id']);
            break;

        case 'subscription_preapproval':
            procesarSuscripcion($data['data']['id']);
            break;

        case 'subscription_authorized_payment':
            procesarPagoRecurrente($data['data']['id']);
            break;

        default:
            error_log("MercadoPago: Evento no manejado: " . $data['type']);
    }

    http_response_code(200);
    echo "OK";
} catch (Exception $e) {
    error_log("Error en webhook MercadoPago: " . $e->getMessage());
    http_response_code(500);
}

function procesarSuscripcion($preapproval_id)
{
    global $pdo;

    // Obtener token
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'mercadopago_access_token'");
    $stmt->execute();
    $mp_token = $stmt->fetchColumn();

    if (!$mp_token) {
        error_log("MercadoPago: Token no configurado");
        return;
    }

    // Obtener detalles de la suscripción
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/preapproval/" . $preapproval_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mp_token]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("MercadoPago: Error HTTP $http_code obteniendo suscripción");
        return;
    }

    $subscription = json_decode($response, true);

    // Buscar pago pendiente
    $stmt = $pdo->prepare("
        SELECT p.*, e.email, e.razon_social, pl.nombre as plan_nombre
        FROM pagos p 
        JOIN empresas e ON p.empresa_id = e.id 
        LEFT JOIN planes pl ON p.plan_id = pl.id
        WHERE p.referencia_externa = ? AND p.estado = 'pendiente'
    ");
    $stmt->execute([$preapproval_id]);
    $pago = $stmt->fetch();

    if (!$pago) {
        error_log("MercadoPago: No se encontró pago pendiente para: " . $preapproval_id);
        return;
    }

    if ($subscription['status'] === 'authorized') {
        $pdo->beginTransaction();

        try {
            $monto = $subscription['auto_recurring']['transaction_amount'];

            // ✅ Actualizar pago
            $stmt = $pdo->prepare("
                UPDATE pagos 
                SET estado = 'aprobado', 
                    fecha_pago = NOW(),
                    monto = ?,
                    respuesta_gateway = ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, json_encode($subscription), $pago['id']]);

            // ✅ DETECTAR ANUAL O MENSUAL
            $tipo = 'mensual';
            $fecha_fin = date('Y-m-d', strtotime('+1 month'));

            // Obtener precios del plan
            $stmt = $pdo->prepare("SELECT precio_mensual, precio_anual FROM planes WHERE id = ?");
            $stmt->execute([$pago['plan_id']]);
            $plan_precios = $stmt->fetch();

            if ($plan_precios) {
                $diferencia_mensual = abs($monto - $plan_precios['precio_mensual']);
                $diferencia_anual = abs($monto - $plan_precios['precio_anual']);

                error_log("MercadoPago - Detección de tipo:");
                error_log("  Monto pagado: $" . $monto);
                error_log("  Precio mensual: $" . $plan_precios['precio_mensual']);
                error_log("  Precio anual: $" . $plan_precios['precio_anual']);

                if ($diferencia_anual < $diferencia_mensual && $diferencia_anual < 1) {
                    $tipo = 'anual';
                    $fecha_fin = date('Y-m-d', strtotime('+1 year'));
                    error_log("  ✅ Detectado pago ANUAL");
                } else {
                    error_log("  ✅ Detectado pago MENSUAL");
                }
            }

            $fecha_proximo = date('Y-m-d', strtotime($subscription['next_payment_date'] ?? $fecha_fin));

            // ✅ Verificar suscripción existente
            $stmt = $pdo->prepare("
                SELECT id FROM suscripciones 
                WHERE empresa_id = ? AND estado = 'activa'
            ");
            $stmt->execute([$pago['empresa_id']]);
            $suscripcion_existente = $stmt->fetch();

            if ($suscripcion_existente) {
                // Actualizar
                $stmt = $pdo->prepare("
                    UPDATE suscripciones 
                    SET plan_id = ?, tipo = ?, fecha_fin = ?, fecha_proximo_pago = ?, 
                        monto = ?, metodo_pago = 'mercadopago', suscripcion_externa_id = ?,
                        referencia_externa = ?, metadata = ?, auto_renovar = 1
                    WHERE id = ?
                ");
                $stmt->execute([
                    $pago['plan_id'],
                    $tipo,
                    $fecha_fin,
                    $fecha_proximo,
                    $monto,
                    $preapproval_id,
                    $preapproval_id,
                    json_encode($subscription),
                    $suscripcion_existente['id']
                ]);
            } else {
                // Crear nueva
                $stmt = $pdo->prepare("
                    INSERT INTO suscripciones 
                    (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, auto_renovar, metodo_pago, referencia_externa, suscripcion_externa_id, fecha_proximo_pago, monto, metadata)
                    VALUES (?, ?, ?, CURDATE(), ?, 'activa', 1, 'mercadopago', ?, ?, ?, ?, ?)
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
            }

            // Activar empresa
            $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ?, activo = 1 WHERE id = ?");
            $stmt->execute([$pago['plan_id'], $pago['empresa_id']]);

            $pdo->commit();

            // ✅ ENVIAR EMAIL
            enviarEmailBienvenida(
                $pago['email'],
                $pago['razon_social'],
                $pago['plan_nombre'],
                $monto,
                $fecha_fin
            );

            error_log("MercadoPago: Suscripción {$tipo} activada para empresa_id: " . $pago['empresa_id']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif (in_array($subscription['status'], ['cancelled', 'paused'])) {
        $nuevo_estado = ($subscription['status'] === 'cancelled') ? 'cancelada' : 'activa';
        $auto_renovar = ($subscription['status'] === 'cancelled') ? 0 : 1;

        $stmt = $pdo->prepare("
            UPDATE suscripciones 
            SET estado = ?, auto_renovar = ?
            WHERE suscripcion_externa_id = ?
        ");
        $stmt->execute([$nuevo_estado, $auto_renovar, $preapproval_id]);

        error_log("MercadoPago: Suscripción actualizada a estado: " . $nuevo_estado);
    }
}

function procesarPagoRecurrente($payment_id)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'mercadopago_access_token'");
    $stmt->execute();
    $mp_token = $stmt->fetchColumn();

    // Obtener detalles del pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/authorized_payments/" . $payment_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mp_token]);

    $response = curl_exec($ch);
    curl_close($ch);

    $payment = json_decode($response, true);

    if ($payment['status'] === 'approved') {
        $preapproval_id = $payment['preapproval_id'];

        // ✅ Verificar duplicado
        $stmt = $pdo->prepare("
            SELECT id FROM pagos 
            WHERE referencia_externa = ? AND metodo = 'mercadopago'
        ");
        $stmt->execute([$payment_id]);

        if ($stmt->fetch()) {
            error_log("MercadoPago: Pago ya procesado: " . $payment_id);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT s.*, e.email, e.razon_social, pl.nombre as plan_nombre
            FROM suscripciones s
            JOIN empresas e ON s.empresa_id = e.id
            LEFT JOIN planes pl ON s.plan_id = pl.id
            WHERE s.suscripcion_externa_id = ?
        ");
        $stmt->execute([$preapproval_id]);
        $suscripcion = $stmt->fetch();

        if ($suscripcion) {
            $pdo->beginTransaction();

            try {
                $monto = $payment['transaction_amount'];

                // Registrar pago de renovación
                $stmt = $pdo->prepare("
                    INSERT INTO pagos 
                    (empresa_id, suscripcion_id, plan_id, monto, metodo, referencia_externa, estado, fecha_pago, respuesta_gateway)
                    VALUES (?, ?, ?, ?, 'mercadopago', ?, 'aprobado', NOW(), ?)
                ");
                $stmt->execute([
                    $suscripcion['empresa_id'],
                    $suscripcion['id'],
                    $suscripcion['plan_id'],
                    $monto,
                    $payment_id,
                    json_encode($payment)
                ]);

                // Extender suscripción
                if ($suscripcion['tipo'] === 'anual') {
                    $nuevo_fecha_fin = date('Y-m-d', strtotime($suscripcion['fecha_fin'] . ' +1 year'));
                } else {
                    $nuevo_fecha_fin = date('Y-m-d', strtotime($suscripcion['fecha_fin'] . ' +1 month'));
                }

                $stmt = $pdo->prepare("
                    UPDATE suscripciones 
                    SET fecha_fin = ?, fecha_proximo_pago = ?, monto = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nuevo_fecha_fin, $nuevo_fecha_fin, $monto, $suscripcion['id']]);

                $pdo->commit();

                // ✅ Email de renovación
                enviarEmailRenovacion(
                    $suscripcion['email'],
                    $suscripcion['razon_social'],
                    $suscripcion['plan_nombre'],
                    $monto,
                    $nuevo_fecha_fin
                );

                error_log("MercadoPago: Renovación procesada para empresa_id: " . $suscripcion['empresa_id']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}

function enviarEmailBienvenida($email, $empresa, $plan, $monto, $fecha_fin)
{
    error_log("=== ENVIANDO EMAIL BIENVENIDA (MP) ===");
    error_log("Email: {$email}");

    $asunto = "¡Bienvenido a MensajeroPro!";
    $mensaje = "
        <h2>¡Gracias por suscribirte, {$empresa}!</h2>
        <p>Tu suscripción al plan <strong>{$plan}</strong> ha sido activada.</p>
        <p><strong>Detalles:</strong></p>
        <ul>
            <li>Monto: $" . number_format($monto, 2) . "</li>
            <li>Válido hasta: " . date('d/m/Y', strtotime($fecha_fin)) . "</li>
        </ul>
        <p>Accede: <a href='" . APP_URL . "'>Iniciar Sesión</a></p>
    ";

    return enviarEmail($email, $asunto, $mensaje);
}

function enviarEmailRenovacion($email, $empresa, $plan, $monto, $fecha_fin)
{
    $asunto = "Suscripción Renovada - MensajeroPro";
    $mensaje = "
        <h2>Renovación Exitosa</h2>
        <p>Hola {$empresa}, tu suscripción al plan <strong>{$plan}</strong> ha sido renovada.</p>
        <ul>
            <li>Monto: $" . number_format($monto, 2) . "</li>
            <li>Próxima renovación: " . date('d/m/Y', strtotime($fecha_fin)) . "</li>
        </ul>
    ";

    return enviarEmail($email, $asunto, $mensaje);
}

function procesarPagoUnico($payment_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'mercadopago_access_token'");
    $stmt->execute();
    $mp_token = $stmt->fetchColumn();
    
    if (!$mp_token) {
        error_log("MercadoPago: Token no configurado");
        return;
    }
    
    // Obtener detalles del pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/" . $payment_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mp_token]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("MercadoPago: Error HTTP $http_code obteniendo pago");
        return;
    }
    
    $payment = json_decode($response, true);
    
    if ($payment['status'] === 'approved') {
        // Extraer external_reference
        $external_ref = $payment['external_reference'];
        preg_match('/empresa_(\d+)_plan_(\d+)_(mensual|anual)/', $external_ref, $matches);
        
        if (!$matches) {
            error_log("MercadoPago: External reference inválido: " . $external_ref);
            return;
        }
        
        $empresa_id = $matches[1];
        $plan_id = $matches[2];
        $tipo_pago = $matches[3];
        
        $pdo->beginTransaction();
        
        try {
            $monto = $payment['transaction_amount'];
            
            // Verificar duplicado
            $stmt = $pdo->prepare("
                SELECT id FROM pagos 
                WHERE referencia_externa = ? AND metodo = 'mercadopago'
            ");
            $stmt->execute([$payment_id]);
            
            if ($stmt->fetch()) {
                error_log("MercadoPago: Pago único ya procesado: " . $payment_id);
                $pdo->rollBack();
                return;
            }
            
            // Registrar pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos 
                (empresa_id, plan_id, monto, metodo, referencia_externa, estado, fecha_pago, respuesta_gateway)
                VALUES (?, ?, ?, 'mercadopago', ?, 'aprobado', NOW(), ?)
            ");
            $stmt->execute([$empresa_id, $plan_id, $monto, $payment_id, json_encode($payment)]);
            
            // Calcular nueva fecha_fin
            $dias = ($tipo_pago === 'anual') ? 365 : 30;
            
            // Verificar suscripción existente
            $stmt = $pdo->prepare("
                SELECT id, fecha_fin FROM suscripciones 
                WHERE empresa_id = ? AND estado = 'activa'
                ORDER BY fecha_fin DESC LIMIT 1
            ");
            $stmt->execute([$empresa_id]);
            $suscripcion = $stmt->fetch();
            
            if ($suscripcion) {
                // Extender desde la fecha actual o fecha_fin (la que sea mayor)
                $fecha_base = max(date('Y-m-d'), $suscripcion['fecha_fin']);
                $nueva_fecha_fin = date('Y-m-d', strtotime($fecha_base . " +{$dias} days"));
                
                $stmt = $pdo->prepare("
                    UPDATE suscripciones 
                    SET plan_id = ?, tipo = ?, fecha_fin = ?
                    WHERE id = ?
                ");
                $stmt->execute([$plan_id, $tipo_pago, $nueva_fecha_fin, $suscripcion['id']]);
            } else {
                // Crear nueva
                $fecha_fin = date('Y-m-d', strtotime("+{$dias} days"));
                
                $stmt = $pdo->prepare("
                    INSERT INTO suscripciones 
                    (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, metodo_pago)
                    VALUES (?, ?, ?, CURDATE(), ?, 'activa', 'mercadopago')
                ");
                $stmt->execute([$empresa_id, $plan_id, $tipo_pago, $fecha_fin]);
            }
            
            // Activar empresa
            $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ?, activo = 1 WHERE id = ?");
            $stmt->execute([$plan_id, $empresa_id]);
            
            $pdo->commit();
            
            error_log("✅ Pago Yape procesado: empresa_id={$empresa_id}, plan_id={$plan_id}, tipo={$tipo_pago}, dias={$dias}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error procesando pago Yape: " . $e->getMessage());
        }
    }
}
