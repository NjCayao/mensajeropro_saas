<?php
// cron/check-payments.php
// Ejecutar cada hora para verificar pagos y vencimientos

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificación de pagos...\n";

// 1. Verificar trials vencidos
$stmt = $pdo->prepare("
    SELECT e.*, s.fecha_fin as fecha_expiracion_trial
    FROM empresas e
    INNER JOIN suscripciones s ON e.id = s.empresa_id
    WHERE s.tipo = 'trial'
    AND s.estado = 'activa'
    AND s.fecha_fin < NOW()
    AND e.activo = 1
");
$stmt->execute();
$trials_vencidos = $stmt->fetchAll();

foreach ($trials_vencidos as $empresa) {
    echo "Trial vencido para empresa ID {$empresa['id']} - {$empresa['nombre_empresa']}\n";
    
    // Verificar si tiene otra suscripción activa (pago)
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM suscripciones 
        WHERE empresa_id = ? 
        AND estado = 'activa'
        AND tipo != 'trial'
    ");
    $stmt_check->execute([$empresa['id']]);
    
    if ($stmt_check->fetchColumn() == 0) {
        // No tiene suscripción de pago, suspender
        $stmt_suspend = $pdo->prepare("UPDATE empresas SET activo = 0 WHERE id = ?");
        $stmt_suspend->execute([$empresa['id']]);
        
        // Marcar trial como vencido
        $stmt_trial = $pdo->prepare("
            UPDATE suscripciones 
            SET estado = 'vencida' 
            WHERE empresa_id = ? AND tipo = 'trial'
        ");
        $stmt_trial->execute([$empresa['id']]);
        
        // Crear notificación
        $stmt_not = $pdo->prepare("
            INSERT INTO notificaciones_pago (empresa_id, tipo, asunto, mensaje)
            VALUES (?, 'trial_vencido', 'Tu periodo de prueba ha terminado', ?)
        ");
        $stmt_not->execute([
            $empresa['id'],
            'Tu periodo de prueba ha finalizado. Por favor, suscríbete a un plan para continuar usando el servicio.'
        ]);
        
        echo "  → Empresa suspendida\n";
    }
}

// 2. Verificar suscripciones por vencer (3 días antes)
$stmt = $pdo->prepare("
    SELECT s.*, e.nombre_empresa, e.email, p.nombre as plan_nombre
    FROM suscripciones s
    JOIN empresas e ON s.empresa_id = e.id
    JOIN planes p ON s.plan_id = p.id
    WHERE s.estado = 'activa' 
    AND s.tipo != 'trial'
    AND s.fecha_fin BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
    AND s.auto_renovar = 0
");
$stmt->execute();
$por_vencer = $stmt->fetchAll();

foreach ($por_vencer as $suscripcion) {
    echo "Suscripción por vencer para empresa ID {$suscripcion['empresa_id']}\n";
    
    // Verificar si ya se envió notificación reciente
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM notificaciones_pago 
        WHERE empresa_id = ? 
        AND tipo = 'recordatorio_pago'
        AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    $stmt_check->execute([$suscripcion['empresa_id']]);
    
    if ($stmt_check->fetchColumn() == 0) {
        $stmt_not = $pdo->prepare("
            INSERT INTO notificaciones_pago (empresa_id, tipo, asunto, mensaje)
            VALUES (?, 'recordatorio_pago', 'Tu suscripción está por vencer', ?)
        ");
        $stmt_not->execute([
            $suscripcion['empresa_id'],
            "Tu suscripción al plan {$suscripcion['plan_nombre']} vence el " . 
            date('d/m/Y', strtotime($suscripcion['fecha_fin'])) . 
            ". Por favor, renueva tu suscripción para evitar interrupciones."
        ]);
        
        echo "  → Notificación creada\n";
    }
}

// 3. Verificar suscripciones vencidas
$stmt = $pdo->prepare("
    SELECT s.*, e.id as empresa_id, e.nombre_empresa
    FROM suscripciones s
    JOIN empresas e ON s.empresa_id = e.id
    WHERE s.estado = 'activa' 
    AND s.tipo != 'trial'
    AND s.fecha_fin < NOW()
");
$stmt->execute();
$vencidas = $stmt->fetchAll();

foreach ($vencidas as $suscripcion) {
    echo "Suscripción vencida para empresa ID {$suscripcion['empresa_id']}\n";
    
    $pdo->beginTransaction();
    try {
        // Marcar como vencida
        $stmt_update = $pdo->prepare("UPDATE suscripciones SET estado = 'vencida' WHERE id = ?");
        $stmt_update->execute([$suscripcion['id']]);
        
        // Crear notificación
        $stmt_not = $pdo->prepare("
            INSERT INTO notificaciones_pago (empresa_id, tipo, asunto, mensaje)
            VALUES (?, 'suscripcion_vencida', 'Tu suscripción ha vencido', ?)
        ");
        $stmt_not->execute([
            $suscripcion['empresa_id'],
            'Tu suscripción ha vencido. Tienes 3 días para renovarla antes de que tu cuenta sea suspendida.'
        ]);
        
        $pdo->commit();
        echo "  → Marcada como vencida\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error procesando suscripción vencida: " . $e->getMessage());
    }
}

// 4. Suspender empresas con suscripción vencida hace +3 días
$stmt = $pdo->prepare("
    SELECT e.* FROM empresas e
    INNER JOIN suscripciones s ON e.id = s.empresa_id
    WHERE s.estado = 'vencida'
    AND s.tipo != 'trial'
    AND s.fecha_fin < DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND e.activo = 1
    AND e.es_superadmin = 0
");
$stmt->execute();
$para_suspender = $stmt->fetchAll();

foreach ($para_suspender as $empresa) {
    echo "Suspendiendo empresa ID {$empresa['id']} - {$empresa['nombre_empresa']}\n";
    
    $stmt_suspend = $pdo->prepare("UPDATE empresas SET activo = 0 WHERE id = ?");
    $stmt_suspend->execute([$empresa['id']]);
    
    // Desconectar WhatsApp
    $stmt_wa = $pdo->prepare("
        UPDATE whatsapp_sesiones_empresa 
        SET estado = 'desconectado' 
        WHERE empresa_id = ?
    ");
    $stmt_wa->execute([$empresa['id']]);
    
    echo "  → Empresa suspendida\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Verificación completada.\n";