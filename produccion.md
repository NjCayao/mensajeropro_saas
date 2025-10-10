# LINUX 
# Carpetas: 755 (rwxr-xr-x)
find /ruta/mensajeropro -type d -exec chmod 755 {} \;

# Archivos PHP: 644 (rw-r--r--)
find /ruta/mensajeropro -type f -name "*.php" -exec chmod 644 {} \;

# instalar dependencias en local primera vez
cd whatsapp-service
npm install 
npm install multer
npm install -g pm2
npm install -g pm2-windows-startup
npm install @wppconnect-team/wppconnect
npm install axios
npm install moment

# Carpetas de escritura: 775
chmod 775 web/uploads
chmod 775 logs
chmod -R 775 web/uploads/*
chmod -R 775 logs/*


# para produccion eliminar estas lineas 
 includes/security
if (defined('IS_LOCALHOST') && IS_LOCALHOST) {
    return ['bloqueado' => false];
}

____________________________________________

# PARA EL ML DE BOT DE VENTAS 
# 1. Instalar pip3
sudo apt update
sudo apt install python3-pip -y

# 2. Verificar instalación
pip3 --version

# 3. Instalar dependencias del sistema
sudo apt install python3-venv python3-dev build-essential -y

# 4. Ir a la carpeta del proyecto
cd /var/www/mensajeropro

# 5. Crear estructura ml-engine
mkdir -p ml-engine/{models,training,src,logs}
cd ml-engine

# 6. Crear entorno virtual
python3 -m venv venv

# 7. Activar entorno virtual
source venv/bin/activate

# 8. Instalar dependencias Python
pip install --upgrade pip
pip install -r requirements.txt

_______________