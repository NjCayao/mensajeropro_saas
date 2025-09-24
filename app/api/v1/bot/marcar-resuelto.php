<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $notas = $_POST['notas'] ?? '';

    if (!$id) {
        Response::error('ID inválido');
    }

    // Actualizar estado a resuelto
    $stmt = $pdo->prepare("
        UPDATE estados_conversacion 
        SET estado = 'resuelto',
            fecha_resuelto = NOW(),
            resuelto_por = ?,
            notas = ?
        WHERE id = ? AND estado = 'escalado_humano' AND empresa_id = ?
    ");

    $result = $stmt->execute([
        $_SESSION['user_id'],
        $notas,
        $id,
        getEmpresaActual()
    ]);

    if ($stmt->rowCount() > 0) {
        Response::success(['message' => 'Conversación marcada como resuelta']);
    } else {
        Response::error('No se pudo actualizar el estado');
    }
} catch (Exception $e) {
    error_log("Error en marcar-resuelto: " . $e->getMessage());
    Response::error('Error en el servidor');
}
