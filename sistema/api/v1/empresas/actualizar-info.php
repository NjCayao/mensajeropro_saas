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
    $nombre_empresa = $_POST['nombre_empresa'] ?? '';
    $telefono = $_POST['telefono'] ?? null;
    $ruc = $_POST['ruc'] ?? null;
    $razon_social = $_POST['razon_social'] ?? null;
    $direccion = $_POST['direccion'] ?? null;

    if (empty($nombre_empresa)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El nombre de la empresa es requerido']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE empresas SET 
            nombre_empresa = ?,
            telefono = ?,
            ruc = ?,
            razon_social = ?,
            direccion = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $nombre_empresa,
        $telefono,
        $ruc,
        $razon_social,
        $direccion,
        $_SESSION['empresa_id']
    ]);

    // Log de actividad
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address, empresa_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['empresa_id'],
            'perfil',
            'actualizar_info',
            "Información de empresa actualizada",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SESSION['empresa_id']
        ]);
    } catch (Exception $e) {
        // Si falla el log, no importa
    }

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Información actualizada correctamente']);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error al actualizar info empresa: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la información']);
}
exit;