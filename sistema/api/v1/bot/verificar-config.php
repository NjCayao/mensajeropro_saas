<?php
// No iniciar sesión si ya existe una
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $empresa_id = getEmpresaActual();
    
    // Obtener configuración
    $stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
    
    if (!$config) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay configuración guardada'
        ]);
        exit;
    }
    
    // Analizar configuración
    $response = [
        'success' => true,
        'data' => [
            'system_prompt_length' => strlen($config['system_prompt'] ?? ''),
            'business_info_length' => strlen($config['business_info'] ?? ''),
            'tiene_emojis' => preg_match('/[\x{1F300}-\x{1F9FF}]/u', $config['system_prompt'] ?? '') ? true : false,
            'activo' => $config['activo'] == 1,
            'api_key_configurada' => !empty($config['openai_api_key']),
            'palabras_activacion' => count(json_decode($config['palabras_activacion'] ?? '[]', true)),
            'horario_configurado' => !empty($config['horario_inicio']) && !empty($config['horario_fin']),
            'delay_respuesta' => $config['delay_respuesta'] ?? 5,
            'responder_no_registrados' => $config['responder_no_registrados'] == 1,
            'modelo_ai' => $config['modelo_ai'] ?? 'No configurado',
            'temperatura' => $config['temperatura'] ?? 0.7,
            'tipo_bot' => $config['tipo_bot'] ?? 'No configurado',
            'modo_prueba' => $config['modo_prueba'] == 1,
            'escalamiento_configurado' => !empty($config['escalamiento_config']),
            'notificaciones_activas' => $config['notificar_escalamiento'] == 1
        ]
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error verificando configuración: ' . $e->getMessage()
    ]);
}
?>