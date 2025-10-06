<?php
// includes/auth.php
// Funciones de autenticación para sistema SaaS

require_once __DIR__ . '/../config/database.php';

/**
 * Verificar credenciales de login (usando tabla empresas directamente)
 */
function verificarLogin($email, $password) {
    global $pdo;
    
    // Buscar empresa por email
    $stmt = $pdo->prepare("
        SELECT * FROM empresas 
        WHERE email = ? AND activo = 1
    ");
    $stmt->execute([$email]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        return ['success' => false, 'message' => 'Empresa no encontrada'];
    }
    
    // Verificar contraseña
    if (!password_verify($password, $empresa['password_hash'])) {
        return ['success' => false, 'message' => 'Contraseña incorrecta'];
    }
    
    return ['success' => true, 'usuario' => $empresa];
}

/**
 * Crear sesión de empresa/usuario
 */
function crearSesion($empresa) {
    $_SESSION['empresa_id'] = $empresa['id'];
    $_SESSION['empresa_nombre'] = $empresa['nombre_empresa'];
    $_SESSION['user_email'] = $empresa['email'];
    
    // NUEVO: Definir rol según campo es_superadmin
    if (isset($empresa['es_superadmin']) && $empresa['es_superadmin'] == 1) {
        $_SESSION['user_rol'] = 'superadmin';
    } else {
        $_SESSION['user_rol'] = 'cliente';
    }
    
    // Alias para compatibilidad con código antiguo
    $_SESSION['user_id'] = $empresa['id'];
    $_SESSION['user_name'] = $empresa['nombre_empresa'];
    
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Actualizar último acceso
    global $pdo;
    $stmt = $pdo->prepare("UPDATE empresas SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$empresa['id']]);
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

/**
 * Obtener ID del usuario actual
 */
function getUsuarioId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}


/**
 * Verificar y forzar autenticación
 */
function verificarSesion(): void
{
    if (!estaLogueado()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}