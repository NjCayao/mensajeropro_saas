<?php
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .legal-page {
            padding: 100px 0 50px;
            background: #f8f9fa;
        }
        .legal-content {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .legal-content h1 {
            color: #25D366;
            margin-bottom: 1rem;
        }
        .legal-content h2 {
            color: #128C7E;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        .legal-content p, .legal-content li {
            color: #666;
            line-height: 1.8;
        }
        .last-update {
            color: #999;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="legal-page">
        <div class="container">
            <div class="legal-content">
                <a href="<?php echo url(); ?>" class="btn btn-outline-success mb-4">
                    <i class="fas fa-arrow-left"></i> Volver al inicio
                </a>
                
                <h1>Términos y Condiciones de Uso</h1>
                <p class="last-update">Última actualización: <?php echo date('d/m/Y'); ?></p>

                <h2>1. Aceptación de los Términos</h2>
                <p>Al registrarse y utilizar <?php echo APP_NAME; ?>, usted acepta estar sujeto a estos Términos y Condiciones. Si no está de acuerdo con alguna parte de estos términos, no debe utilizar nuestro servicio.</p>

                <h2>2. Descripción del Servicio</h2>
                <p><?php echo APP_NAME; ?> es una plataforma SaaS que proporciona automatización de mensajería a través de WhatsApp con funcionalidades de:</p>
                <ul>
                    <li>Bot de ventas con inteligencia artificial</li>
                    <li>Agendamiento automático de citas</li>
                    <li>Envío de mensajes masivos y programados</li>
                    <li>Gestión de catálogos de productos</li>
                    <li>Integración con Google Calendar</li>
                </ul>

                <h2>3. Registro de Cuenta</h2>
                <p>Para utilizar nuestro servicio, debe:</p>
                <ul>
                    <li>Proporcionar información verídica y actualizada</li>
                    <li>Mantener la seguridad de su contraseña</li>
                    <li>Ser mayor de 18 años o tener el consentimiento de un tutor legal</li>
                    <li>No compartir su cuenta con terceros</li>
                </ul>

                <h2>4. Planes y Pagos</h2>
                <p><strong>4.1 Periodo de Prueba:</strong> Ofrecemos un periodo de prueba gratuito de <?php echo TRIAL_DAYS; ?> días. No se requiere tarjeta de crédito para el trial.</p>
                <p><strong>4.2 Planes de Pago:</strong> Después del trial, debe suscribirse a uno de nuestros planes de pago para continuar usando el servicio.</p>
                <p><strong>4.3 Facturación:</strong> Los pagos se procesan mensual o anualmente según el plan elegido. La facturación es automática y recurrente.</p>
                <p><strong>4.4 Cancelación:</strong> Puede cancelar su suscripción en cualquier momento desde su panel de control. La cancelación será efectiva al final del periodo de facturación actual.</p>
                <p><strong>4.5 Reembolsos:</strong> No ofrecemos reembolsos por periodos no utilizados, excepto en casos excepcionales a nuestra discreción.</p>

                <h2>5. Uso Aceptable</h2>
                <p>Se prohíbe estrictamente utilizar <?php echo APP_NAME; ?> para:</p>
                <ul>
                    <li>Enviar spam o contenido no solicitado</li>
                    <li>Actividades ilegales o fraudulentas</li>
                    <li>Contenido ofensivo, amenazante o de acoso</li>
                    <li>Violación de derechos de propiedad intelectual</li>
                    <li>Distribución de malware o virus</li>
                    <li>Suplantar identidades</li>
                </ul>

                <h2>6. WhatsApp y Terceros</h2>
                <p><?php echo APP_NAME; ?> no es propiedad de WhatsApp ni está afiliado oficialmente a WhatsApp Inc. El uso del servicio está sujeto a los términos de WhatsApp. No nos hacemos responsables por cambios en las políticas de WhatsApp que puedan afectar el funcionamiento del servicio.</p>

                <h2>7. Limitaciones del Servicio</h2>
                <p>Cada plan tiene límites específicos de:</p>
                <ul>
                    <li>Número de contactos</li>
                    <li>Mensajes mensuales</li>
                    <li>Usuarios simultáneos</li>
                    <li>Tamaño de archivos</li>
                </ul>
                <p>Exceder estos límites puede resultar en la suspensión temporal del servicio hasta que se actualice a un plan superior.</p>

                <h2>8. Privacidad y Datos</h2>
                <p>Sus datos son tratados según nuestra <a href="<?php echo url('privacidad.php'); ?>">Política de Privacidad</a>. Usted es responsable de cumplir con las leyes de protección de datos aplicables en su jurisdicción.</p>

                <h2>9. Propiedad Intelectual</h2>
                <p>Todo el contenido de <?php echo APP_NAME; ?>, incluyendo diseño, código, logos y marcas, es propiedad exclusiva de <?php echo APP_NAME; ?> y está protegido por leyes de propiedad intelectual.</p>

                <h2>10. Disponibilidad del Servicio</h2>
                <p>Nos esforzamos por mantener el servicio disponible 24/7, pero no garantizamos disponibilidad ininterrumpida. Podemos realizar mantenimientos programados con previo aviso.</p>

                <h2>11. Modificaciones del Servicio</h2>
                <p>Nos reservamos el derecho de modificar, suspender o descontinuar cualquier aspecto del servicio en cualquier momento, con o sin previo aviso.</p>

                <h2>12. Suspensión y Terminación</h2>
                <p>Podemos suspender o terminar su cuenta si:</p>
                <ul>
                    <li>Viola estos términos</li>
                    <li>No realiza los pagos correspondientes</li>
                    <li>Utiliza el servicio de manera abusiva</li>
                    <li>Incurre en actividades fraudulentas</li>
                </ul>

                <h2>13. Limitación de Responsabilidad</h2>
                <p><?php echo APP_NAME; ?> se proporciona "tal cual" sin garantías de ningún tipo. No nos hacemos responsables por:</p>
                <ul>
                    <li>Pérdida de datos</li>
                    <li>Interrupciones del servicio</li>
                    <li>Pérdidas económicas derivadas del uso del servicio</li>
                    <li>Bloqueos de WhatsApp a números de usuarios</li>
                </ul>

                <h2>14. Indemnización</h2>
                <p>Usted acepta indemnizar y mantener indemne a <?php echo APP_NAME; ?> de cualquier reclamo derivado de su uso del servicio o violación de estos términos.</p>

                <h2>15. Ley Aplicable</h2>
                <p>Estos términos se rigen por las leyes de Perú. Cualquier disputa será resuelta en los tribunales de Lima, Perú.</p>

                <h2>16. Modificaciones de los Términos</h2>
                <p>Podemos actualizar estos términos en cualquier momento. Los cambios significativos serán notificados por email. El uso continuado del servicio después de las modificaciones constituye la aceptación de los nuevos términos.</p>

                <h2>17. Contacto</h2>
                <p>Para preguntas sobre estos términos, contáctenos en:</p>
                <ul>
                    <li>Email: nilson.jhonny@gmail.com</li>
                    <!-- <li>Email: soporte@<?php echo strtolower(str_replace(' ', '', APP_NAME)); ?>.com</li> -->
                    <li>WhatsApp: +51982226835</li>
                </ul>

                <hr class="my-4">
                <p class="text-muted small">Al usar <?php echo APP_NAME; ?>, usted reconoce que ha leído, entendido y acepta estos Términos y Condiciones.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>