<?php
// includes/auth.php
// Funciones de autenticación para sistema SaaS

require_once __DIR__ . '/../config/database.php';

/**
 * Verificar credenciales de login
 */
function verificarLogin($email, $password) {
    global $pdo;
    
    // Buscar usuario por email
    $stmt = $pdo->prepare("
        SELECT u.*, e.nombre_empresa, e.activo as empresa_activa
        FROM usuarios u
        INNER JOIN empresas e ON u.empresa_id = e.id
        WHERE u.email = ? AND u.activo = 1
    ");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        return ['success' => false, 'message' => 'Usuario no encontrado'];
    }
    
    // Verificar contraseña
    if (!password_verify($password, $usuario['password'])) {
        return ['success' => false, 'message' => 'Contraseña incorrecta'];
    }
    
    // Verificar que la empresa esté activa
    if (!$usuario['empresa_activa']) {
        return ['success' => false, 'message' => 'Cuenta suspendida'];
    }
    
    return ['success' => true, 'usuario' => $usuario];
}

/**
 * Crear sesión de usuario
 */
function crearSesion($usuario) {
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_name'] = $usuario['nombre'];
    $_SESSION['user_email'] = $usuario['email'];
    $_SESSION['user_rol'] = $usuario['rol'];
    $_SESSION['empresa_id'] = $usuario['empresa_id'];
    $_SESSION['empresa_nombre'] = $usuario['nombre_empresa'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Actualizar último acceso
    global $pdo;
    $stmt = $pdo->prepare("UPDATE empresas SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$usuario['empresa_id']]);
}

/**
 * Verificar si el usuario está logueado
 */
function estaLogueado() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Verificar rol de usuario
 */
function tieneRol($rol) {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === $rol;
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Redirigir si no está logueado
 */
function requireLogin() {
    if (!estaLogueado()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

/**
 * Generar token CSRF
 */
function generarCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificarCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>