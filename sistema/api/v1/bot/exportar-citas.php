<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../../../includes/SimpleXLSXGen.php';

try {
    $empresa_id = getEmpresaActual();
    $fecha = $_GET['fecha'] ?? null;
    
    // Query base
    $where = "empresa_id = ?";
    $params = [$empresa_id];
    
    if ($fecha) {
        $where .= " AND fecha_cita = ?";
        $params[] = $fecha;
    } else {
        $where .= " AND fecha_cita >= CURDATE()";
    }
    
    $where .= " AND estado IN ('agendada', 'confirmada')";
    
    // Obtener citas
    $sql = "SELECT 
                fecha_cita,
                hora_cita,
                nombre_cliente,
                numero_cliente,
                tipo_servicio,
                estado,
                notas,
                fecha_creacion
            FROM citas_bot 
            WHERE $where 
            ORDER BY fecha_cita ASC, hora_cita ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas = $stmt->fetchAll();
    
    if (empty($citas)) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<script>alert("No hay citas para exportar"); window.close();</script>';
        exit;
    }
    
    // Preparar datos para Excel
    $data = [];
    
    // Encabezados con estilo
    $data[] = [
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Fecha</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Hora</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Cliente</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Teléfono</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Servicio</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Estado</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Notas</b></style>',
        '<style bgcolor="#4472C4" color="#FFFFFF"><b>Fecha Registro</b></style>'
    ];
    
    // Datos con colores según estado
    foreach ($citas as $cita) {
        // Limpiar número
        $numero_limpio = preg_replace('/@c\.us|@s\.whatsapp\.net|\s+/', '', $cita['numero_cliente']);
        
        // Color según estado
        $colorEstado = $cita['estado'] === 'confirmada' ? '#D4EDDA' : '#D1ECF1';
        
        $data[] = [
            date('d/m/Y', strtotime($cita['fecha_cita'])),
            substr($cita['hora_cita'], 0, 5),
            $cita['nombre_cliente'],
            $numero_limpio,
            $cita['tipo_servicio'],
            '<style bgcolor="' . $colorEstado . '">' . ucfirst($cita['estado']) . '</style>',
            $cita['notas'] ?: '-',
            date('d/m/Y H:i', strtotime($cita['fecha_creacion']))
        ];
    }
    
    // Crear Excel
    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($data);
    
    // Configurar anchos de columna (opcional)
    $xlsx->setColWidth(1, 12);  // Fecha
    $xlsx->setColWidth(2, 8);   // Hora
    $xlsx->setColWidth(3, 25);  // Cliente
    $xlsx->setColWidth(4, 15);  // Teléfono
    $xlsx->setColWidth(5, 25);  // Servicio
    $xlsx->setColWidth(6, 12);  // Estado
    $xlsx->setColWidth(7, 35);  // Notas
    $xlsx->setColWidth(8, 18);  // Fecha Registro
    
    // Nombre del archivo
    $filename = 'Citas_' . ($fecha ? date('Y-m-d', strtotime($fecha)) : 'Proximas') . '_' . date('YmdHis') . '.xlsx';
    
    // Descargar
    $xlsx->downloadAs($filename);
    exit;
    
} catch (Exception $e) {
    error_log('Error exportando citas: ' . $e->getMessage());
    header('Content-Type: text/html; charset=utf-8');
    echo '<script>alert("Error al exportar: ' . addslashes($e->getMessage()) . '"); window.close();</script>';
    exit;
}