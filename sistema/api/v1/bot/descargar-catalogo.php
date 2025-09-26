<?php
// sistema/api/v1/bot/descargar-catalogo.php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
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
        echo json_encode(['success' => false, 'message' => 'No hay catálogo cargado']);
        exit;
    }
    
    // Determinar qué archivo descargar
    $archivo = null;
    $contentType = '';
    
    if ($tipo === 'excel' && $catalogo['archivo_excel']) {
        $archivo = $catalogo['archivo_excel'];
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } elseif ($tipo === 'pdf' && $catalogo['archivo_pdf']) {
        $archivo = $catalogo['archivo_pdf'];
        $contentType = 'application/pdf';
    }
    
    if (!$archivo || !file_exists($archivo)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        exit;
    }
    
    // Enviar archivo
    $fileName = basename($archivo);
    
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($archivo));
    header('Cache-Control: no-cache, must-revalidate');
    
    readfile($archivo);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al descargar archivo: ' . $e->getMessage()
    ]);
}