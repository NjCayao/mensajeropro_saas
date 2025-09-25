<?php
// Desactivar reporte de errores para evitar corrupción del JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Obtener estado de la BD
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
    $stmt->execute([getEmpresaActual()]);
    $whatsapp = $stmt->fetch(PDO::FETCH_ASSOC);

    // Inicializar valores por defecto si no existe el registro
    if (!$whatsapp) {
        $whatsapp = [
            'estado' => 'desconectado',
            'puerto' => 3001,
            'qr_code' => null,
            'numero_conectado' => null,
            'nombre_conectado' => null
        ];
    }

    // Intentar conectar con el servicio Node.js
    $nodeConnected = false;
    $nodeStatus = null;

    $puerto = $whatsapp['puerto'] ?? 3001;
    
    // Usar cURL para verificar el servicio
    $ch = curl_init("http://localhost:$puerto/api/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: mensajeroPro2025']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && $response) {
        $nodeData = json_decode($response, true);
        if ($nodeData && isset($nodeData['success']) && $nodeData['success']) {
            $nodeConnected = true;
            $nodeStatus = $nodeData['data'];
        }
    }

    // Si hay QR pendiente, incluirlo en la respuesta
    if ($whatsapp['estado'] == 'qr_pendiente' && !empty($whatsapp['qr_code'])) {
        echo json_encode([
            'success' => true,
            'connected' => false,
            'data' => [
                'estado' => 'qr_pendiente',
                'qr_code' => $whatsapp['qr_code'],
                'numero_conectado' => null,
                'node_service' => $nodeConnected
            ],
            'qr' => $whatsapp['qr_code']
        ]);
        exit;
    }

    // Combinar estado de BD y Node.js
    $connected = $nodeConnected && $nodeStatus && isset($nodeStatus['connected']) && $nodeStatus['connected'];

    echo json_encode([
        'success' => true,
        'connected' => $connected,
        'data' => [
            'estado' => $connected ? 'conectado' : ($whatsapp['estado'] ?: 'desconectado'),
            'qr_code' => $whatsapp['qr_code'] ?? null,
            'numero_conectado' => $whatsapp['numero_conectado'] ?? null,
            'node_service' => $nodeConnected
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;