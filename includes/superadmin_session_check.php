<?php
// includes/superadmin_session_check.php
// Verificación de sesión para SuperAdmin

// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Verificar si el usuario es SuperAdmin
 */
function esSuperAdmin() {
    return estaLogueado() && isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'superadmin';
}

/**
 * Función principal de verificación para SuperAdmin
 */
function checkSuperAdminSession() {
    // Verificar que esté logueado
    if (!estaLogueado()) {
        header('Location: ' . url('login.php'));
        exit();
    }
    
    // Verificar que sea superadmin
    if (!esSuperAdmin()) {
        $_SESSION['error'] = 'No tienes permisos de administrador';
        header('Location: ' . url('login.php')); // CAMBIO: redirigir a login, no a cliente
        exit();
    }
    
    // Verificar que la empresa esté activa
    global $pdo;
    $stmt = $pdo->prepare("SELECT activo FROM empresas WHERE id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $empresa = $stmt->fetch();
    
    if (!$empresa || !$empresa['activo']) {
        cerrarSesion();
        header('Location: ' . url('login.php?error=suspended'));
        exit();
    }
}

// Regenerar ID de sesión cada 30 minutos
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Ejecutar verificación automáticamente
checkSuperAdminSession();