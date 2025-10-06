<?php

declare(strict_types=1);

/**
 * Sanitizar entrada de datos
 */
function sanitize(string|null $input): string
{
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar si hay sesión activa
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) || isset($_SESSION['empresa_id']);
}

/**
 * Redirección simple
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit();
}

/**
 * Respuesta JSON estándar
 */
function jsonResponse(bool $success, string $message, mixed $data = null): never
{
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Validar teléfono
 */
function validatePhone(string $phone): bool
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return (bool) preg_match('/^\+?[1-9]\d{8,14}$/', $phone);
}

/**
 * Formatear teléfono (agregar código de país Perú si es necesario)
 */
function formatPhone(string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Si tiene 9 dígitos y empieza con 9, agregar código de Perú
    if (strlen($phone) == 9 && substr($phone, 0, 1) == '9') {
        $phone = '51' . $phone;
    }

    // Agregar + al inicio
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }

    return $phone;
}

/**
 * Obtener ID de empresa actual (multi-tenant)
 */
function getEmpresaId(): int
{
    return $_SESSION['empresa_id'] ?? 0;
}

/**
 * Log de actividad del sistema (versión multi-tenant)
 */
function logActivity($pdo, string $modulo, string $accion, ?string $descripcion = null): bool
{
    // Si no hay empresa en sesión, no registrar
    if (!isset($_SESSION['empresa_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema 
            (empresa_id, usuario_id, modulo, accion, descripcion, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['empresa_id'],
            $_SESSION['user_id'] ?? null,
            $modulo,
            $accion,
            $descripcion,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Error al guardar log: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar email de verificación
 */
function enviarEmailVerificacion(string $email, string $nombre_empresa, string $codigo): bool {
    return enviarEmailPlantilla('verificacion_email', $email, [
        'nombre_empresa' => $nombre_empresa,
        'codigo_verificacion' => $codigo
    ]);
}

/**
 * Validar CSRF Token
 */
function validarCSRF(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar token aleatorio
 */
function generarToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * Formatear fecha en español
 */
function formatearFecha(string $fecha, string $formato = 'd/m/Y H:i'): string
{
    $timestamp = strtotime($fecha);
    return date($formato, $timestamp);
}

/**
 * Formatear moneda
 */
function formatearMoneda(float $monto, string $moneda = 'S/'): string
{
    return $moneda . ' ' . number_format($monto, 2, '.', ',');
}

/**
 * Validar email (no permitir temporales)
 */
function validarEmail(string $email): array
{
    // Dominios temporales bloqueados
    $dominiosTemporales = [
        '10minutemail.com',
        'tempmail.com',
        'guerrillamail.com',
        'mailinator.com',
        'throwawaymail.com',
        'yopmail.com',
        'temp-mail.org',
        'fakeinbox.com',
        'trashmail.com'
    ];

    // Validar formato
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valido' => false, 'mensaje' => 'Formato de email inválido'];
    }

    // Extraer dominio
    $dominio = strtolower(substr(strrchr($email, "@"), 1));

    // Verificar si es temporal
    if (in_array($dominio, $dominiosTemporales)) {
        return ['valido' => false, 'mensaje' => 'No se permiten emails temporales'];
    }

    return ['valido' => true, 'mensaje' => 'Email válido'];
}

/**
 * Subir archivo con validaciones
 */
function subirArchivo(array $file, string $destino, array $extensionesPermitidas, int $pesoMaximo): array
{
    // Verificar errores
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['exito' => false, 'mensaje' => 'Error al subir archivo'];
    }

    // Verificar peso
    if ($file['size'] > $pesoMaximo) {
        $pesoMB = $pesoMaximo / (1024 * 1024);
        return ['exito' => false, 'mensaje' => "El archivo supera el tamaño máximo de {$pesoMB}MB"];
    }

    // Verificar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensionesPermitidas)) {
        return ['exito' => false, 'mensaje' => 'Tipo de archivo no permitido'];
    }

    // Generar nombre único
    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
    $rutaCompleta = $destino . '/' . $nombreArchivo;

    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
        return ['exito' => true, 'archivo' => $nombreArchivo, 'ruta' => $rutaCompleta];
    }

    return ['exito' => false, 'mensaje' => 'Error al guardar archivo'];
}

function enviarEmailRecuperacion(string $email, string $nombre_empresa, string $reset_link): bool {
    return enviarEmailPlantilla('recuperacion_password', $email, [
        'nombre_empresa' => $nombre_empresa,
        'reset_link' => $reset_link
    ]);
}

/**
 * Enviar email usando plantilla de BD
 */
function enviarEmailPlantilla(string $codigo_plantilla, string $email_destino, array $variables): bool
{
    require_once __DIR__ . '/email-sender.php';
    $emailSender = new EmailSender();
    return $emailSender->enviarDesdePlantilla($codigo_plantilla, $email_destino, $variables);
}

/**
 * Enviar email simple sin plantilla
 */
function enviarEmailSimple(string $email_destino, string $asunto, string $mensaje_html): bool
{
    require_once __DIR__ . '/email-sender.php';
    $emailSender = new EmailSender();
    return $emailSender->enviarSimple($email_destino, $asunto, $mensaje_html);
}


/**
 * Enviar mensaje de WhatsApp via API
 */
function enviarWhatsApp(int $empresa_id, string $numero, string $mensaje, ?string $imagen_path = null): array
{
    $url = WHATSAPP_API_URL . '/api/send-message';
    
    $payload = [
        'empresa_id' => $empresa_id,
        'numero' => $numero,
        'mensaje' => $mensaje
    ];
    
    // Agregar imagen si existe
    if ($imagen_path && file_exists($imagen_path)) {
        $payload['imagen'] = $imagen_path;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: mensajeroPro2025',
        'X-Empresa-ID: ' . $empresa_id
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $http_code: " . ($response ?: 'Sin respuesta')
        ];
    }
    
    $result = json_decode($response, true);
    
    return $result ?: [
        'success' => false,
        'error' => 'Respuesta JSON inválida'
    ];
}