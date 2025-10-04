<?php
// config/app.php - Versión 2.0 SaaS
declare(strict_types=1);

// Configuración general
define('APP_NAME', 'MensajeroPro');
define('APP_VERSION', '2.0.0'); // Versión SaaS

// Detección de entorno
define('IS_LOCALHOST', (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) || (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1'));
// define('IS_LOCALHOST', strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['SERVER_ADDR'] === '127.0.0.1');
define('ENVIRONMENT', IS_LOCALHOST ? 'development' : 'production');

// URLs dinámicas según el entorno
// if (IS_LOCALHOST) {
//     define('APP_URL', 'http://localhost/mensajeroprov2');
//     define('WHATSAPP_API_URL', 'http://localhost:3001');
// } else {
//     // En producción usar HTTPS
//     define('APP_URL', 'https://' . $_SERVER['HTTP_HOST']);
//     define('WHATSAPP_API_URL', 'https://' . $_SERVER['HTTP_HOST'] . ':3001');
// }

if (IS_LOCALHOST) {
    define('APP_URL', 'http://localhost/mensajeroprov2');
    define('WHATSAPP_API_URL', 'http://localhost:3001');
} else {
    // En producción
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'tudominio.com'; // ← CAMBIAR
    define('APP_URL', 'https://' . $host);
    define('WHATSAPP_API_URL', 'https://' . $host . ':3001');
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

// URLs relativas para assets
define('ASSETS_URL', '/assets');
define('UPLOADS_URL', '/uploads');

// Crear directorios necesarios con estructura por empresa
$requiredDirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'logos',
    UPLOAD_PATH . 'catalogos',
    UPLOAD_PATH . 'empresas', // Nuevo para SaaS
    LOGS_PATH,
    LOGS_PATH . 'empresas' // Logs por empresa
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true); // 0755 es más seguro que 0777
    }
}

// Zona horaria
date_default_timezone_set('America/Lima');

// Configuración de sesión - SOLO si no hay sesión activa
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', !IS_LOCALHOST);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400); // 24 horas
}

// Configuración de errores según entorno
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . 'php_errors.log');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . 'php_errors.log');
}

// Límites del sistema
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_CATALOG_SIZE', 10 * 1024 * 1024); // 10MB para catálogos
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_CATALOG_TYPES', ['pdf']);

// Configuración SaaS
define('TRIAL_DAYS', 30);
define('DEFAULT_PLAN_ID', 1); // Trial
define('SESSION_LIFETIME', 86400); // 24 horas

// Límites por defecto (pueden ser sobrescritos por plan)
define('DEFAULT_MESSAGES_PER_DAY', 1000);
define('DEFAULT_CONTACTS_LIMIT', 500);

// Configuración de seguridad
define('CSRF_TOKEN_NAME', 'csrf_token');
define('API_RATE_LIMIT', 100); // requests por minuto

// Función helper para obtener URL con path
function url(string $path = ''): string {
    return APP_URL . '/' . ltrim($path, '/');
}

// Función helper para assets
function asset(string $path = ''): string {
    return APP_URL . ASSETS_URL . '/' . ltrim($path, '/');
}

// Función para obtener path de uploads por empresa
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

// Autoload de clases (si no usas Composer)
spl_autoload_register(function ($class) {
    $paths = [
        INCLUDES_PATH . '/' . $class . '.php',
        APP_PATH . '/models/' . $class . '.php',
        APP_PATH . '/controllers/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
});


function getWhatsAppServiceUrl($empresa_id) {
    // En local: usar puerto dinámico basado en empresa_id
    if (IS_LOCALHOST) {
        $puerto = 3000 + $empresa_id;  // 3001, 3002, 3003, etc.
        return "http://localhost:{$puerto}";
    } else {
        // En producción: usar proxy reverso
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . "/whatsapp/empresa/{$empresa_id}";
    }
}

// Función helper para el API
function getWhatsAppApiUrl($empresa_id) {
    return getWhatsAppServiceUrl($empresa_id) . '/api';
}

// Función para verificar si el servicio está accesible
function isWhatsAppServiceAccessible($empresa_id) {
    $url = getWhatsAppServiceUrl($empresa_id) . '/health';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}
?>