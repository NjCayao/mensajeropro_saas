#!/bin/bash
NODE_ENV=$1
PROCESS_NAME=$2
PUERTO=$3
EMPRESA_ID=$4

cd /var/www/mensajeropro/whatsapp-service
/usr/bin/pm2 start src/index.js --name "$PROCESS_NAME" --no-autorestart -- "$PUERTO" "$EMPRESA_ID"