<?php
// cron/procesar_cola.php
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Procesando cola de mensajes...\n";

try {
    // Obtener mensajes pendientes de TODAS las empresas
    $stmt = $pdo->query("
        SELECT cm.*, c.numero, c.nombre, e.nombre_empresa,
               ws.estado as whatsapp_estado
        FROM cola_mensajes cm
        INNER JOIN contactos c ON cm.contacto_id = c.id
        INNER JOIN empresas e ON cm.empresa_id = e.id
        LEFT JOIN whatsapp_sesiones_empresa ws ON ws.empresa_id = cm.empresa_id
        WHERE cm.estado = 'pendiente'
        AND cm.intentos < 3
        AND e.activo = 1
        ORDER BY cm.fecha_creacion ASC
        LIMIT 10
    ");
    
    $mensajes = $stmt->fetchAll();
    echo "Mensajes en cola: " . count($mensajes) . "\n";
    
    foreach ($mensajes as $mensaje) {
        echo "\n[{$mensaje['nombre_empresa']}] Enviando a {$mensaje['nombre']} ({$mensaje['numero']})...\n";
        
        // Verificar que WhatsApp esté conectado para esta empresa
        if ($mensaje['whatsapp_estado'] !== 'conectado') {
            echo "WhatsApp no conectado para esta empresa, saltando...\n";
            continue;
        }
        
        try {
            // Aquí iría la lógica para enviar el mensaje
            // Por ahora solo simulamos el envío
            
            // TODO: Integrar con la API de WhatsApp
            // $resultado = enviarWhatsApp($mensaje['numero'], $mensaje['mensaje'], $mensaje['imagen_path']);
            
            // Simulación temporal
            $exito = (rand(1, 10) > 2); // 80% de éxito
            
            if ($exito) {
                // Marcar como enviado
                $stmt = $pdo->prepare("
                    UPDATE cola_mensajes 
                    SET estado = 'enviado', fecha_envio = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$mensaje['id']]);
                
                // Actualizar historial
                $stmt = $pdo->prepare("
                    UPDATE historial_mensajes 
                    SET estado = 'enviado', fecha_envio = NOW() 
                    WHERE contacto_id = ? AND mensaje = ? AND estado = 'programado'
                    ORDER BY fecha_creacion DESC LIMIT 1
                ");
                $stmt->execute([$mensaje['contacto_id'], $mensaje['mensaje']]);
                
                echo "✓ Enviado exitosamente\n";
                
                // Delay aleatorio entre mensajes (3-8 segundos)
                sleep(rand(3, 8));
                
            } else {
                // Incrementar intentos
                $stmt = $pdo->prepare("
                    UPDATE cola_mensajes 
                    SET intentos = intentos + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$mensaje['id']]);
                
                echo "✗ Error en el envío, reintentando más tarde\n";
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            
            // Marcar como error después de 3 intentos
            $stmt = $pdo->prepare("
                UPDATE cola_mensajes 
                SET estado = CASE 
                    WHEN intentos >= 2 THEN 'error' 
                    ELSE estado 
                END,
                intentos = intentos + 1
                WHERE id = ?
            ");
            $stmt->execute([$mensaje['id']]);
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Proceso completado.\n";
    
} catch (Exception $e) {
    echo "ERROR GENERAL: " . $e->getMessage() . "\n";
    error_log("Error en procesar_cola: " . $e->getMessage());
}