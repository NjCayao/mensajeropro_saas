# 📋 Changelog - WhatsApp Multi-Tenant
✅ Cambios Implementados
🔧 Infraestructura

Nginx Proxy Dinámico: Configurado /whatsapp-port-{puerto}/ para acceder a múltiples instancias vía HTTPS
Scripts Wrapper: Creados start-pm2.sh, stop-pm2.sh y clean-tokens.sh para manejar permisos con sudo
Sudoers: Configurado para permitir que www-data ejecute scripts PM2 sin contraseña

🛠️ WhatsApp Service

Host Binding: Cambiado de 127.0.0.1 a 0.0.0.0 en producción para aceptar conexiones externas
QR Timeout: Implementado límite de 60 segundos para escanear QR, después se detiene automáticamente
PM2 No-Autorestart: Desactivado reinicio automático para evitar bucles infinitos
Limpieza de Tokens: Forzada antes de cada inicio para garantizar generación de nuevo QR

🗄️ Base de Datos

Estados Corregidos: Timeout y errores ahora marcan como "desconectado" en lugar de "error" o "timeout_qr"

🎯 Control de Servicio

Iniciar: Limpia tokens → Inicia PM2 → Genera QR (60 seg máximo)
Detener: Detiene PM2 → Limpia tokens → Actualiza estado a desconectado
Frontend: Detiene verificación automática cuando QR expira

📂 Archivos Creados/Modificados
Nuevos archivos:

/var/www/mensajeropro/whatsapp-service/start-pm2.sh
/var/www/mensajeropro/whatsapp-service/stop-pm2.sh
/var/www/mensajeropro/whatsapp-service/clean-tokens.sh

Modificados:

/etc/nginx/sites-available/default (proxy dinámico)
/etc/sudoers (permisos www-data)
/var/www/mensajeropro/sistema/api/v1/whatsapp/control-servicio.php
/var/www/mensajeropro/whatsapp-service/src/index.js
/var/www/mensajeropro/whatsapp-service/src/whatsapp-wppconnect.js
/var/www/mensajeropro/sistema/cliente/modulos/whatsapp.php

# 📋 Changelog - Correcciones PayPal y Sistema
✅ Correcciones Implementadas
🔧 Pagos PayPal

Duplicación eliminada: Agregado índice único en pagos.referencia_externa
Detección anual/mensual: Ahora detecta automáticamente si la suscripción es mensual o anual comparando monto pagado vs precios del plan
Emails funcionando: Corregido envío de emails de bienvenida y renovación
Sincronización de tablas: empresas.plan_id y suscripciones.plan_id siempre sincronizados

🕐 Timezone

MySQL en UTC: Configurado timezone global a +00:00 para evitar conflictos
Consistencia: Eliminado timezone hardcodeado en código PHP

🗄️ Base de Datos

Índice único en suscripciones: (empresa_id, estado) - Solo 1 suscripción activa por empresa
Índice único en pagos: referencia_externa - Previene duplicados de webhooks

🔄 Sincronización de Planes

SuperAdmin cambio de plan: Ahora actualiza tanto empresas como suscripciones
Cliente cambio de plan: Ya funcionaba, mantiene sincronización

📧 Sistema de Emails

Wrapper creado: includes/email.php para compatibilidad con webhooks
Logs detallados: Agregados logs para debugging de envío


📂 Archivos Modificados

/var/www/mensajeropro/config/database.php (timezone eliminado)
/var/www/mensajeropro/sistema/api/v1/webhooks/paypal.php (detección anual/mensual)
/var/www/mensajeropro/sistema/api/v1/superadmin/cambiar-plan.php (sincronización)
/var/www/mensajeropro/includes/email.php (creado)
/etc/mysql/mysql.conf.d/mysqld.cnf (timezone UTC)

# 📝 CHANGELOG - Integración Yape/MercadoPago
Archivos modificados (3):

sistema/api/v1/cliente/pagos/crear-suscripcion.php

Cambiado de preapproval (suscripciones) a preference (pagos únicos)
Agregada conversión automática USD → PEN
Header X-meli-site-id: MPE agregado


sistema/api/v1/webhooks/mercadopago.php

Agregado case payment en switch
Nueva función procesarPagoUnico() para pagos Yape
Procesa pagos mensuales (30 días) y anuales (365 días)


sistema/cliente/modulos/mi-plan.php

Botón cambiado de "MercadoPago" a "Yape / Plin"
Icono y color actualizados



Configuración MercadoPago:

Webhook configurado: https://mensajeropro.com/api/v1/webhooks/mercadopago
Evento activo: payment
Credenciales: Producción (Perú)