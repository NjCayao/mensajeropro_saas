<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app_config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([getEmpresaActual()]);
    $config = $stmt->fetch();

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
            'temperatura' => $config['temperatura'] ?? 0.7
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error verificando configuración: ' . $e->getMessage()
    ]);
}
