<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    $mensaje = $_POST['mensaje'] ?? '';

    if (empty($mensaje)) {
        Response::error('El mensaje está vacío');
    }

    // Obtener configuración del bot
    $stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([getEmpresaActual()]);
    $config = $stmt->fetch();

    if (!$config) {
        Response::error('No se encontró la configuración del bot. Por favor, guarda la configuración primero.');
    }

    // Obtener API Key GLOBAL
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_api_key'");
    $stmt->execute();
    $api_key_global = $stmt->fetchColumn();

    if (empty($api_key_global)) {
        Response::error('El administrador no ha configurado la API Key de OpenAI en la plataforma. Contacta a soporte.');
    }

    // Obtener configuración global de OpenAI
    $stmt = $pdo->prepare("SELECT clave, valor FROM configuracion_plataforma WHERE clave IN ('openai_modelo', 'openai_temperatura', 'openai_max_tokens')");
    $stmt->execute();
    $config_global = [];
    while ($row = $stmt->fetch()) {
        $config_global[$row['clave']] = $row['valor'];
    }

    // Preparar el prompt del sistema
    $systemPrompt = $config['system_prompt'] ?? 'Eres un asistente virtual amigable y profesional.';

    // Agregar información del negocio si existe
    if (!empty($config['business_info'])) {
        $systemPrompt .= "\n\nINFORMACIÓN DEL NEGOCIO:\n" . $config['business_info'];
    }

    // Llamar a OpenAI
    $inicio = microtime(true);
    $respuesta = callOpenAI(
        $api_key_global,
        $config_global['openai_modelo'] ?? 'gpt-3.5-turbo',
        $systemPrompt,
        $mensaje,
        floatval($config_global['openai_temperatura'] ?? 0.7),
        intval($config_global['openai_max_tokens'] ?? 150)
    );
    $tiempoRespuesta = round((microtime(true) - $inicio) * 1000);

    if ($respuesta['success']) {
        Response::success([
            'respuesta' => $respuesta['content'],
            'tokens_usados' => $respuesta['tokens'],
            'tiempo_respuesta' => $tiempoRespuesta
        ]);
    } else {
        Response::error($respuesta['error']);
    }
} catch (Exception $e) {
    error_log("Error en test bot: " . $e->getMessage());
    Response::error('Error en el servidor: ' . $e->getMessage());
}

function callOpenAI($apiKey, $model, $systemPrompt, $userMessage, $temperature, $maxTokens)
{
    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => floatval($temperature),
        'max_tokens' => intval($maxTokens)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Error de conexión con OpenAI'];
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        $error = $result['error']['message'] ?? 'Error desconocido de OpenAI';
        return ['success' => false, 'error' => $error];
    }

    if (!isset($result['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Respuesta inválida de OpenAI'];
    }

    return [
        'success' => true,
        'content' => $result['choices'][0]['message']['content'],
        'tokens' => $result['usage']['total_tokens'] ?? 0
    ];
}