<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== VERIFICANDO SUSCRIPCIONES VENCIDAS ===\n";
echo "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Buscar empresas con suscripción vencida pero WhatsApp activo
    $stmt = $pdo->query("
        SELECT 
            e.id as empresa_id,
            e.nombre_empresa,
            s.fecha_fin,
            s.estado as suscripcion_estado,
            w.estado as whatsapp_estado,
            w.puerto
        FROM empresas e
        LEFT JOIN suscripciones s ON e.id = s.empresa_id AND s.estado = 'activa'
        LEFT JOIN whatsapp_sesiones_empresa w ON e.id = w.empresa_id
        WHERE e.es_superadmin = 0
        AND w.estado IN ('conectado', 'qr_pendiente', 'iniciando')
        AND (
            s.id IS NULL 
            OR (s.fecha_fin < NOW() AND s.estado = 'activa')
        )
    ");
    
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($empresas) === 0) {
        echo "✅ No hay suscripciones vencidas con WhatsApp activo\n";
        exit(0);
    }
    
    echo "⚠️ Encontradas " . count($empresas) . " empresa(s) con suscripción vencida\n\n";
    
    foreach ($empresas as $empresa) {
        echo "--- Empresa: {$empresa['nombre_empresa']} (ID: {$empresa['empresa_id']}) ---\n";
        echo "Fecha vencimiento: " . ($empresa['fecha_fin'] ?? 'Sin suscripción') . "\n";
        echo "Estado WhatsApp: {$empresa['whatsapp_estado']}\n";
        echo "Puerto: {$empresa['puerto']}\n";
        
        // 1. Marcar suscripción como vencida (si existe)
        if ($empresa['fecha_fin']) {
            $stmt = $pdo->prepare("UPDATE suscripciones SET estado = 'vencida' WHERE empresa_id = ? AND estado = 'activa'");
            $stmt->execute([$empresa['empresa_id']]);
            echo "✅ Suscripción marcada como 'vencida'\n";
        }
        
        // 2. Intentar cerrar sesión de WhatsApp vía API
        $whatsappUrl = IS_LOCALHOST 
            ? "http://localhost:{$empresa['puerto']}/api/disconnect" 
            : rtrim(APP_URL, '/') . ":{$empresa['puerto']}/api/disconnect";
        
        echo "📡 Llamando a: $whatsappUrl\n";
        
        $ch = curl_init($whatsappUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: mensajeroPro2025',
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo "✅ Sesión cerrada vía API\n";
            $responseData = json_decode($response, true);
            if ($responseData) {
                echo "   Respuesta: " . ($responseData['message'] ?? 'OK') . "\n";
            }
        } else {
            echo "⚠️ No se pudo cerrar vía API (código: $httpCode)\n";
            if ($curlError) {
                echo "   Error cURL: $curlError\n";
            }
        }
        
        // 3. Actualizar BD de todas formas (por si el servicio ya murió)
        $stmt = $pdo->prepare("
            UPDATE whatsapp_sesiones_empresa 
            SET estado = 'desconectado', 
                numero_conectado = NULL,
                qr_code = NULL,
                ultima_actualizacion = NOW()
            WHERE empresa_id = ?
        ");
        $stmt->execute([$empresa['empresa_id']]);
        echo "✅ BD actualizada a 'desconectado'\n";
        
        // 4. Intentar matar el proceso del puerto (solo en localhost Windows)
        if (IS_LOCALHOST && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $puerto = $empresa['puerto'];
            echo "🔪 Intentando matar proceso en puerto $puerto...\n";
            
            // Buscar PID usando netstat
            $output = @shell_exec("netstat -ano | findstr :$puerto");
            if ($output) {
                preg_match_all('/\s+(\d+)\s*$/m', $output, $matches);
                if (!empty($matches[1])) {
                    $pids = array_unique($matches[1]);
                    foreach ($pids as $pid) {
                        if ($pid && $pid > 0) {
                            @exec("taskkill /PID $pid /F 2>&1", $killOutput);
                            echo "   ✅ Proceso PID $pid terminado\n";
                        }
                    }
                } else {
                    echo "   ℹ️ No se encontraron PIDs en el puerto\n";
                }
            } else {
                echo "   ℹ️ Puerto no está en uso\n";
            }
        } elseif (!IS_LOCALHOST) {
            echo "ℹ️ En producción, el proceso se cerrará automáticamente\n";
        }
        
        echo "\n";
    }
    
    echo "=== PROCESO COMPLETADO ===\n";
    echo "Total de empresas procesadas: " . count($empresas) . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    error_log("Error en cron cerrar-sesiones-vencidas: " . $e->getMessage());
    exit(1);
}