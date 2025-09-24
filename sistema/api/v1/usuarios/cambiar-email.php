<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    Response::error('No autorizado', 401);
}

try {
    $nuevo_email = $_POST['nuevo_email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validar email
    if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email inválido');
    }

    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();

    if (!password_verify($password, $usuario['password'])) {
        Response::error('Contraseña incorrecta');
    }

    // Verificar que el email no exista
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$nuevo_email, $_SESSION['user_id']]);
    if ($stmt->rowCount() > 0) {
        Response::error('El email ya está en uso');
    }

    // Actualizar email
    $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
    $stmt->execute([$nuevo_email, $_SESSION['user_id']]);

    // Log de actividad
    $stmt = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address, empresa_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        'perfil',
        'cambiar_email',
        "Email actualizado",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        getEmpresaActual()
    ]);

    Response::success(['message' => 'Email actualizado correctamente']);
} catch (Exception $e) {
    error_log("Error al cambiar email: " . $e->getMessage());
    Response::error('Error al actualizar: ' . $e->getMessage());
}
