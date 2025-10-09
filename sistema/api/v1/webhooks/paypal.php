<?php
// sistema/api/v1/webhooks/paypal.php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/email.php';

$raw_input = file_get_contents('php://input');
error_log("Webhook PayPal recibido: " . $raw_input);

$data = json_decode($raw_input, true);

if (!$data || !isset($data['event_type'])) {
    http_response_code(400);
    exit;
}

try {
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

    http_response_code(200);
    echo "OK";
} catch (Exception $e) {
    error_log("Error en webhook PayPal: " . $e->getMessage());
    http_response_code(500);
}

/**
 * Procesar suscripción activada (NO crea pago, solo actualiza estado)
 */
function procesarSuscripcionActivada($data)
{
    global $pdo;

    $subscription_id = $data['resource']['id'];
    $status = $data['resource']['status'];

    // Buscar el pago pendiente
    $stmt = $pdo->prepare("
        SELECT p.*, e.email, e.razon_social, pl.nombre as plan_nombre
        FROM pagos p 
        JOIN empresas e ON p.empresa_id = e.id 
        LEFT JOIN planes pl ON p.plan_id = pl.id
        WHERE p.referencia_externa = ? AND p.estado = 'pendiente'
    ");
    $stmt->execute([$subscription_id]);
    $pago = $stmt->fetch();

    if (!$pago) {
        error_log("PayPal: No se encontró pago pendiente para: " . $subscription_id);
        return;
    }

    if ($status === 'ACTIVE') {
        $pdo->beginTransaction();

        try {
            // Obtener monto del último pago
            $monto = 0;
            if (isset($data['resource']['billing_info']['last_payment']['amount']['value'])) {
                $monto = (float)$data['resource']['billing_info']['last_payment']['amount']['value'];
            }

            // ✅ CONFIRMAR EL PAGO
            $stmt = $pdo->prepare("
                UPDATE pagos 
                SET estado = 'aprobado', 
                    fecha_pago = NOW(),
                    monto = ?,
                    respuesta_gateway = ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, json_encode($data), $pago['id']]);

            // ✅ DETECTAR SI ES MENSUAL O ANUAL
            $tipo = 'mensual';
            $fecha_fin = date('Y-m-d', strtotime('+1 month'));

            // Obtener precios del plan para comparar
            $stmt = $pdo->prepare("SELECT precio_mensual, precio_anual FROM planes WHERE id = ?");
            $stmt->execute([$pago['plan_id']]);
            $plan_precios = $stmt->fetch();

            if ($plan_precios) {
                // Comparar monto pagado con precios (tolerancia de $1)
                $diferencia_mensual = abs($monto - $plan_precios['precio_mensual']);
                $diferencia_anual = abs($monto - $plan_precios['precio_anual']);

                error_log("PayPal - Detección de tipo:");
                error_log("  Monto pagado: $" . $monto);
                error_log("  Precio mensual: $" . $plan_precios['precio_mensual']);
                error_log("  Precio anual: $" . $plan_precios['precio_anual']);
                error_log("  Diferencia mensual: $" . $diferencia_mensual);
                error_log("  Diferencia anual: $" . $diferencia_anual);

                if ($diferencia_anual < $diferencia_mensual && $diferencia_anual < 1) {
                    // Es pago anual
                    $tipo = 'anual';
                    $fecha_fin = date('Y-m-d', strtotime('+1 year'));
                    error_log("  ✅ Detectado pago ANUAL");
                } else {
                    error_log("  ✅ Detectado pago MENSUAL");
                }
            }

            $next_billing_time = $data['resource']['billing_info']['next_billing_time'] ?? null;
            $fecha_proximo = $next_billing_time ? date('Y-m-d', strtotime($next_billing_time)) : $fecha_fin;

            // ✅ VERIFICAR SI YA EXISTE SUSCRIPCIÓN ACTIVA
            $stmt = $pdo->prepare("
                SELECT id FROM suscripciones 
                WHERE empresa_id = ? AND estado = 'activa'
            ");
            $stmt->execute([$pago['empresa_id']]);
            $suscripcion_existente = $stmt->fetch();

            if ($suscripcion_existente) {
                // ✅ ACTUALIZAR suscripción existente
                $stmt = $pdo->prepare("
                    UPDATE suscripciones 
                    SET plan_id = ?,
                        tipo = ?,
                        fecha_fin = ?,
                        fecha_proximo_pago = ?,
                        monto = ?,
                        metodo_pago = 'paypal',
                        suscripcion_externa_id = ?,
                        referencia_externa = ?,
                        metadata = ?,
                        auto_renovar = 1
                    WHERE id = ?
                ");

                $stmt->execute([
                    $pago['plan_id'],
                    $tipo,
                    $fecha_fin,
                    $fecha_proximo,
                    $monto,
                    $subscription_id,
                    $subscription_id,
                    json_encode($data),
                    $suscripcion_existente['id']
                ]);

                error_log("PayPal: Suscripción actualizada (ID: {$suscripcion_existente['id']})");
            } else {
                // ✅ CREAR nueva suscripción
                $stmt = $pdo->prepare("
                    INSERT INTO suscripciones 
                    (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado, auto_renovar, metodo_pago, referencia_externa, suscripcion_externa_id, fecha_proximo_pago, monto, metadata)
                    VALUES (?, ?, ?, CURDATE(), ?, 'activa', 1, 'paypal', ?, ?, ?, ?, ?)
                ");

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

                error_log("PayPal: Nueva suscripción creada");
            }

            // Activar empresa
            $stmt = $pdo->prepare("UPDATE empresas SET plan_id = ?, activo = 1 WHERE id = ?");
            $stmt->execute([$pago['plan_id'], $pago['empresa_id']]);

            $pdo->commit();

            // ✅ ENVIAR EMAIL DE BIENVENIDA
            enviarEmailBienvenida(
                $pago['email'],
                $pago['razon_social'],
                $pago['plan_nombre'],
                $monto,
                $fecha_fin
            );

            error_log("PayPal: Suscripción {$tipo} activada para empresa_id: " . $pago['empresa_id']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error procesando suscripción: " . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Procesar pago recurrente (AQUÍ se confirma el pago y se envía email)
 */
function procesarPagoRecurrente($data)
{
    global $pdo;

    $sale_id = $data['resource']['id'];
    $billing_agreement_id = $data['resource']['billing_agreement_id'] ?? null;

    if (!$billing_agreement_id) {
        error_log("PayPal: Pago sin billing_agreement_id");
        return;
    }

    // Obtener suscripción
    $stmt = $pdo->prepare("
        SELECT s.*, e.email, e.razon_social, pl.nombre as plan_nombre
        FROM suscripciones s
        JOIN empresas e ON s.empresa_id = e.id
        LEFT JOIN planes pl ON s.plan_id = pl.id
        WHERE s.suscripcion_externa_id = ?
    ");
    $stmt->execute([$billing_agreement_id]);
    $suscripcion = $stmt->fetch();

    if (!$suscripcion) {
        error_log("PayPal: No se encontró suscripción para: " . $billing_agreement_id);
        return;
    }

    if ($data['resource']['state'] === 'completed') {
        $pdo->beginTransaction();

        try {
            $monto = (float)$data['resource']['amount']['total'];

            // ✅ Verificar si ya existe este pago (evitar duplicados)
            $stmt = $pdo->prepare("
                SELECT id FROM pagos 
                WHERE referencia_externa = ? AND metodo = 'paypal'
            ");
            $stmt->execute([$sale_id]);

            if ($stmt->fetch()) {
                error_log("PayPal: Pago ya procesado: " . $sale_id);
                $pdo->rollBack();
                return;
            }

            // ✅ Buscar pago pendiente o en procesamiento
            $stmt = $pdo->prepare("
                SELECT id FROM pagos 
                WHERE empresa_id = ? 
                AND suscripcion_id = ? 
                AND estado IN ('pendiente', 'procesando')
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$suscripcion['empresa_id'], $suscripcion['id']]);
            $pago_pendiente = $stmt->fetch();

            if ($pago_pendiente) {
                // ✅ Actualizar pago existente
                $stmt = $pdo->prepare("
                    UPDATE pagos 
                    SET estado = 'aprobado',
                        fecha_pago = NOW(),
                        referencia_externa = ?,
                        monto = ?,
                        respuesta_gateway = ?
                    WHERE id = ?
                ");
                $stmt->execute([$sale_id, $monto, json_encode($data), $pago_pendiente['id']]);

                $es_primer_pago = true;
            } else {
                // ✅ Crear nuevo pago (renovación mensual)
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

                $es_primer_pago = false;
            }

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

            // ✅ ENVIAR EMAIL DE CONFIRMACIÓN
            if ($es_primer_pago) {
                enviarEmailBienvenida(
                    $suscripcion['email'],
                    $suscripcion['razon_social'],
                    $suscripcion['plan_nombre'],
                    $monto,
                    $nuevo_fecha_fin
                );
            } else {
                enviarEmailRenovacion(
                    $suscripcion['email'],
                    $suscripcion['razon_social'],
                    $suscripcion['plan_nombre'],
                    $monto,
                    $nuevo_fecha_fin
                );
            }

            error_log("PayPal: Pago procesado correctamente para empresa_id: " . $suscripcion['empresa_id']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

function procesarSuscripcionCancelada($data)
{
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

function enviarEmailBienvenida($email, $empresa, $plan, $monto, $fecha_fin)
{
    $asunto = "¡Bienvenido a MensajeroPro!";
    $mensaje = "
        <h2>¡Gracias por suscribirte, {$empresa}!</h2>
        <p>Tu suscripción al plan <strong>{$plan}</strong> ha sido activada exitosamente.</p>
        <p><strong>Detalles:</strong></p>
        <ul>
            <li>Monto: $" . number_format($monto, 2) . " USD</li>
            <li>Válido hasta: " . date('d/m/Y', strtotime($fecha_fin)) . "</li>
        </ul>
        <p>Accede a tu panel: <a href='" . APP_URL . "'>Iniciar Sesión</a></p>
    ";

    enviarEmail($email, $asunto, $mensaje);
}

function enviarEmailRenovacion($email, $empresa, $plan, $monto, $fecha_fin)
{
    $asunto = "Suscripción Renovada - MensajeroPro";
    $mensaje = "
        <h2>Renovación Exitosa</h2>
        <p>Hola {$empresa},</p>
        <p>Tu suscripción al plan <strong>{$plan}</strong> ha sido renovada.</p>
        <p><strong>Detalles:</strong></p>
        <ul>
            <li>Monto: $" . number_format($monto, 2) . " USD</li>
            <li>Próxima renovación: " . date('d/m/Y', strtotime($fecha_fin)) . "</li>
        </ul>
    ";

    enviarEmail($email, $asunto, $mensaje);
}
