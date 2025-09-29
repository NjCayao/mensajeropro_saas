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
