<?php
// sistema/includes/whatsapp_ports.php - ARCHIVO NUEVO

/**
 * Gestión de puertos para WhatsApp multi-empresa
 */

function obtenerPuertoEmpresa($pdo, $empresa_id) {
    // Verificar si ya tiene puerto asignado
    $stmt = $pdo->prepare("SELECT puerto FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $result = $stmt->fetch();
    
    if ($result && $result['puerto']) {
        return $result['puerto'];
    }
    
    // Buscar siguiente puerto disponible (3001-3100)
    $stmt = $pdo->query("SELECT puerto FROM whatsapp_sesiones_empresa WHERE puerto IS NOT NULL ORDER BY puerto ASC");
    $puertosUsados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $nuevoPuerto = 3001;
    while (in_array($nuevoPuerto, $puertosUsados) && $nuevoPuerto < 3100) {
        $nuevoPuerto++;
    }
    
    if ($nuevoPuerto >= 3100) {
        throw new Exception("No hay puertos disponibles");
    }
    
    // Asignar el puerto
    $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET puerto = ? WHERE empresa_id = ?");
    $stmt->execute([$nuevoPuerto, $empresa_id]);
    
    return $nuevoPuerto;
}

function liberarPuertoEmpresa($pdo, $empresa_id) {
    $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET puerto = NULL, estado = 'desconectado' WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
}

function verificarPuertoActivo($puerto) {
    $connection = @fsockopen("localhost", $puerto, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}
?>