<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

// Obtener configuración desde BD
function getGoogleConfig($clave)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : '';
}

$google_client_id = getGoogleConfig('google_client_id');
$google_client_secret = getGoogleConfig('google_client_secret');
$google_oauth_activo = getGoogleConfig('google_oauth_activo');

// Verificar que esté configurado y activo
if (empty($google_client_id) || empty($google_client_secret)) {
    die('Google OAuth no está configurado. Contacta al administrador.');
}

if ($google_oauth_activo !== '1') {
    die('Google OAuth está desactivado temporalmente.');
}

define('GOOGLE_CLIENT_ID', $google_client_id);
define('GOOGLE_CLIENT_SECRET', $google_client_secret);
define('GOOGLE_REDIRECT_URI', APP_URL . '/api/v1/auth/google-oauth.php');

// Si no hay código, redirigir a Google
if (!isset($_GET['code'])) {
    $auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online'
    ]);

    header('Location: ' . $auth_url);
    exit;
}

// Intercambiar código por token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
$token_response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($token_response, true);

if (!isset($token_data['access_token'])) {
    die('Error al obtener token de Google');
}

// Obtener información del usuario
$user_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($user_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token']
]);
$user_response = curl_exec($ch);
curl_close($ch);

$google_user = json_decode($user_response, true);

if (!isset($google_user['email'])) {
    die('Error al obtener información del usuario');
}

try {
    // Buscar si la empresa ya existe
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE email = ? OR google_id = ?");
    $stmt->execute([$google_user['email'], $google_user['id']]);
    $empresa = $stmt->fetch();

    if ($empresa) {
        // Login existente
        crearSesion($empresa);
        header('Location: ' . url('cliente/dashboard'));
        exit;
    }

    // Registro nuevo
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO empresas 
        (nombre_empresa, email, google_id, timezone, metodo_registro, 
        email_verificado, plan_id, fecha_registro, activo) 
        VALUES (?, ?, ?, 'America/Lima', 'google', 1, ?, NOW(), 1)
    ");

    $nombre_empresa = $google_user['name'] ?? 'Mi Empresa';

    $stmt->execute([
        $nombre_empresa,
        $google_user['email'],
        $google_user['id'],
        DEFAULT_PLAN_ID
    ]);

    $empresa_id = $pdo->lastInsertId();

    // Crear suscripción trial
    $stmt = $pdo->prepare("
        INSERT INTO suscripciones 
        (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado) 
        VALUES (?, ?, 'trial', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'activa')
    ");
    $stmt->execute([$empresa_id, DEFAULT_PLAN_ID, TRIAL_DAYS]);

    // Crear registros iniciales (igual que en registro)
    $stmt = $pdo->prepare("
        INSERT INTO categorias (nombre, descripcion, color, activo, empresa_id) 
        VALUES ('General', 'Categoría por defecto', '#17a2b8', 1, ?)
    ");
    $stmt->execute([$empresa_id]);

    // Asignar puerto automáticamente
    $puerto_asignado = 3001 + ($empresa_id - 1);
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_sesiones_empresa (empresa_id, estado, puerto) 
        VALUES (?, 'desconectado', ?)
    ");
    $stmt->execute([$empresa_id, $puerto_asignado]);

    $stmt = $pdo->prepare("
        INSERT INTO configuracion_bot (empresa_id, activo) 
        VALUES (?, 0)
    ");
    $stmt->execute([$empresa_id]);

    $stmt = $pdo->prepare("
        INSERT INTO configuracion_negocio (empresa_id, nombre_negocio) 
        VALUES (?, ?)
    ");
    $stmt->execute([$empresa_id, $nombre_empresa]);

    $pdo->commit();

    // Obtener empresa creada y crear sesión
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();

    crearSesion($empresa);
    header('Location: ' . url('cliente/dashboard'));
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en Google OAuth: " . $e->getMessage());
    die('Error al procesar el registro con Google');
}
