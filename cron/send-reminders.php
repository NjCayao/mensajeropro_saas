<?php
// cron/send-reminders.php
// Ejecutar diariamente a las 9 AM para enviar recordatorios
// Crontab: 0 9 * * * php /ruta/mensajeropro/cron/send-reminders.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando envío de recordatorios...\n";

$emails_enviados = 0;

try {
    // 1. RECORDATORIO: Trial expira en 1 día
    echo "\n--- Verificando trials por expirar ---\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            e.id, 
            e.nombre_empresa, 
            e.email,
            s.fecha_fin as fecha_expiracion_trial,
            DATEDIFF(s.fecha_fin, NOW()) as dias_restantes
        FROM empresas e
        INNER JOIN suscripciones s ON e.id = s.empresa_id
        WHERE s.tipo = 'trial'
        AND s.estado = 'activa'
        AND DATE(s.fecha_fin) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND e.activo = 1
        AND NOT EXISTS (
            SELECT 1 FROM notificaciones_pago np
            WHERE np.empresa_id = e.id
            AND np.tipo = 'recordatorio_pago'
            AND DATE(np.created_at) = CURDATE()
        )
    ");
    $stmt->execute();
    $trials_expirando = $stmt->fetchAll();
    
    foreach ($trials_expirando as $empresa) {
        echo "Trial expira mañana: {$empresa['nombre_empresa']} ({$empresa['email']})\n";
        
        $variables = [
            'nombre_empresa' => $empresa['nombre_empresa'],
            'dias_restantes' => 1,
            'fecha_expiracion' => date('d/m/Y', strtotime($empresa['fecha_expiracion_trial'])),
            'url_planes' => APP_URL . '/cliente/mi-plan'
        ];
        
        if (enviarEmailPlantilla('recordatorio_pago', $empresa['email'], $variables)) {
            $stmt_not = $pdo->prepare("
                INSERT INTO notificaciones_pago 
                (empresa_id, tipo, asunto, mensaje, enviado, fecha_envio)
                VALUES (?, 'recordatorio_pago', ?, ?, 1, NOW())
            ");
            $stmt_not->execute([
                $empresa['id'],
                'Tu periodo de prueba expira mañana',
                'Trial expira en 1 día'
            ]);
            
            $emails_enviados++;
            echo "  ✓ Email enviado\n";
        } else {
            echo "  ✗ Error enviando email\n";
        }
    }
    
    // 2. RECORDATORIO: Suscripción vence en 3 días
    echo "\n--- Verificando suscripciones por vencer ---\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            s.*, 
            e.nombre_empresa, 
            e.email,
            p.nombre as plan_nombre,
            p.precio_mensual,
            DATEDIFF(s.fecha_fin, NOW()) as dias_restantes
        FROM suscripciones s
        JOIN empresas e ON s.empresa_id = e.id
        JOIN planes p ON s.plan_id = p.id
        WHERE s.estado = 'activa'
        AND s.tipo != 'trial'
        AND s.auto_renovar = 0
        AND DATE(s.fecha_fin) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM notificaciones_pago np
            WHERE np.empresa_id = e.id
            AND np.tipo = 'recordatorio_pago'
            AND DATE(np.created_at) = CURDATE()
        )
    ");
    $stmt->execute();
    $suscripciones_por_vencer = $stmt->fetchAll();
    
    foreach ($suscripciones_por_vencer as $suscripcion) {
        echo "Suscripción vence en 3 días: {$suscripcion['nombre_empresa']} ({$suscripcion['email']})\n";
        
        $variables = [
            'nombre_empresa' => $suscripcion['nombre_empresa'],
            'plan_nombre' => $suscripcion['plan_nombre'],
            'fecha_vencimiento' => date('d/m/Y', strtotime($suscripcion['fecha_fin'])),
            'monto' => number_format($suscripcion['precio_mensual'], 2),
            'url_pago' => APP_URL . '/cliente/mi-plan'
        ];
        
        if (enviarEmailPlantilla('recordatorio_pago', $suscripcion['email'], $variables)) {
            $stmt_not = $pdo->prepare("
                INSERT INTO notificaciones_pago 
                (empresa_id, tipo, asunto, mensaje, enviado, fecha_envio)
                VALUES (?, 'recordatorio_pago', ?, ?, 1, NOW())
            ");
            $stmt_not->execute([
                $suscripcion['empresa_id'],
                'Tu suscripción vence en 3 días',
                'Renovar suscripción'
            ]);
            
            $emails_enviados++;
            echo "  ✓ Email enviado\n";
        } else {
            echo "  ✗ Error enviando email\n";
        }
    }
    
    // 3. ENVIAR NOTIFICACIONES PENDIENTES
    echo "\n--- Enviando notificaciones pendientes ---\n";
    
    $stmt = $pdo->prepare("
        SELECT np.*, e.email, e.nombre_empresa
        FROM notificaciones_pago np
        JOIN empresas e ON np.empresa_id = e.id
        WHERE np.enviado = 0
        AND e.activo = 1
        ORDER BY np.created_at ASC
        LIMIT 50
    ");
    $stmt->execute();
    $pendientes = $stmt->fetchAll();
    
    foreach ($pendientes as $notificacion) {
        echo "Notificación pendiente: {$notificacion['nombre_empresa']} - {$notificacion['tipo']}\n";
        
        if (enviarEmailSimple($notificacion['email'], $notificacion['asunto'], $notificacion['mensaje'])) {
            $stmt_update = $pdo->prepare("
                UPDATE notificaciones_pago 
                SET enviado = 1, fecha_envio = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$notificacion['id']]);
            
            $emails_enviados++;
            echo "  ✓ Email enviado\n";
        } else {
            echo "  ✗ Error enviando email\n";
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Proceso completado.\n";
    echo "Total emails enviados: $emails_enviados\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Error en send-reminders: " . $e->getMessage());
}