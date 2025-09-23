<?php
header('Content-Type: application/json');

// Establecer zona horaria
date_default_timezone_set('America/Lima');

// Devolver hora actual del servidor
echo json_encode([
    'success' => true,
    'data' => [
        'datetime' => date('Y-m-d H:i:s'),
        'timestamp' => time(),
        'timezone' => date_default_timezone_get(),
        'time' => date('H:i:s'),
        'date' => date('Y-m-d')
    ]
]);
?>