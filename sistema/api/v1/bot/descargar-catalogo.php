<?php
// sistema/api/v1/bot/descargar-catalogo.php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die('Método no permitido');
}

try {
    $empresa_id = getEmpresaActual();
    $tipo = $_GET['tipo'] ?? 'excel';
    
    // Obtener catálogo de la BD
    $stmt = $pdo->prepare("SELECT * FROM catalogo_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $catalogo = $stmt->fetch();
    
    if (!$catalogo) {
        http_response_code(404);
        die('No hay catálogo cargado');
    }
    
    // Determinar qué archivo descargar
    $archivo = null;
    $contentType = '';
    $extension = '';
    
    if ($tipo === 'excel' && $catalogo['archivo_excel']) {
        $archivo = $catalogo['archivo_excel'];
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $extension = '.xlsx';
    } elseif ($tipo === 'pdf' && $catalogo['archivo_pdf']) {
        $archivo = $catalogo['archivo_pdf'];
        $contentType = 'application/pdf';
        $extension = '.pdf';
    }
    
    if (!$archivo || !file_exists($archivo)) {
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // ✅ CRÍTICO: Limpiar cualquier output previo
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar archivo
    $fileName = 'catalogo_' . time() . $extension;
    
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($archivo));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Leer archivo en modo binario
    $handle = fopen($archivo, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
    
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error al descargar archivo: ' . $e->getMessage());
}