<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Obtener estado de la BD
    $stmt = $pdo->query("SELECT * FROM whatsapp_sesion WHERE id = 1");
    $whatsapp = $stmt->fetch(PDO::FETCH_ASSOC);

    // Intentar conectar con el servicio Node.js
    $nodeConnected = false;
    $nodeStatus = null;

    $ch = curl_init('http://localhost:3001/api/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: mensajeroPro2025']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);

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
    if ($whatsapp['estado'] == 'qr_pendiente') {
        $ch = curl_init('http://localhost:3001/api/qr');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: mensajeroPro2025']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $qrResponse = curl_exec($ch);
        $qrHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($qrHttpCode == 200 && $qrResponse) {
            $qrData = json_decode($qrResponse, true);
            if ($qrData && isset($qrData['qr'])) {
                echo json_encode([
                    'success' => true,
                    'connected' => false,
                    'data' => [
                        'estado' => 'qr_pendiente',
                        'qr_code' => $qrData['qr'],
                        'numero_conectado' => null,
                        'node_service' => $nodeConnected
                    ],
                    'qr' => $qrData['qr']
                ]);
                exit;
            }
        }
    }

    // Combinar estado de BD y Node.js
    $connected = $nodeConnected && $nodeStatus && $nodeStatus['connected'];

    echo json_encode([
        'success' => true,
        'connected' => $connected,
        'data' => [
            'estado' => $connected ? 'conectado' : ($whatsapp['estado'] ?: 'desconectado'),
            'qr_code' => $whatsapp['qr_code'],
            'numero_conectado' => $whatsapp['numero_conectado'],
            'node_service' => $nodeConnected
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
