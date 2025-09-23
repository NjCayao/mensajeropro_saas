<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

try {
    // Filtros opcionales
    $categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;
    $uso_general = isset($_GET['uso_general']) ? intval($_GET['uso_general']) : null;
    
    $sql = "
        SELECT p.*, c.nombre as categoria_nombre
        FROM plantillas_mensajes p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Aplicar filtros
    if ($categoria_id !== null) {
        $sql .= " AND (p.categoria_id = ? OR p.uso_general = 1)";
        $params[] = $categoria_id;
    }
    
    if ($uso_general !== null) {
        $sql .= " AND p.uso_general = ?";
        $params[] = $uso_general;
    }
    
    $sql .= " ORDER BY p.veces_usado DESC, p.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $plantillas = $stmt->fetchAll();
    
    // Decodificar variables JSON
    foreach ($plantillas as &$plantilla) {
        $plantilla['variables'] = json_decode($plantilla['variables'] ?: '[]', true);
    }
    
    jsonResponse(true, 'Plantillas obtenidas', $plantillas);
    
} catch (Exception $e) {
    error_log("Error al listar plantillas: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener plantillas');
}
?>