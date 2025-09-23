<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../response.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    Response::error('No autorizado', 401);
}

try {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    
    // Validar longitud
    if (strlen($password_nueva) < 8) {
        Response::error('La contraseña debe tener al menos 8 caracteres');
    }
    
    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();
    
    if (!password_verify($password_actual, $usuario['password'])) {
        Response::error('Contraseña actual incorrecta');
    }
    
    // Actualizar contraseña
    $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    $stmt->execute([$nuevo_hash, $_SESSION['user_id']]);
    
    // Log de actividad
    $stmt = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        'perfil',
        'cambiar_password',
        "Contraseña actualizada",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
    Response::success(['message' => 'Contraseña actualizada correctamente']);
    
} catch (Exception $e) {
    error_log("Error al cambiar contraseña: " . $e->getMessage());
    Response::error('Error al actualizar: ' . $e->getMessage());
}
?>