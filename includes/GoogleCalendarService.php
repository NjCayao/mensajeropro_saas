<?php
class GoogleCalendarService {
    private $clientId;
    private $clientSecret;
    private $refreshToken;
    private $calendarId;
    private $accessToken;
    
    public function __construct($clientId, $clientSecret, $refreshToken, $calendarId) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->calendarId = $calendarId;
        
        // Obtener access token
        $this->refreshAccessToken();
    }
    
    /**
     * Refrescar el access token usando el refresh token
     */
    private function refreshAccessToken() {
        $url = 'https://oauth2.googleapis.com/token';
        
        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->accessToken = $data['access_token'];
        } else {
            throw new Exception('Error obteniendo access token: ' . $response);
        }
    }
    
    /**
     * Obtener eventos de un día específico
     */
    public function getEventosDelDia($fecha) {
        try {
            $timeMin = $fecha . 'T00:00:00-05:00';
            $timeMax = $fecha . 'T23:59:59-05:00';
            
            $params = [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'singleEvents' => 'true',
                'orderBy' => 'startTime'
            ];
            
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' . 
                   urlencode($this->calendarId) . '/events?' . http_build_query($params);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['items'] ?? [];
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log('Error obteniendo eventos: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Crear evento en Google Calendar
     */
    public function crearEvento($cita) {
        try {
            $fechaInicio = $cita['fecha_cita'] . 'T' . $cita['hora_cita'] . ':00-05:00';
            $fechaFin = date('Y-m-d\TH:i:s-05:00', 
                strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']) + 
                ($cita['duracion_minutos'] * 60)
            );
            
            $evento = [
                'summary' => $cita['tipo_servicio'] . ' - ' . $cita['nombre_cliente'],
                'description' => "Cliente: {$cita['nombre_cliente']}\n" .
                               "Teléfono: {$cita['numero_cliente']}\n" .
                               "Servicio: {$cita['tipo_servicio']}\n" .
                               "Duración: {$cita['duracion_minutos']} minutos\n\n" .
                               "Cita #{$cita['id']} - Sistema MensajeroPro",
                'start' => [
                    'dateTime' => $fechaInicio,
                    'timeZone' => 'America/Lima'
                ],
                'end' => [
                    'dateTime' => $fechaFin,
                    'timeZone' => 'America/Lima'
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 10],
                        ['method' => 'popup', 'minutes' => 60]
                    ]
                ]
            ];
            
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' . 
                   urlencode($this->calendarId) . '/events';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($evento));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                $data = json_decode($response, true);
                return $data['id'];
            } else {
                throw new Exception('Error creando evento: ' . $response);
            }
            
        } catch (Exception $e) {
            error_log('Error creando evento: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar evento de Google Calendar
     */
    public function eliminarEvento($eventId) {
        try {
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' . 
                   urlencode($this->calendarId) . '/events/' . urlencode($eventId);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 204 || $httpCode === 200;
            
        } catch (Exception $e) {
            error_log('Error eliminando evento: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener slots disponibles de un día
     */
    public function getSlotsDisponibles($fecha, $horaInicio, $horaFin, $duracionCita, $duracionServicio) {
        $slots = [];
        $eventos = $this->getEventosDelDia($fecha);
        
        // Convertir eventos a array de horarios ocupados
        $horariosOcupados = [];
        foreach ($eventos as $evento) {
            if (isset($evento['start']['dateTime'])) {
                $horariosOcupados[] = [
                    'inicio' => strtotime($evento['start']['dateTime']),
                    'fin' => strtotime($evento['end']['dateTime'])
                ];
            }
        }
        
        // Generar slots posibles
        $current = strtotime($fecha . ' ' . $horaInicio);
        $end = strtotime($fecha . ' ' . $horaFin);
        
        while ($current + ($duracionServicio * 60) <= $end) {
            $slotInicio = $current;
            $slotFin = $current + ($duracionServicio * 60);
            
            // Verificar si este slot está disponible
            $disponible = true;
            foreach ($horariosOcupados as $ocupado) {
                if (($slotInicio >= $ocupado['inicio'] && $slotInicio < $ocupado['fin']) ||
                    ($slotFin > $ocupado['inicio'] && $slotFin <= $ocupado['fin']) ||
                    ($slotInicio <= $ocupado['inicio'] && $slotFin >= $ocupado['fin'])) {
                    $disponible = false;
                    break;
                }
            }
            
            if ($disponible) {
                $slots[] = date('H:i', $slotInicio);
            }
            
            $current += ($duracionCita * 60); // Avanzar según duración de slots
        }
        
        return $slots;
    }
}