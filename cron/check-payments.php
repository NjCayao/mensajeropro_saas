<?php
// cron/check-payments.php
// Ejecutar cada hora para verificar pagos y vencimientos

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificación de pagos...\n";

// 1. Verificar trials vencidos
$stmt = $pdo->prepare("
    SELECT * FROM empresas 
    WHERE plan_id = 1 
    AND fecha_expiracion_trial < NOW() 
    AND activo = 1
");
$stmt->execute();
$trials_vencidos = $stmt->fetchAll();

foreach ($trials_vencidos as $empresa) {
    echo "Trial vencido para empresa ID {$empresa['id']} - {$empresa['nombre_empresa']}\n";
    
    // Suspender empresa si no tiene suscripción activa
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM suscripciones 
        WHERE empresa_id = ? AND estado = 'activa'
    ");
    $stmt_check->execute([$empresa['id']]);
    
    if ($stmt_check->fetchColumn() == 0) {
        // Suspender
        $stmt_suspend = $pdo->prepare("UPDATE empresas SET activo = 0 WHERE id = ?");
        $stmt_suspend->execute([$empresa['id']]);
        
        // Notificar
        crearNotificacion($empresa['id'], 'trial_vencido', 
            'Tu periodo de prueba ha terminado', 
            'Tu periodo de prueba de ' . TRIAL_DAYS . ' días ha finalizado. Por favor, suscríbete a un plan para continuar usando el servicio.'
        );
    }
}

// 2. Verificar suscripciones por vencer (3 días antes)
$stmt = $pdo->prepare("
    SELECT s.*, e.nombre_empresa, e.email, p.nombre as plan_nombre
    FROM suscripciones s
    JOIN empresas e ON s.empresa_id = e.id
    JOIN planes p ON s.plan_id = p.id
    WHERE s.estado = 'activa' 
    AND s.fecha_fin BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
    AND s.auto_renovar = 0
");
$stmt->execute();
$por_vencer = $stmt->fetchAll();

foreach ($por_vencer as $suscripcion) {
    echo "Suscripción por vencer para empresa ID {$suscripcion['empresa_id']}\n";
    
    // Verificar si ya se envió notificación
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM notificaciones_pago 
        WHERE empresa_id = ? 
        AND tipo = 'recordatorio_pago'
        AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    $stmt_check->execute([$suscripcion['empresa_id']]);
    
    if ($stmt_check->fetchColumn() == 0) {
        crearNotificacion($suscripcion['empresa_id'], 'recordatorio_pago',
            'Tu suscripción está por vencer',
            "Tu suscripción al plan {$suscripcion['plan_nombre']} vence el " . 
            date('d/m/Y', strtotime($suscripcion['fecha_fin'])) . 
            ". Por favor, renueva tu suscripción para evitar interrupciones en el servicio."
        );
    }
}

// 3. Verificar suscripciones vencidas
$stmt = $pdo->prepare("
    SELECT s.*, e.id as empresa_id
    FROM suscripciones s
    JOIN empresas e ON s.empresa_id = e.id
    WHERE s.estado = 'activa' 
    AND s.fecha_fin < NOW()
");
$stmt->execute();
$vencidas = $stmt->fetchAll();

foreach ($vencidas as $suscripcion) {
    echo "Suscripción vencida para empresa ID {$suscripcion['empresa_id']}\n";
    
    $pdo->beginTransaction();
    try {
        // Marcar suscripción como vencida
        $stmt_update = $pdo->prepare("
            UPDATE suscripciones 
            SET estado = 'vencida' 
            WHERE id = ?
        ");
        $stmt_update->execute([$suscripcion['id']]);
        
        // Cambiar a plan trial
        $stmt_plan = $pdo->prepare("
            UPDATE empresas 
            SET plan_id = 1 
            WHERE id = ?
        ");
        $stmt_plan->execute([$suscripcion['empresa_id']]);
        
        // Dar 3 días de gracia antes de suspender
        crearNotificacion($suscripcion['empresa_id'], 'suscripcion_vencida',
            'Tu suscripción ha vencido',
            'Tu suscripción ha vencido. Tienes 3 días para renovarla antes de que tu cuenta sea suspendida.'
        );
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error procesando suscripción vencida: " . $e->getMessage());
    }
}

// 4. Suspender empresas con suscripción vencida hace más de 3 días
$stmt = $pdo->prepare("
    SELECT e.* FROM empresas e
    JOIN suscripciones s ON e.id = s.empresa_id
    WHERE s.estado = 'vencida'
    AND s.fecha_fin < DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND e.activo = 1
");
$stmt->execute();
$para_suspender = $stmt->fetchAll();

foreach ($para_suspender as $empresa) {
    echo "Suspendiendo empresa ID {$empresa['id']} por falta de pago\n";
    
    $stmt_suspend = $pdo->prepare("UPDATE empresas SET activo = 0 WHERE id = ?");
    $stmt_suspend->execute([$empresa['id']]);
    
    // Desconectar WhatsApp
    $stmt_wa = $pdo->prepare("
        UPDATE whatsapp_sesiones_empresa 
        SET estado = 'desconectado' 
        WHERE empresa_id = ?
    ");
    $stmt_wa->execute([$empresa['id']]);
}

echo "[" . date('Y-m-d H:i:s') . "] Verificación completada.\n";

/**
 * Crear notificación
 */
function crearNotificacion($empresa_id, $tipo, $asunto, $mensaje) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO notificaciones_pago (empresa_id, tipo, asunto, mensaje)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$empresa_id, $tipo, $asunto, $mensaje]);
    
    // Aquí también enviarías el email
    // enviarEmail($empresa['email'], $asunto, $mensaje);
}
?>