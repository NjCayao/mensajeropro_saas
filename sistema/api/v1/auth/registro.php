<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

$nombre_empresa = trim($input['nombre_empresa'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$telefono = trim($input['telefono'] ?? '');

// Validaciones
if (empty($nombre_empresa) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios son requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

try {
    // Verificar si email existe
    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Generar código de verificación
    $codigo_verificacion = sprintf('%06d', mt_rand(0, 999999));
    $fecha_expiracion_trial = date('Y-m-d H:i:s', strtotime('+' . TRIAL_DAYS . ' days'));
    
    // Crear empresa
    $stmt = $pdo->prepare("
        INSERT INTO empresas 
        (nombre_empresa, email, password_hash, telefono, 
         metodo_registro, token_verificacion, email_verificado, 
         plan_id, fecha_registro, fecha_expiracion_trial, activo) 
        VALUES (?, ?, ?, ?, 'email', ?, 0, ?, NOW(), ?, 0)
    ");
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt->execute([
        $nombre_empresa, 
        $email, 
        $password_hash, 
        $telefono,
        $codigo_verificacion,
        DEFAULT_PLAN_ID,
        $fecha_expiracion_trial
    ]);
    
    $empresa_id = $pdo->lastInsertId();
    
    // Crear categoría General
    $stmt = $pdo->prepare("
        INSERT INTO categorias (nombre, descripcion, color, activo, empresa_id) 
        VALUES ('General', 'Categoría por defecto', '#17a2b8', 1, ?)
    ");
    $stmt->execute([$empresa_id]);
    
    // Crear sesión WhatsApp
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_sesiones_empresa (empresa_id, estado) 
        VALUES (?, 'desconectado')
    ");
    $stmt->execute([$empresa_id]);
    
    // Crear configuración del bot
    $stmt = $pdo->prepare("
        INSERT INTO configuracion_bot (empresa_id, activo) 
        VALUES (?, 0)
    ");
    $stmt->execute([$empresa_id]);
    
    // Crear configuración de negocio
    $stmt = $pdo->prepare("
        INSERT INTO configuracion_negocio (empresa_id, nombre_negocio) 
        VALUES (?, ?)
    ");
    $stmt->execute([$empresa_id, $nombre_empresa]);
    
    // Crear suscripción trial
    $stmt = $pdo->prepare("
        INSERT INTO suscripciones 
        (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado) 
        VALUES (?, ?, 'trial', NOW(), ?, 'activa')
    ");
    $stmt->execute([$empresa_id, DEFAULT_PLAN_ID, $fecha_expiracion_trial]);
    
    $pdo->commit();
    
    // Enviar email de verificación
    $email_enviado = enviarEmailVerificacion($email, $nombre_empresa, $codigo_verificacion);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registro exitoso. Revisa tu email para verificar tu cuenta.',
        'empresa_id' => $empresa_id,
        'email_enviado' => $email_enviado
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en registro API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al registrar. Intenta nuevamente.']);
}