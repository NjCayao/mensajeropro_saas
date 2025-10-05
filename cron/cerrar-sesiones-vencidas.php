<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Buscar empresas con suscripción vencida pero WhatsApp activo
    $stmt = $pdo->query("
        SELECT 
            w.empresa_id,
            w.puerto,
            e.nombre_empresa
        FROM whatsapp_sesiones_empresa w
        INNER JOIN empresas e ON w.empresa_id = e.id
        LEFT JOIN suscripciones s ON e.id = s.empresa_id AND s.estado = 'activa'
        WHERE w.estado = 'conectado'
        AND (
            s.id IS NULL 
            OR s.fecha_fin < NOW()
        )
    ");
    
    $sesiones_vencidas = $stmt->fetchAll();
    
    foreach ($sesiones_vencidas as $sesion) {
        echo "Cerrando WhatsApp de: {$sesion['nombre_empresa']} (ID: {$sesion['empresa_id']})\n";
        
        // Llamar al API para cerrar sesión
        $url = WHATSAPP_API_URL . "/api/disconnect";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: mensajeroPro2025',
            'X-Empresa-ID: ' . $sesion['empresa_id']
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Actualizar BD
        $stmt = $pdo->prepare("
            UPDATE whatsapp_sesiones_empresa 
            SET estado = 'desconectado', 
                ultima_desconexion = NOW()
            WHERE empresa_id = ?
        ");
        $stmt->execute([$sesion['empresa_id']]);
        
        echo "✓ Sesión cerrada correctamente\n";
    }
    
    echo "Proceso completado. Total cerradas: " . count($sesiones_vencidas) . "\n";
    
} catch (Exception $e) {
    error_log("Error en cron cerrar sesiones: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
}