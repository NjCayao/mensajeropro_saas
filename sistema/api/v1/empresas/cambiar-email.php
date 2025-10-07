<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['empresa_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $nuevo_email = $_POST['nuevo_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password_hash FROM empresas WHERE id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $empresa = $stmt->fetch();

    if (!$empresa || !password_verify($password, $empresa['password_hash'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit;
    }

    // Verificar que el email no exista
    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE email = ? AND id != ?");
    $stmt->execute([$nuevo_email, $_SESSION['empresa_id']]);
    if ($stmt->rowCount() > 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El email ya está en uso']);
        exit;
    }

    // Actualizar email
    $stmt = $pdo->prepare("UPDATE empresas SET email = ? WHERE id = ?");
    $stmt->execute([$nuevo_email, $_SESSION['empresa_id']]);

    // Log de actividad
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address, empresa_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['empresa_id'],
            'perfil',
            'cambiar_email',
            "Email actualizado a: $nuevo_email",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SESSION['empresa_id']
        ]);
    } catch (Exception $e) {
        // Si falla el log, no importa
    }

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Email actualizado correctamente']);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error al cambiar email: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el email']);
}
exit;