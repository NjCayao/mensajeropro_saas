#!/bin/bash
PROCESS_NAME=$1

cd /var/www/mensajeropro/whatsapp-service
/usr/bin/pm2 stop "$PROCESS_NAME" 2>&1
/usr/bin/pm2 delete "$PROCESS_NAME" 2>&1