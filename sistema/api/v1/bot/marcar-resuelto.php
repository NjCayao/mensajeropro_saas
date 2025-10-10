<?php
// sistema/api/v1/bot/marcar-resuelto.php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $notas = $_POST['notas'] ?? '';

    if (!$id) {
        Response::error('ID inválido');
    }

    $empresaId = getEmpresaActual();

    // Obtener número del cliente antes de actualizar
    $stmt = $pdo->prepare("
        SELECT numero_cliente 
        FROM estados_conversacion 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$id, $empresaId]);
    $escalado = $stmt->fetch();
    
    if (!$escalado) {
        Response::error('Conversación no encontrada');
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
        $empresaId
    ]);

    // ✅ NUEVO: También reactivar intervención humana si existe
    $stmt = $pdo->prepare("
        UPDATE intervencion_humana 
        SET estado = 'bot_activo' 
        WHERE numero_cliente = ? AND empresa_id = ?
    ");
    $stmt->execute([$escalado['numero_cliente'], $empresaId]);

    if ($result) {
        Response::success(['message' => 'Conversación marcada como resuelta y bot reactivado']);
    } else {
        Response::error('No se pudo actualizar el estado');
    }
} catch (Exception $e) {
    error_log("Error en marcar-resuelto: " . $e->getMessage());
    Response::error('Error en el servidor');
}