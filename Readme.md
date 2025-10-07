# configuraciones 
- config/app.php
- config/email.php

-whatsapp-service/src/database.js


# para configurar 
- config/app
- web/robots.txt

# Ejecutar cada minuto para procesar mensajes programados
* * * * * /usr/bin/php /ruta/a/mensajeroprov2/cron/procesar_programados.php >> /var/log/mensajero_programados.log 2>&1

# Ejecutar cada 2 minutos para procesar la cola de envío
*/2 * * * * /usr/bin/php /ruta/a/mensajeroprov2/cron/procesar_cola.php >> /var/log/mensajero_cola.log 2>&1

# Editar crontab
crontab -e

# Agregar esta línea para ejecutar cada hora los recordatorios
0 * * * * cd /ruta/a/tu/proyecto/whatsapp-service && node src/cronRecordatorios.js >> logs/cron.log 2>&1

# configurar en whatsapp-service/src/config.js tambien en whatsapp-service/src/database.js
poner dominio ejemplo https://devcayao.com - no incluir carpetas

# Para que el sistema verifique automáticamente los pagos, configura el cron:
bash Verificar pagos cada hora (consola)
0 * * * * php /ruta/a/tu/proyecto/cron/check-payments.php >> /ruta/a/logs/cron-payments.log 2>&1

# # Verificar pagos cada hora
0 * * * * php /var/www/mensajeropro/cron/check-payments.php >> /var/www/mensajeropro/logs/cron.log 2>&1

# Enviar recordatorios diario 9 AM
0 9 * * * php /var/www/mensajeropro/cron/send-reminders.php >> /var/www/mensajeropro/logs/cron.log 2>&1

# Limpiar sesiones diario 3 AM
0 3 * * * php /var/www/mensajeropro/cron/clean-sessions.php >> /var/www/mensajeropro/logs/cron.log 2>&1

# Procesar cola cada 2 minutos
*/2 * * * * php /var/www/mensajeropro/cron/procesar_cola.php >> /var/www/mensajeropro/logs/cron.log 2>&1

# Procesar programados cada minuto
* * * * * php /var/www/mensajeropro/cron/procesar_programados.php >> /var/www/mensajeropro/logs/cron.log 2>&1

# cerrar-sesiones-vencidas en QR.php
0 * * * * /usr/bin/php /ruta/a/mensajeroprov2/cron/cerrar-sesiones-vencidas.php

# instalar dependencias en local primera vez
cd whatsapp-service
npm install 
npm install multer
npm install -g pm2
npm install -g pm2-windows-startup
npm install @wppconnect-team/wppconnect
npm install axios
npm install moment

. src/database.js en la funcion getDBConfig() poner manualmente por si falle la lectura de base de datos php

# NO USAR whatsapp-service / scripts (scripst de mantenimiento para fallas de puertos NO USAR EN OPERACIÓN NORMAL)
# ejecutar por unica vez en produccion solo linux
chmod +x whatsapp-service/start.sh
# ejecutar por unica vez si es windows o local
- por cmd
cd whatsapp-service
start.bat 3001 1

- Opción B - Usar PowerShell:

cd whatsapp-service
.\start.ps1 3001 1

- Si PowerShell te da error de permisos, ejecuta esto UNA VEZ:

powershellSet-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser


_____________________________

# en produccion probar que :

Flujo de compra directa (cuando alguien hace clic en "Comprar Plan" sin usar el trial)
Actualmente:

✅ Registro funciona
✅ Login funciona
✅ Google OAuth funciona
✅ Seguridad implementada (CSRF, rate limit, fuerza bruta)
✅ Control de suscripciones vencidas

- en la base de datos suscripciones  si esta vencido el plan que se cambie el plan a vencido 

- agregar un input cuando el humano habla el bot espera unos 10 segundos para poder responder mientras no haya hablado el humano. 