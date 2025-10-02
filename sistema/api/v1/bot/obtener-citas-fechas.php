<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

try {
    $empresa_id = getEmpresaActual();
    
    // Parámetros
    $fecha = $_GET['fecha'] ?? null;
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $por_pagina = 10;
    $offset = ($pagina - 1) * $por_pagina;
    
    // Query base
    $where = "empresa_id = ?";
    $params = [$empresa_id];
    
    // Filtro por fecha
    if ($fecha) {
        $where .= " AND fecha_cita = ?";
        $params[] = $fecha;
    } else {
        // Solo citas futuras (de hoy en adelante)
        $where .= " AND fecha_cita >= CURDATE()";
    }
    
    // Solo pendientes (agendadas o confirmadas)
    $where .= " AND estado IN ('agendada', 'confirmada')";
    
    // Contar total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM citas_bot WHERE $where");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Obtener citas
    $sql = "SELECT * FROM citas_bot 
            WHERE $where 
            ORDER BY fecha_cita ASC, hora_cita ASC 
            LIMIT $por_pagina OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $citas,
        'paginacion' => [
            'total' => $total,
            'pagina_actual' => $pagina,
            'por_pagina' => $por_pagina,
            'total_paginas' => ceil($total / $por_pagina)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}