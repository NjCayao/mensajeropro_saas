<?php
// cron/procesar_cola.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Procesando cola de mensajes...\n";

try {
    // Obtener mensajes pendientes
    $stmt = $pdo->query("
        SELECT cm.*, c.numero, c.nombre, e.nombre_empresa,
               ws.estado as whatsapp_estado, ws.puerto
        FROM cola_mensajes cm
        INNER JOIN contactos c ON cm.contacto_id = c.id
        INNER JOIN empresas e ON cm.empresa_id = e.id
        LEFT JOIN whatsapp_sesiones_empresa ws ON ws.empresa_id = cm.empresa_id
        WHERE cm.estado = 'pendiente'
        AND cm.intentos < 3
        AND e.activo = 1
        ORDER BY cm.prioridad DESC, cm.fecha_creacion ASC
        LIMIT 10
    ");
    
    $mensajes = $stmt->fetchAll();
    echo "Mensajes en cola: " . count($mensajes) . "\n";
    
    foreach ($mensajes as $mensaje) {
        echo "\n[{$mensaje['nombre_empresa']}] Enviando a {$mensaje['nombre']} ({$mensaje['numero']})...\n";
        
        // Verificar que WhatsApp esté conectado
        if ($mensaje['whatsapp_estado'] !== 'conectado') {
            echo "WhatsApp no conectado para esta empresa, saltando...\n";
            
            // Marcar como error si ya se intentó 2 veces
            if ($mensaje['intentos'] >= 2) {
                $stmt = $pdo->prepare("
                    UPDATE cola_mensajes 
                    SET estado = 'error', 
                        error_mensaje = 'WhatsApp no conectado'
                    WHERE id = ?
                ");
                $stmt->execute([$mensaje['id']]);
            }
            continue;
        }
        
        try {
            // Construir payload
            $payload = [
                'empresa_id' => $mensaje['empresa_id'],
                'numero' => $mensaje['numero'],
                'mensaje' => $mensaje['mensaje']
            ];
            
            // Si hay imagen, agregarla
            if (!empty($mensaje['imagen_path'])) {
                $imagen_completa = BASE_PATH . '/' . $mensaje['imagen_path'];
                if (file_exists($imagen_completa)) {
                    $payload['imagen'] = $imagen_completa;
                }
            }
            
            // Llamar a la API de WhatsApp
            $resultado = enviarWhatsAppAPI($payload);
            
            if ($resultado['success']) {
                // Marcar como enviado
                $stmt = $pdo->prepare("
                    UPDATE cola_mensajes 
                    SET estado = 'enviado', fecha_envio = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$mensaje['id']]);
                
                // Actualizar historial
                $stmt = $pdo->prepare("
                    INSERT INTO historial_mensajes 
                    (empresa_id, contacto_id, mensaje, tipo, estado, fecha)
                    VALUES (?, ?, ?, 'saliente', 'enviado', NOW())
                ");
                $stmt->execute([
                    $mensaje['empresa_id'],
                    $mensaje['contacto_id'],
                    $mensaje['mensaje']
                ]);
                
                echo "Enviado exitosamente\n";
                
                // Delay entre mensajes (3-8 segundos)
                sleep(rand(3, 8));
                
            } else {
                throw new Exception($resultado['error'] ?? 'Error desconocido');
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            
            // Incrementar intentos
            $stmt = $pdo->prepare("
                UPDATE cola_mensajes 
                SET estado = CASE 
                    WHEN intentos >= 2 THEN 'error' 
                    ELSE estado 
                END,
                intentos = intentos + 1,
                error_mensaje = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $mensaje['id']]);
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Proceso completado.\n";
    
} catch (Exception $e) {
    echo "ERROR GENERAL: " . $e->getMessage() . "\n";
    error_log("Error en procesar_cola: " . $e->getMessage());
}

/**
 * Enviar mensaje por WhatsApp via API Node.js
 */
function enviarWhatsAppAPI(array $payload): array
{
    $url = WHATSAPP_API_URL . '/api/send-message';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: mensajeroPro2025',
        'X-Empresa-ID: ' . $payload['empresa_id']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $http_code: " . ($response ?: 'Sin respuesta')
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        return [
            'success' => false,
            'error' => 'Respuesta JSON inválida'
        ];
    }
    
    return $result;
}