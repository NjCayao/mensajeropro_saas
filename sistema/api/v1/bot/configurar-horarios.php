<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '/../../../../config/database.php';
require_once '/../../../../includes/session_check.php';
require_once '/../../../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => false, 'message' => 'Método no permitido']);   
    exit;
}

try {
    $empresa_id = getEmpresaActual();

    $dias = $_POST['dias'] ?? [];

    //eliminar los horarios existentes
    $stmt = $conn->prepare("DELETE FROM horarios_atencion WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);

    //insertar los nuevos horarios
    $sql = "INSERT INTO horarios_atencion (empresa_id, dia_semana, hora_inicio, hora_fin, duracion_cita,  activo) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);

    $horariosGuardados = 0;

    foreach ($dias as $dia) {
        $activo = isset($_POST["activo_$dia"]) ? 1 : 0;

        if ($activo) {
            $hora_inicio = $_POST["hora_inicio_$dia"] ?? '09:00';
            $hora_fin = $_POST["hora_fin_$dia"] ?? '18:00';
            $duracion_cita = $_POST["duracion_cita_$dia"] ?? 30;

            if ($hora_inicio >= $hora_fin) {
                throw new Exception("La hora de inicio no puede ser mayor que la hora de fin");
            }

            $stmt->execute([
                $empresa_id, 
                $dia, 
                $hora_inicio, 
                $hora_fin, 
                $duracion, 
                1
            ]);

            $horariosGuardados++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Horarios guardados correctamente. $horariosGuardados días activos configurados.",
        'dias_activos' => $horariosGuardados
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}




?>