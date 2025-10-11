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

# # Agregar esta línea (ejecuta diario a las 3 AM)
0 3 * * * php /ruta/completa/sistema/cron/ml-cleanup-auto.php >> /ruta/logs/cleanup.log 2>&1

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

# cerrar-sesiones-vencidas en QR.php cada hora
0 * * * * /usr/bin/php /var/www/html/mensajeroprov2/cron/cerrar-sesiones-vencidas.php >> /var/www/html/mensajeroprov2/logs/vencimientos.log 2>&1

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

___________________________

# INSTALACION ML EN WINDOWS

# 1. Abrir PowerShell como Administrador
# 2. Verificar Python
python --version

# 3. Ir a la carpeta del proyecto
cd C:\xampp\htdocs\mensajeropro  # o donde tengas el proyecto

# 4. Crear estructura ml-engine
mkdir ml-engine\models, ml-engine\training, ml-engine\src, ml-engine\logs

# 5. Ir a ml-engine
cd ml-engine

# 6. Crear entorno virtual
python -m venv venv

# 7. Activar entorno virtual
venv\Scripts\activate

# 8. Instalar dependencias
python -m pip install --upgrade pip --force-reinstall
pip install -r requirements.txt

___________________________________

# NO USAR whatsapp-service / scripts (scripst de mantenimiento para fallas de puertos NO USAR EN OPERACIÓN NORMAL)

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




- agregar un input cuando el humano habla el bot espera unos 10 segundos para poder responder mientras no haya hablado el humano. 

- el boton de probar con numero solo funciona con el puerto 3001 

- si no cuenta con la activacion del catalogo citas que la informacion del negocio sirva solo para brindar informacion nada mas .

- solo el puerto 30001 esta o cuenta con bot para que pueda responder y
por mas que tenga informacion de negocio; solo se bugea diciendo mi espcialidad es ayudarte  con nuestros productos y servicios .  hay algo en que pueda asistirte  en ese sentido?

- si finalizo sesion desde el dispositivo en la base de datos me sale qr_pendiente y muestra el qr para escanear 
(cuando se sierra sesion desde el dispositivo ya no debe de conectar o de tratar de mostrar el qr solo tiene que matar el proceso el servidor y poner en modo desconectado, solo estu sucede con los demas puertos que no pertenecen al 3001)