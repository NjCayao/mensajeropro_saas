<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/GoogleCalendarService.php';

header('Content-Type: application/json');

// Permitir CORS para llamadas desde Node.js
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos inválidos');
    }
    
    $cita_id = $data['id'] ?? null;
    $empresa_id = $data['empresa_id'] ?? null;
    
    if (!$cita_id || !$empresa_id) {
        throw new Exception('Faltan datos requeridos');
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
        throw new Exception('Google Calendar no configurado para esta empresa');
    }
    
    // Crear servicio de Google Calendar
    $calendar = new GoogleCalendarService(
        $config['google_client_id'],
        $config['google_client_secret'],
        $config['google_refresh_token'],
        $config['google_calendar_id']
    );
    
    // Usar los datos recibidos directamente
    $eventId = $calendar->crearEvento($data);
    
    echo json_encode([
        'success' => true,
        'event_id' => $eventId,
        'message' => 'Evento creado en Google Calendar'
    ]);
    
} catch (Exception $e) {
    error_log('Error en crear-evento-google.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}