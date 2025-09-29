<?php
require_once __DIR__ . '/../../../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$empresa_id = $input['empresa_id'] ?? null;
$numero_cliente = $input['numero_cliente'] ?? '';
$ultimo_mensaje = $input['ultimo_mensaje'] ?? '';
$motivo = $input['motivo'] ?? 'Solicitó atención humana';

if (!$empresa_id) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada']);
    exit;
}

try {
    // Obtener configuración
    $stmt = $pdo->prepare("
        SELECT notificar_escalamiento, numeros_notificacion, mensaje_notificacion 
        FROM configuracion_bot 
        WHERE empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
    
    if (!$config || !$config['notificar_escalamiento']) {
        echo json_encode(['success' => true, 'message' => 'Notificaciones desactivadas']);
        exit;
    }
    
    $numeros = json_decode($config['numeros_notificacion'] ?? '[]', true);
    
    if (empty($numeros)) {
        echo json_encode(['success' => true, 'message' => 'No hay números configurados']);
        exit;
    }
    
    // Preparar mensaje
    $mensaje = str_replace(
        ['{numero}', '{ultimo_mensaje}', '{motivo}', '{hora}'],
        [$numero_cliente, $ultimo_mensaje, $motivo, date('H:i')],
        $config['mensaje_notificacion']
    );
    
    // Aquí deberías integrar con tu servicio de WhatsApp
    // Por ahora solo retornamos los datos
    echo json_encode([
        'success' => true,
        'numeros_a_notificar' => $numeros,
        'mensaje' => $mensaje
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>