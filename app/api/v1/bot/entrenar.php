<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../response.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

try {
    // Obtener datos del formulario
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');

    // Validaciones
    if (empty($titulo)) {
        Response::error('El título es obligatorio');
    }

    if (empty($contenido)) {
        Response::error('El contenido es obligatorio');
    }

    if (strlen($titulo) > 200) {
        Response::error('El título es demasiado largo (máximo 200 caracteres)');
    }

    if (strlen($contenido) < 50) {
        Response::error('El contenido es muy corto (mínimo 50 caracteres)');
    }

    if (strlen($contenido) > 50000) {
        Response::error('El contenido es demasiado largo (máximo 50,000 caracteres)');
    }

    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO conocimiento_bot (nombre_archivo, tipo_archivo, contenido_texto, activo, empresa_id)
        VALUES (?, ?, ?, 1, ?)
        ");

    $result = $stmt->execute([
        $titulo,
        'text',
        $contenido,
        getEmpresaActual()
    ]);

    if ($result) {
        Response::success([
            'message' => 'Conocimiento guardado correctamente',
            'titulo' => $titulo,
            'caracteres' => strlen($contenido)
        ]);
    } else {
        Response::error('Error al guardar en la base de datos');
    }
} catch (Exception $e) {
    error_log("Error en entrenar bot: " . $e->getMessage());
    Response::error('Error en el servidor: ' . $e->getMessage());
}
