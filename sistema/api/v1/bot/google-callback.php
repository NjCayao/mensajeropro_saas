<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../includes/session_check.php';

if (!isset($_GET['code'])) {
    die('Error: No se recibió código de autorización');
}

try {
    $empresa_id = getEmpresaActual();
    $code = $_GET['code'];
    
    // Obtener configuración
    $stmt = $pdo->prepare("SELECT google_client_id, google_client_secret FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
    
    if (!$config) {
        die('Error: Configuración no encontrada');
    }
    
    // Intercambiar código por token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code' => $code,
        'client_id' => $config['google_client_id'],
        'client_secret' => $config['google_client_secret'],
        'redirect_uri' => url('sistema/api/v1/bot/google-callback.php'),
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        die('Error al obtener token: ' . $response);
    }
    
    $token = json_decode($response, true);
    
    // Guardar refresh token
    $stmt = $pdo->prepare("
        UPDATE configuracion_bot 
        SET google_refresh_token = ?, google_calendar_activo = 1 
        WHERE empresa_id = ?
    ");
    $stmt->execute([$token['refresh_token'], $empresa_id]);
    
    // Redirigir de vuelta al módulo
    header('Location: ' . url('cliente/horarios-bot') . '?google=connected');
    exit;
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}