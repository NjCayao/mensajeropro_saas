<?php
// includes/tipo-cambio.php
// Obtener tipo de cambio USD a PEN en tiempo real

function obtenerTipoCambio() {
    global $pdo;
    
    try {
        // 1. Verificar si hay tipo de cambio en cache (menos de 1 hora)
        $stmt = $pdo->prepare("
            SELECT valor, updated_at 
            FROM configuracion_plataforma 
            WHERE clave = 'tipo_cambio_usd_pen'
        ");
        $stmt->execute();
        $cache = $stmt->fetch();
        
        if ($cache) {
            $ultima_actualizacion = strtotime($cache['updated_at']);
            $hace_una_hora = strtotime('-1 hour');
            
            // Si el cache es reciente (menos de 1 hora), usarlo
            if ($ultima_actualizacion > $hace_una_hora) {
                error_log("Tipo cambio desde cache: " . $cache['valor']);
                return (float)$cache['valor'];
            }
        }
        
        // 2. Obtener tipo de cambio actualizado desde API
        $tipo_cambio = obtenerDesdeBCRP();
        
        if (!$tipo_cambio) {
            // Fallback: API gratuita
            $tipo_cambio = obtenerDesdeExchangeRateAPI();
        }
        
        if (!$tipo_cambio) {
            // Fallback final: tipo de cambio fijo
            error_log("⚠️ No se pudo obtener tipo de cambio, usando fallback: 3.75");
            return 3.75;
        }
        
        // 3. Guardar en cache
        $stmt = $pdo->prepare("
            INSERT INTO configuracion_plataforma (clave, valor, updated_at) 
            VALUES ('tipo_cambio_usd_pen', ?, NOW())
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()
        ");
        $stmt->execute([$tipo_cambio]);
        
        error_log("✅ Tipo cambio actualizado: " . $tipo_cambio);
        return (float)$tipo_cambio;
        
    } catch (Exception $e) {
        error_log("Error obteniendo tipo de cambio: " . $e->getMessage());
        return 3.75; // Fallback
    }
}

/**
 * Obtener desde BCRP (Banco Central de Reserva del Perú)
 */
function obtenerDesdeBCRP() {
    try {
        // API del BCRP - Tipo de cambio del día
        $fecha = date('Y-m-d');
        $url = "https://estadisticas.bcrp.gob.pe/estadisticas/series/api/PD04637PD/" . $fecha;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['periods'][0]['values'][0])) {
                $tipo_cambio = (float)$data['periods'][0]['values'][0];
                error_log("Tipo cambio desde BCRP: " . $tipo_cambio);
                return $tipo_cambio;
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error BCRP: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener desde ExchangeRate-API (Fallback gratuito)
 */
function obtenerDesdeExchangeRateAPI() {
    try {
        $url = "https://api.exchangerate-api.com/v4/latest/USD";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['rates']['PEN'])) {
                $tipo_cambio = (float)$data['rates']['PEN'];
                error_log("Tipo cambio desde ExchangeRate-API: " . $tipo_cambio);
                return $tipo_cambio;
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error ExchangeRate-API: " . $e->getMessage());
        return null;
    }
}