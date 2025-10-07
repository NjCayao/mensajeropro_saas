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
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';

    if (strlen($password_nueva) < 8) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
        exit;
    }

    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password_hash FROM empresas WHERE id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $empresa = $stmt->fetch();

    if (!$empresa || !password_verify($password_actual, $empresa['password_hash'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
        exit;
    }

    // Actualizar contraseña
    $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE empresas SET password_hash = ? WHERE id = ?");
    $stmt->execute([$nuevo_hash, $_SESSION['empresa_id']]);

    // Log de actividad
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address, empresa_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['empresa_id'],
            'perfil',
            'cambiar_password',
            "Contraseña actualizada",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SESSION['empresa_id']
        ]);
    } catch (Exception $e) {
        // Si falla el log, no importa
    }

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error al cambiar contraseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
}
exit;