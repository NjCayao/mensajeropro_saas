<?php
// includes/email-templates.php
// Plantillas de email para el sistema de pagos

require_once __DIR__ . '/../config/app.php';

/**
 * Obtener plantilla de email
 */
function getEmailTemplate($tipo, $datos = []) {
    $templates = [
        'suscripcion_activa' => [
            'asunto' => 'Tu suscripción a ' . APP_NAME . ' está activa',
            'html' => '
                <h2>¡Bienvenido al plan {plan_nombre}!</h2>
                <p>Hola {nombre_empresa},</p>
                <p>Tu suscripción ha sido activada exitosamente. Aquí están los detalles:</p>
                <ul>
                    <li><strong>Plan:</strong> {plan_nombre}</li>
                    <li><strong>Precio:</strong> S/ {monto}</li>
                    <li><strong>Próximo pago:</strong> {fecha_proximo_pago}</li>
                    <li><strong>Método de pago:</strong> {metodo_pago}</li>
                </ul>
                <p>Ahora puedes disfrutar de todas las funcionalidades de tu plan.</p>
                <p><a href="{app_url}/cliente/dashboard" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ir al Dashboard</a></p>
            '
        ],
        
        'pago_exitoso' => [
            'asunto' => 'Pago recibido - ' . APP_NAME,
            'html' => '
                <h2>Pago procesado exitosamente</h2>
                <p>Hola {nombre_empresa},</p>
                <p>Hemos recibido tu pago de <strong>S/ {monto}</strong> correspondiente a tu suscripción.</p>
                <p><strong>Referencia:</strong> {referencia}</p>
                <p><strong>Fecha:</strong> {fecha_pago}</p>
                <p>Tu suscripción continuará activa hasta el {fecha_proximo_pago}.</p>
                <p>Gracias por confiar en nosotros.</p>
            '
        ],
        
        'pago_fallido' => [
            'asunto' => 'Problema con tu pago - Acción requerida',
            'html' => '
                <h2>No pudimos procesar tu pago</h2>
                <p>Hola {nombre_empresa},</p>
                <p>Intentamos procesar tu pago recurrente pero no fue exitoso.</p>
                <p><strong>Plan:</strong> {plan_nombre}</p>
                <p><strong>Monto:</strong> S/ {monto}</p>
                <p>Por favor, actualiza tu método de pago para evitar la suspensión del servicio.</p>
                <p><a href="{app_url}/cliente/mi-plan" style="background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Actualizar Método de Pago</a></p>
                <p>Si ya realizaste el pago, ignora este mensaje.</p>
            '
        ],
        
        'trial_por_vencer' => [
            'asunto' => 'Tu periodo de prueba está por terminar',
            'html' => '
                <h2>Quedan {dias_restantes} días de tu prueba gratuita</h2>
                <p>Hola {nombre_empresa},</p>
                <p>Tu periodo de prueba de ' . TRIAL_DAYS . ' días está por terminar.</p>
                <p>Para continuar usando ' . APP_NAME . ' sin interrupciones, suscríbete a uno de nuestros planes:</p>
                <ul>
                    <li><strong>Básico:</strong> S/ 7.00 al mes</li>
                    <li><strong>Profesional:</strong> S/ 18.00 al mes</li>
                </ul>
                <p><a href="{app_url}/cliente/mi-plan" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Planes</a></p>
                <p>No pierdas el acceso a tus contactos y configuraciones.</p>
            '
        ],
        
        'suscripcion_cancelada' => [
            'asunto' => 'Tu suscripción ha sido cancelada',
            'html' => '
                <h2>Confirmación de cancelación</h2>
                <p>Hola {nombre_empresa},</p>
                <p>Tu suscripción ha sido cancelada según lo solicitado.</p>
                <p><strong>Importante:</strong> Seguirás teniendo acceso hasta el {fecha_fin_acceso}.</p>
                <p>Después de esa fecha:</p>
                <ul>
                    <li>Tu cuenta pasará al plan gratuito limitado</li>
                    <li>Tus datos se mantendrán por 30 días</li>
                    <li>Puedes reactivar tu suscripción en cualquier momento</li>
                </ul>
                <p>Lamentamos verte partir. Si hay algo que podamos mejorar, no dudes en contactarnos.</p>
                <p><a href="{app_url}/cliente/mi-plan" style="background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Reactivar Suscripción</a></p>
            '
        ],
        
        'recordatorio_pago' => [
            'asunto' => 'Recordatorio: Tu suscripción vence pronto',
            'html' => '
                <h2>Tu suscripción vence en {dias_para_vencer} días</h2>
                <p>Hola {nombre_empresa},</p>
                <p>Te recordamos que tu suscripción al plan <strong>{plan_nombre}</strong> vence el {fecha_vencimiento}.</p>
                <p>Para evitar interrupciones en el servicio, asegúrate de tener fondos suficientes en tu método de pago registrado.</p>
                <p>Si no deseas renovar, puedes cancelar la renovación automática desde tu panel.</p>
                <p><a href="{app_url}/cliente/mi-plan" style="background-color: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Gestionar Suscripción</a></p>
            '
        ]
    ];
    
    if (!isset($templates[$tipo])) {
        return false;
    }
    
    $template = $templates[$tipo];
    $html = $template['html'];
    
    // Reemplazar variables
    foreach ($datos as $key => $value) {
        $html = str_replace('{' . $key . '}', htmlspecialchars($value), $html);
    }
    
    // Agregar wrapper HTML
    $html_completo = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: white; }
            .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #666; }
            h2 { color: #333; }
            a { color: #007bff; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
            </div>
            <div class="content">
                ' . $html . '
            </div>
            <div class="footer">
                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                <p>' . APP_NAME . ' - ' . date('Y') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return [
        'asunto' => $template['asunto'],
        'html' => $html_completo
    ];
}

/**
 * Enviar email usando la plantilla
 */
function enviarEmailPlantilla($email, $tipo, $datos) {
    $template = getEmailTemplate($tipo, $datos);
    
    if (!$template) {
        return false;
    }
    
    return enviarEmail($email, $template['asunto'], $template['html']);
}

/**
 * Función genérica para enviar emails
 */
function enviarEmail($para, $asunto, $html) {
    // Aquí usarías PHPMailer o tu librería preferida
    // Por ahora solo un ejemplo básico
    
    $headers = "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($para, $asunto, $html, $headers);
}
?>