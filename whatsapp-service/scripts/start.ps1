# whatsapp-service/start.ps1

# Colores
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   MensajeroPro WhatsApp Service" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Detectar entorno
if (-not $env:NODE_ENV) {
    $env:NODE_ENV = "development"
}

Write-Host "Entorno: $env:NODE_ENV" -ForegroundColor Green
Write-Host "Directorio: $(Get-Location)" -ForegroundColor Green

# Verificar directorio
if (-not (Test-Path "package.json")) {
    Write-Host "ERROR: No se encuentra package.json" -ForegroundColor Red
    Write-Host "Asegurate de estar en la carpeta whatsapp-service" -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
}

# Instalar dependencias si no existen
if (-not (Test-Path "node_modules")) {
    Write-Host "Instalando dependencias..." -ForegroundColor Yellow
    npm install
}

# Crear .env si no existe
if (-not (Test-Path ".env")) {
    Write-Host "Creando archivo .env..." -ForegroundColor Yellow
    @"
# Configuracion del servicio
NODE_ENV=$env:NODE_ENV
API_KEY=mensajeroPro2025
SESSION_PATH=.wwebjs_auth
MAX_MESSAGES_PER_MINUTE=20
DELAY_MIN_MS=3000
DELAY_MAX_MS=8000
LOG_LEVEL=info
"@ | Out-File -FilePath ".env" -Encoding UTF8
}

# Crear logs si no existe
if (-not (Test-Path "logs")) {
    New-Item -ItemType Directory -Name "logs" | Out-Null
}

# Obtener parametros
$puerto = $args[0]
$empresaId = $args[1]

# Validar
if (-not $puerto -or -not $empresaId) {
    Write-Host "ERROR: Debes especificar puerto y empresa_id" -ForegroundColor Red
    Write-Host "Uso: .\start.ps1 [puerto] [empresa_id]" -ForegroundColor Yellow
    Write-Host "Ejemplo: .\start.ps1 3001 1" -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir"
    exit 1
}

Write-Host "Puerto: $puerto" -ForegroundColor Green
Write-Host "Empresa ID: $empresaId" -ForegroundColor Green

# Iniciar
Write-Host "Iniciando servicio..." -ForegroundColor Yellow
node src/index.js $puerto $empresaId