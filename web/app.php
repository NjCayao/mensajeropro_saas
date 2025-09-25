<?php
// public/app.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("=== INICIO APP.PHP ===");
error_log("Session ID: " . session_id());
error_log("User ID en sesión: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NO EXISTE'));

require_once __DIR__ . '/../config/app.php';

// Obtener la ruta solicitada
$request = $_SERVER['REQUEST_URI'];
$base_path = '/mensajeroprov2/';

// Limpiar la ruta
$path = str_replace($base_path, '', $request);
$path = explode('?', $path)[0];
$path = trim($path, '/');

// Debug detallado
error_log("=== DEBUG RUTAS ===");
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("BASE_PATH: " . $base_path);
error_log("PATH FINAL: '" . $path . "'");
error_log("PATH length: " . strlen($path));

// Si no hay path o es 'app.php', ir al dashboard si está logueado
error_log("=== VERIFICANDO PATH VACÍO ===");
error_log("Path empty?: " . (empty($path) ? 'SI' : 'NO'));
error_log("Path es app.php?: " . ($path === 'app.php' ? 'SI' : 'NO'));

if (empty($path) || $path === 'app.php') {
    error_log("Entrando a redirección por path vacío/app.php");
    if (isset($_SESSION['user_id'])) {
        $redirect_url = APP_URL . '/cliente/modulos/dashboard';
        error_log("Redirigiendo a: " . $redirect_url);
        header('Location: ' . $redirect_url);
    } else {
        $redirect_url = APP_URL . '/index.php';
        error_log("Redirigiendo a login: " . $redirect_url);
        header('Location: ' . $redirect_url);
    }
    exit;
}

// Verificar si es archivo estático
$static_extensions = ['css', 'js', 'jpg', 'png', 'gif', 'ico', 'woff', 'woff2', 'ttf'];
$path_extension = pathinfo($path, PATHINFO_EXTENSION);
error_log("=== VERIFICANDO ARCHIVO ESTÁTICO ===");
error_log("Extensión: " . $path_extension);
error_log("Es archivo estático?: " . (in_array($path_extension, $static_extensions) ? 'SI' : 'NO'));

if (in_array($path_extension, $static_extensions)) {
    error_log("Es archivo estático, retornando 404");
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Rutas de API
error_log("=== VERIFICANDO RUTA API ===");
error_log("Comienza con 'api/'?: " . (strpos($path, 'api/') === 0 ? 'SI' : 'NO'));

if (strpos($path, 'api/') === 0) {
    error_log("Es ruta API");
    $api_path = str_replace('api/', '', $path);
    $api_file = __DIR__ . '/../sistema/api/' . $api_path;
    
    error_log("API path: " . $api_path);
    error_log("API file: " . $api_file);
    error_log("API file exists?: " . (file_exists($api_file) ? 'SI' : 'NO'));
    
    if (file_exists($api_file)) {
        error_log("Incluyendo archivo API");
        require_once $api_file;
        exit;
    } else {
        error_log("API no encontrada");
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'API endpoint not found',
            'path' => $api_path
        ]);
        exit;
    }
}

// Rutas de módulos del cliente
error_log("=== VERIFICANDO RUTA CLIENTE ===");
error_log("Path actual: '" . $path . "'");
error_log("strpos result: " . strpos($path, 'cliente/'));
error_log("Comienza con 'cliente/'?: " . (strpos($path, 'cliente/') === 0 ? 'SI' : 'NO'));

if (strpos($path, 'cliente/') === 0) {
    error_log("Es ruta cliente!");
    
    // Verificar sesión antes de incluir session_check
    error_log("=== VERIFICANDO SESIÓN ANTES DE SESSION_CHECK ===");
    error_log("Session variables: " . print_r($_SESSION, true));
    
    // Verificar si el archivo session_check existe
    $session_check_file = __DIR__ . '/../includes/session_check.php';
    error_log("Session check file: " . $session_check_file);
    error_log("Session check exists?: " . (file_exists($session_check_file) ? 'SI' : 'NO'));
    
    if (file_exists($session_check_file)) {
        error_log("Incluyendo session_check.php");
        require_once $session_check_file;
        error_log("Session_check.php incluido exitosamente");
    } else {
        error_log("WARNING: session_check.php no encontrado!");
    }
    
    // Construir ruta del módulo
    $module_path = '/' . substr($path, 7); // Quita 'cliente' (7 caracteres)
    $module_file = __DIR__ . '/../sistema/cliente' . $module_path . '.php';
    
    error_log("Module path: " . $module_path);
    error_log("Module file: " . $module_file);
    error_log("Module file real path: " . (file_exists($module_file) ? realpath($module_file) : 'NO EXISTE'));
    error_log("Module file exists?: " . (file_exists($module_file) ? 'SI' : 'NO'));
    
    if (file_exists($module_file)) {
        error_log("Incluyendo módulo: " . $module_file);
        require_once $module_file;
        error_log("Módulo incluido, saliendo");
        exit;
    } else {
        error_log("ERROR: Módulo no encontrado");
        // Listar archivos en el directorio para debug
        $dir = dirname($module_file);
        if (is_dir($dir)) {
            error_log("Archivos en " . $dir . ":");
            $files = scandir($dir);
            foreach ($files as $file) {
                error_log("  - " . $file);
            }
        }
    }
} else {
    error_log("NO es ruta cliente");
    error_log("Primeros 8 caracteres del path: '" . substr($path, 0, 8) . "'");
}

// Si llegamos aquí, la ruta no existe
error_log("=== RUTA NO ENCONTRADA ===");
error_log("Mostrando 404 para path: " . $path);

header('HTTP/1.1 404 Not Found');
if (file_exists(__DIR__ . '/404.php')) {
    include __DIR__ . '/404.php';
} else {
    echo "404 - Not Found";
}

error_log("=== FIN APP.PHP ===");
?>