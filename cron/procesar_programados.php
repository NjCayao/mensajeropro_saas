<?php
// cron/procesar_programados.php
require_once __DIR__ . '/../config/database.php';

// Log de inicio
echo "[" . date('Y-m-d H:i:s') . "] Iniciando procesamiento de mensajes programados...\n";

try {
    // Obtener TODOS los mensajes programados pendientes de TODAS las empresas
    $stmt = $pdo->query("
        SELECT mp.*, e.activo as empresa_activa, e.nombre_empresa
        FROM mensajes_programados mp
        INNER JOIN empresas e ON mp.empresa_id = e.id
        WHERE mp.estado = 'pendiente' 
        AND mp.fecha_programada <= NOW()
        AND e.activo = 1
        ORDER BY mp.fecha_programada ASC
    ");
    
    $mensajes = $stmt->fetchAll();
    
    echo "Mensajes encontrados: " . count($mensajes) . "\n";
    
    foreach ($mensajes as $mensaje) {
        echo "\n[{$mensaje['nombre_empresa']}] Procesando mensaje ID {$mensaje['id']}: {$mensaje['titulo']}\n";
        
        try {
            // Marcar como procesando
            $stmt = $pdo->prepare("UPDATE mensajes_programados SET estado = 'procesando' WHERE id = ?");
            $stmt->execute([$mensaje['id']]);
            
            // Obtener contactos según el tipo de envío
            $contactos = [];
            
            if ($mensaje['enviar_a_todos']) {
                // Todos los contactos de la empresa
                $stmt = $pdo->prepare("
                    SELECT id, nombre, numero 
                    FROM contactos 
                    WHERE activo = 1 AND empresa_id = ?
                ");
                $stmt->execute([$mensaje['empresa_id']]);
                $contactos = $stmt->fetchAll();
                
            } elseif ($mensaje['categoria_id']) {
                // Contactos de una categoría
                $stmt = $pdo->prepare("
                    SELECT id, nombre, numero 
                    FROM contactos 
                    WHERE activo = 1 
                    AND categoria_id = ? 
                    AND empresa_id = ?
                ");
                $stmt->execute([$mensaje['categoria_id'], $mensaje['empresa_id']]);
                $contactos = $stmt->fetchAll();
                
            } else {
                // Verificar si es mensaje individual
                $stmt = $pdo->prepare("
                    SELECT c.id, c.nombre, c.numero 
                    FROM mensajes_programados_individuales mpi
                    JOIN contactos c ON mpi.contacto_id = c.id
                    WHERE mpi.mensaje_programado_id = ? 
                    AND c.empresa_id = ?
                ");
                $stmt->execute([$mensaje['id'], $mensaje['empresa_id']]);
                $contactos = $stmt->fetchAll();
            }
            
            echo "Contactos encontrados: " . count($contactos) . "\n";
            
            // Crear cola de mensajes para cada contacto
            $enviados = 0;
            $errores = 0;
            
            foreach ($contactos as $contacto) {
                try {
                    // Personalizar mensaje
                    $mensaje_personalizado = str_replace(
                        ['{{nombre}}', '{{fecha}}', '{{hora}}'],
                        [
                            $contacto['nombre'],
                            date('d/m/Y'),
                            date('H:i')
                        ],
                        $mensaje['mensaje']
                    );
                    
                    // Insertar en cola_mensajes
                    $stmt = $pdo->prepare("
                        INSERT INTO cola_mensajes 
                        (contacto_id, mensaje, imagen_path, estado, prioridad, empresa_id)
                        VALUES (?, ?, ?, 'pendiente', 1, ?)
                    ");
                    
                    $stmt->execute([
                        $contacto['id'],
                        $mensaje_personalizado,
                        $mensaje['imagen_path'],
                        $mensaje['empresa_id']
                    ]);
                    
                    // Registrar en historial
                    $stmt = $pdo->prepare("
                        INSERT INTO historial_mensajes 
                        (contacto_id, mensaje, tipo, estado, fecha, empresa_id)
                        VALUES (?, ?, 'saliente', 'programado', NOW(), ?)
                    ");
                    
                    $stmt->execute([
                        $contacto['id'],
                        $mensaje_personalizado,
                        $mensaje['empresa_id']
                    ]);
                    
                    $enviados++;
                    
                } catch (Exception $e) {
                    echo "Error al procesar contacto {$contacto['id']}: " . $e->getMessage() . "\n";
                    $errores++;
                }
            }
            
            // Actualizar estado del mensaje programado
            if ($enviados > 0) {
                $stmt = $pdo->prepare("
                    UPDATE mensajes_programados 
                    SET estado = 'completado', 
                        mensajes_enviados = ?,
                        fecha_procesado = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$enviados, $mensaje['id']]);
                
                echo "Mensaje completado: $enviados enviados, $errores errores\n";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE mensajes_programados 
                    SET estado = 'error',
                        fecha_procesado = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$mensaje['id']]);
                
                echo "Mensaje marcado como error: no se pudo enviar a ningún contacto\n";
            }
            
        } catch (Exception $e) {
            // Si hay error, marcar el mensaje como error
            $stmt = $pdo->prepare("
                UPDATE mensajes_programados 
                SET estado = 'error', 
                    notas = ?
                WHERE id = ?
            ");
            $stmt->execute([
                'Error: ' . $e->getMessage(),
                $mensaje['id']
            ]);
            
            echo "ERROR procesando mensaje: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Procesamiento completado.\n";
    
} catch (Exception $e) {
    echo "ERROR GENERAL: " . $e->getMessage() . "\n";
    error_log("Error en cron programados: " . $e->getMessage());
}