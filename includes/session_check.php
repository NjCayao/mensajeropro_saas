<?php
// includes/session_check.php
// Verificación de sesión para sistema SaaS

// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// Función principal de verificación
function checkSession() {
    if (!estaLogueado()) {
        header('Location: ' . url('login.php'));
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
checkSession();
?>