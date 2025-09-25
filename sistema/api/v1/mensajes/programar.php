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

date_default_timezone_set('America/Lima');

// Este archivo maneja la programación desde el módulo de mensajes
// cuando el usuario marca "Programar envío"

$tipo_envio = $_POST['tipo_envio'] ?? '';
$contacto_id = !empty($_POST['contacto_id']) ? intval($_POST['contacto_id']) : null;
$categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
$mensaje = sanitize($_POST['mensaje'] ?? '');
$fecha_envio = $_POST['fecha_envio'] ?? '';
$tipo_mensaje = $_POST['tipo_mensaje'] ?? 'texto';
$titulo = sanitize($_POST['titulo'] ?? 'Mensaje programado');

// Validaciones
if (empty($mensaje) || empty($fecha_envio) || empty($tipo_envio)) {
    jsonResponse(false, 'Faltan datos requeridos');
}

// Validar fecha
$fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fecha_envio);
if (!$fecha) {
    jsonResponse(false, 'Formato de fecha inválido');
}

$ahora = new DateTime();
$ahora->modify('+3 minutes');
if ($fecha <= $ahora) {
    jsonResponse(false, 'La fecha debe ser al menos 3 minutos en el futuro');
}

try {
    // Manejar archivo si se envió
    $imagen_path = null;
    if ($tipo_mensaje !== 'texto' && isset($_FILES['archivo'])) {
        $archivo = $_FILES['archivo'];
        if ($archivo['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            // Validar tipo según el tipo de mensaje
            if ($tipo_mensaje === 'imagen') {
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    jsonResponse(false, 'Formato de imagen no válido');
                }
            } else {
                if (!in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
                    jsonResponse(false, 'Formato de documento no válido');
                }
            }

            // Generar nombre único
            $imagen_path = uniqid('prog_') . '.' . $extension;

            // Usar ruta dinámica
            $upload_dir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'mensajes' . DIRECTORY_SEPARATOR;

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (!move_uploaded_file($archivo['tmp_name'], $upload_dir . $imagen_path)) {
                jsonResponse(false, 'Error al subir el archivo');
            }
        }
    }

    // Determinar título y destinatarios
    $enviar_a_todos = 0;
    $total_destinatarios = 0;

    // Para envío individual
    if ($tipo_envio === 'individual') {
        if (!$contacto_id) {
            jsonResponse(false, 'Debe seleccionar un contacto');
        }

        // Obtener datos del contacto
        $stmt = $pdo->prepare("SELECT nombre FROM contactos WHERE id = ? AND activo = 1 AND empresa_id = ?");
        $stmt->execute([$contacto_id, getEmpresaActual()]);
        $contacto = $stmt->fetch();

        if (!$contacto) {
            jsonResponse(false, 'Contacto no encontrado');
        }

        $titulo = "Mensaje programado para " . $contacto['nombre'];
        $total_destinatarios = 1;

        // Para individual, guardamos el contacto_id temporalmente en categoria_id
        // y usamos un flag especial
        $categoria_id = -$contacto_id; // Negativo indica que es individual

    } elseif ($tipo_envio === 'todos') {
        // Para envío a todos
        $enviar_a_todos = 1;
        $titulo = "Mensaje programado para TODOS";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE activo = 1 AND empresa_id = ?");
        $stmt->execute([getEmpresaActual()]);
        $total_destinatarios = $stmt->fetchColumn();
    } elseif ($tipo_envio === 'categoria') {
        // Para envío por categoría
        if (!$categoria_id) {
            jsonResponse(false, 'Debe seleccionar una categoría');
        }

        // Obtener nombre de categoría
        $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$categoria_id, getEmpresaActual()]);
        $cat = $stmt->fetch();

        if ($cat) {
            $titulo = "Mensaje programado para categoría " . $cat['nombre'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id = ? AND activo = 1 AND empresa_id = ?");
        $stmt->execute([$categoria_id, getEmpresaActual()]);
        $total_destinatarios = $stmt->fetchColumn();
    }

    if ($total_destinatarios == 0) {
        jsonResponse(false, 'No hay contactos activos para enviar');
    }

    // Crear entrada en mensajes_programados
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
        ($categoria_id < 0) ? null : $categoria_id, // Si es negativo (individual), guardar NULL
        $enviar_a_todos,
        $fecha->format('Y-m-d H:i:s'),
        $total_destinatarios,
        getEmpresaActual()
    ]);

    $mensaje_id = $pdo->lastInsertId();

    // Si es envío individual, crear registro especial en una tabla temporal
    if ($tipo_envio === 'individual' && $contacto_id) {
        // Crear tabla temporal si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mensajes_programados_individuales (
                mensaje_programado_id INT,
                contacto_id INT,
                PRIMARY KEY (mensaje_programado_id),
                FOREIGN KEY (mensaje_programado_id) REFERENCES mensajes_programados(id) ON DELETE CASCADE,
                FOREIGN KEY (contacto_id) REFERENCES contactos(id)
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO mensajes_programados_individuales (mensaje_programado_id, contacto_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$mensaje_id, $contacto_id]);
    }

    // Log
    logActivity(
        $pdo,
        'mensajes',
        'programar',
        "Mensaje programado para " . $fecha->format('d/m/Y H:i') . " - $total_destinatarios destinatarios"
    );

    jsonResponse(
        true,
        "Mensaje programado exitosamente para el " . $fecha->format('d/m/Y') . " a las " . $fecha->format('H:i'),
        [
            'mensaje_id' => $mensaje_id,
            'total_destinatarios' => $total_destinatarios,
            'fecha_envio' => $fecha->format('d/m/Y H:i')
        ]
    );
} catch (Exception $e) {
    error_log("Error al programar mensaje: " . $e->getMessage());

    // Eliminar archivo si se subió y hubo error
    if ($imagen_path && isset($upload_dir) && file_exists($upload_dir . $imagen_path)) {
        @unlink($upload_dir . $imagen_path);
    }

    jsonResponse(false, 'Error al programar el mensaje: ' . $e->getMessage());
}
