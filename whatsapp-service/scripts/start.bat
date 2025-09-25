@echo off
REM whatsapp-service/start.bat

REM Colores (en Windows es más limitado)
echo ========================================
echo   MensajeroPro WhatsApp Service
echo ========================================

REM Detectar entorno
if "%NODE_ENV%"=="" (
    REM Por defecto desarrollo en Windows
    set NODE_ENV=development
)

echo Entorno: %NODE_ENV%
echo Directorio: %cd%

REM Verificar si estamos en el directorio correcto
if not exist "package.json" (
    echo ERROR: No se encuentra package.json
    echo Asegurate de ejecutar este script desde la carpeta whatsapp-service
    pause
    exit /b 1
)

REM Instalar dependencias si no existen
if not exist "node_modules" (
    echo Instalando dependencias...
    call npm install
)

REM Crear archivo .env si no existe
if not exist ".env" (
    echo Creando archivo .env...
    (
        echo # Configuracion del servicio
        echo NODE_ENV=%NODE_ENV%
        echo API_KEY=mensajeroPro2025
        echo SESSION_PATH=.wwebjs_auth
        echo MAX_MESSAGES_PER_MINUTE=20
        echo DELAY_MIN_MS=3000
        echo DELAY_MAX_MS=8000
        echo LOG_LEVEL=info
    ) > .env
)

REM Crear directorio de logs
if not exist "logs" mkdir logs

REM Obtener parametros
set PUERTO=%1
set EMPRESA_ID=%2

REM Validar parametros
if "%PUERTO%"=="" (
    echo ERROR: Debes especificar puerto y empresa_id
    echo Uso: start.bat [puerto] [empresa_id]
    echo Ejemplo: start.bat 3001 1
    pause
    exit /b 1
)

if "%EMPRESA_ID%"=="" (
    echo ERROR: Debes especificar empresa_id
    echo Uso: start.bat [puerto] [empresa_id]
    echo Ejemplo: start.bat 3001 1
    pause
    exit /b 1
)

echo Puerto: %PUERTO%
echo Empresa ID: %EMPRESA_ID%

REM Iniciar servicio
echo Iniciando servicio...
node src/index.js %PUERTO% %EMPRESA_ID%