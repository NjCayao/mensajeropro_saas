<?php
/**
 * Limpieza automática de ML Engine
 * Ejecutar diariamente con cron:
 * 0 3 * * * php /ruta/sistema/cron/ml-cleanup-auto.php
 */

require_once __DIR__ . '/../../config/database.php';

echo "🧹 Iniciando limpieza automática ML Engine...\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Función para obtener días de retención
function getDiasRetencion($tipo, $pdo) {
    $defaults = [
        'conversaciones' => 3,
        'ejemplos_descartados' => 7,
        'ejemplos_usados' => 30,
        'logs_entrenamiento' => 90
    ];
    
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute(["ml_retencion_{$tipo}"]);
    $result = $stmt->fetch();
    
    return $result ? (int)$result['valor'] : $defaults[$tipo];
}

try {
    $resultado = [
        'conversaciones' => 0,
        'ejemplos_descartados' => 0,
        'ejemplos_usados' => 0,
        'logs_entrenamiento' => 0,
        'metricas_antiguas' => 0
    ];
    
    // 1. Limpiar conversaciones antiguas
    $diasConv = getDiasRetencion('conversaciones', $pdo);
    $stmt = $pdo->prepare("
        DELETE FROM conversaciones_bot 
        WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$diasConv]);
    $resultado['conversaciones'] = $stmt->rowCount();
    echo "✓ Conversaciones eliminadas (>{$diasConv} días): {$resultado['conversaciones']}\n";
    
    // 2. Limpiar ejemplos descartados
    $diasDesc = getDiasRetencion('ejemplos_descartados', $pdo);
    $stmt = $pdo->prepare("
        DELETE FROM training_samples 
        WHERE estado = 'descartado' 
          AND fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$diasDesc]);
    $resultado['ejemplos_descartados'] = $stmt->rowCount();
    echo "✓ Ejemplos descartados eliminados (>{$diasDesc} días): {$resultado['ejemplos_descartados']}\n";
    
    // 3. Limpiar ejemplos ya usados
    $diasUsados = getDiasRetencion('ejemplos_usados', $pdo);
    $stmt = $pdo->prepare("
        DELETE FROM training_samples 
        WHERE usado_entrenamiento = 1 
          AND estado = 'confirmado'
          AND fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$diasUsados]);
    $resultado['ejemplos_usados'] = $stmt->rowCount();
    echo "✓ Ejemplos usados eliminados (>{$diasUsados} días): {$resultado['ejemplos_usados']}\n";
    
    // 4. Limpiar logs antiguos
    $diasLogs = getDiasRetencion('logs_entrenamiento', $pdo);
    $stmt = $pdo->prepare("
        DELETE FROM log_entrenamientos 
        WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$diasLogs]);
    $resultado['logs_entrenamiento'] = $stmt->rowCount();
    echo "✓ Logs eliminados (>{$diasLogs} días): {$resultado['logs_entrenamiento']}\n";
    
    // 5. Mantener solo últimas 10 versiones
    $stmt = $pdo->query("
        DELETE FROM metricas_modelo 
        WHERE version_modelo NOT IN (
            SELECT version_modelo FROM (
                SELECT version_modelo 
                FROM metricas_modelo 
                ORDER BY version_modelo DESC 
                LIMIT 10
            ) tmp
        )
    ");
    $resultado['metricas_antiguas'] = $stmt->rowCount();
    echo "✓ Métricas antiguas eliminadas: {$resultado['metricas_antiguas']}\n";
    
    // Registrar en logs
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (empresa_id, modulo, accion, descripcion, ip_address)
        VALUES (NULL, 'ml_cleanup', 'limpieza_cron', ?, 'cron')
    ");
    $stmt->execute([json_encode($resultado)]);
    
    $total = array_sum($resultado);
    echo "\n✅ Limpieza completada: {$total} registros eliminados\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}