#!/bin/bash
# whatsapp-service/start.sh

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Detectar entorno
if [ -z "$NODE_ENV" ]; then
    # Verificar si estamos en producción (varios métodos)
    if [ -f "/etc/nginx/sites-enabled/mensajeropro" ] || \
       [ -d "/var/www" ] || \
       [ "$HOSTNAME" = "produccion" ] || \
       [ ! -z "$PRODUCTION_SERVER" ]; then
        export NODE_ENV="production"
    else
        export NODE_ENV="development"
    fi
fi

echo -e "${BLUE}🚀 Iniciando MensajeroPro WhatsApp Service${NC}"
echo -e "${GREEN}📍 Entorno: $NODE_ENV${NC}"
echo -e "${GREEN}📍 Directorio: $(pwd)${NC}"

# Verificar si estamos en el directorio correcto
if [ ! -f "package.json" ]; then
    echo "❌ Error: No se encuentra package.json"
    echo "📁 Asegúrate de ejecutar este script desde la carpeta whatsapp-service"
    exit 1
fi

# Instalar dependencias si no existen
if [ ! -d "node_modules" ]; then
    echo -e "${BLUE}📦 Instalando dependencias...${NC}"
    npm install
fi

# Ya no necesitamos archivos .env separados porque leemos de database.php
# Solo crear un .env básico si no existe
if [ ! -f ".env" ]; then
    echo -e "${BLUE}📝 Creando archivo .env básico...${NC}"
    cat > .env << EOF
# Configuración del servicio
NODE_ENV=$NODE_ENV
API_KEY=mensajeroPro2025
SESSION_PATH=tokens
MAX_MESSAGES_PER_MINUTE=20
DELAY_MIN_MS=3000
DELAY_MAX_MS=8000
LOG_LEVEL=info
EOF
fi

# Crear directorio de logs si no existe
mkdir -p logs

# Obtener parámetros
PUERTO=$1
EMPRESA_ID=$2

# Validar parámetros
if [ -z "$PUERTO" ] || [ -z "$EMPRESA_ID" ]; then
    echo "❌ Error: Debes especificar puerto y empresa_id"
    echo "Uso: ./start.sh [puerto] [empresa_id]"
    echo "Ejemplo: ./start.sh 3001 1"
    exit 1
fi

echo -e "${GREEN}🔌 Puerto: $PUERTO${NC}"
echo -e "${GREEN}🏢 Empresa ID: $EMPRESA_ID${NC}"

# Iniciar servicio
if [ "$NODE_ENV" = "production" ] && command -v pm2 &> /dev/null; then
    echo -e "${BLUE}🔄 Iniciando con PM2...${NC}"
    
    # Crear configuración PM2 dinámica
    cat > ecosystem.config.js << EOF
module.exports = {
  apps: [{
    name: 'mensajeropro-whatsapp-empresa-$EMPRESA_ID',
    script: 'src/index.js',
    args: '$PUERTO $EMPRESA_ID',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    env: {
      NODE_ENV: 'production'
    },
    error_file: 'logs/empresa-$EMPRESA_ID-error.log',
    out_file: 'logs/empresa-$EMPRESA_ID-out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss'
  }]
}
EOF
    
    # Detener instancia anterior si existe
    pm2 stop "mensajeropro-whatsapp-empresa-$EMPRESA_ID" 2>/dev/null
    pm2 delete "mensajeropro-whatsapp-empresa-$EMPRESA_ID" 2>/dev/null
    
    # Iniciar nueva instancia
    pm2 start ecosystem.config.js
    
    # Guardar configuración PM2
    pm2 save
    
    echo -e "${GREEN}✅ Servicio iniciado con PM2${NC}"
    echo "📊 Ver logs: pm2 logs mensajeropro-whatsapp-empresa-$EMPRESA_ID"
else
    echo -e "${BLUE}🔄 Iniciando con Node.js...${NC}"
    node src/index.js $PUERTO $EMPRESA_ID
fi