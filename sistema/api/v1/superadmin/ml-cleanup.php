<?php
// sistema/api/v1/superadmin/ml-cleanup.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Obtener configuración de retención
function getDiasRetencion($tipo) {
    global $pdo;
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
    switch ($accion) {
        case 'ejecutar':
            // Ejecutar limpieza completa
            $resultado = [
                'conversaciones' => 0,
                'ejemplos_descartados' => 0,
                'ejemplos_usados' => 0,
                'logs_entrenamiento' => 0
            ];
            
            // 1. Limpiar conversaciones antiguas (3 días por defecto)
            $diasConv = getDiasRetencion('conversaciones');
            $stmt = $pdo->prepare("
                DELETE FROM conversaciones_bot 
                WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasConv]);
            $resultado['conversaciones'] = $stmt->rowCount();
            
            // 2. Limpiar ejemplos descartados (7 días)
            $diasDesc = getDiasRetencion('ejemplos_descartados');
            $stmt = $pdo->prepare("
                DELETE FROM training_samples 
                WHERE estado = 'descartado' 
                  AND fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasDesc]);
            $resultado['ejemplos_descartados'] = $stmt->rowCount();
            
            // 3. Limpiar ejemplos ya usados en entrenamiento (30 días)
            $diasUsados = getDiasRetencion('ejemplos_usados');
            $stmt = $pdo->prepare("
                DELETE FROM training_samples 
                WHERE usado_entrenamiento = 1 
                  AND estado = 'confirmado'
                  AND fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasUsados]);
            $resultado['ejemplos_usados'] = $stmt->rowCount();
            
            // 4. Limpiar logs antiguos de entrenamiento (90 días)
            $diasLogs = getDiasRetencion('logs_entrenamiento');
            $stmt = $pdo->prepare("
                DELETE FROM log_entrenamientos 
                WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasLogs]);
            $resultado['logs_entrenamiento'] = $stmt->rowCount();
            
            // 5. Mantener solo últimas 10 versiones de métricas
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
            
            // Registrar limpieza
            $stmt = $pdo->prepare("
                INSERT INTO logs_sistema 
                (empresa_id, modulo, accion, descripcion, ip_address)
                VALUES (NULL, 'ml_cleanup', 'limpieza_automatica', ?, ?)
            ");
            $stmt->execute([
                json_encode($resultado),
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Limpieza completada',
                'resultado' => $resultado,
                'total_eliminados' => array_sum($resultado)
            ]);
            break;

        case 'preview':
            // Vista previa de qué se eliminará
            $preview = [];
            
            $diasConv = getDiasRetencion('conversaciones');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM conversaciones_bot 
                WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasConv]);
            $preview['conversaciones'] = $stmt->fetch()['total'];
            
            $diasDesc = getDiasRetencion('ejemplos_descartados');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM training_samples 
                WHERE estado = 'descartado' 
                  AND fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasDesc]);
            $preview['ejemplos_descartados'] = $stmt->fetch()['total'];
            
            $diasUsados = getDiasRetencion('ejemplos_usados');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM training_samples 
                WHERE usado_entrenamiento = 1 
                  AND estado = 'confirmado'
                  AND fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasUsados]);
            $preview['ejemplos_usados'] = $stmt->fetch()['total'];
            
            $diasLogs = getDiasRetencion('logs_entrenamiento');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM log_entrenamientos 
                WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasLogs]);
            $preview['logs_entrenamiento'] = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("
                SELECT COUNT(*) as total 
                FROM metricas_modelo 
                WHERE version_modelo NOT IN (
                    SELECT version_modelo FROM (
                        SELECT version_modelo 
                        FROM metricas_modelo 
                        ORDER BY version_modelo DESC 
                        LIMIT 10
                    ) tmp
                )
            ");
            $preview['metricas_antiguas'] = $stmt->fetch()['total'];
            
            echo json_encode([
                'success' => true,
                'preview' => $preview,
                'total' => array_sum($preview),
                'config' => [
                    'conversaciones' => $diasConv,
                    'ejemplos_descartados' => $diasDesc,
                    'ejemplos_usados' => $diasUsados,
                    'logs_entrenamiento' => $diasLogs
                ]
            ]);
            break;

        case 'guardar_config':
            // Guardar configuración de retención
            $configs = [
                'ml_retencion_conversaciones' => $_POST['dias_conversaciones'] ?? 3,
                'ml_retencion_ejemplos_descartados' => $_POST['dias_descartados'] ?? 7,
                'ml_retencion_ejemplos_usados' => $_POST['dias_usados'] ?? 30,
                'ml_retencion_logs_entrenamiento' => $_POST['dias_logs'] ?? 90
            ];
            
            foreach ($configs as $clave => $valor) {
                $stmt = $pdo->prepare("
                    INSERT INTO configuracion_plataforma (clave, valor, tipo)
                    VALUES (?, ?, 'numero')
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                ");
                $stmt->execute([$clave, $valor]);
            }
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Configuración guardada'
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}