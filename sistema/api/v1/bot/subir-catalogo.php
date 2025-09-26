<?php
// sistema/api/v1/bot/subir-catalogo.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/app.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Función para leer y procesar Excel
function procesarExcel($archivo) {
    // Incluir SimpleXLSX directamente
    require_once __DIR__ . '/../../../../includes/SimpleXLSX.php';
    
    $datos = [
        'productos' => [],
        'promociones' => [],
        'delivery' => [
            'zonas' => [],
            'gratis_desde' => null
        ]
    ];
    
    try {
        // Usar SimpleXLSX para leer el Excel
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($archivo)) {
            $sheets = $xlsx->sheetNames();
            
            foreach ($sheets as $index => $sheetName) {
                $rows = $xlsx->rows($index);
                
                if (strtoupper($sheetName) === 'PRODUCTOS') {
                    $header = array_shift($rows); // Quitar encabezado
                    foreach ($rows as $row) {
                        if (!empty($row[0])) { // Si hay categoría
                            $datos['productos'][] = [
                                'categoria' => trim($row[0] ?? ''),
                                'producto' => trim($row[1] ?? ''),
                                'precio' => floatval($row[2] ?? 0),
                                'disponible' => strtoupper(trim($row[3] ?? 'SI')) === 'SI'
                            ];
                        }
                    }
                }
                
                elseif (strtoupper($sheetName) === 'PROMOCIONES') {
                    $header = array_shift($rows);
                    foreach ($rows as $row) {
                        if (!empty($row[0])) {
                            $datos['promociones'][] = [
                                'producto' => trim($row[0] ?? ''),
                                'tipo' => trim($row[1] ?? ''),
                                'descripcion' => trim($row[2] ?? ''),
                                'precio_promo' => floatval($row[3] ?? 0)
                            ];
                        }
                    }
                }
                
                elseif (strtoupper($sheetName) === 'DELIVERY') {
                    $header = array_shift($rows);
                    foreach ($rows as $row) {
                        if (!empty($row[0])) {
                            // Buscar la fila especial "GRATIS DESDE:"
                            if (strpos(strtoupper($row[0]), 'GRATIS DESDE') !== false) {
                                $datos['delivery']['gratis_desde'] = floatval($row[1] ?? 0);
                            } else {
                                $datos['delivery']['zonas'][] = [
                                    'zona' => trim($row[0] ?? ''),
                                    'costo' => floatval($row[1] ?? 0),
                                    'tiempo' => trim($row[2] ?? '')
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            throw new Exception('No se pudo leer el archivo Excel');
        }
        
    } catch (Exception $e) {
        throw new Exception('Error procesando Excel: ' . $e->getMessage());
    }
    
    return $datos;
}

try {
    $empresa_id = getEmpresaActual();
    
    // Verificar si existe registro previo
    $stmt = $pdo->prepare("SELECT * FROM catalogo_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $catalogoExistente = $stmt->fetch();
    
    // Rutas de upload
    $uploadPath = getEmpresaUploadPath($empresa_id, 'catalogo');
    
    $archivoExcel = $catalogoExistente ? $catalogoExistente['archivo_excel'] : null;
    $archivoPdf = $catalogoExistente ? $catalogoExistente['archivo_pdf'] : null;
    $datosJson = null;
    
    // Procesar archivo Excel si se subió
    if (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['archivo_excel']['tmp_name'];
        $fileName = $_FILES['archivo_excel']['name'];
        $fileSize = $_FILES['archivo_excel']['size'];
        
        // Validar tamaño
        if ($fileSize > MAX_CATALOG_SIZE) {
            throw new Exception('El archivo Excel excede el tamaño máximo permitido (10MB)');
        }
        
        // Validar extensión
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            throw new Exception('El archivo debe ser Excel (.xlsx o .xls)');
        }
        
        // Generar nombre único
        $nuevoNombre = 'catalogo_' . $empresa_id . '_' . time() . '.' . $ext;
        $rutaCompleta = $uploadPath . '/' . $nuevoNombre;
        
        // Mover archivo
        if (!move_uploaded_file($tmpName, $rutaCompleta)) {
            throw new Exception('Error al guardar el archivo Excel');
        }
        
        // Eliminar archivo anterior si existe
        if ($catalogoExistente && $catalogoExistente['archivo_excel'] && file_exists($catalogoExistente['archivo_excel'])) {
            @unlink($catalogoExistente['archivo_excel']);
        }
        
        $archivoExcel = $rutaCompleta;
        
        // Procesar el Excel y extraer datos
        $datos = procesarExcel($rutaCompleta);
        $datosJson = json_encode($datos);
    }
    
    // Procesar archivo PDF si se subió
    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['archivo_pdf']['tmp_name'];
        $fileName = $_FILES['archivo_pdf']['name'];
        $fileSize = $_FILES['archivo_pdf']['size'];
        
        // Validar tamaño
        if ($fileSize > MAX_CATALOG_SIZE) {
            throw new Exception('El archivo PDF excede el tamaño máximo permitido (10MB)');
        }
        
        // Validar extensión
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('El archivo debe ser PDF');
        }
        
        // Generar nombre único
        $nuevoNombre = 'catalogo_visual_' . $empresa_id . '_' . time() . '.pdf';
        $rutaCompleta = $uploadPath . '/' . $nuevoNombre;
        
        // Mover archivo
        if (!move_uploaded_file($tmpName, $rutaCompleta)) {
            throw new Exception('Error al guardar el archivo PDF');
        }
        
        // Eliminar archivo anterior si existe
        if ($catalogoExistente && $catalogoExistente['archivo_pdf'] && file_exists($catalogoExistente['archivo_pdf'])) {
            @unlink($catalogoExistente['archivo_pdf']);
        }
        
        $archivoPdf = $rutaCompleta;
    }
    
    // Si no se subió Excel pero existe uno previo, recargar datos
    if (!isset($_FILES['archivo_excel']) && $catalogoExistente && $catalogoExistente['archivo_excel']) {
        if (file_exists($catalogoExistente['archivo_excel'])) {
            $datos = procesarExcel($catalogoExistente['archivo_excel']);
            $datosJson = json_encode($datos);
        }
    }
    
    // Validar que tengamos datos
    if (!$archivoExcel && !$catalogoExistente) {
        throw new Exception('Debes subir un archivo Excel con el catálogo');
    }
    
    // Guardar o actualizar en base de datos
    if ($catalogoExistente) {
        // Actualizar
        $sql = "UPDATE catalogo_bot SET 
                archivo_excel = ?,
                archivo_pdf = ?,
                datos_json = ?,
                fecha_actualizacion = NOW()
                WHERE empresa_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $archivoExcel,
            $archivoPdf,
            $datosJson ?: $catalogoExistente['datos_json'],
            $empresa_id
        ]);
    } else {
        // Insertar
        $sql = "INSERT INTO catalogo_bot (empresa_id, archivo_excel, archivo_pdf, datos_json)
                VALUES (?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $empresa_id,
            $archivoExcel,
            $archivoPdf,
            $datosJson
        ]);
    }
    
    // Decodificar datos para mostrar resumen
    $resumen = json_decode($datosJson ?: '{}', true);
    
    echo json_encode([
        'success' => true,
        'message' => 'Catálogo actualizado correctamente',
        'data' => [
            'productos' => count($resumen['productos'] ?? []),
            'promociones' => count($resumen['promociones'] ?? []),
            'zonas_delivery' => count($resumen['delivery']['zonas'] ?? [])
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ya no necesitamos esta clase dummy porque usaremos el archivo real