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