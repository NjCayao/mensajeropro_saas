<?php
// web/app.php - Router principal corregido
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

// Obtener la ruta solicitada
$request = $_SERVER['REQUEST_URI'];
$base_path = '/mensajeroprov2/';

// Limpiar la ruta
$path = str_replace($base_path, '', $request);
$path = explode('?', $path)[0];
$path = trim($path, '/');

// Si no hay path o es 'app.php', ir al dashboard si está logueado
if (empty($path) || $path === 'app.php') {
    if (isset($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/cliente/dashboard');
    } else {
        header('Location: ' . APP_URL . '/index.php');
    }
    exit;
}

// Verificar si es archivo estático
$static_extensions = ['css', 'js', 'jpg', 'png', 'gif', 'ico', 'woff', 'woff2', 'ttf'];
$path_extension = pathinfo($path, PATHINFO_EXTENSION);

if (in_array($path_extension, $static_extensions)) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Rutas de API
if (strpos($path, 'api/') === 0) {
    $api_path = str_replace('api/', '', $path);
    $api_file = __DIR__ . '/../sistema/api/' . $api_path;
    
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'API endpoint not found'
        ]);
        exit;
    }
}

// Rutas del cliente
if (strpos($path, 'cliente/') === 0) {
    // Verificar sesión
    require_once __DIR__ . '/../includes/session_check.php';
    
    // Quitar 'cliente/' del path
    $module_path = substr($path, 8);
    
    // MAPEO DE RUTAS CORREGIDO
    $route_mapping = [
        // Dashboard
        'dashboard' => '/dashboard.php',
        
        // Módulos principales (están en /sistema/cliente/modulos/)
        'contactos' => '/modulos/contactos.php',
        'categorias' => '/modulos/categorias.php',
        'mensajes' => '/modulos/mensajes.php',
        'plantillas' => '/modulos/plantillas.php',
        'programados' => '/modulos/programados.php',
        'whatsapp' => '/modulos/whatsapp.php',
        'perfil' => '/modulos/perfil.php',
        'bot-config' => '/modulos/bot-config.php',
        'escalados' => '/modulos/escalados.php',
        'catalogo-bot' => '/modulos/catalogo-bot.php',
        'bot-templates' => '/modulos/bot-templates.php',
        'horarios-bot' => '/modulos/horarios-bot.php',
        
        // Rutas especiales
        'logout' => '/logout.php'
    ];
    
    // Buscar en el mapeo
    if (isset($route_mapping[$module_path])) {
        $module_file = __DIR__ . '/../sistema/cliente' . $route_mapping[$module_path];
    } else {
        // Si no está en el mapeo, intentar ruta directa
        $module_file = __DIR__ . '/../sistema/cliente/' . $module_path . '.php';
    }
    
    if (file_exists($module_file)) {
        require_once $module_file;
        exit;
    }
}

// Si llegamos aquí, la ruta no existe
header('HTTP/1.1 404 Not Found');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
    <link rel="stylesheet" href="<?php echo asset('dist/css/adminlte.min.css'); ?>">
</head>
<body class="hold-transition">
    <div class="error-page">
        <h2 class="headline text-warning"> 404</h2>
        <div class="error-content">
            <h3><i class="fas fa-exclamation-triangle text-warning"></i> Página no encontrada</h3>
            <p>La página que buscas no existe.</p>
            <a href="<?php echo APP_URL; ?>">Volver al inicio</a>
        </div>
    </div>
</body>
</html>