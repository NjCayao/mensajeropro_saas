<?php
// includes/plan-limits.php
// Sistema de límites por plan para MensajeroPro SaaS

/**
 * Obtener límites del plan actual de la empresa
 * @return array Límites del plan
 */
function obtenerLimitesPlan() {
    global $pdo;
    
    try {
        $empresa_id = getEmpresaActual();
        
        $stmt = $pdo->prepare("
            SELECT 
                e.id as empresa_id,
                e.plan_id,
                e.fecha_expiracion_trial,
                p.nombre as plan_nombre,
                p.limite_contactos,
                p.limite_mensajes_mes,
                p.caracteristicas_json
            FROM empresas e
            LEFT JOIN planes p ON e.plan_id = p.id
            WHERE e.id = ?
        ");
        $stmt->execute([$empresa_id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            throw new Exception('No se encontró información del plan');
        }
        
        // Decodificar características
        $caracteristicas = json_decode($data['caracteristicas_json'] ?? '{}', true);
        
        // Determinar si es trial
        $es_trial = ($data['plan_id'] == 1);
        $trial_activo = false;
        
        if ($es_trial && $data['fecha_expiracion_trial']) {
            $fecha_exp = new DateTime($data['fecha_expiracion_trial']);
            $hoy = new DateTime();
            $trial_activo = ($hoy < $fecha_exp);
        }
        
        return [
            'plan_id' => $data['plan_id'],
            'plan_nombre' => $data['plan_nombre'],
            'es_trial' => $es_trial,
            'trial_activo' => $trial_activo,
            'limite_contactos' => $data['limite_contactos'],
            'limite_mensajes_mes' => $data['limite_mensajes_mes'],
            'caracteristicas' => $caracteristicas
        ];
        
    } catch (Exception $e) {
        error_log("Error en obtenerLimitesPlan: " . $e->getMessage());
        return [
            'plan_id' => 1,
            'plan_nombre' => 'Trial',
            'es_trial' => true,
            'trial_activo' => false,
            'limite_contactos' => 50,
            'limite_mensajes_mes' => 100,
            'caracteristicas' => []
        ];
    }
}

/**
 * Verificar si el plan tiene acceso a una característica específica
 * @param string $caracteristica Nombre de la característica a verificar
 * @return bool True si tiene acceso, False si no
 */
function tieneAccesoCaracteristica($caracteristica) {
    $limites = obtenerLimitesPlan();
    $plan_id = $limites['plan_id'];
    
    // Trial (Plan 1): TODO habilitado por 48h
    if ($plan_id == 1) {
        return $limites['trial_activo'];
    }
    
    // Básico (Plan 2)
    if ($plan_id == 2) {
        $caracteristicas_basico = ['bot_ventas', 'bot_citas', 'mensajes', 'contactos'];
        return in_array($caracteristica, $caracteristicas_basico);
    }
    
    // Profesional (Plan 3): TODO habilitado
    if ($plan_id == 3) {
        return true;
    }
    
    return false;
}

/**
 * Verificar si tiene acceso al módulo de escalamiento
 * @return bool
 */
function tieneEscalamiento() {
    $limites = obtenerLimitesPlan();
    
    // Solo Trial (mientras esté activo) y Profesional tienen escalamiento
    if ($limites['plan_id'] == 1) {
        return $limites['trial_activo'];
    }
    
    return $limites['plan_id'] == 3; // Solo Profesional
}

/**
 * Verificar si tiene acceso al módulo de catálogo bot
 * @return bool
 */
function tieneCatalogoBot() {
    $limites = obtenerLimitesPlan();
    
    // Solo Trial (mientras esté activo) y Profesional tienen catálogo bot
    if ($limites['plan_id'] == 1) {
        return $limites['trial_activo'];
    }
    
    return $limites['plan_id'] == 3; // Solo Profesional
}

/**
 * Verificar si tiene acceso al módulo de horarios/citas
 * @return bool
 */
function tieneHorariosBot() {
    $limites = obtenerLimitesPlan();
    
    // Solo Trial (mientras esté activo) y Profesional tienen horarios bot
    if ($limites['plan_id'] == 1) {
        return $limites['trial_activo'];
    }
    
    return $limites['plan_id'] == 3; // Solo Profesional
}

/**
 * Verificar si tiene acceso a Google Calendar
 * @return bool
 */
function tieneGoogleCalendar() {
    $limites = obtenerLimitesPlan();
    
    // Solo Trial (mientras esté activo) y Profesional tienen Google Calendar
    if ($limites['plan_id'] == 1) {
        return $limites['trial_activo'];
    }
    
    return $limites['plan_id'] == 3; // Solo Profesional
}

/**
 * Obtener límite máximo de MB para catálogo según plan
 * @return int Límite en MB
 */
function getLimiteCatalogoMB() {
    $limites = obtenerLimitesPlan();
    $caracteristicas = $limites['caracteristicas'];
    
    // Retornar límite desde características o valor por defecto
    return $caracteristicas['catalogo_mb'] ?? 5;
}

/**
 * Verificar si alcanzó el límite de contactos
 * @return array ['alcanzado' => bool, 'actual' => int, 'limite' => int, 'porcentaje' => float]
 */
function verificarLimiteContactos() {
    global $pdo;
    
    try {
        $empresa_id = getEmpresaActual();
        $limites = obtenerLimitesPlan();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE empresa_id = ? AND activo = 1");
        $stmt->execute([$empresa_id]);
        $actual = (int)$stmt->fetchColumn();
        
        $limite = $limites['limite_contactos'] ?? 0;
        
        if ($limite === 0 || $limite === null) {
            return [
                'alcanzado' => false,
                'actual' => $actual,
                'limite' => 0,
                'porcentaje' => 0,
                'ilimitado' => true
            ];
        }
        
        $porcentaje = ($actual / $limite) * 100;
        
        return [
            'alcanzado' => $actual >= $limite,
            'actual' => $actual,
            'limite' => $limite,
            'porcentaje' => round($porcentaje, 1),
            'ilimitado' => false
        ];
        
    } catch (Exception $e) {
        error_log("Error en verificarLimiteContactos: " . $e->getMessage());
        return [
            'alcanzado' => true,
            'actual' => 0,
            'limite' => 0,
            'porcentaje' => 0,
            'ilimitado' => false
        ];
    }
}

/**
 * Verificar si alcanzó el límite de mensajes del mes
 * @return array ['alcanzado' => bool, 'actual' => int, 'limite' => int, 'porcentaje' => float]
 */
function verificarLimiteMensajes() {
    global $pdo;
    
    try {
        $empresa_id = getEmpresaActual();
        $limites = obtenerLimitesPlan();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM historial_mensajes 
            WHERE empresa_id = ? 
            AND tipo = 'saliente'
            AND MONTH(fecha) = MONTH(CURRENT_DATE())
            AND YEAR(fecha) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$empresa_id]);
        $actual = (int)$stmt->fetchColumn();
        
        $limite = $limites['limite_mensajes_mes'] ?? 0;
        
        if ($limite === 0 || $limite === null) {
            return [
                'alcanzado' => false,
                'actual' => $actual,
                'limite' => 0,
                'porcentaje' => 0,
                'ilimitado' => true
            ];
        }
        
        $porcentaje = ($actual / $limite) * 100;
        
        return [
            'alcanzado' => $actual >= $limite,
            'actual' => $actual,
            'limite' => $limite,
            'porcentaje' => round($porcentaje, 1),
            'ilimitado' => false
        ];
        
    } catch (Exception $e) {
        error_log("Error en verificarLimiteMensajes: " . $e->getMessage());
        return [
            'alcanzado' => true,
            'actual' => 0,
            'limite' => 0,
            'porcentaje' => 0,
            'ilimitado' => false
        ];
    }
}

