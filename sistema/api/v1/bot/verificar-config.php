<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $empresa_id = getEmpresaActual();
    
    // Obtener configuración del bot
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
    
    // Obtener API Key GLOBAL
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_api_key'");
    $stmt->execute();
    $api_key_global = $stmt->fetchColumn();
    
    // Obtener configuración global de OpenAI
    $stmt = $pdo->prepare("SELECT clave, valor FROM configuracion_plataforma WHERE clave IN ('openai_modelo', 'openai_temperatura', 'openai_max_tokens')");
    $stmt->execute();
    $config_global = [];
    while ($row = $stmt->fetch()) {
        $config_global[$row['clave']] = $row['valor'];
    }
    
    // Obtener estado de notificaciones desde la nueva tabla
    $stmt = $pdo->prepare("SELECT notificar_escalamiento, notificar_ventas, notificar_citas FROM notificaciones_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $notif = $stmt->fetch();
    
    // Analizar configuración
    $response = [
        'success' => true,
        'data' => [
            'system_prompt_length' => strlen($config['system_prompt'] ?? ''),
            'business_info_length' => strlen($config['business_info'] ?? ''),
            'tiene_emojis' => preg_match('/[\x{1F300}-\x{1F9FF}]/u', $config['system_prompt'] ?? '') ? true : false,
            'activo' => $config['activo'] == 1,
            'api_key_configurada' => !empty($api_key_global),
            'palabras_activacion' => count(json_decode($config['palabras_activacion'] ?? '[]', true)),
            'horario_configurado' => !empty($config['horario_inicio']) && !empty($config['horario_fin']),
            'delay_respuesta' => $config['delay_respuesta'] ?? 5,
            'responder_no_registrados' => $config['responder_no_registrados'] == 1,
            'modelo_ai' => $config_global['openai_modelo'] ?? 'gpt-3.5-turbo',
            'temperatura' => $config_global['openai_temperatura'] ?? 0.7,
            'tipo_bot' => $config['tipo_bot'] ?? 'No configurado',
            'modo_prueba' => $config['modo_prueba'] == 1,
            'escalamiento_configurado' => !empty($config['escalamiento_config']),
            'notificaciones_activas' => ($notif && ($notif['notificar_escalamiento'] || $notif['notificar_ventas'] || $notif['notificar_citas']))
        ]
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error verificando configuración: ' . $e->getMessage()
    ]);
}