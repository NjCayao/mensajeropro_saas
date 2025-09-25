<?php
// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// Desactivar reporte de errores para APIs JSON
error_reporting(0);
ini_set('display_errors', 0);

// Buffer de salida
ob_start();

// Headers JSON
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';

    // Validar longitud
    if (strlen($password_nueva) < 8) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
        exit;
    }

    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
        exit;
    }

    // Actualizar contraseña
    $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    $stmt->execute([$nuevo_hash, $_SESSION['user_id']]);

    // Log de actividad
    $stmt = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address, empresa_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        'perfil',
        'cambiar_password',
        "Contraseña actualizada",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        getEmpresaActual()
    ]);

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error al cambiar contraseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
}
exit;