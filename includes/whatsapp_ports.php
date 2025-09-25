<?php
/**
 * Gestión de puertos para WhatsApp multi-empresa
 */

/**
 * Obtiene el puerto asignado a una empresa
 * Si no tiene puerto asignado, le asigna uno automáticamente
 */
function obtenerPuertoEmpresa($pdo, $empresa_id) {
    // Buscar puerto asignado
    $stmt = $pdo->prepare("SELECT puerto FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $result = $stmt->fetch();
    
    if ($result && $result['puerto']) {
        return $result['puerto'];
    }
    
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