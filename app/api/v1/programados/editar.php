<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

$id = intval($_POST['id'] ?? 0);
$titulo = sanitize($_POST['titulo'] ?? '');
$mensaje = sanitize($_POST['mensaje'] ?? '');
$fecha_programada = $_POST['fecha_programada'] ?? '';

if ($id <= 0 || empty($titulo) || empty($mensaje) || empty($fecha_programada)) {
    jsonResponse(false, 'Datos incompletos');
}

try {
    // Verificar que existe y es del usuario y está pendiente
    $stmt = $pdo->prepare("
        SELECT * FROM mensajes_programados 
        WHERE id = ? AND usuario_id = ? AND estado = 'pendiente'
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $mensajeProg = $stmt->fetch();
    
    if (!$mensajeProg) {
        jsonResponse(false, 'No puedes editar este mensaje o ya fue procesado');
    }
    
    // Validar fecha
    $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fecha_programada);
    if (!$fecha) {
        jsonResponse(false, 'Formato de fecha inválido');
    }
    
    $ahora = new DateTime();
    $ahora->modify('+5 minutes');
    if ($fecha <= $ahora) {
        jsonResponse(false, 'La fecha debe ser al menos 5 minutos en el futuro');
    }
    
    // Actualizar
    $stmt = $pdo->prepare("
        UPDATE mensajes_programados 
        SET titulo = ?, mensaje = ?, fecha_programada = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $titulo,
        $mensaje,
        $fecha->format('Y-m-d H:i:s'),
        $id
    ]);
    
    logActivity($pdo, 'programados', 'editar', "Mensaje programado editado: ID $id");
    
    jsonResponse(true, 'Mensaje actualizado exitosamente');
    
} catch (Exception $e) {
    error_log("Error al editar mensaje programado: " . $e->getMessage());
    jsonResponse(false, 'Error al actualizar mensaje');
}
?>