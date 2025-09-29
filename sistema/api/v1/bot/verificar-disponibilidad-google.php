<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/session_check.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../../../includes/GoogleCalendarService.php';

header('Content-Type: application/json');

try {
    $empresa_id = getEmpresaActual();
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    $servicio_id = $_GET['servicio_id'] ?? null;
    
    // Obtener configuración de Google Calendar
    $stmt = $pdo->prepare("
        SELECT google_calendar_activo, google_client_id, google_client_secret, 
               google_refresh_token, google_calendar_id
        FROM configuracion_bot 
        WHERE empresa_id = ? AND google_calendar_activo = 1
    ");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
    
    if (!$config || !$config['google_refresh_token']) {
        // Si no hay Google Calendar, usar lógica básica
        echo json_encode([
            'success' => true,
            'disponible' => true,
            'mensaje' => 'Verificación básica'
        ]);
        exit;
    }
    
    // Obtener duración del servicio si se especificó
    $duracionServicio = 30; // Default
    if ($servicio_id) {
        $stmt = $pdo->prepare("
            SELECT duracion_minutos 
            FROM servicios_disponibles 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$servicio_id, $empresa_id]);
        $servicio = $stmt->fetch();
        if ($servicio) {
            $duracionServicio = $servicio['duracion_minutos'];
        }
    }
    
    // Crear servicio de Google Calendar
    $calendar = new GoogleCalendarService(
        $config['google_client_id'],
        $config['google_client_secret'],
        $config['google_refresh_token'],
        $config['google_calendar_id']
    );
    
    // Obtener horario del día
    $diaSemana = date('N', strtotime($fecha)); // 1=Lunes, 7=Domingo
    $stmt = $pdo->prepare("
        SELECT hora_inicio, hora_fin, duracion_cita
        FROM horarios_atencion 
        WHERE empresa_id = ? AND dia_semana = ? AND activo = 1
    ");
    $stmt->execute([$empresa_id, $diaSemana]);
    $horario = $stmt->fetch();
    
    if (!$horario) {
        echo json_encode([
            'success' => true,
            'disponible' => false,
            'mensaje' => 'No hay atención este día'
        ]);
        exit;
    }
    
    // Obtener slots disponibles
    $slotsDisponibles = $calendar->getSlotsDisponibles(
        $fecha,
        $horario['hora_inicio'],
        $horario['hora_fin'],
        $horario['duracion_cita'],
        $duracionServicio
    );
    
    // También verificar citas en la BD local
    $stmt = $pdo->prepare("
        SELECT hora_cita 
        FROM citas_bot 
        WHERE empresa_id = ? AND fecha_cita = ? 
        AND estado IN ('agendada', 'confirmada')
    ");
    $stmt->execute([$empresa_id, $fecha]);
    $citasLocales = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Filtrar slots que también estén en BD local
    $slotsFiltrados = array_filter($slotsDisponibles, function($slot) use ($citasLocales) {
        return !in_array($slot . ':00', $citasLocales);
    });
    
    echo json_encode([
        'success' => true,
        'disponible' => count($slotsFiltrados) > 0,
        'slots' => array_values($slotsFiltrados),
        'total_slots' => count($slotsFiltrados)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error verificando disponibilidad: ' . $e->getMessage()
    ]);
}