<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../../../includes/GoogleCalendarService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $cita_id = $_POST['cita_id'] ?? null;
    $empresa_id = getEmpresaActual();
    
    if (!$cita_id) {
        throw new Exception('ID de cita no proporcionado');
    }
    
    // Obtener el google_event_id de la cita
    $stmt = $pdo->prepare("
        SELECT google_event_id 
        FROM citas_bot 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$cita_id, $empresa_id]);
    $cita = $stmt->fetch();
    
    if (!$cita || !$cita['google_event_id']) {
        echo json_encode([
            'success' => true,
            'message' => 'No hay evento de Google Calendar asociado'
        ]);
        exit;
    }
    
    // Obtener configuración de Google Calendar
    $stmt = $pdo->prepare("
        SELECT google_client_id, google_client_secret, google_refresh_token, google_calendar_id
        FROM configuracion_bot 
        WHERE empresa_id = ? AND google_calendar_activo = 1
    ");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
    
    if (!$config || !$config['google_refresh_token']) {
        throw new Exception('Google Calendar no configurado');
    }
    
    // Crear servicio de Google Calendar
    $calendar = new GoogleCalendarService(
        $config['google_client_id'],
        $config['google_client_secret'],
        $config['google_refresh_token'],
        $config['google_calendar_id']
    );
    
    // Eliminar evento
    $resultado = $calendar->eliminarEvento($cita['google_event_id']);
    
    if ($resultado) {
        // Limpiar google_event_id en BD
        $stmt = $pdo->prepare("
            UPDATE citas_bot 
            SET google_event_id = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$cita_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Evento eliminado de Google Calendar'
        ]);
    } else {
        throw new Exception('No se pudo eliminar el evento de Google Calendar');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}