# Ejecutar cada minuto para procesar mensajes programados
* * * * * /usr/bin/php /ruta/a/mensajeroprov2/cron/procesar_programados.php >> /var/log/mensajero_programados.log 2>&1

# Ejecutar cada 2 minutos para procesar la cola de envío
*/2 * * * * /usr/bin/php /ruta/a/mensajeroprov2/cron/procesar_cola.php >> /var/log/mensajero_cola.log 2>&1