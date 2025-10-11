<?php
// sistema/api/v1/superadmin/ml-ejemplos.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'listar_pendientes':
            // Listar ejemplos pendientes de aprobar
            $limite = $_GET['limite'] ?? 20;
            $offset = $_GET['offset'] ?? 0;
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    texto_usuario,
                    intencion_detectada,
                    confianza,
                    DATE_FORMAT(fecha, '%d/%m/%Y %H:%i') as fecha_formateada,
                    empresa_id
                FROM training_samples
                WHERE estado = 'pendiente' AND usado_entrenamiento = 0
                ORDER BY fecha DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limite, $offset]);
            $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total
            $stmtTotal = $pdo->query("
                SELECT COUNT(*) as total 
                FROM training_samples 
                WHERE estado = 'pendiente' AND usado_entrenamiento = 0
            ");
            $total = $stmtTotal->fetch()['total'];
            
            echo json_encode([
                'success' => true,
                'ejemplos' => $ejemplos,
                'total' => $total
            ]);
            break;

        case 'aprobar':
            // Aprobar ejemplo
            $id = $_POST['id'] ?? 0;
            $intencion_corregida = $_POST['intencion'] ?? '';
            
            if (!$id) {
                throw new Exception('ID no especificado');
            }
            
            $stmt = $pdo->prepare("
                UPDATE training_samples 
                SET estado = 'confirmado',
                    intencion_confirmada = ?
                WHERE id = ?
            ");
            $stmt->execute([$intencion_corregida, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Ejemplo aprobado correctamente'
            ]);
            break;

        case 'rechazar':
            // Rechazar ejemplo
            $id = $_POST['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID no especificado');
            }
            
            $stmt = $pdo->prepare("
                UPDATE training_samples 
                SET estado = 'descartado'
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Ejemplo descartado correctamente'
            ]);
            break;

        case 'aprobar_masivo':
            // Aprobar múltiples ejemplos a la vez
            $ids = $_POST['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                throw new Exception('IDs no especificados');
            }
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("
                UPDATE training_samples 
                SET estado = 'confirmado',
                    intencion_confirmada = intencion_detectada
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($ids);
            
            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' ejemplos aprobados',
                'cantidad' => count($ids)
            ]);
            break;

        case 'intenciones_disponibles':
            // Listar todas las intenciones del sistema
            $stmt = $pdo->query("
                SELECT DISTINCT clave, nombre 
                FROM intenciones_sistema 
                WHERE activa = 1
                ORDER BY nombre
            ");
            $intenciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'intenciones' => $intenciones
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}