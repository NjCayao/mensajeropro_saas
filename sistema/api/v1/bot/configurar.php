<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
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

    // Actualizar configuración
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

    $params = [
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
        getEmpresaActual()
    ];

    // Debug: log los valores
    error_log("SQL: " . $sql);
    error_log("Params: " . json_encode($params));

    $result = $stmt->execute($params);

    // Verificar si realmente se actualizó
    if ($result) {
        $rowCount = $stmt->rowCount();
        error_log("Filas actualizadas: " . $rowCount);

        // Verificar qué se guardó realmente
        $stmt = $pdo->prepare("SELECT system_prompt, business_info FROM configuracion_bot WHERE empresa_id = ?");
        $stmt->execute([getEmpresaActual()]);
        $check = $stmt->fetch();
        error_log("Valores guardados: " . json_encode($check));

        Response::success(['message' => 'Configuración guardada correctamente']);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Error SQL: " . json_encode($errorInfo));
        Response::error('Error al guardar la configuración: ' . $errorInfo[2]);
    }
} catch (Exception $e) {
    error_log("Error en configurar bot: " . $e->getMessage());
    Response::error('Error en el servidor: ' . $e->getMessage());
}
