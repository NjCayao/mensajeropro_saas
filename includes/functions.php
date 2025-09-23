<?php
declare(strict_types=1);

function sanitize(string|null $input): string {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit();
}

function logActivity($pdo, string $module, string $action, string $description = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema (usuario_id, modulo, accion, descripcion, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt->execute([$userId, $module, $action, $description, $ip]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

function jsonResponse(bool $success, string $message, mixed $data = null): never {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Función para validar teléfono (si no existe)
function validatePhone($phone) {
    // Acepta números con o sin código de país
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^\+?[1-9]\d{8,14}$/', $phone);
}

// Función para formatear teléfono (si no existe)
function formatPhone($phone) {
    // Eliminar todo excepto números
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Si no tiene código de país y tiene 9 dígitos, asumir Perú
    if (strlen($phone) == 9 && substr($phone, 0, 1) == '9') {
        $phone = '51' . $phone;
    }
    
    // Agregar + al inicio si no lo tiene
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }
    
    return $phone;
}

// Función para generar URLs con path
// function url(string $path = ''): string {
//     return APP_URL . '/' . ltrim($path, '/');
// }

// // Función para assets
// function asset(string $path = ''): string {
//     return APP_URL . '/public/assets/' . ltrim($path, '/');
// }

// Función para obtener empresa actual
function getEmpresaId(): int {
    return $_SESSION['empresa_id'] ?? 0;
}

?>