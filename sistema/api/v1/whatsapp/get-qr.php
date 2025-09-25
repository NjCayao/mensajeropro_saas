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
    $empresa_id = getEmpresaActual();
    
    // Obtener QR de la base de datos
    $stmt = $pdo->prepare("SELECT qr_code, estado FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['qr_code'] && $result['estado'] === 'qr_pendiente') {
        echo json_encode([
            'success' => true,
            'qr' => $result['qr_code'],
            'estado' => $result['estado']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No hay QR disponible',
            'estado' => $result['estado'] ?? 'desconectado'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;