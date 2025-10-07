<?php
$current_page = 'bot-config';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../../../includes/plan-limits.php';

// Obtener configuración actual
$stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch();

// Si no existe configuración, crear una por defecto
if (!$config) {
    $stmt = $pdo->prepare("INSERT INTO configuracion_bot (empresa_id) VALUES (?)");
    $stmt->execute([$empresa_id]);

    $stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
}

// Decodificar JSONs
$escalamiento_config = json_decode($config['escalamiento_config'] ?? '{}', true);

// Obtener notificaciones
$stmt = $pdo->prepare("SELECT * FROM notificaciones_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$notificaciones = $stmt->fetch();

// Si no existe, crear registro vacío
if (!$notificaciones) {
    $stmt = $pdo->prepare("INSERT INTO notificaciones_bot (empresa_id) VALUES (?)");
    $stmt->execute([$empresa_id]);
    
    $stmt = $pdo->prepare("SELECT * FROM notificaciones_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $notificaciones = $stmt->fetch();
}

// Obtener templates disponibles
$stmt = $pdo->query("SELECT * FROM bot_templates WHERE activo = 1 ORDER BY tipo_bot, tipo_negocio, nombre_template");
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
                        <li class="nav-item">
                            <a class="nav-link" id="notificaciones-tab" data-toggle="pill" href="#notificaciones" role="tab">
                                <i class="fas fa-bell"></i> Notificaciones
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
                                                <div class="col-md-4">
                                                    <div class="card" style="cursor: pointer;" onclick="$('#tipo_bot_ventas').prop('checked', true).trigger('change')">
                                                        <div class="card-body text-center">
                                                            <i class="fas fa-shopping-cart fa-3x text-success"></i>
                                                            <h5 class="mt-2">Ventas</h5>
                                                            <p class="text-muted small">Productos, pedidos, delivery</p>
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
                                                <div class="col-md-4">
                                                    <div class="card" style="cursor: pointer;" onclick="$('#tipo_bot_citas').prop('checked', true).trigger('change')">
                                                        <div class="card-body text-center">
                                                            <i class="fas fa-calendar-check fa-3x text-info"></i>
                                                            <h5 class="mt-2">Citas</h5>
                                                            <p class="text-muted small">Agendamiento, reservas</p>
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
                                                <div class="col-md-4">
                                                    <div class="card" style="cursor: pointer;" onclick="$('#tipo_bot_soporte').prop('checked', true).trigger('change')">
                                                        <div class="card-body text-center">
                                                            <i class="fas fa-headset fa-3x text-warning"></i>
                                                            <h5 class="mt-2">Soporte</h5>
                                                            <p class="text-muted small">ISP, tickets, SaaS</p>
                                                            <div class="form-check">
                                                                <label  class="form-check-label">Muy pronto</label>
                                                                <!-- <input class="form-check-input" type="radio" name="tipo_bot" id="tipo_bot_soporte" value="soporte"
                                                                    <?= $config['tipo_bot'] == 'soporte' ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="tipo_bot_soporte">
                                                                    Seleccionar
                                                                </label> -->
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
                            </form>
                        </div>

                        <!-- Tab Templates -->
                        <div class="tab-pane fade" id="templates" role="tabpanel">
                            <h4>Templates para Bot de <span id="tipoActual"><?= ucfirst($config['tipo_bot']) ?></span></h4>

                            <div id="descripcionTipo" class="alert alert-info">
                                <!-- Se llena dinámicamente con JavaScript -->
                            </div>

                            <div class="row mt-3" id="contenedorTemplates">
                                <!-- Se llena dinámicamente con JavaScript -->
                            </div>

                            <div class="alert alert-warning mt-3">
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
                                                    placeholder="Ejemplo: Tu nombre es Sofia, eres una asistente virtual amigable y profesional..."><?= htmlspecialchars($config['system_prompt'] ?? '') ?></textarea>
                                                <small class="text-muted">
                                                    Define: nombre del bot, tono de voz, nivel de formalidad, uso de emojis, forma de saludar
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. INSTRUCCIONES ESPECÍFICAS SEGÚN TIPO DE BOT -->
                                    <div id="instruccionesEspecificas">
                                        <!-- Se llena dinámicamente según el tipo -->
                                    </div>

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

                                </div>
                            </div>
                        </div>

                        <!-- Tab Notificaciones -->
                        <div class="tab-pane fade" id="notificaciones" role="tabpanel">
                            <h4>Configuración de Notificaciones por WhatsApp</h4>
                            <p class="text-muted">Recibe alertas automáticas en WhatsApp cuando ocurran eventos importantes</p>

                            <form id="formNotificaciones">
                                <!-- Números de notificación (compartidos) -->
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-phone"></i> Números para Notificaciones
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Números de WhatsApp (separados por coma):</label>
                                            <input type="text" class="form-control" name="numeros_notificacion"
                                                value="<?= implode(', ', json_decode($notificaciones['numeros_notificacion'] ?? '[]', true)) ?>"
                                                placeholder="+51999999999, +51888888888">
                                            <small class="text-muted">Incluye el código de país. Estos números recibirán todas las notificaciones activas.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Escalamiento - SIEMPRE VISIBLE -->
                                    <div class="col-md-4" id="card-escalamiento">
                                        <div class="card card-warning">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-exclamation-triangle"></i> Escalamiento
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="notificar_escalamiento"
                                                            name="notificar_escalamiento" <?= $notificaciones['notificar_escalamiento'] ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="notificar_escalamiento">
                                                            <strong>Activar notificaciones</strong>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Mensaje de notificación:</label>
                                                    <textarea class="form-control" name="mensaje_escalamiento" rows="6"><?= htmlspecialchars($notificaciones['mensaje_escalamiento'] ?? '🚨 *ESCALAMIENTO*

Cliente: {nombre_cliente}
Número: {numero_cliente}
Motivo: {motivo}

⏰ {fecha_hora}') ?></textarea>
                                                    <small class="text-muted">Variables: {nombre_cliente}, {numero_cliente}, {motivo}, {fecha_hora}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ventas - SOLO para bot de ventas y soporte -->
                                    <div class="col-md-4" id="card-ventas" style="display: none;">
                                        <div class="card card-success">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-shopping-cart"></i> Ventas
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="notificar_ventas"
                                                            name="notificar_ventas" <?= $notificaciones['notificar_ventas'] ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="notificar_ventas">
                                                            <strong>Activar notificaciones</strong>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Mensaje de notificación:</label>
                                                    <textarea class="form-control" name="mensaje_ventas" rows="6"><?= htmlspecialchars($notificaciones['mensaje_ventas'] ?? '🛍️ *NUEVA VENTA*

Cliente: {nombre_cliente}
Productos: {productos}
Total: {total}

⏰ {fecha_hora}') ?></textarea>
                                                    <small class="text-muted">Variables: {nombre_cliente}, {productos}, {total}, {fecha_hora}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Citas - SOLO para bot de citas y soporte -->
                                    <div class="col-md-4" id="card-citas" style="display: none;">
                                        <div class="card card-info">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-calendar-check"></i> Citas
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="notificar_citas"
                                                            name="notificar_citas" <?= $notificaciones['notificar_citas'] ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="notificar_citas">
                                                            <strong>Activar notificaciones</strong>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Mensaje de notificación:</label>
                                                    <textarea class="form-control" name="mensaje_citas" rows="6"><?= htmlspecialchars($notificaciones['mensaje_citas'] ?? '📅 *NUEVA CITA*

Cliente: {nombre_cliente}
Servicio: {servicio}
Fecha: {fecha_cita}
Hora: {hora_cita}

⏰ {fecha_hora}') ?></textarea>
                                                    <small class="text-muted">Variables: {nombre_cliente}, {servicio}, {fecha_cita}, {hora_cita}, {fecha_hora}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Notificaciones
                                </button>
                            </form>
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
// DATOS DE TEMPLATES (PHP → JavaScript)
const ALL_TEMPLATES = <?= json_encode($templates) ?>;
const TIPO_BOT_ACTUAL = '<?= $config['tipo_bot'] ?? 'ventas' ?>';

$(document).ready(function() {
    // Inicializar UI según tipo de bot
    actualizarUISegunTipo(TIPO_BOT_ACTUAL);
    
    // Cambio de tipo de bot
    $('input[name="tipo_bot"]').on('change', function() {
        const tipo = $(this).val();
        actualizarUISegunTipo(tipo);
        
        Swal.fire({
            icon: 'info',
            title: 'Tipo de bot cambiado',
            text: `Mostrando plantillas y notificaciones para bot de ${tipo}. Recuerda guardar los cambios.`,
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

// Actualizar UI completa según tipo de bot
function actualizarUISegunTipo(tipo) {
    // 1. Actualizar descripción del tipo
    const descripciones = {
        'ventas': '<i class="fas fa-shopping-cart"></i> Mostrando plantillas para vender productos y tomar pedidos',
        'citas': '<i class="fas fa-calendar-check"></i> Mostrando plantillas para agendar citas y reservas',
        'soporte': '<i class="fas fa-headset"></i> Mostrando plantillas para soporte técnico, ISP y SaaS'
    };
    
    $('#tipoActual').text(tipo.charAt(0).toUpperCase() + tipo.slice(1));
    $('#descripcionTipo').html(descripciones[tipo]);
    
    // 2. Filtrar y mostrar templates
    cargarTemplatesSegunTipo(tipo);
    
    // 3. Actualizar sección de instrucciones
    actualizarInstrucciones(tipo);
    
    // 4. Mostrar/ocultar tarjetas de notificaciones según tipo
    actualizarNotificacionesSegunTipo(tipo);
}

// Filtrar templates por tipo
function cargarTemplatesSegunTipo(tipo) {
    const templatesFiltrados = ALL_TEMPLATES.filter(t => t.tipo_bot === tipo);
    
    let html = '';
    
    if (templatesFiltrados.length === 0) {
        html = `
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No hay templates disponibles para bot de ${tipo}.
                </div>
            </div>
        `;
    } else {
        const iconos = {
            'restaurante': 'fa-utensils',
            'tienda': 'fa-store',
            'farmacia': 'fa-pills',
            'ferreteria': 'fa-tools',
            'clinica': 'fa-hospital',
            'salon': 'fa-cut',
            'dental': 'fa-tooth',
            'isp': 'fa-wifi',
            'soporte_tecnico': 'fa-headset',
            'saas': 'fa-laptop-code'
        };
        
        templatesFiltrados.forEach(template => {
            const icono = iconos[template.tipo_negocio] || 'fa-building';
            
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas ${icono}"></i> ${template.nombre_template}
                            </h5>

                            <div class="template-preview mb-2" style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 0.85em;">
                                <strong>Preview:</strong><br>
                                ${template.personalidad_bot.substring(0, 150)}...
                            </div>

                            <button class="btn btn-primary btn-sm" onclick="cargarTemplate(${template.id})">
                                <i class="fas fa-download"></i> Usar este template
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $('#contenedorTemplates').html(html);
}

// Actualizar instrucciones según tipo
function actualizarInstrucciones(tipo) {
    let html = '';
    
    if (tipo === 'ventas') {
        html = `
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
        `;
    } else if (tipo === 'citas') {
        html = `
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
        `;
    } else if (tipo === 'soporte') {
        html = `
            <div class="card card-warning mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-headset"></i> Protocolo de Soporte
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Para ventas de planes/servicios:</label>
                                <textarea class="form-control" id="prompt_ventas" name="prompt_ventas" rows="8"><?= htmlspecialchars($config['prompt_ventas'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Para agendar visitas técnicas:</label>
                                <textarea class="form-control" id="prompt_citas" name="prompt_citas" rows="8"><?= htmlspecialchars($config['prompt_citas'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    $('#instruccionesEspecificas').html(html);
}

// Controlar visibilidad de notificaciones según tipo de bot
function actualizarNotificacionesSegunTipo(tipo) {
    // Escalamiento siempre visible
    $('#card-escalamiento').show();
    
    if (tipo === 'ventas') {
        $('#card-ventas').show();
        $('#card-citas').hide();
    } else if (tipo === 'citas') {
        $('#card-ventas').hide();
        $('#card-citas').show();
    } else if (tipo === 'soporte') {
        // Soporte usa los 3
        $('#card-ventas').show();
        $('#card-citas').show();
    }
}

function cargarTemplate(templateId) {
    Swal.fire({
        title: '¿Cargar template?',
        html: `
        <p>Esto cargará la configuración del template en los siguientes campos:</p>
        <ul style="text-align: left;">
            <li>✅ Personalidad del Bot</li>
            <li>✅ Estrategia/Protocolo</li>
            <li>✅ Información del Negocio (ejemplo)</li>
            <li>✅ Mensajes de Notificación</li>
        </ul>
        <p class="text-warning"><small>Los cambios NO se guardarán hasta que hagas clic en "Guardar Configuración"</small></p>
    `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cargar template',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: API_URL + "/bot/cargar-template",
                method: 'GET',
                data: {id: templateId},
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const template = response.data;

                        // Cambiar a pestaña de personalización
                        $('#prompts-tab').tab('show');

                        // Cargar personalidad
                        $('#system_prompt').val(template.personalidad_bot);

                        // Cargar instrucciones según tipo
                        if (template.instrucciones_ventas) {
                            $('#prompt_ventas').val(template.instrucciones_ventas);
                        }
                        if (template.instrucciones_citas) {
                            $('#prompt_citas').val(template.instrucciones_citas);
                        }

                        // Cargar información del negocio
                        if (template.informacion_negocio_ejemplo) {
                            $('#business_info').val(template.informacion_negocio_ejemplo);
                        }

                        // Cargar mensajes de notificación (cambiar a tab de notificaciones)
                        if (template.mensaje_notificacion_escalamiento) {
                            $('textarea[name="mensaje_escalamiento"]').val(template.mensaje_notificacion_escalamiento);
                        }
                        if (template.mensaje_notificacion_ventas) {
                            $('textarea[name="mensaje_ventas"]').val(template.mensaje_notificacion_ventas);
                        }
                        if (template.mensaje_notificacion_citas) {
                            $('textarea[name="mensaje_citas"]').val(template.mensaje_notificacion_citas);
                        }

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
                error: function() {
                    Swal.fire('Error', 'Error al cargar el template', 'error');
                }
            });
        }
    });
}

function guardarConfiguracion() {
    // Recopilar datos
    const formData = {
        activo: $('#activo').is(':checked') ? 1 : 0,
        tipo_bot: $('input[name="tipo_bot"]:checked').val(),
        templates_activo: $('#templates_activo').is(':checked') ? 1 : 0,
        delay_respuesta: $('input[name="delay_respuesta"]').val(),
        horario_inicio: $('input[name="horario_inicio"]').val(),
        horario_fin: $('input[name="horario_fin"]').val(),
        mensaje_fuera_horario: $('textarea[name="mensaje_fuera_horario"]').val(),
        responder_no_registrados: $('#responder_no_registrados').is(':checked') ? 1 : 0,
        palabras_activacion: $('input[name="palabras_activacion"]').val(),
        system_prompt: $('#system_prompt').val(),
        business_info: $('#business_info').val(),
        prompt_ventas: $('#prompt_ventas').val(),
        prompt_citas: $('#prompt_citas').val(),
        max_mensajes_sin_resolver: $('input[name="max_mensajes_sin_resolver"]').val(),
        palabras_escalamiento: $('textarea[name="palabras_escalamiento"]').val(),
        mensaje_escalamiento: $('textarea[name="mensaje_escalamiento"]').val(),
        modo_prueba: $('#modo_prueba').is(':checked') ? 1 : 0,
        numero_prueba: $('input[name="numero_prueba"]').val()
    };

    Swal.fire({
        title: 'Guardando configuración...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: API_URL + "/bot/configurar",
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                // Guardar notificaciones también
                guardarNotificaciones().then(() => {
                    Swal.close();
                    Swal.fire({
                        icon: 'success',
                        title: 'Configuración guardada',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>✅ Configuración guardada correctamente</strong></p>
                                <hr>
                                <p><i class="fas fa-info-circle text-info"></i> <strong>Importante:</strong></p>
                                <ul style="margin-left: 20px;">
                                    <li>Los cambios se aplicarán en <strong>máximo 30 segundos</strong></li>
                                    <li>Si activaste <strong>Modo Prueba</strong>, espera 30 segundos antes de probar</li>
                                </ul>
                            </div>
                        `,
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        location.reload();
                    });
                });
            } else {
                Swal.close();
                Swal.fire('Error', response.message || 'Error al guardar', 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        }
    });
}

// Guardar notificaciones
function guardarNotificaciones() {
    return new Promise((resolve) => {
        const notifData = {
            numeros_notificacion: $('input[name="numeros_notificacion"]').val(),
            notificar_escalamiento: $('#notificar_escalamiento').is(':checked') ? 1 : 0,
            mensaje_escalamiento: $('textarea[name="mensaje_escalamiento"]').val(),
            notificar_ventas: $('#notificar_ventas').is(':checked') ? 1 : 0,
            mensaje_ventas: $('textarea[name="mensaje_ventas"]').val(),
            notificar_citas: $('#notificar_citas').is(':checked') ? 1 : 0,
            mensaje_citas: $('textarea[name="mensaje_citas"]').val()
        };
        
        $.post(API_URL + '/bot/guardar-notificaciones', notifData, function() {
            resolve();
        });
    });
}

// Guardar solo notificaciones
$('#formNotificaciones').on('submit', function(e) {
    e.preventDefault();
    
    guardarNotificaciones().then(() => {
        Swal.fire('Éxito', 'Notificaciones guardadas correctamente', 'success');
    });
});

function enviarMensajePrueba() {
    const mensaje = $('#mensajePrueba').val().trim();
    if (!mensaje) return;

    $('#chatTest').append(`
        <div class="direct-chat-msg right">
            <div class="direct-chat-text bg-primary">
                ${mensaje}
            </div>
        </div>
    `);

    $('#mensajePrueba').val('');

    $('#chatTest').append(`
        <div class="direct-chat-msg" id="typing">
            <div class="direct-chat-text">
                <i class="fas fa-ellipsis-h"></i> Bot escribiendo...
            </div>
        </div>
    `);

    $('#chatTest').scrollTop($('#chatTest')[0].scrollHeight);

    $.post(API_URL + "/bot/test", {mensaje: mensaje}, function(response) {
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
                    Error de conexión
                </div>
            </div>
        `);
    });
}

function verificarConfiguracion() {
    $.get(API_URL + "/bot/verificar-config", function(response) {
        if (response.success) {
            Swal.fire({
                title: 'Configuración Actual',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Sistema:</strong></p>
                        <ul>
                            <li>Bot activo: ${response.data.activo ? 'SÍ ✅' : 'NO ❌'}</li>
                            <li>Tipo: ${response.data.tipo_bot}</li>
                            <li>API Key: ${response.data.api_key_configurada ? 'Configurada ✅' : 'NO CONFIGURADA ❌'}</li>
                            <li>Modelo IA: ${response.data.modelo_ai}</li>
                        </ul>
                        
                        <p><strong>Configuración:</strong></p>
                        <ul>
                            <li>System Prompt: ${response.data.system_prompt_length} caracteres</li>
                            <li>Info Negocio: ${response.data.business_info_length} caracteres</li>
                            <li>Horario configurado: ${response.data.horario_configurado ? 'SÍ ✅' : 'NO ❌'}</li>
                        </ul>
                    </div>
                `,
                icon: 'info',
                width: '600px'
            });
        }
    });
}
</script>

<style>
    .card {
        box-shadow: 0 0 1px rgb(24 0 227), 0 1px 3px rgb(41 3 249 / 97%) !important;
        margin-bottom: 1rem !important;
    }
</style>