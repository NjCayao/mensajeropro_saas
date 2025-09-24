<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'ID inválido');
}

try {
    $stmt = $pdo->prepare("
        SELECT mp.*, u.nombre as usuario_nombre, c.nombre as categoria_nombre
        FROM mensajes_programados mp
        LEFT JOIN usuarios u ON mp.usuario_id = u.id
        LEFT JOIN categorias c ON mp.categoria_id = c.id AND c.empresa_id = mp.empresa_id
        WHERE mp.id = ? AND mp.empresa_id = ?
    ");
    $stmt->execute([$id, getEmpresaActual()]);
    $mensaje = $stmt->fetch();

    if ($mensaje) {
        // Formatear datos
        $mensaje['fecha_creacion'] = date('d/m/Y H:i', strtotime($mensaje['fecha_creacion']));
        $mensaje['fecha_programada'] = date('d/m/Y H:i', strtotime($mensaje['fecha_programada']));

        // Determinar tipo de destinatarios
        if ($mensaje['enviar_a_todos']) {
            $mensaje['destinatarios_texto'] = 'TODOS los contactos';
        } elseif ($mensaje['categoria_nombre']) {
            $mensaje['destinatarios_texto'] = 'Categoría: ' . $mensaje['categoria_nombre'];
        } else {
            // Verificar si es individual
            $stmt = $pdo->prepare("
                SELECT c.nombre, c.numero 
                FROM mensajes_programados_individuales mpi
                JOIN contactos c ON mpi.contacto_id = c.id
                WHERE mpi.mensaje_programado_id = ?
            ");
            $stmt->execute([$id]);
            $contacto = $stmt->fetch();

            if ($contacto) {
                $mensaje['destinatarios_texto'] = 'Individual: ' . $contacto['nombre'] . ' (' . $contacto['numero'] . ')';
            } else {
                $mensaje['destinatarios_texto'] = 'Sin especificar';
            }
        }

        // Clase para badge de estado
        $mensaje['estado_class'] = [
            'pendiente' => 'info',
            'procesando' => 'warning',
            'completado' => 'success',
            'cancelado' => 'danger',
            'error' => 'danger'
        ][$mensaje['estado']] ?? 'secondary';

        // Calcular porcentaje si está procesando o completado
        if (in_array($mensaje['estado'], ['procesando', 'completado']) && $mensaje['total_destinatarios'] > 0) {
            $mensaje['porcentaje'] = round(($mensaje['mensajes_enviados'] / $mensaje['total_destinatarios']) * 100);
        } else {
            $mensaje['porcentaje'] = 0;
        }

        jsonResponse(true, 'Detalles obtenidos', $mensaje);
    } else {
        jsonResponse(false, 'Mensaje no encontrado');
    }
} catch (Exception $e) {
    error_log("Error al obtener detalles: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener detalles');
}
