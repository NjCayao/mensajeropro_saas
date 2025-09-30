<?php
$current_page = 'bot-config';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../../../includes/plan-limits.php';

// Obtener configuración actual
$stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch();

if ($config['escalamiento_config']) {
    echo "<!-- ESCALAMIENTO RAW: " . $config['escalamiento_config'] . " -->";
}


// Si no existe configuración, crear una por defecto
if (!$config) {
    $stmt = $pdo->prepare("INSERT INTO configuracion_bot (empresa_id) VALUES (?)");
    $stmt->execute([$empresa_id]);

    $stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
}

// Decodificar JSONs
$respuestas_rapidas = json_decode($config['respuestas_rapidas'] ?? '{}', true);
$escalamiento_config = json_decode($config['escalamiento_config'] ?? '{}', true);

// Obtener templates disponibles
$stmt = $pdo->query("SELECT * FROM bot_templates WHERE activo = 1 ORDER BY tipo_negocio, nombre_template");
$templates = $stmt->fetchAll();

// Obtener métricas del día
$stmt = $pdo->prepare("
    SELECT * FROM bot_metricas 
    WHERE empresa_id = ? AND fecha = CURDATE()
");
$stmt->execute([$empresa_id]);
$metricas_hoy = $stmt->fetch();

// Si no hay métricas de hoy, crear registro vacío
if (!$metricas_hoy) {
    $metricas_hoy = [
        'conversaciones_iniciadas' => 0,
        'conversaciones_completadas' => 0,
        'escalamientos' => 0,
        'hora_pico' => null,
        'preguntas_frecuentes' => '[]'
    ];
}

// Estadísticas generales
$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversaciones_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$total_conversaciones = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversaciones_bot WHERE DATE(fecha_hora) = CURDATE() AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$conversaciones_hoy = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(tokens_usados), 0) FROM conversaciones_bot WHERE DATE(fecha_hora) = CURDATE() AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$tokens_usados_hoy = $stmt->fetchColumn();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bot IA Inteligente</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Bot IA</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Estadísticas -->
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($total_conversaciones) ?></h3>
                            <p>Total Conversaciones</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comments"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($conversaciones_hoy) ?></h3>
                            <p>Conversaciones Hoy</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($tokens_usados_hoy) ?></h3>
                            <p>Tokens Usados Hoy</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="small-box <?= $config['activo'] ? 'bg-success' : 'bg-danger' ?>">
                        <div class="inner">
                            <h3><?= $config['activo'] ? 'ACTIVO' : 'INACTIVO' ?></h3>
                            <p>Estado del Bot</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-power-off"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs de configuración -->
            <div class="card card-primary card-tabs">
                <div class="card-header p-0 pt-1">
                    <ul class="nav nav-tabs" id="bot-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="config-tab" data-toggle="pill" href="#config" role="tab">
                                <i class="fas fa-cog"></i> Configuración
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="templates-tab" data-toggle="pill" href="#templates" role="tab">
                                <i class="fas fa-file-alt"></i> Templates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="prompts-tab" data-toggle="pill" href="#prompts" role="tab">
                                <i class="fas fa-robot"></i> Personalización IA
                            </a>
                        </li>
                        <?php if (tieneEscalamiento()): ?>
                            <li class="nav-item">
                                <a class="nav-link" id="escalamiento-tab" data-toggle="pill" href="#escalamiento" role="tab">
                                    <i class="fas fa-user-tie"></i> Escalamiento
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" id="pruebas-tab" data-toggle="pill" href="#pruebas" role="tab">
                                <i class="fas fa-vial"></i> Modo Prueba
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="metricas-tab" data-toggle="pill" href="#metricas" role="tab">
                                <i class="fas fa-chart-line"></i> Métricas
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="bot-tabsContent">

                        <!-- Tab Configuración -->
                        <div class="tab-pane fade show active" id="config" role="tabpanel">
                            <form id="formConfiguracion">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4>Configuración General</h4>

                                        <!-- Estado del Bot -->
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="activo"
                                                    name="activo" <?= $config['activo'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="activo">
                                                    <strong>Bot Activo</strong> - Responder mensajes automáticamente
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Tipo de Bot -->
                                        <div class="form-group">
                                            <label class="text-primary font-weight-bold">
                                                <i class="fas fa-robot"></i> Tipo de Bot para tu Negocio:
                                            </label>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card" style="cursor: pointer;" onclick="$('#tipo_bot_ventas').prop('checked', true)">
                                                        <div class="card-body text-center">
                                                            <i class="fas fa-shopping-cart fa-3x text-success"></i>
                                                            <h5 class="mt-2">Bot de Ventas</h5>
                                                            <p class="text-muted small">Para vender productos, tomar pedidos, delivery</p>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="tipo_bot" id="tipo_bot_ventas" value="ventas"
                                                                    <?= $config['tipo_bot'] == 'ventas' ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="tipo_bot_ventas">
                                                                    Seleccionar
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card" style="cursor: pointer;" onclick="$('#tipo_bot_citas').prop('checked', true)">
                                                        <div class="card-body text-center">
                                                            <i class="fas fa-calendar-check fa-3x text-info"></i>
                                                            <h5 class="mt-2">Bot de Citas</h5>
                                                            <p class="text-muted small">Para agendar citas, reservas, turnos</p>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="tipo_bot" id="tipo_bot_citas" value="citas"
                                                                    <?= $config['tipo_bot'] == 'citas' ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="tipo_bot_citas">
                                                                    Seleccionar
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted">Esta selección determina qué plantillas verás y cómo funcionará tu bot</small>
                                        </div>

                                        <!-- Usar Templates -->
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="templates_activo"
                                                    name="templates_activo" <?= $config['templates_activo'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="templates_activo">
                                                    Usar templates predefinidos
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Delay de respuesta -->
                                        <div class="form-group">
                                            <label>Delay de respuesta (segundos):</label>
                                            <input type="number" class="form-control" name="delay_respuesta"
                                                value="<?= $config['delay_respuesta'] ?>" min="1" max="60">
                                            <small class="text-muted">Simula tiempo de escritura humana</small>
                                        </div>

                                        <!-- Horario de atención -->
                                        <div class="form-group">
                                            <label>Horario de atención:</label>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <input type="time" class="form-control" name="horario_inicio"
                                                        value="<?= $config['horario_inicio'] ?>">
                                                    <small class="text-muted">Hora inicio</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="time" class="form-control" name="horario_fin"
                                                        value="<?= $config['horario_fin'] ?>">
                                                    <small class="text-muted">Hora fin</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mensaje fuera de horario -->
                                        <div class="form-group">
                                            <label>Mensaje fuera de horario:</label>
                                            <textarea class="form-control" name="mensaje_fuera_horario" rows="3"><?= htmlspecialchars($config['mensaje_fuera_horario'] ?? '') ?></textarea>
                                        </div>

                                        <div class="col-md-6">                                            
                                            <!-- Responder a no registrados -->
                                            <div class="form-group">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="responder_no_registrados"
                                                        name="responder_no_registrados" <?= $config['responder_no_registrados'] ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="responder_no_registrados">
                                                        Responder a números no registrados
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Palabras de activación -->
                                            <div class="form-group">
                                                <label>Palabras de activación (separadas por coma):</label>
                                                <input type="text" class="form-control" name="palabras_activacion"
                                                    value="<?= implode(', ', json_decode($config['palabras_activacion'] ?? '[]', true)) ?>"
                                                    placeholder="hola, info, precio, consulta">
                                                <small class="text-muted">Dejar vacío para responder a todos los mensajes</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Tab Templates -->
                        <div class="tab-pane fade" id="templates" role="tabpanel">
                            <h4>Templates para Bot de <?= ucfirst($config['tipo_bot']) ?></h4>

                            <?php if ($config['tipo_bot'] == 'ventas'): ?>
                                <p class="text-muted">
                                    <i class="fas fa-shopping-cart"></i>
                                    Mostrando plantillas diseñadas para vender productos y tomar pedidos
                                </p>
                            <?php else: ?>
                                <p class="text-muted">
                                    <i class="fas fa-calendar-check"></i>
                                    Mostrando plantillas diseñadas para agendar citas y reservas
                                </p>
                            <?php endif; ?>

                            <div class="row mt-3">
                                <?php
                                $templates_filtrados = array_filter($templates, fn($t) => $t['tipo_bot'] == $config['tipo_bot']);

                                if (empty($templates_filtrados)):
                                ?>
                                    <div class="col-12">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            No hay templates disponibles para bot de <?= $config['tipo_bot'] ?>.
                                        </div>
                                    </div>
                                    <?php
                                else:
                                    foreach ($templates_filtrados as $template):
                                    ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title">
                                                        <?php
                                                        $iconos = [
                                                            'restaurante' => 'fa-utensils',
                                                            'tienda' => 'fa-store',
                                                            'clinica' => 'fa-hospital',
                                                            'salon' => 'fa-cut'
                                                        ];
                                                        $icono = $iconos[$template['tipo_negocio']] ?? 'fa-building';
                                                        ?>
                                                        <i class="fas <?= $icono ?>"></i> <?= htmlspecialchars($template['nombre_template']) ?>
                                                    </h5>

                                                    <div class="template-preview mb-2" style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 0.85em;">
                                                        <strong>Preview:</strong><br>
                                                        <?= nl2br(htmlspecialchars(substr($template['personalidad_bot'] ?? '', 0, 150))) ?>...
                                                    </div>

                                                    <button class="btn btn-primary btn-sm" onclick="cargarTemplate(<?= $template['id'] ?>)">
                                                        <i class="fas fa-download"></i> Usar este template
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Tip:</strong> Para ver templates de otro tipo, cambia el "Tipo de Bot" en la pestaña Configuración y guarda los cambios.
                            </div>
                        </div>

                        <!-- Tab Personalización IA -->
                        <div class="tab-pane fade" id="prompts" role="tabpanel">
                            <div class="row">
                                <div class="col-md-12">

                                    <!-- 1. PERSONALIDAD DEL BOT -->
                                    <div class="card card-primary mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-robot"></i> Personalidad del Bot
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Define la personalidad de tu asistente virtual:</label>
                                                <textarea class="form-control" id="system_prompt" name="system_prompt" rows="4"
                                                    placeholder="Ejemplo: Tu nombre es Sofia, eres una asistente virtual amigable y profesional. Usas un tono cordial pero no demasiado informal. Utilizas emojis de manera moderada (😊 ✅ 📍). Siempre saludas con entusiasmo y te despides cordialmente."><?= htmlspecialchars($config['system_prompt'] ?? '') ?></textarea>
                                                <small class="text-muted">
                                                    Define: nombre del bot, tono de voz, nivel de formalidad, uso de emojis, forma de saludar
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. INSTRUCCIONES ESPECÍFICAS SEGÚN TIPO DE BOT -->
                                    <?php if ($config['tipo_bot'] == 'ventas'): ?>
                                        <div class="card card-success mb-3">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-shopping-cart"></i> Estrategia de Ventas
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Instrucciones específicas para vender:</label>
                                                    <textarea class="form-control" id="prompt_ventas" name="prompt_ventas" rows="8"><?= htmlspecialchars($config['prompt_ventas'] ?? '') ?></textarea>
                                                    <small class="text-muted">
                                                        Define cómo debe vender, qué sugerir, cómo cerrar ventas, etc.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="card card-info mb-3">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-calendar-check"></i> Protocolo de Agendamiento
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Instrucciones para agendar citas:</label>
                                                    <textarea class="form-control" id="prompt_citas" name="prompt_citas" rows="8"><?= htmlspecialchars($config['prompt_citas'] ?? '') ?></textarea>
                                                    <small class="text-muted">
                                                        Define el proceso de agendamiento, qué información solicitar, etc.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- 3. INFORMACIÓN DEL NEGOCIO -->
                                    <div class="card card-warning mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-building"></i> Información del Negocio
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Base de conocimiento del bot (información real de tu negocio):</label>
                                                <textarea class="form-control" id="business_info" name="business_info" rows="12"><?= htmlspecialchars($config['business_info'] ?? '') ?></textarea>
                                                <small class="text-muted">
                                                    Incluye: nombre, dirección, horarios, productos/servicios, precios, métodos de pago, políticas
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4. RESPUESTAS RÁPIDAS -->
                                    <div class="card card-secondary">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-bolt"></i> Respuestas Rápidas
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Configura respuestas instantáneas para preguntas frecuentes:</p>
                                            <div id="respuestas_rapidas_container">
                                                <!-- Las respuestas rápidas se cargan aquí -->
                                            </div>
                                            <button type="button" class="btn btn-success btn-sm" onclick="agregarRespuestaRapida()">
                                                <i class="fas fa-plus"></i> Agregar respuesta rápida
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Tab Escalamiento -->
                        <?php if (tieneEscalamiento()): ?>
                            <div class="tab-pane fade" id="escalamiento" role="tabpanel">
                                <h4>Configuración de Escalamiento a Humano</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Máximo de mensajes sin resolver:</label>
                                            <input type="number" class="form-control" name="max_mensajes_sin_resolver"
                                                value="<?= $escalamiento_config['max_mensajes_sin_resolver'] ?? 5 ?>" min="1" max="20">
                                            <small class="text-muted">Después de X mensajes sin resolver, escalar a humano</small>
                                        </div>

                                        <div class="form-group">
                                            <label>Mensaje de escalamiento:</label>
                                            <textarea class="form-control" name="mensaje_escalamiento" rows="3"><?= htmlspecialchars($escalamiento_config['mensaje_escalamiento'] ?? 'Te estoy transfiriendo con un asesor humano que te ayudará mejor.') ?></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Palabras clave para escalamiento (separadas por coma):</label>
                                            <textarea class="form-control" name="palabras_escalamiento" rows="5"
                                                placeholder="hablar con humano, operador, ayuda real, problema, reclamo, queja"><?= implode(', ', $escalamiento_config['palabras_clave'] ?? []) ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="text-primary"><i class="fas fa-bell"></i> Notificaciones de Escalamiento</h5>

                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="notificar_escalamiento"
                                            name="notificar_escalamiento" <?= $config['notificar_escalamiento'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="notificar_escalamiento">
                                            <strong>Notificar escalamiento por WhatsApp</strong> - Enviar alerta a números de soporte
                                        </label>
                                    </div>
                                </div>

                                <div id="config_notificacion" style="<?= !$config['notificar_escalamiento'] ? 'display:none;' : '' ?>">
                                    <div class="form-group">
                                        <label>Números a notificar (separados por coma):</label>
                                        <input type="text" class="form-control" name="numeros_notificacion"
                                            value="<?= implode(', ', json_decode($config['numeros_notificacion'] ?? '[]', true)) ?>"
                                            placeholder="+51999999999, +51888888888">
                                        <small class="text-muted">Incluye el código de país. Estos números recibirán alertas cuando un cliente necesite atención humana.</small>
                                    </div>

                                    <div class="form-group">
                                        <label>Plantilla de mensaje de notificación:</label>
                                        <textarea class="form-control" name="mensaje_notificacion" rows="5"><?= htmlspecialchars($config['mensaje_notificacion'] ?? '🚨 *ESCALAMIENTO URGENTE*

Cliente: {numero}
Último mensaje: "{ultimo_mensaje}"
Motivo: {motivo}
Hora: {hora}

Por favor atiende este caso lo antes posible.') ?></textarea>
                                        <small class="text-muted">Variables disponibles: {numero}, {ultimo_mensaje}, {motivo}, {hora}</small>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Cuando se escala a humano, el bot deja de responder automáticamente hasta que un operador marque la conversación como resuelta.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Tab Modo Prueba -->
                        <div class="tab-pane fade" id="pruebas" role="tabpanel">
                            <h4>Modo de Prueba</h4>

                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="modo_prueba"
                                        name="modo_prueba" <?= $config['modo_prueba'] ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="modo_prueba">
                                        <strong>Activar Modo Prueba</strong> - El bot solo responderá al número de prueba
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="numero_prueba_group" style="<?= !$config['modo_prueba'] ? 'display:none;' : '' ?>">
                                <label>Número de WhatsApp para pruebas:</label>
                                <input type="text" class="form-control" name="numero_prueba"
                                    value="<?= htmlspecialchars($config['numero_prueba'] ?? '') ?>"
                                    placeholder="+51999999999">
                                <small class="text-muted">Solo este número recibirá respuestas del bot mientras esté en modo prueba</small>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Probar Bot en Tiempo Real</h5>
                                </div>
                                <div class="card-body">
                                    <div class="direct-chat-messages" id="chatTest" style="height: 400px; overflow-y: auto; background: #f4f4f4;">
                                        <div class="text-center text-muted p-4">
                                            <i class="fas fa-comments fa-3x mb-3"></i>
                                            <p>Escribe un mensaje para probar el bot...</p>
                                        </div>
                                    </div>
                                    <div class="input-group mt-3">
                                        <input type="text" class="form-control" id="mensajePrueba"
                                            placeholder="Escribe un mensaje de prueba...">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" onclick="enviarMensajePrueba()">
                                                <i class="fas fa-paper-plane"></i> Enviar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Métricas -->
                        <div class="tab-pane fade" id="metricas" role="tabpanel">
                            <h4>Métricas del Bot - Hoy</h4>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info"><i class="fas fa-play"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Conversaciones Iniciadas</span>
                                            <span class="info-box-number"><?= $metricas_hoy['conversaciones_iniciadas'] ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Conversaciones Completadas</span>
                                            <span class="info-box-number"><?= $metricas_hoy['conversaciones_completadas'] ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-warning"><i class="fas fa-user-tie"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Escaladas a Humano</span>
                                            <span class="info-box-number"><?= $metricas_hoy['escalamientos'] ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-danger"><i class="fas fa-clock"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Hora Pico</span>
                                            <span class="info-box-number"><?= $metricas_hoy['hora_pico'] ?? 'N/A' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mt-4">Preguntas Más Frecuentes de Hoy</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Pregunta/Tema</th>
                                            <th>Frecuencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $preguntas_frecuentes = json_decode($metricas_hoy['preguntas_frecuentes'] ?? '[]', true);
                                        if (empty($preguntas_frecuentes)):
                                        ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">
                                                    No hay datos de preguntas frecuentes aún
                                                </td>
                                            </tr>
                                            <?php else:
                                            foreach ($preguntas_frecuentes as $pregunta => $frecuencia): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($pregunta) ?></td>
                                                    <td><?= $frecuencia ?></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button class="btn btn-success" onclick="guardarConfiguracion()">
                        <i class="fas fa-save"></i> Guardar Configuración
                    </button>
                    <button class="btn btn-info" id="btnVerificarConfig">
                        <i class="fas fa-check-circle"></i> Verificar Configuración
                    </button>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    // Variables globales
    let respuestaRapidaIndex = <?= count($respuestas_rapidas) / 2 + 1 ?>;

    $(document).ready(function() {
        // Mostrar/ocultar campos según tipo de bot
        $('#tipo_bot').on('change', function() {
            if ($(this).val() === 'ventas') {
                $('#prompt_ventas_group').show();
                $('#prompt_citas_group').hide();
            } else {
                $('#prompt_ventas_group').hide();
                $('#prompt_citas_group').show();
            }
        }).trigger('change');

        // NUEVO CÓDIGO: Mensaje cuando cambie el tipo de bot
        $('input[name="tipo_bot"]').on('change', function() {
            const tipo = $(this).val();

            // Solo mostrar un mensaje informativo
            Swal.fire({
                icon: 'info',
                title: 'Tipo de bot cambiado',
                text: `Para ver las plantillas de bot de ${tipo}, guarda los cambios primero.`,
                timer: 3000,
                showConfirmButton: false
            });
        });

        // Mostrar/ocultar número de prueba
        $('#modo_prueba').on('change', function() {
            if ($(this).is(':checked')) {
                $('#numero_prueba_group').slideDown();
            } else {
                $('#numero_prueba_group').slideUp();
            }
        });

        // Permitir enviar con Enter en chat de prueba
        $('#mensajePrueba').on('keypress', function(e) {
            if (e.which === 13) {
                enviarMensajePrueba();
            }
        });

        $('#btnVerificarConfig').on('click', function() {
            verificarConfiguracion();
        });
    });


    function cambiarTipoBot(tipo) {
        // Actualizar radio button
        $(`#tipo_bot_${tipo}`).prop('checked', true);

        // Actualizar cards
        $('.card').removeClass('border-success border-info');
        $('.card i.fa-shopping-cart, .card i.fa-calendar-check').removeClass('text-success text-info').addClass('text-muted');

        if (tipo === 'ventas') {
            $('.card:has(#tipo_bot_ventas)').addClass('border-success');
            $('.card:has(#tipo_bot_ventas) i').removeClass('text-muted').addClass('text-success');
        } else {
            $('.card:has(#tipo_bot_citas)').addClass('border-info');
            $('.card:has(#tipo_bot_citas) i').removeClass('text-muted').addClass('text-info');
        }

        // Mostrar alerta de cambio
        Swal.fire({
            icon: 'info',
            title: 'Tipo de bot cambiado',
            text: `Ahora verás plantillas para bot de ${tipo}. Recuerda guardar los cambios.`,
            timer: 3000,
            showConfirmButton: false
        });
    }

    function agregarRespuestaRapida() {
        const html = `
        <div class="respuesta-rapida-item mb-3">
            <div class="row">
                <div class="col-md-5">
                    <input type="text" class="form-control" 
                        name="respuestas_rapidas[pregunta${respuestaRapidaIndex}]" 
                        placeholder="Pregunta frecuente">
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" 
                        name="respuestas_rapidas[respuesta${respuestaRapidaIndex}]" 
                        placeholder="Respuesta">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarRespuestaRapida(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

        $('#respuestas_rapidas_container').append(html);
        respuestaRapidaIndex++;
    }

    function eliminarRespuestaRapida(btn) {
        $(btn).closest('.respuesta-rapida-item').remove();
    }

    function cargarTemplate(templateId) {
        Swal.fire({
            title: '¿Cargar template?',
            html: `
            <p>Esto cargará la configuración del template en los siguientes campos:</p>
            <ul style="text-align: left;">
                <li>✅ Personalidad del Bot</li>
                <li>✅ Estrategia de ${$('#tipo_bot_ventas').is(':checked') ? 'Ventas' : 'Agendamiento'}</li>
                <li>✅ Información del Negocio (ejemplo)</li>
                <li>✅ Respuestas Rápidas</li>
            </ul>
            <p class="text-warning"><small>Los cambios NO se guardarán hasta que hagas clic en "Guardar Configuración"</small></p>
        `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cargar template',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: API_URL + "/bot/cargar-template.php",
                    method: 'GET',
                    data: {
                        id: templateId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            const template = response.data;

                            // 1. Cambiar a la pestaña de personalización
                            $('#prompts-tab').tab('show');

                            // 2. Cargar personalidad del bot
                            $('#system_prompt').val(template.personalidad_bot);

                            // 3. Cargar instrucciones según el tipo
                            if (template.tipo_bot === 'ventas' && template.instrucciones_ventas) {
                                $('#prompt_ventas').val(template.instrucciones_ventas);
                            } else if (template.tipo_bot === 'citas' && template.instrucciones_citas) {
                                $('#prompt_citas').val(template.instrucciones_citas);
                            }

                            // 4. SIEMPRE cargar el ejemplo de información del negocio
                            if (template.informacion_negocio_ejemplo) {
                                $('#business_info').val(template.informacion_negocio_ejemplo);
                            }

                            // 5. Cargar respuestas rápidas
                            $('#respuestas_rapidas_container').empty();
                            respuestaRapidaIndex = 1;

                            if (template.respuestas_rapidas_template && typeof template.respuestas_rapidas_template === 'object') {
                                for (const [pregunta, respuesta] of Object.entries(template.respuestas_rapidas_template)) {
                                    agregarRespuestaRapidaConDatos(pregunta, respuesta);
                                }
                            }

                            // 6. Mostrar mensaje de éxito
                            Swal.fire({
                                icon: 'success',
                                title: 'Template cargado',
                                html: `
                                <p>✅ Se ha cargado el template <strong>"${template.nombre_template}"</strong></p>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Importante:</strong> Los cambios NO están guardados aún.
                                </div>
                                <p class="mt-2"><strong>Ahora debes:</strong></p>
                                <ol style="text-align: left;">
                                    <li>Personalizar los campos con tu información real</li>
                                    <li>Reemplazar los textos [ENTRE CORCHETES]</li>
                                    <li>Hacer clic en "Guardar Configuración" cuando termines</li>
                                </ol>
                            `,
                                confirmButtonText: 'Entendido'
                            });

                        } else {
                            Swal.fire('Error', 'No se pudo cargar el template', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error cargando template:', error);
                        Swal.fire('Error', 'Error al cargar el template', 'error');
                    }
                });
            }
        });
    }

    // Función auxiliar para agregar respuesta rápida con datos
    function agregarRespuestaRapidaConDatos(pregunta, respuesta) {
        const html = `
        <div class="respuesta-rapida-item mb-3">
            <div class="row">
                <div class="col-md-5">
                    <input type="text" class="form-control" 
                        name="respuestas_rapidas[pregunta${respuestaRapidaIndex}]" 
                        placeholder="Pregunta frecuente"
                        value="${pregunta}">
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" 
                        name="respuestas_rapidas[respuesta${respuestaRapidaIndex}]" 
                        placeholder="Respuesta"
                        value="${respuesta}">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarRespuestaRapida(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
        $('#respuestas_rapidas_container').append(html);
        respuestaRapidaIndex++;
    }

    function guardarConfiguracion() {
        // Recopilar todos los datos correctamente
        const formData = {
            // Configuración básica
            activo: $('#activo').is(':checked') ? 1 : 0,
            tipo_bot: $('input[name="tipo_bot"]:checked').val(),
            templates_activo: $('#templates_activo').is(':checked') ? 1 : 0,
            delay_respuesta: $('input[name="delay_respuesta"]').val(),
            horario_inicio: $('input[name="horario_inicio"]').val(),
            horario_fin: $('input[name="horario_fin"]').val(),
            mensaje_fuera_horario: $('textarea[name="mensaje_fuera_horario"]').val(),
            responder_no_registrados: $('#responder_no_registrados').is(':checked') ? 1 : 0,
            palabras_activacion: $('input[name="palabras_activacion"]').val(),

            // Prompts - IMPORTANTE: Obtener correctamente
            system_prompt: $('#system_prompt').val(),
            business_info: $('#business_info').val(),
            prompt_ventas: $('#prompt_ventas').val(),
            prompt_citas: $('#prompt_citas').val(),

            // Escalamiento
            max_mensajes_sin_resolver: $('input[name="max_mensajes_sin_resolver"]').val(),
            palabras_escalamiento: $('textarea[name="palabras_escalamiento"]').val(),
            mensaje_escalamiento: $('textarea[name="mensaje_escalamiento"]').val(),
            // Notificaciones de escalamiento
            notificar_escalamiento: $('#notificar_escalamiento').is(':checked') ? 1 : 0,
            numeros_notificacion: $('input[name="numeros_notificacion"]').val(),
            mensaje_notificacion: $('textarea[name="mensaje_notificacion"]').val(),

            // Modo prueba
            modo_prueba: $('#modo_prueba').is(':checked') ? 1 : 0,
            numero_prueba: $('input[name="numero_prueba"]').val()
        };

        // Respuestas rápidas - recopilar correctamente
        const respuestasRapidas = {};
        $('.respuesta-rapida-item').each(function() {
            const pregunta = $(this).find('input').eq(0).val().trim();
            const respuesta = $(this).find('input').eq(1).val().trim();
            if (pregunta && respuesta) {
                respuestasRapidas[pregunta] = respuesta;
            }
        });

        // Agregar respuestas rápidas al formData
        formData['respuestas_rapidas'] = respuestasRapidas;

        // Mostrar loading
        Swal.fire({
            title: 'Guardando configuración...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar con AJAX
        $.ajax({
            url: API_URL + "/bot/configurar.php",
            method: 'POST',
            data: formData,
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: 'Configuración guardada correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Recargar la página para actualizar todo
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message || 'Error al guardar', 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                Swal.fire('Error', 'Error de conexión con el servidor', 'error');
            }
        });
    }

    function enviarMensajePrueba() {
        const mensaje = $('#mensajePrueba').val().trim();
        if (!mensaje) return;

        // Agregar mensaje del usuario
        $('#chatTest').append(`
        <div class="direct-chat-msg right">
            <div class="direct-chat-text bg-primary">
                ${mensaje}
            </div>
        </div>
    `);

        $('#mensajePrueba').val('');

        // Mostrar typing
        $('#chatTest').append(`
        <div class="direct-chat-msg" id="typing">
            <div class="direct-chat-text">
                <i class="fas fa-ellipsis-h"></i> Bot escribiendo...
            </div>
        </div>
    `);

        // Scroll al fondo
        $('#chatTest').scrollTop($('#chatTest')[0].scrollHeight);

        // Enviar al bot
        $.post(API_URL + "/bot/test.php", {
            mensaje: mensaje
        }, function(response) {
            $('#typing').remove();

            if (response.success) {
                $('#chatTest').append(`
                <div class="direct-chat-msg">
                    <div class="direct-chat-text">
                        ${response.data.respuesta}
                    </div>
                    <small class="text-muted">
                        Tokens: ${response.data.tokens_usados} | 
                        Tiempo: ${response.data.tiempo_respuesta}ms
                    </small>
                </div>
            `);
            } else {
                $('#chatTest').append(`
                <div class="direct-chat-msg">
                    <div class="direct-chat-text bg-danger">
                        Error: ${response.message}
                    </div>
                </div>
            `);
            }

            $('#chatTest').scrollTop($('#chatTest')[0].scrollHeight);
        }).fail(function() {
            $('#typing').remove();
            $('#chatTest').append(`
            <div class="direct-chat-msg">
                <div class="direct-chat-text bg-danger">
                    Error de conexión con el servidor
                </div>
            </div>
        `);
        });
    }

    function verificarConfiguracion() {
        $.get(API_URL + "/bot/verificar-config.php", function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'Configuración Actual',
                    html: `
                    <div style="text-align: left;">
                        <p><strong>Sistema:</strong></p>
                        <ul>
                            <li>Bot activo: ${response.data.activo ? 'SÍ ✅' : 'NO ❌'}</li>
                            <li>API Key: ${response.data.api_key_configurada ? 'Configurada ✅' : 'NO CONFIGURADA ❌'}</li>
                            <li>Modelo IA: ${response.data.modelo_ai}</li>
                            <li>Temperatura: ${response.data.temperatura}</li>
                        </ul>
                        
                        <p><strong>Configuración:</strong></p>
                        <ul>
                            <li>System Prompt: ${response.data.system_prompt_length} caracteres</li>
                            <li>Info Negocio: ${response.data.business_info_length} caracteres</li>
                            <li>Palabras activación: ${response.data.palabras_activacion}</li>
                            <li>Delay respuesta: ${response.data.delay_respuesta} segundos</li>
                        </ul>
                        
                        <p><strong>Comportamiento:</strong></p>
                        <ul>
                            <li>Horario configurado: ${response.data.horario_configurado ? 'SÍ ✅' : 'NO ❌'}</li>
                            <li>Responder a no registrados: ${response.data.responder_no_registrados ? 'SÍ ✅' : 'NO ❌'}</li>
                            <li>Tiene emojis: ${response.data.tiene_emojis ? 'SÍ ✅' : 'NO ❌'}</li>
                        </ul>
                    </div>
                `,
                    icon: 'info',
                    width: '600px'
                });
            }
        });
    }

    // Mostrar/ocultar configuración de notificación para escalar 
    $('#notificar_escalamiento').on('change', function() {
        if ($(this).is(':checked')) {
            $('#config_notificacion').slideDown();
        } else {
            $('#config_notificacion').slideUp();
        }
    });
</script>

<style>
    .card {
        box-shadow: 0 0 1px rgb(24 0 227), 0 1px 3px rgb(41 3 249 / 97%) !important;
        margin-bottom: 1rem !important;
    }
</style>