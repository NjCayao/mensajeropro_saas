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