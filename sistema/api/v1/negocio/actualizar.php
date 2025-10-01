<?php
// sistema/api/v1/negocio/actualizar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';

// IMPORTANTE: Limpiar cualquier salida previa
ob_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $empresa_id = getEmpresaActual();
    
    // Obtener datos del POST
    $nombre_negocio = $_POST['nombre_negocio'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $cuentas_pago = $_POST['cuentas_pago'] ?? '{}';
    
    // Validar que el JSON sea válido
    $test_json = json_decode($cuentas_pago);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON de cuentas de pago inválido');
    }
    
    // Verificar si ya existe configuración
    $stmt = $pdo->prepare("SELECT id FROM configuracion_negocio WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE configuracion_negocio 
            SET nombre_negocio = ?, 
                telefono = ?, 
                direccion = ?, 
                cuentas_pago = ?, 
                updated_at = NOW()
            WHERE empresa_id = ?
        ");
        $result = $stmt->execute([
            $nombre_negocio, 
            $telefono, 
            $direccion, 
            $cuentas_pago, 
            $empresa_id
        ]);
    } else {
        // Insertar nuevo
        $stmt = $pdo->prepare("
            INSERT INTO configuracion_negocio 
            (empresa_id, nombre_negocio, telefono, direccion, cuentas_pago, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([
            $empresa_id, 
            $nombre_negocio, 
            $telefono, 
            $direccion, 
            $cuentas_pago
        ]);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuración guardada correctamente'
        ]);
    } else {
        throw new Exception('No se pudo guardar en la base de datos');
    }
    
} catch (Exception $e) {
    error_log("Error en actualizar negocio: " . $e->getMessage());
    http_response_code(200); // Cambiar a 200 para evitar el error de CORS
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
exit;
?>