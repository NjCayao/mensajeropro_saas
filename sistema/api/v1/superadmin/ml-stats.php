<?php
// sistema/api/v1/superadmin/ml-stats.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

try {
    $tipo = $_GET['tipo'] ?? '';

    switch ($tipo) {
        case 'accuracy_historico':
            // Últimos 10 entrenamientos con su accuracy
            $stmt = $pdo->query("
                SELECT 
                    version_modelo,
                    accuracy,
                    DATE_FORMAT(fecha_entrenamiento, '%d/%m %H:%i') as fecha_corta
                FROM metricas_modelo
                ORDER BY version_modelo DESC
                LIMIT 10
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Invertir para que el más antiguo esté primero
            $data = array_reverse($data);
            
            echo json_encode([
                'success' => true,
                'labels' => array_column($data, 'fecha_corta'),
                'versiones' => array_column($data, 'version_modelo'),
                'accuracy' => array_map(function($val) {
                    return round($val * 100, 2);
                }, array_column($data, 'accuracy'))
            ]);
            break;

        case 'ml_vs_gpt':
            // Contar conversaciones de los últimos 7 días
            $stmt = $pdo->query("
                SELECT 
                    DATE(fecha_hora) as fecha,
                    SUM(CASE WHEN confianza_respuesta >= 0.80 THEN 1 ELSE 0 END) as ml_count,
                    SUM(CASE WHEN confianza_respuesta < 0.80 OR confianza_respuesta IS NULL THEN 1 ELSE 0 END) as gpt_count
                FROM conversaciones_bot
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(fecha_hora)
                ORDER BY fecha ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'labels' => array_map(function($row) {
                    return date('d/m', strtotime($row['fecha']));
                }, $data),
                'ml' => array_column($data, 'ml_count'),
                'gpt' => array_column($data, 'gpt_count')
            ]);
            break;

        case 'intenciones_top':
            // Top 10 intenciones más detectadas (últimos 30 días)
            $stmt = $pdo->query("
                SELECT 
                    categoria_detectada as intencion,
                    COUNT(*) as total
                FROM conversaciones_bot
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND categoria_detectada IS NOT NULL
                    AND categoria_detectada != ''
                GROUP BY categoria_detectada
                ORDER BY total DESC
                LIMIT 10
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'labels' => array_column($data, 'intencion'),
                'totales' => array_column($data, 'total')
            ]);
            break;

        case 'metricas_resumen':
            // Resumen general
            $stmt = $pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM training_samples WHERE estado = 'pendiente') as ejemplos_pendientes,
                    (SELECT COUNT(*) FROM training_samples WHERE usado_entrenamiento = 1) as ejemplos_usados,
                    (SELECT COUNT(*) FROM conversaciones_bot WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as conversaciones_7d,
                    (SELECT AVG(confianza_respuesta) FROM conversaciones_bot WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as confianza_promedio
            ");
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Tipo no especificado']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}