/**
 * Obtener resumen completo de límites y uso
 * @return array Resumen completo
 */
function obtenerResumenLimites() {
    $limites = obtenerLimitesPlan();
    $contactos = verificarLimiteContactos();
    $mensajes = verificarLimiteMensajes();
    
    return [
        'plan' => [
            'id' => $limites['plan_id'],
            'nombre' => $limites['plan_nombre'],
            'es_trial' => $limites['es_trial'],
            'trial_activo' => $limites['trial_activo']
        ],
        'contactos' => $contactos,
        'mensajes' => $mensajes,
        'modulos' => [
            'escalamiento' => tieneEscalamiento(),
            'catalogo_bot' => tieneCatalogoBot(),
            'horarios_bot' => tieneHorariosBot(),
            'google_calendar' => tieneGoogleCalendar()
        ],
        'limites_especiales' => [
            'catalogo_mb' => getLimiteCatalogoMB()
        ]
    ];
}

/**
 * Mostrar alerta de límite alcanzado (HTML)
 * @param string $tipo Tipo de límite ('contactos' o 'mensajes')
 * @return string HTML de la alerta
 */
function mostrarAlertaLimite($tipo) {
    $limites = obtenerLimitesPlan();
    
    if ($tipo === 'contactos') {
        $check = verificarLimiteContactos();
        $nombre_limite = 'contactos';
    } else {
        $check = verificarLimiteMensajes();
        $nombre_limite = 'mensajes del mes';
    }
    
    if (!$check['alcanzado']) {
        return '';
    }
    
    $plan_nombre = $limites['plan_nombre'];
    
    return <<<HTML
    <div class="alert alert-warning alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h5><i class="icon fas fa-exclamation-triangle"></i> Límite Alcanzado</h5>
        Has alcanzado el límite de <strong>$nombre_limite</strong> de tu plan <strong>$plan_nombre</strong> 
        ({$check['actual']}/{$check['limite']}).
        <br>
        <a href="/cliente/mi-plan" class="btn btn-sm btn-primary mt-2">
            <i class="fas fa-arrow-up"></i> Mejorar Plan
        </a>
    </div>
HTML;
}

/**
 * Verificar acceso a módulo y redireccionar si no tiene permiso
 * @param string $modulo Nombre del módulo
 * @param bool $ajax Si es una petición AJAX
 * @return void
 */
function verificarAccesoModulo($modulo, $ajax = false) {
    $tiene_acceso = false;
    
    switch($modulo) {
        case 'escalados':
            $tiene_acceso = tieneEscalamiento();
            break;
        case 'catalogo-bot':
            $tiene_acceso = tieneCatalogoBot();
            break;
        case 'horarios-bot':
            $tiene_acceso = tieneHorariosBot();
            break;
        default:
            $tiene_acceso = true;
    }
    
    if (!$tiene_acceso) {
        if ($ajax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Tu plan actual no tiene acceso a este módulo. Mejora tu plan para desbloquear esta función.',
                'codigo' => 'PLAN_INSUFICIENTE'
            ]);
            exit;
        } else {
            $_SESSION['error_plan'] = "Este módulo no está disponible en tu plan actual.";
            header('Location: ' . url('cliente/mi-plan'));
            exit;
        }
    }
}