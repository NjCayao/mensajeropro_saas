<?php
// web/app.php - Router dinámico
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

// Obtener la ruta desde REQUEST_URI
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Detectar base_path automáticamente
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;

// Limpiar la ruta
$path = str_replace($base_path, '', $request);
$path = trim($path, '/');

// Si no hay path, redirigir según sesión
if (empty($path) || $path === 'app.php') {
    if (isset($_SESSION['user_id']) || isset($_SESSION['empresa_id'])) {
        if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'superadmin') {
            header('Location: ' . url('superadmin/dashboard'));
        } else {
            header('Location: ' . url('cliente/dashboard'));
        }
    } else {
        header('Location: ' . url('index.php'));
    }
    exit;
}

// Archivos estáticos
$static_extensions = ['css', 'js', 'jpg', 'png', 'gif', 'ico', 'woff', 'woff2', 'ttf'];
$path_extension = pathinfo($path, PATHINFO_EXTENSION);

if (in_array($path_extension, $static_extensions)) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Rutas de API
if (strpos($path, 'api/') === 0) {
    $api_path = str_replace('api/', '', $path);
    $api_file = __DIR__ . '/../sistema/api/' . $api_path . '.php';

    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
        exit;
    }
}

// Rutas SuperAdmin
if (strpos($path, 'superadmin/') === 0) {
    require_once __DIR__ . '/../includes/superadmin_session_check.php';
    
    $module_path = substr($path, 11);
    
    $route_mapping = [
        'dashboard' => '/dashboard.php',
        'empresas' => '/modulos/empresas.php',
        'planes' => '/modulos/planes.php',
        'pagos' => '/modulos/pagos.php',
        'configuracion' => '/modulos/configuracion.php',
        'emails' => '/modulos/emails.php',
        'bot-templates' => '/modulos/bot-templates.php',
        'logs' => '/modulos/logs.php',
    ];

    if (isset($route_mapping[$module_path])) {
        $module_file = __DIR__ . '/../sistema/superadmin' . $route_mapping[$module_path];
    } else {
        $module_file = __DIR__ . '/../sistema/superadmin/' . $module_path . '.php';
    }

    if (file_exists($module_file)) {
        require_once $module_file;
        exit;
    }
}

// Rutas Cliente
if (strpos($path, 'cliente/') === 0) {
    require_once __DIR__ . '/../includes/session_check.php';
    
    $module_path = substr($path, 8);
    
    $route_mapping = [
        'dashboard' => '/dashboard.php',
        'contactos' => '/modulos/contactos.php',
        'categorias' => '/modulos/categorias.php',
        'mensajes' => '/modulos/mensajes.php',
        'plantillas' => '/modulos/plantillas.php',
        'programados' => '/modulos/programados.php',
        'whatsapp' => '/modulos/whatsapp.php',
        'negocio-config' => '/modulos/negocio-config.php',
        'perfil' => '/modulos/perfil.php',
        'bot-config' => '/modulos/bot-config.php',
        'mi-plan' => '/modulos/mi-plan.php',
        'logout' => '/logout.php'
    ];

    if (isset($route_mapping[$module_path])) {
        $module_file = __DIR__ . '/../sistema/cliente' . $route_mapping[$module_path];
    } else {
        $module_file = __DIR__ . '/../sistema/cliente/' . $module_path . '.php';
    }

    if (file_exists($module_file)) {
        require_once $module_file;
        exit;
    }
}

// 404
header('HTTP/1.1 404 Not Found');
require_once __DIR__ . '/404.php';