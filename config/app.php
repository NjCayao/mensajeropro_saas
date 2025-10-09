<?php
// config/app.php - Versión limpia
declare(strict_types=1);

define('APP_NAME', 'MensajeroPro');
define('APP_VERSION', '5.0.0');

// Detección de entorno mejorada
$isLocalhost = (
    (isset($_SERVER['HTTP_HOST']) && (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
    )) ||
    (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')
);

define('IS_LOCALHOST', $isLocalhost);
define('ENVIRONMENT', IS_LOCALHOST ? 'development' : 'production');

// URLs dinámicas
if (IS_LOCALHOST) {
    // Detectar automáticamente el subdirectorio - CORREGIDO
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    // Si estamos en /web, quitar ese segmento
    $scriptPath = str_replace('/web', '', $scriptPath);
    
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . $scriptPath;
    define('APP_URL', rtrim($baseUrl, '/'));
    define('WHATSAPP_API_URL', 'http://localhost:3001');
} else {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    define('APP_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);
    define('WHATSAPP_API_URL', APP_URL . ':3001');
}

// Cargar funciones globales
require_once __DIR__ . '/../includes/functions.php';

// Rutas del sistema
define('BASE_PATH', dirname(__DIR__));
define('WEB_PATH', BASE_PATH . '/web');
define('APP_PATH', BASE_PATH . '/sistema');
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOAD_PATH', WEB_PATH . '/uploads/');
define('DS', DIRECTORY_SEPARATOR);
define('LOGS_PATH', BASE_PATH . DS . 'logs' . DS);

// Crear directorios necesarios
$requiredDirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'logos',
    UPLOAD_PATH . 'catalogos',
    UPLOAD_PATH . 'empresas',
    LOGS_PATH,
    LOGS_PATH . 'empresas'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

date_default_timezone_set('America/Lima');

// Configuración de sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', IS_LOCALHOST ? '0' : '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '86400');
}

// Errores según entorno
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . 'php_errors.log');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . 'php_errors.log');
}

// Constantes del sistema
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('MAX_CATALOG_SIZE', 10 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_CATALOG_TYPES', ['pdf']);
define('TRIAL_DAYS', 1);
define('DEFAULT_PLAN_ID', 1);
define('SESSION_LIFETIME', 86400);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Helpers
function url(string $path = ''): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function asset(string $path = ''): string {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function getEmpresaUploadPath(int $empresa_id, string $tipo = ''): string {
    $path = UPLOAD_PATH . 'empresas/' . $empresa_id;
    if ($tipo) {
        $path .= '/' . $tipo;
    }
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    return $path;
}