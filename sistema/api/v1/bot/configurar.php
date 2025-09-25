<?php
// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// Desactivar reporte de errores para evitar que se mezclen con el JSON
error_reporting(0);
ini_set('display_errors', 0);

// Asegurarse de que no haya salida antes del JSON
ob_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos
    $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;
    $delay_respuesta = (int)($_POST['delay_respuesta'] ?? 5);
    $horario_inicio = $_POST['horario_inicio'] ?? null;
    $horario_fin = $_POST['horario_fin'] ?? null;
    $mensaje_fuera_horario = $_POST['mensaje_fuera_horario'] ?? '';
    $responder_no_registrados = isset($_POST['responder_no_registrados']) ? (int)$_POST['responder_no_registrados'] : 0;
    $palabras_activacion = $_POST['palabras_activacion'] ?? '';
    $openai_api_key = $_POST['openai_api_key'] ?? '';
    $modelo_ai = $_POST['modelo_ai'] ?? 'gpt-3.5-turbo';
    $temperatura = (float)($_POST['temperatura'] ?? 0.7);
    $max_tokens = (int)($_POST['max_tokens'] ?? 150);
    $system_prompt = $_POST['system_prompt'] ?? '';
    $business_info = $_POST['business_info'] ?? '';

    // Convertir palabras de activación a JSON
    $palabras_array = [];
    if (!empty($palabras_activacion)) {
        $palabras_array = array_map('trim', explode(',', $palabras_activacion));
        $palabras_array = array_filter($palabras_array); // Eliminar vacíos
    }

    $empresa_id = getEmpresaActual();

    // Verificar si existe la configuración
    $stmt = $pdo->prepare("SELECT id FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $existe = $stmt->fetch();

    if ($existe) {
        // Actualizar configuración existente
        $sql = "UPDATE configuracion_bot SET
                activo = ?,
                delay_respuesta = ?,
                horario_inicio = ?,
                horario_fin = ?,
                mensaje_fuera_horario = ?,
                responder_no_registrados = ?,
                palabras_activacion = ?,
                openai_api_key = ?,
                modelo_ai = ?,
                temperatura = ?,
                max_tokens = ?,
                system_prompt = ?,
                business_info = ?,
                actualizado = NOW()
            WHERE empresa_id = ?";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $activo,
            $delay_respuesta,
            $horario_inicio,
            $horario_fin,
            $mensaje_fuera_horario,
            $responder_no_registrados,
            json_encode($palabras_array),
            $openai_api_key,
            $modelo_ai,
            $temperatura,
            $max_tokens,
            $system_prompt,
            $business_info,
            $empresa_id
        ]);
    } else {
        // Crear nueva configuración
        $sql = "INSERT INTO configuracion_bot 
                (empresa_id, activo, delay_respuesta, horario_inicio, horario_fin, 
                 mensaje_fuera_horario, responder_no_registrados, palabras_activacion, 
                 openai_api_key, modelo_ai, temperatura, max_tokens, 
                 system_prompt, business_info, actualizado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $empresa_id,
            $activo,
            $delay_respuesta,
            $horario_inicio,
            $horario_fin,
            $mensaje_fuera_horario,
            $responder_no_registrados,
            json_encode($palabras_array),
            $openai_api_key,
            $modelo_ai,
            $temperatura,
            $max_tokens,
            $system_prompt,
            $business_info
        ]);
    }

    // Limpiar cualquier salida previa
    ob_clean();

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuración guardada correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al guardar la configuración'
        ]);
    }

} catch (Exception $e) {
    ob_clean();
    error_log("Error en configurar bot: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
exit;