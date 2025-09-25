#!/bin/bash
# whatsapp-service/start.sh

# Detectar entorno
if [ -z "$NODE_ENV" ]; then
    # Si no está definido, verificar si estamos en local
    if [ -f "/etc/nginx/sites-enabled/mensajeropro" ]; then
        export NODE_ENV="production"
    else
        export NODE_ENV="development"
    fi
fi

echo "🚀 Iniciando en modo: $NODE_ENV"

# Cargar variables de entorno según el ambiente
if [ "$NODE_ENV" = "production" ]; then
    source .env.production
else
    source .env.development
fi

# Iniciar con PM2 o directamente
if [ "$NODE_ENV" = "production" ] && command -v pm2 &> /dev/null; then
    pm2 start ecosystem.config.js
else
    node src/index.js $1 $2
fi