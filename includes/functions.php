<?php
declare(strict_types=1);

/**
 * Sanitizar entrada de datos
 */
function sanitize(string|null $input): string {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar si hay sesión activa
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) || isset($_SESSION['empresa_id']);
}

/**
 * Redirección simple
 */
function redirect(string $url): void {
    header("Location: $url");
    exit();
}

/**
 * Respuesta JSON estándar
 */
function jsonResponse(bool $success, string $message, mixed $data = null): never {
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
function validatePhone(string $phone): bool {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^\+?[1-9]\d{8,14}$/', $phone);
}

/**
 * Formatear teléfono (agregar código de país Perú si es necesario)
 */
function formatPhone(string $phone): string {
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
function getEmpresaId(): int {
    return $_SESSION['empresa_id'] ?? 0;
}

/**
 * Log de actividad del sistema (versión multi-tenant)
 */
function logActivity($pdo, string $modulo, string $accion, ?string $descripcion = null): bool {
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
    $asunto = APP_NAME . ' - Verifica tu cuenta';
    
    $mensaje = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); 
                      color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; }
            .code { font-size: 36px; font-weight: bold; color: #25D366; 
                    text-align: center; padding: 20px; background: #f5f5f5; 
                    margin: 30px 0; letter-spacing: 8px; border-radius: 8px; }
            .footer { text-align: center; color: #999; font-size: 12px; 
                      margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
            .btn { display: inline-block; padding: 12px 30px; background: #25D366; 
                   color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0;'>🚀 " . APP_NAME . "</h1>
            </div>
            <div class='content'>
                <h2 style='color: #333;'>¡Hola, {$nombre_empresa}!</h2>
                <p style='color: #666; font-size: 16px;'>
                    Gracias por registrarte en " . APP_NAME . ". 
                    Para activar tu cuenta, usa el siguiente código de verificación:
                </p>
                <div class='code'>{$codigo}</div>
                <p style='color: #666;'>
                    Este código expirará en 24 horas. 
                    Si no solicitaste este registro, puedes ignorar este email.
                </p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . APP_NAME . ". Todos los derechos reservados.</p>
                <p style='color: #ccc; font-size: 11px;'>Este es un email automático, por favor no responder.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // En desarrollo, solo loguear (no enviar email real)
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log("=== EMAIL DE VERIFICACIÓN ===");
        error_log("Para: {$email}");
        error_log("Empresa: {$nombre_empresa}");
        error_log("Código: {$codigo}");
        error_log("============================");
        return true;
    }
    
    // En producción, enviar email real
    return mail($email, $asunto, $mensaje, $headers);
}

/**
 * Validar CSRF Token
 */
function validarCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar token aleatorio
 */
function generarToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Formatear fecha en español
 */
function formatearFecha(string $fecha, string $formato = 'd/m/Y H:i'): string {
    $timestamp = strtotime($fecha);
    return date($formato, $timestamp);
}

/**
 * Formatear moneda
 */
function formatearMoneda(float $monto, string $moneda = 'S/'): string {
    return $moneda . ' ' . number_format($monto, 2, '.', ',');
}

/**
 * Validar email (no permitir temporales)
 */
function validarEmail(string $email): array {
    // Dominios temporales bloqueados
    $dominiosTemporales = [
        '10minutemail.com', 'tempmail.com', 'guerrillamail.com',
        'mailinator.com', 'throwawaymail.com', 'yopmail.com',
        'temp-mail.org', 'fakeinbox.com', 'trashmail.com'
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
function subirArchivo(array $file, string $destino, array $extensionesPermitidas, int $pesoMaximo): array {
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