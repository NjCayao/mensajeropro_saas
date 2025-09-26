<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $empresa_id = getEmpresaActual();
    
    // Obtener configuración
    $stmt = $pdo->prepare("
        SELECT google_client_id, google_client_secret, google_refresh_token 
        FROM configuracion_bot WHERE empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
    
    if (!$config || !$config['google_refresh_token']) {
        throw new Exception('No hay conexión con Google Calendar');
    }
    
    // Obtener access token usando refresh token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'refresh_token' => $config['google_refresh_token'],
        'client_id' => $config['google_client_id'],
        'client_secret' => $config['google_client_secret'],
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token = json_decode($response, true);
    
    if (!isset($token['access_token'])) {
        throw new Exception('Error obteniendo access token');
    }
    
    // Obtener lista de calendarios
    $calendarUrl = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
    
    $ch = curl_init($calendarUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token['access_token']
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $calendars = json_decode($response, true);
    
    // Filtrar solo calendarios principales
    $calendarList = [];
    foreach ($calendars['items'] as $calendar) {
        if (isset($calendar['accessRole']) && in_array($calendar['accessRole'], ['owner', 'writer'])) {
            $calendarList[] = [
                'id' => $calendar['id'],
                'summary' => $calendar['summary'],
                'primary' => $calendar['primary'] ?? false
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $calendarList
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}