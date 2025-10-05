<?php
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad - <?php echo APP_NAME; ?></title>
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
                
                <h1>Política de Privacidad</h1>
                <p class="last-update">Última actualización: <?php echo date('d/m/Y'); ?></p>

                <h2>1. Introducción</h2>
                <p><?php echo APP_NAME; ?> respeta su privacidad y se compromete a proteger sus datos personales. Esta política explica cómo recopilamos, usamos y protegemos su información.</p>

                <h2>2. Información que Recopilamos</h2>
                <p><strong>2.1 Información de Registro:</strong></p>
                <ul>
                    <li>Nombre de empresa</li>
                    <li>Dirección de email</li>
                    <li>Número de teléfono</li>
                    <li>Información de facturación</li>
                </ul>

                <p><strong>2.2 Información de Uso:</strong></p>
                <ul>
                    <li>Mensajes enviados y recibidos</li>
                    <li>Contactos importados</li>
                    <li>Logs de actividad</li>
                    <li>Dirección IP</li>
                    <li>Tipo de navegador y dispositivo</li>
                </ul>

                <p><strong>2.3 Información de WhatsApp:</strong></p>
                <ul>
                    <li>Número de WhatsApp conectado</li>
                    <li>Sesión de WhatsApp Web</li>
                    <li>Mensajes procesados por el bot</li>
                </ul>

                <h2>3. Cómo Usamos su Información</h2>
                <p>Utilizamos sus datos para:</p>
                <ul>
                    <li>Proveer y mejorar nuestros servicios</li>
                    <li>Procesar pagos y facturación</li>
                    <li>Enviar notificaciones importantes del servicio</li>
                    <li>Brindar soporte técnico</li>
                    <li>Prevenir fraudes y abusos</li>
                    <li>Cumplir con obligaciones legales</li>
                </ul>

                <h2>4. Compartir Información</h2>
                <p>NO vendemos sus datos a terceros. Compartimos información solo con:</p>
                <ul>
                    <li><strong>Procesadores de pago:</strong> MercadoPago, PayPal (para procesar transacciones)</li>
                    <li><strong>Servicios en la nube:</strong> Servidores de alojamiento</li>
                    <li><strong>Servicios de IA:</strong> OpenAI (para procesamiento de mensajes del bot)</li>
                    <li><strong>Autoridades:</strong> Cuando sea requerido por ley</li>
                </ul>

                <h2>5. Google OAuth</h2>
                <p>Si se registra con Google, recopilamos su email y nombre de perfil de Google. No accedemos a otros datos de su cuenta Google.</p>

                <h2>6. Cookies y Tecnologías Similares</h2>
                <p>Usamos cookies para:</p>
                <ul>
                    <li>Mantener su sesión activa</li>
                    <li>Recordar preferencias</li>
                    <li>Analizar el uso del servicio</li>
                </ul>
                <p>Puede desactivar las cookies en su navegador, pero esto puede afectar la funcionalidad del servicio.</p>

                <h2>7. Seguridad de los Datos</h2>
                <p>Implementamos medidas de seguridad para proteger sus datos:</p>
                <ul>
                    <li>Cifrado SSL/TLS en todas las transmisiones</li>
                    <li>Contraseñas hasheadas con algoritmos seguros</li>
                    <li>Acceso restringido a datos personales</li>
                    <li>Monitoreo de actividades sospechosas</li>
                    <li>Backups regulares</li>
                </ul>

                <h2>8. Retención de Datos</h2>
                <p>Conservamos sus datos mientras su cuenta esté activa. Después de la cancelación:</p>
                <ul>
                    <li>Datos de facturación: 5 años (obligación legal)</li>
                    <li>Mensajes y contactos: 30 días</li>
                    <li>Logs del sistema: 90 días</li>
                </ul>

                <h2>9. Sus Derechos</h2>
                <p>Usted tiene derecho a:</p>
                <ul>
                    <li><strong>Acceso:</strong> Solicitar copia de sus datos</li>
                    <li><strong>Rectificación:</strong> Corregir datos inexactos</li>
                    <li><strong>Eliminación:</strong> Solicitar borrado de sus datos</li>
                    <li><strong>Portabilidad:</strong> Recibir sus datos en formato estructurado</li>
                    <li><strong>Oposición:</strong> Objetar ciertos procesamientos</li>
                </ul>
                <p>Para ejercer estos derechos, contáctenos en: privacidad@<?php echo strtolower(str_replace(' ', '', APP_NAME)); ?>.com</p>

                <h2>10. Transferencias Internacionales</h2>
                <p>Sus datos pueden ser transferidos a servidores fuera de su país. Nos aseguramos de que estas transferencias cumplan con las leyes aplicables de protección de datos.</p>

                <h2>11. Menores de Edad</h2>
                <p>Nuestro servicio no está dirigido a menores de 18 años. No recopilamos intencionalmente datos de menores.</p>

                <h2>12. Cambios en esta Política</h2>
                <p>Podemos actualizar esta política ocasionalmente. Notificaremos cambios significativos por email y en el panel de control.</p>

                <h2>13. Cumplimiento Legal</h2>
                <p>Cumplimos con:</p>
                <ul>
                    <li>Ley de Protección de Datos Personales de Perú (Ley N° 29733)</li>
                    <li>GDPR (si tiene usuarios en la UE)</li>
                    <li>Otras leyes aplicables de protección de datos</li>
                </ul>

                <h2>14. Contacto</h2>
                <p>Para preguntas sobre privacidad:</p>
                <ul>
                    <li>Email: nilson.jhonny@gmail.com</li>
                    <!-- <li>Email: soporte@<?php echo strtolower(str_replace(' ', '', APP_NAME)); ?>.com</li> -->
                    <li>WhatsApp: +51982226835</li>
                </ul>

                <hr class="my-4">
                <p class="text-muted small">Al usar <?php echo APP_NAME; ?>, usted acepta esta Política de Privacidad.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>