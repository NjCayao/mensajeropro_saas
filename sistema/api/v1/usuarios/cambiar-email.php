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
    $nuevo_email = $_POST['nuevo_email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validar email
    if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit;
    }

    // Verificar que el email no exista
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$nuevo_email, $_SESSION['user_id']]);
    if ($stmt->rowCount() > 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El email ya está en uso']);
        exit;
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

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Email actualizado correctamente']);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error al cambiar email: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el email']);
}
exit;