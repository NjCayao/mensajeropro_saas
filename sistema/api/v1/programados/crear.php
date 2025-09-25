<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

// Obtener datos
$titulo = sanitize($_POST['titulo'] ?? '');
$mensaje = sanitize($_POST['mensaje'] ?? '');
$fecha_programada = $_POST['fecha_programada'] ?? '';
$tipo_destinatarios = $_POST['tipo_destinatarios'] ?? '';
$categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;

// Validaciones
if (empty($titulo) || empty($mensaje) || empty($fecha_programada) || empty($tipo_destinatarios)) {
    jsonResponse(false, 'Todos los campos obligatorios deben ser completados');
}

// Validar fecha
$fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fecha_programada);
if (!$fecha) {
    jsonResponse(false, 'Formato de fecha inválido');
}

$ahora = new DateTime();
$ahora->modify('+3 minutes');
if ($fecha <= $ahora) {
    jsonResponse(false, 'La fecha debe ser al menos 3 minutos en el futuro');
}

// Determinar destinatarios
$enviar_a_todos = $tipo_destinatarios === 'todos' ? 1 : 0;
$total_destinatarios = 0;

try {
    if ($enviar_a_todos) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE activo = 1 AND empresa_id = ?");
        $stmt->execute([getEmpresaActual()]);
        $total_destinatarios = $stmt->fetchColumn();
    } elseif ($categoria_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id = ? AND activo = 1 AND empresa_id = ?");
        $stmt->execute([$categoria_id, getEmpresaActual()]);
        $total_destinatarios = $stmt->fetchColumn();
    }

    if ($total_destinatarios == 0) {
        jsonResponse(false, 'No hay contactos para enviar el mensaje');
    }

    // Manejar imagen si se subió
    $imagen_path = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['imagen'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        // Validar tipo
        if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
            jsonResponse(false, 'Solo se permiten imágenes JPG o PNG');
        }

        // Validar tamaño (5MB)
        if ($archivo['size'] > 5 * 1024 * 1024) {
            jsonResponse(false, 'La imagen no debe superar 5MB');
        }

        // Generar nombre único
        $imagen_path = uniqid('prog_') . '.' . $extension;
        $upload_dir = __DIR__ . '/../../../uploads/mensajes/';

        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Mover archivo
        if (!move_uploaded_file($archivo['tmp_name'], $upload_dir . $imagen_path)) {
            jsonResponse(false, 'Error al subir la imagen');
        }
    }

    // Insertar en BD
    $stmt = $pdo->prepare("
        INSERT INTO mensajes_programados 
        (usuario_id, titulo, mensaje, imagen_path, categoria_id, enviar_a_todos, 
         fecha_programada, estado, total_destinatarios, empresa_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $titulo,
        $mensaje,
        $imagen_path,
        $categoria_id,
        $enviar_a_todos,
        $fecha->format('Y-m-d H:i:s'),
        $total_destinatarios
    ]);

    // Log
    logActivity($pdo, 'programados', 'crear', "Mensaje programado: $titulo para " . $fecha->format('d/m/Y H:i'));

    jsonResponse(true, 'Mensaje programado exitosamente', ['id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log("Error al programar mensaje: " . $e->getMessage());

    // Eliminar imagen si se subió
    if ($imagen_path && file_exists($upload_dir . $imagen_path)) {
        @unlink($upload_dir . $imagen_path);
    }

    jsonResponse(false, 'Error al programar el mensaje');
}
