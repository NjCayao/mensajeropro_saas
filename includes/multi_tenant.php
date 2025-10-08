<?php
// includes/multi_tenant.php
// Funciones para manejar múltiples empresas en el sistema SaaS

// ✅ Usar getEmpresaActual() de auth.php
require_once __DIR__ . '/auth.php';

/**
 * Verificar si el usuario pertenece a la empresa
 */
function verificarAccesoEmpresa($usuario_id, $empresa_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$usuario_id, $empresa_id]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Agregar filtro de empresa a una consulta
 * 
 * @deprecated Usar prepared statements en su lugar para evitar SQL injection
 * Esta función usa concatenación directa y debe evitarse en código nuevo
 */
function addEmpresaFilter($query, $empresa_id = null) {
    if ($empresa_id === null) {
        $empresa_id = getEmpresaActual();
    }
    
    // Si la consulta tiene WHERE, usar AND. Si no, usar WHERE
    if (stripos($query, 'WHERE') !== false) {
        return $query . " AND empresa_id = " . intval($empresa_id);
    } else {
        return $query . " WHERE empresa_id = " . intval($empresa_id);
    }
}

/**
 * Obtener información de la empresa actual
 */
function getDatosEmpresa($empresa_id = null) {
    global $pdo;
    
    if ($empresa_id === null) {
        $empresa_id = getEmpresaActual();
    }
    
    $stmt = $pdo->prepare("
        SELECT e.*, p.nombre as plan_nombre, p.limite_contactos, p.limite_mensajes_mes
        FROM empresas e
        LEFT JOIN planes p ON e.plan_id = p.id
        WHERE e.id = ?
    ");
    $stmt->execute([$empresa_id]);
    
    return $stmt->fetch();
}

/**
 * Verificar límites del plan
 */
function verificarLimitePlan($tipo = 'contactos') {
    $empresa = getDatosEmpresa();
    
    switch($tipo) {
        case 'contactos':
            if (is_null($empresa['limite_contactos'])) {
                return true; // Sin límite
            }
            
            global $pdo;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE empresa_id = ?");
            $stmt->execute([getEmpresaActual()]);
            $actual = $stmt->fetchColumn();
            
            return $actual < $empresa['limite_contactos'];
            
        case 'mensajes':
            if (is_null($empresa['limite_mensajes_mes'])) {
                return true; // Sin límite
            }
            
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM historial_mensajes 
                WHERE empresa_id = ? 
                AND tipo = 'saliente'
                AND MONTH(fecha) = MONTH(CURRENT_DATE())
                AND YEAR(fecha) = YEAR(CURRENT_DATE())
            ");
            $stmt->execute([getEmpresaActual()]);
            $actual = $stmt->fetchColumn();
            
            return $actual < $empresa['limite_mensajes_mes'];
    }
    
    return true;
}

/**
 * Helper para agregar empresa_id en inserts
 */
function prepareInsertWithEmpresa($table, $data) {
    $data['empresa_id'] = getEmpresaActual();
    return $data;
}