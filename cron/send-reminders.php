<?php
// cron/send-reminders.php
// Ejecutar diariamente a las 9 AM para enviar recordatorios
// Crontab: 0 9 * * * php /ruta/mensajeropro/cron/send-reminders.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email-sender.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando envío de recordatorios...\n";

$emails_enviados = 0;

try {
    // =========================================
    // 1. RECORDATORIO: Trial expira en 1 día
    // =========================================
    echo "\n--- Verificando trials por expirar ---\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            e.id, 
            e.nombre_empresa, 
            e.email,
            e.fecha_expiracion_trial,
            DATEDIFF(e.fecha_expiracion_trial, NOW()) as dias_restantes
        FROM empresas e
        WHERE e.plan_id = 1
        AND e.activo = 1
        AND DATE(e.fecha_expiracion_trial) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM notificaciones_pago np
            WHERE np.empresa_id = e.id
            AND np.tipo = 'recordatorio_trial'
            AND DATE(np.created_at) = CURDATE()
        )
    ");
    $stmt->execute();
    $trials_expirando = $stmt->fetchAll();
    
    foreach ($trials_expirando as $empresa) {
        echo "Trial expira mañana: {$empresa['nombre_empresa']} ({$empresa['email']})\n";
        
        // Obtener plantilla de email
        $plantilla = obtenerPlantillaEmail('trial_expirando');
        
        if ($plantilla) {
            $variables = [
                'nombre_empresa' => $empresa['nombre_empresa'],
                'dias_restantes' => 1,
                'fecha_expiracion' => date('d/m/Y', strtotime($empresa['fecha_expiracion_trial'])),
                'url_planes' => APP_URL . '/cliente/mi-plan'
            ];
            
            $asunto = reemplazarVariables($plantilla['asunto'], $variables);
            $contenido = reemplazarVariables($plantilla['contenido_html'], $variables);
            
            if (enviarEmail($empresa['email'], $asunto, $contenido)) {
                // Registrar notificación
                $stmt_not = $pdo->prepare("
                    INSERT INTO notificaciones_pago 
                    (empresa_id, tipo, asunto, mensaje, enviado, fecha_envio)
                    VALUES (?, 'recordatorio_trial', ?, ?, 1, NOW())
                ");
                $stmt_not->execute([
                    $empresa['id'],
                    $asunto,
                    'Trial expira mañana'
                ]);
                
                $emails_enviados++;
                echo "  ✓ Email enviado\n";
            } else {
                echo "  ✗ Error enviando email\n";
            }
        }
    }
    
    // =========================================
    // 2. RECORDATORIO: Suscripción vence en 3 días
    // =========================================
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
        
        $plantilla = obtenerPlantillaEmail('recordatorio_pago');
        
        if ($plantilla) {
            $variables = [
                'nombre_empresa' => $suscripcion['nombre_empresa'],
                'plan_nombre' => $suscripcion['plan_nombre'],
                'fecha_vencimiento' => date('d/m/Y', strtotime($suscripcion['fecha_fin'])),
                'monto' => number_format($suscripcion['precio_mensual'], 2),
                'url_pago' => APP_URL . '/cliente/mi-plan'
            ];
            
            $asunto = reemplazarVariables($plantilla['asunto'], $variables);
            $contenido = reemplazarVariables($plantilla['contenido_html'], $variables);
            
            if (enviarEmail($suscripcion['email'], $asunto, $contenido)) {
                $stmt_not = $pdo->prepare("
                    INSERT INTO notificaciones_pago 
                    (empresa_id, tipo, asunto, mensaje, enviado, fecha_envio)
                    VALUES (?, 'recordatorio_pago', ?, ?, 1, NOW())
                ");
                $stmt_not->execute([
                    $suscripcion['empresa_id'],
                    $asunto,
                    'Suscripción vence en 3 días'
                ]);
                
                $emails_enviados++;
                echo "  ✓ Email enviado\n";
            } else {
                echo "  ✗ Error enviando email\n";
            }
        }
    }
    
    // =========================================
    // 3. ENVIAR NOTIFICACIONES PENDIENTES
    // =========================================
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

// =========================================
// FUNCIONES HELPER
// =========================================

/**
 * Obtener plantilla de email por código
 */
function obtenerPlantillaEmail($codigo) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM plantillas_email 
        WHERE codigo = ? AND activa = 1
    ");
    $stmt->execute([$codigo]);
    
    return $stmt->fetch();
}

/**
 * Reemplazar variables en texto
 */
function reemplazarVariables($texto, $variables) {
    foreach ($variables as $key => $value) {
        $texto = str_replace('{{' . $key . '}}', $value, $texto);
    }
    return $texto;
}

/**
 * Enviar email usando plantilla
 */
function enviarEmail($destinatario, $asunto, $contenido_html) {
    // Aquí integras con tu sistema de emails (PHPMailer, etc)
    // Por ahora solo simulamos
    
    // TODO: Implementar envío real con PHPMailer
    // require_once __DIR__ . '/../includes/email-sender.php';
    // return EmailSender::enviar($destinatario, $asunto, $contenido_html);
    
    // Simulación temporal
    error_log("EMAIL: To=$destinatario | Subject=$asunto");
    return true;
}

/**
 * Enviar email simple (sin plantilla)
 */
function enviarEmailSimple($destinatario, $asunto, $mensaje) {
    $html = "<html><body style='font-family: Arial;'><p>$mensaje</p></body></html>";
    return enviarEmail($destinatario, $asunto, $html);
}