<?php
// cron/clean-sessions.php
// Ejecutar diariamente a las 3 AM para limpiar sesiones viejas
// Crontab: 0 3 * * * php /ruta/mensajeropro/cron/clean-sessions.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando limpieza de sesiones...\n";

$carpetas_eliminadas = 0;
$puertos_liberados = 0;
$qr_limpiados = 0;

try {
    // =========================================
    // 1. LIMPIAR CARPETAS DE SESIONES VIEJAS EN tokens/
    // =========================================
    echo "\n--- Limpiando carpetas de tokens sin uso (+15 días) ---\n";
    
    // CORREGIDO: Usar la carpeta tokens/ en lugar de sessions/
    $tokens_dir = __DIR__ . '/../whatsapp-service/tokens/';
    
    if (is_dir($tokens_dir)) {
        // Buscar carpetas empresa-* dentro de tokens/
        $carpetas = glob($tokens_dir . 'empresa-*', GLOB_ONLYDIR);
        
        echo "Carpetas encontradas: " . count($carpetas) . "\n";
        
        foreach ($carpetas as $carpeta) {
            // Extraer el ID de empresa del nombre de la carpeta (empresa-123)
            $nombre_carpeta = basename($carpeta);
            preg_match('/empresa-(\d+)/', $nombre_carpeta, $matches);
            
            if (!isset($matches[1])) {
                continue;
            }
            
            $empresa_id = $matches[1];
            
            // Verificar última modificación
            $ultima_modificacion = filemtime($carpeta);
            $dias_sin_uso = round((time() - $ultima_modificacion) / (60 * 60 * 24), 1);
            
            if ($dias_sin_uso > 15) {
                echo "Carpeta sin uso por $dias_sin_uso días: $nombre_carpeta (ID: $empresa_id)\n";
                
                // Verificar si la empresa sigue activa
                $stmt = $pdo->prepare("SELECT activo FROM empresas WHERE id = ?");
                $stmt->execute([$empresa_id]);
                $empresa = $stmt->fetch();
                
                // Solo eliminar si:
                // 1. La empresa no existe, o
                // 2. La empresa está inactiva, o
                // 3. Han pasado más de 15 días sin uso
                $debe_eliminar = false;
                
                if (!$empresa) {
                    echo "  → Empresa no existe en BD\n";
                    $debe_eliminar = true;
                } elseif (!$empresa['activo']) {
                    echo "  → Empresa inactiva\n";
                    $debe_eliminar = true;
                } elseif ($dias_sin_uso > 15) {
                    echo "  → Sin uso por más de 15 días\n";
                    $debe_eliminar = true;
                }
                
                if ($debe_eliminar) {
                    if (eliminarDirectorio($carpeta)) {
                        echo "  ✓ Carpeta eliminada\n";
                        $carpetas_eliminadas++;
                        
                        // Limpiar registro en BD
                        $stmt_delete = $pdo->prepare("
                            UPDATE whatsapp_sesiones_empresa 
                            SET estado = 'desconectado',
                                session_data = NULL,
                                qr_code = NULL
                            WHERE empresa_id = ?
                        ");
                        $stmt_delete->execute([$empresa_id]);
                    } else {
                        echo "  ✗ Error eliminando carpeta\n";
                    }
                } else {
                    echo "  → Carpeta conservada (empresa activa con uso reciente)\n";
                }
            }
        }
    } else {
        echo "⚠ Carpeta tokens/ no existe: $tokens_dir\n";
    }
    
    // =========================================
    // 2. LIBERAR PUERTOS DE EMPRESAS INACTIVAS
    // =========================================
    echo "\n--- Liberando puertos de empresas inactivas ---\n";
    
    $stmt = $pdo->query("
        SELECT ws.empresa_id, ws.puerto, e.activo
        FROM whatsapp_sesiones_empresa ws
        LEFT JOIN empresas e ON ws.empresa_id = e.id
        WHERE ws.puerto IS NOT NULL
        AND (e.id IS NULL OR e.activo = 0)
    ");
    $sesiones_inactivas = $stmt->fetchAll();
    
    foreach ($sesiones_inactivas as $sesion) {
        echo "Liberando puerto {$sesion['puerto']} de empresa {$sesion['empresa_id']}\n";
        
        $stmt_update = $pdo->prepare("
            UPDATE whatsapp_sesiones_empresa 
            SET puerto = NULL,
                estado = 'desconectado',
                numero_conectado = NULL,
                qr_code = NULL
            WHERE empresa_id = ?
        ");
        
        if ($stmt_update->execute([$sesion['empresa_id']])) {
            $puertos_liberados++;
            echo "  ✓ Puerto liberado\n";
        }
    }
    
    // =========================================
    // 3. LIMPIAR QR CODES EXPIRADOS
    // =========================================
    echo "\n--- Limpiando QR codes expirados (+1 hora) ---\n";
    
    $stmt = $pdo->prepare("
        UPDATE whatsapp_sesiones_empresa 
        SET qr_code = NULL
        WHERE qr_code IS NOT NULL
        AND qr_expiration < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    
    $stmt->execute();
    $qr_limpiados = $stmt->rowCount();
    echo "QR codes limpiados: $qr_limpiados\n";
    
    // =========================================
    // 4. ELIMINAR LOGS ANTIGUOS (OPCIONAL)
    // =========================================
    echo "\n--- Limpiando logs antiguos (+90 días) ---\n";
    
    $stmt = $pdo->prepare("
        DELETE FROM logs_sistema 
        WHERE fecha < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $logs_eliminados = $stmt->rowCount();
    echo "Logs eliminados: $logs_eliminados\n";
    
    // =========================================
    // 5. LIMPIAR NOTIFICACIONES VIEJAS
    // =========================================
    echo "\n--- Limpiando notificaciones enviadas (+30 días) ---\n";
    
    $stmt = $pdo->prepare("
        DELETE FROM notificaciones_pago 
        WHERE enviado = 1
        AND fecha_envio < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $notif_eliminadas = $stmt->rowCount();
    echo "Notificaciones eliminadas: $notif_eliminadas\n";
    
    // =========================================
    // 6. LIMPIAR ARCHIVOS TEMPORALES
    // =========================================
    echo "\n--- Limpiando archivos temporales ---\n";
    
    $uploads_dir = __DIR__ . '/../public/uploads/';
    $archivos_temp_eliminados = 0;
    
    if (is_dir($uploads_dir)) {
        // Limpiar archivos .tmp antiguos
        $archivos = glob($uploads_dir . '*.tmp');
        foreach ($archivos as $archivo) {
            $dias_antiguo = (time() - filemtime($archivo)) / (60 * 60 * 24);
            if ($dias_antiguo > 1) {
                if (unlink($archivo)) {
                    $archivos_temp_eliminados++;
                }
            }
        }
    }
    echo "Archivos temporales eliminados: $archivos_temp_eliminados\n";
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Limpieza completada.\n";
    echo "Resumen:\n";
    echo "  - Carpetas tokens eliminadas: $carpetas_eliminadas\n";
    echo "  - Puertos liberados: $puertos_liberados\n";
    echo "  - QR codes limpiados: $qr_limpiados\n";
    echo "  - Logs eliminados: $logs_eliminados\n";
    echo "  - Notificaciones eliminadas: $notif_eliminadas\n";
    echo "  - Archivos temporales eliminados: $archivos_temp_eliminados\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Error en clean-sessions: " . $e->getMessage());
}

// =========================================
// FUNCIONES HELPER
// =========================================

/**
 * Eliminar directorio recursivamente
 */
function eliminarDirectorio($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            eliminarDirectorio($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}