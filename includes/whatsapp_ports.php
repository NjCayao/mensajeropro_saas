<?php
/**
 * Gestión de puertos para WhatsApp multi-empresa
 */

/**
 * Obtiene el puerto asignado a una empresa
 * Si no tiene puerto asignado, le asigna uno automáticamente
 */
function obtenerPuertoEmpresa($pdo, $empresa_id) {
    error_log("=== obtenerPuertoEmpresa LLAMADA ===");
    error_log("Empresa ID recibida: " . $empresa_id);
    error_log("PDO válido: " . (is_object($pdo) ? 'SI' : 'NO'));
    
    // Buscar puerto asignado
    $stmt = $pdo->prepare("SELECT puerto FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $result = $stmt->fetch();
    
    error_log("Resultado fetch: " . print_r($result, true));
    
    if ($result && $result['puerto']) {
        error_log("Puerto encontrado: " . $result['puerto']);
        return $result['puerto'];
    }
    
    error_log("Puerto NO encontrado, asignando nuevo...");
    
    // Si no tiene puerto, asignar uno nuevo
    // Puerto base 3001, incrementar según empresa_id
    $puerto_base = 3001;
    $puerto = $puerto_base + ($empresa_id - 1);
    
    // Verificar que el puerto no esté en uso por otra empresa
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM whatsapp_sesiones_empresa WHERE puerto = ? AND empresa_id != ?");
    $stmt->execute([$puerto, $empresa_id]);
    
    while ($stmt->fetchColumn() > 0) {
        // Si está en uso, buscar el siguiente puerto libre
        $puerto++;
        $stmt->execute([$puerto, $empresa_id]);
    }
    
    // Asignar el puerto a la empresa
    $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET puerto = ? WHERE empresa_id = ?");
    $stmt->execute([$puerto, $empresa_id]);
    
    error_log("Puerto asignado: " . $puerto);
    
    return $puerto;
}

/**
 * Verifica si un puerto está activo
 */
function verificarPuertoActivo($puerto) {
    $connection = @fsockopen("localhost", $puerto, $errno, $errstr, 1);
    
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    
    return false;
}

/**
 * Libera el puerto de una empresa
 */
function liberarPuertoEmpresa($pdo, $empresa_id) {
    // No borramos el puerto, solo marcamos como desconectado
    $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'desconectado' WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    
    return true;
}

/**
 * Obtiene el siguiente puerto disponible
 */
function obtenerPuertoDisponible($pdo, $puerto_inicial = 3001) {
    $puerto = $puerto_inicial;
    $max_intentos = 100; // Máximo 100 puertos para verificar
    $intento = 0;
    
    while ($intento < $max_intentos) {
        // Verificar si el puerto está asignado en la BD
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM whatsapp_sesiones_empresa WHERE puerto = ?");
        $stmt->execute([$puerto]);
        
        if ($stmt->fetchColumn() == 0 && !verificarPuertoActivo($puerto)) {
            return $puerto;
        }
        
        $puerto++;
        $intento++;
    }
    
    throw new Exception("No se pudo encontrar un puerto disponible");
}

function limpiarPuertoEmpresa($puerto) {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($isWindows) {
        // Buscar proceso usando el puerto
        $output = shell_exec("netstat -ano | findstr :$puerto");
        if ($output) {
            // Extraer todos los PIDs
            preg_match_all('/\s+(\d+)\s*$/m', $output, $matches);
            if (!empty($matches[1])) {
                $pids = array_unique($matches[1]);
                foreach ($pids as $pid) {
                    @exec("taskkill /PID $pid /F 2>&1");
                }
                return true;
            }
        }
    } else {
        // Linux/Mac
        @exec("lsof -t -i:$puerto | xargs kill -9 2>&1");
        return true;
    }
    
    return false;
}