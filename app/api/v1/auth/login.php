<?php
// Habilitar reporte de errores para debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers para CORS y JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rutas corregidas - solo 3 niveles arriba
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app_config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Log para debugging
error_log("Login attempt at " . date('Y-m-d H:i:s'));

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

// Validaciones
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email y contraseña son requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    // Buscar usuario
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Login exitoso
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['rol'];
        
        // Log de actividad
        logActivity($pdo, 'auth', 'login', 'Inicio de sesión exitoso');
        
        echo json_encode(['success' => true, 'message' => 'Login exitoso']);
    } else {
        // Log de intento fallido
        logActivity($pdo, 'auth', 'login_failed', "Intento fallido para: $email");
        
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
    }
    
} catch (Exception $e) {
    error_log("Error en login: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>