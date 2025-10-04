<?php
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/superadmin_session_check.php';

// Filtros
$modulo = $_GET['modulo'] ?? '';
$accion = $_GET['accion'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$empresa_id = $_GET['empresa_id'] ?? '';
$limit = $_GET['limit'] ?? 1000;

// Construir WHERE
$where = ["DATE(l.fecha) BETWEEN ? AND ?"];
$params = [$fecha_inicio, $fecha_fin];

if ($modulo) {
    $where[] = "l.modulo = ?";
    $params[] = $modulo;
}

if ($accion) {
    $where[] = "l.accion LIKE ?";
    $params[] = "%$accion%";
}

if ($empresa_id) {
    $where[] = "l.empresa_id = ?";
    $params[] = $empresa_id;
}

$whereClause = implode(' AND ', $where);

// Obtener logs
$stmt = $pdo->prepare("
    SELECT 
        l.id,
        l.fecha,
        e.nombre_empresa,
        l.empresa_id,
        u.nombre as usuario_nombre,
        l.usuario_id,
        l.modulo,
        l.accion,
        l.descripcion,
        l.ip_address
    FROM logs_sistema l
    LEFT JOIN empresas e ON l.empresa_id = e.id
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE $whereClause
    ORDER BY l.fecha DESC
    LIMIT ?
");
$params[] = (int)$limit;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Generar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=logs_' . date('Y-m-d_His') . '.csv');

$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
fputcsv($output, [
    'ID',
    'Fecha',
    'Hora',
    'Empresa',
    'Empresa ID',
    'Usuario',
    'Usuario ID',
    'Módulo',
    'Acción',
    'Descripción',
    'IP'
]);

// Datos
foreach ($logs as $log) {
    fputcsv($output, [
        $log['id'],
        date('d/m/Y', strtotime($log['fecha'])),
        date('H:i:s', strtotime($log['fecha'])),
        $log['nombre_empresa'] ?? 'Sistema',
        $log['empresa_id'] ?? '-',
        $log['usuario_nombre'] ?? '-',
        $log['usuario_id'] ?? '-',
        $log['modulo'],
        $log['accion'],
        $log['descripcion'] ?? '-',
        $log['ip_address'] ?? '-'
    ]);
}

fclose($output);
exit;