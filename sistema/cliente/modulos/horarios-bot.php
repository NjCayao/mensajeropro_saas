<?php
$current_page = 'horarios-bot';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Obtener horarios configurados
$stmt = $pdo->prepare("SELECT * FROM horarios_atencion WHERE empresa_id = ? ORDER BY dia_semana");
$stmt->execute([$empresa_id]);
$horarios = $stmt->fetchAll();

// Convertir a array asociativo por día
$horarios_por_dia = [];
foreach ($horarios as $h) {
    $horarios_por_dia[$h['dia_semana']] = $h;
}

// Obtener servicios disponibles
$stmt = $pdo->prepare("SELECT * FROM servicios_disponibles WHERE empresa_id = ? ORDER BY id");
$stmt->execute([$empresa_id]);
$servicios = $stmt->fetchAll();

// Obtener citas de hoy
$stmt = $pdo->prepare("
    SELECT * FROM citas_bot 
    WHERE empresa_id = ? AND fecha_cita = CURDATE() 
    ORDER BY hora_cita
");
$stmt->execute([$empresa_id]);
$citas_hoy = $stmt->fetchAll();

// Obtener citas de la semana
$stmt = $pdo->prepare("
    SELECT DATE(fecha_cita) as fecha, COUNT(*) as total 
    FROM citas_bot 
    WHERE empresa_id = ? 
    AND fecha_cita BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_cita)
");
$stmt->execute([$empresa_id]);
$citas_semana = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$dias_semana = [
    1 => 'Lunes',
    2 => 'Martes', 
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo'
];
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Configuración de Horarios y Citas</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/bot-config'); ?>">Bot IA</a></li>
                        <li class="breadcrumb-item active">Horarios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            <!-- Resumen de citas -->
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= count($citas_hoy) ?></h3>
                            <p>Citas Hoy</p>
                            <!-- Tab Google Calendar -->
                        <div class="tab-pane fade" id="google-calendar" role="tabpanel">
                            <h4>Integración con Google Calendar</h4>
                            
                            <?php 
                            // Obtener configuración de Google Calendar
                            $stmt = $pdo->prepare("SELECT google_calendar_activo, google_client_id, google_client_secret, 
                                                  google_refresh_token, google_calendar_id, sincronizar_citas 
                                                  FROM configuracion_bot WHERE empresa_id = ?");
                            $stmt->execute([$empresa_id]);
                            $google_config = $stmt->fetch();
                            ?>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card <?= ($google_config && $google_config['google_refresh_token']) ? 'border-success' : 'border-warning' ?>">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">
                                                <i class="fab fa-google"></i> Configuración de Google Calendar
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <form id="formGoogleCalendar">
                                                <div class="form-group">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" 
                                                               id="google_calendar_activo" name="google_calendar_activo"
                                                               <?= ($google_config && $google_config['google_calendar_activo']) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="google_calendar_activo">
                                                            <strong>Activar sincronización con Google Calendar</strong>
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Client ID:</label>
                                                    <input type="text" class="form-control" id="google_client_id" 
                                                           name="google_client_id" 
                                                           value="<?= htmlspecialchars($google_config['google_client_id'] ?? '') ?>"
                                                           placeholder="xxxxx.apps.googleusercontent.com">
                                                    <small class="text-muted">
                                                        Obtener desde 
                                                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank">
                                                            Google Cloud Console
                                                        </a>
                                                    </small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Client Secret:</label>
                                                    <input type="password" class="form-control" id="google_client_secret" 
                                                           name="google_client_secret" 
                                                           value="<?= htmlspecialchars($google_config['google_client_secret'] ?? '') ?>">
                                                </div>
                                                
                                                <?php if ($google_config && $google_config['google_refresh_token']): ?>
                                                    <div class="alert alert-success">
                                                        <i class="fas fa-check-circle"></i> 
                                                        <strong>Estado:</strong> Conectado a Google Calendar
                                                        <button type="button" class="btn btn-sm btn-danger float-right" 
                                                                onclick="desconectarGoogle()">
                                                            Desconectar
                                                        </button>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Calendario a usar:</label>
                                                        <select class="form-control" id="google_calendar_id" name="google_calendar_id">
                                                            <option value="">Cargando calendarios...</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" 
                                                                   id="sincronizar_citas" name="sincronizar_citas"
                                                                   <?= ($google_config['sincronizar_citas']) ? 'checked' : '' ?>>
                                                            <label class="custom-control-label" for="sincronizar_citas">
                                                                Sincronizar citas automáticamente
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-exclamation-circle"></i> 
                                                        <strong>Estado:</strong> No conectado
                                                    </div>
                                                    
                                                    <button type="button" class="btn btn-primary" onclick="autorizarGoogle()" 
                                                            id="btnAutorizar" <?= empty($google_config['google_client_id']) ? 'disabled' : '' ?>>
                                                        <i class="fab fa-google"></i> Autorizar con Google
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <hr>
                                                
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-save"></i> Guardar Configuración
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card bg-info">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0 text-white">
                                                <i class="fas fa-info-circle"></i> Instrucciones
                                            </h5>
                                        </div>
                                        <div class="card-body text-white">
                                            <ol class="mb-0">
                                                <li>Crea un proyecto en Google Cloud Console</li>
                                                <li>Habilita la API de Google Calendar</li>
                                                <li>Crea credenciales OAuth 2.0</li>
                                                <li>Añade la URL de redirección:<br>
                                                    <code><?= url('api/v1/bot/google-callback.php') ?></code>
                                                </li>
                                                <li>Copia el Client ID y Secret aquí</li>
                                                <li>Autoriza la aplicación</li>
                                            </ol>
                                            
                                            <hr class="bg-white">
                                            
                                            <h6>Ventajas:</h6>
                                            <ul class="mb-0">
                                                <li>Evita doble agendamiento</li>
                                                <li>Ve las citas en tu calendario habitual</li>
                                                <li>Recibe notificaciones automáticas</li>
                                                <li>Sincronización bidireccional</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= array_sum($citas_semana) ?></h3>
                            <p>Citas Esta Semana</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= count($servicios) ?></h3>
                            <p>Servicios Activos</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <?php
                            $dias_activos = 0;
                            foreach ($horarios as $h) {
                                if ($h['activo']) $dias_activos++;
                            }
                            ?>
                            <h3><?= $dias_activos ?></h3>
                            <p>Días Laborables</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="card card-primary card-tabs">
                <div class="card-header p-0 pt-1">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#horarios" role="tab">
                                <i class="fas fa-clock"></i> Horarios de Atención
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#servicios" role="tab">
                                <i class="fas fa-list"></i> Servicios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#citas" role="tab">
                                <i class="fas fa-calendar-check"></i> Citas Agendadas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#calendario" role="tab">
                                <i class="fas fa-calendar-alt"></i> Calendario
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#google-calendar" role="tab">
                                <i class="fab fa-google"></i> Google Calendar
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        
                        <!-- Tab Horarios -->
                        <div class="tab-pane fade show active" id="horarios" role="tabpanel">
                            <h4>Configurar Horarios de Atención</h4>
                            <p class="text-muted">Define los días y horas en que puedes recibir citas</p>
                            
                            <form id="formHorarios">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Día</th>
                                                <th>Activo</th>
                                                <th>Hora Inicio</th>
                                                <th>Hora Fin</th>
                                                <th>Duración Cita (min)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dias_semana as $dia => $nombre): 
                                                $horario = $horarios_por_dia[$dia] ?? null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= $nombre ?></strong>
                                                    <input type="hidden" name="dias[]" value="<?= $dia ?>">
                                                </td>
                                                <td>
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input dia-activo" 
                                                               id="activo_<?= $dia ?>" name="activo_<?= $dia ?>"
                                                               <?= ($horario && $horario['activo']) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="activo_<?= $dia ?>"></label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="time" class="form-control" name="hora_inicio_<?= $dia ?>"
                                                           value="<?= $horario ? $horario['hora_inicio'] : '09:00' ?>"
                                                           <?= (!$horario || !$horario['activo']) ? 'disabled' : '' ?>>
                                                </td>
                                                <td>
                                                    <input type="time" class="form-control" name="hora_fin_<?= $dia ?>"
                                                           value="<?= $horario ? $horario['hora_fin'] : '18:00' ?>"
                                                           <?= (!$horario || !$horario['activo']) ? 'disabled' : '' ?>>
                                                </td>
                                                <td>
                                                    <select class="form-control" name="duracion_<?= $dia ?>"
                                                            <?= (!$horario || !$horario['activo']) ? 'disabled' : '' ?>>
                                                        <option value="15" <?= ($horario && $horario['duracion_cita'] == 15) ? 'selected' : '' ?>>15 min</option>
                                                        <option value="30" <?= (!$horario || $horario['duracion_cita'] == 30) ? 'selected' : '' ?>>30 min</option>
                                                        <option value="45" <?= ($horario && $horario['duracion_cita'] == 45) ? 'selected' : '' ?>>45 min</option>
                                                        <option value="60" <?= ($horario && $horario['duracion_cita'] == 60) ? 'selected' : '' ?>>60 min</option>
                                                        <option value="90" <?= ($horario && $horario['duracion_cita'] == 90) ? 'selected' : '' ?>>90 min</option>
                                                        <option value="120" <?= ($horario && $horario['duracion_cita'] == 120) ? 'selected' : '' ?>>120 min</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Horarios
                                </button>
                            </form>
                        </div>
                        
                        <!-- Tab Servicios -->
                        <div class="tab-pane fade" id="servicios" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8">
                                    <h4>Servicios Disponibles</h4>
                                    <p class="text-muted">Define los servicios que ofreces para las citas</p>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="tablaServicios">
                                            <thead>
                                                <tr>
                                                    <th>Servicio</th>
                                                    <th>Duración</th>
                                                    <th>Preparación</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($servicios as $servicio): ?>
                                                <tr data-id="<?= $servicio['id'] ?>">
                                                    <td><?= htmlspecialchars($servicio['nombre_servicio']) ?></td>
                                                    <td><?= $servicio['duracion_minutos'] ?> min</td>
                                                    <td><?= htmlspecialchars($servicio['requiere_preparacion'] ?? 'No requiere') ?></td>
                                                    <td>
                                                        <?php if ($servicio['activo']): ?>
                                                            <span class="badge badge-success">Activo</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactivo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" onclick="editarServicio(<?= $servicio['id'] ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="eliminarServicio(<?= $servicio['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card card-primary">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-plus"></i> Agregar Servicio
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <form id="formServicio">
                                                <input type="hidden" id="servicio_id" value="">
                                                
                                                <div class="form-group">
                                                    <label>Nombre del Servicio:</label>
                                                    <input type="text" class="form-control" id="nombre_servicio" required
                                                           placeholder="Ej: Consulta General">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Duración (minutos):</label>
                                                    <select class="form-control" id="duracion_servicio">
                                                        <option value="15">15 minutos</option>
                                                        <option value="30" selected>30 minutos</option>
                                                        <option value="45">45 minutos</option>
                                                        <option value="60">1 hora</option>
                                                        <option value="90">1.5 horas</option>
                                                        <option value="120">2 horas</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Requiere preparación:</label>
                                                    <textarea class="form-control" id="preparacion_servicio" rows="3"
                                                              placeholder="Ej: Venir en ayunas, traer estudios previos..."></textarea>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="servicio_activo" checked>
                                                        <label class="custom-control-label" for="servicio_activo">
                                                            Servicio Activo
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="fas fa-save"></i> Guardar Servicio
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tab Citas -->
                        <div class="tab-pane fade" id="citas" role="tabpanel">
                            <h4>Citas Agendadas</h4>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="filtroFecha" 
                                               value="<?= date('Y-m-d') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" onclick="filtrarCitas()">
                                                <i class="fas fa-search"></i> Filtrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button class="btn btn-success" onclick="exportarCitas()">
                                        <i class="fas fa-download"></i> Exportar Excel
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tablaCitas">
                                    <thead>
                                        <tr>
                                            <th>Hora</th>
                                            <th>Paciente</th>
                                            <th>Teléfono</th>
                                            <th>Servicio</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="listaCitas">
                                        <?php foreach ($citas_hoy as $cita): ?>
                                        <tr data-id="<?= $cita['id'] ?>">
                                            <td><?= date('H:i', strtotime($cita['hora_cita'])) ?></td>
                                            <td><?= htmlspecialchars($cita['nombre_cliente']) ?></td>
                                            <td>
                                                <a href="https://wa.me/<?= $cita['numero_cliente'] ?>" target="_blank">
                                                    <?= $cita['numero_cliente'] ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($cita['tipo_servicio']) ?></td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'agendada' => 'badge-info',
                                                    'confirmada' => 'badge-success',
                                                    'cancelada' => 'badge-danger',
                                                    'completada' => 'badge-secondary'
                                                ];
                                                $badge = $badges[$cita['estado']] ?? 'badge-info';
                                                ?>
                                                <span class="badge <?= $badge ?>"><?= ucfirst($cita['estado']) ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="cambiarEstadoCita(<?= $cita['id'] ?>, 'confirmada')"
                                                        <?= $cita['estado'] != 'agendada' ? 'disabled' : '' ?>>
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="cambiarEstadoCita(<?= $cita['id'] ?>, 'cancelada')"
                                                        <?= in_array($cita['estado'], ['cancelada', 'completada']) ? 'disabled' : '' ?>>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="verDetallesCita(<?= $cita['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Calendario -->
                        <div class="tab-pane fade" id="calendario" role="tabpanel">
                            <h4>Calendario de Citas</h4>
                            <div id="calendar"></div>
                        </div>
                        
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal detalles cita -->
<div class="modal fade" id="modalDetallesCita" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de la Cita</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detallesCitaContent">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<!-- FullCalendar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>

<script>
// Calendario
let calendar;

$(document).ready(function() {
    // Habilitar/deshabilitar campos según checkbox de día activo
    $('.dia-activo').on('change', function() {
        const dia = $(this).attr('id').replace('activo_', '');
        const row = $(this).closest('tr');
        const inputs = row.find('input[type="time"], select');
        
        if ($(this).is(':checked')) {
            inputs.prop('disabled', false);
        } else {
            inputs.prop('disabled', true);
        }
    });
    
    // Inicializar calendario
    const calendarEl = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: function(info, successCallback) {
            $.ajax({
                url: API_URL + '/bot/obtener-citas.php',
                data: {
                    start: info.startStr,
                    end: info.endStr
                },
                success: function(response) {
                    if (response.success) {
                        successCallback(response.data);
                    }
                }
            });
        },
        eventClick: function(info) {
            verDetallesCita(info.event.id);
        }
    });
    
    // Solo renderizar si el tab está visible
    $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
        if ($(e.target).attr('href') === '#calendario') {
            calendar.render();
        }
    });
});

// Guardar horarios
$('#formHorarios').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: API_URL + '/bot/configurar-horarios.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                Swal.fire('Éxito', 'Horarios guardados correctamente', 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Error al guardar horarios', 'error');
        }
    });
});

// Guardar servicio
$('#formServicio').on('submit', function(e) {
    e.preventDefault();
    
    const data = {
        id: $('#servicio_id').val(),
        nombre: $('#nombre_servicio').val(),
        duracion: $('#duracion_servicio').val(),
        preparacion: $('#preparacion_servicio').val(),
        activo: $('#servicio_activo').is(':checked') ? 1 : 0
    };
    
    $.ajax({
        url: API_URL + '/bot/guardar-servicio.php',
        method: 'POST',
        data: data,
        success: function(response) {
            if (response.success) {
                Swal.fire('Éxito', 'Servicio guardado correctamente', 'success');
                $('#formServicio')[0].reset();
                $('#servicio_id').val('');
                location.reload(); // Recargar para ver cambios
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    });
});

// Editar servicio
function editarServicio(id) {
    $.get(API_URL + '/bot/obtener-servicio.php', {id: id}, function(response) {
        if (response.success) {
            const servicio = response.data;
            $('#servicio_id').val(servicio.id);
            $('#nombre_servicio').val(servicio.nombre_servicio);
            $('#duracion_servicio').val(servicio.duracion_minutos);
            $('#preparacion_servicio').val(servicio.requiere_preparacion);
            $('#servicio_activo').prop('checked', servicio.activo == 1);
            
            // Cambiar título del card
            $('.card-primary .card-title').html('<i class="fas fa-edit"></i> Editar Servicio');
        }
    });
}

// Eliminar servicio
function eliminarServicio(id) {
    Swal.fire({
        title: '¿Eliminar servicio?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: API_URL + '/bot/eliminar-servicio.php',
                method: 'POST',
                data: {id: id},
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Eliminado', 'Servicio eliminado correctamente', 'success');
                        location.reload();
                    }
                }
            });
        }
    });
}

// Filtrar citas
function filtrarCitas() {
    const fecha = $('#filtroFecha').val();
    
    $.get(API_URL + '/bot/obtener-citas-fecha.php', {fecha: fecha}, function(response) {
        if (response.success) {
            let html = '';
            response.data.forEach(cita => {
                const badgeClass = {
                    'agendada': 'badge-info',
                    'confirmada': 'badge-success',
                    'cancelada': 'badge-danger',
                    'completada': 'badge-secondary'
                }[cita.estado] || 'badge-info';
                
                html += `
                    <tr data-id="${cita.id}">
                        <td>${cita.hora_cita}</td>
                        <td>${cita.nombre_cliente}</td>
                        <td><a href="https://wa.me/${cita.numero_cliente}" target="_blank">${cita.numero_cliente}</a></td>
                        <td>${cita.tipo_servicio}</td>
                        <td><span class="badge ${badgeClass}">${cita.estado}</span></td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="cambiarEstadoCita(${cita.id}, 'confirmada')"
                                    ${cita.estado != 'agendada' ? 'disabled' : ''}>
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="cambiarEstadoCita(${cita.id}, 'cancelada')"
                                    ${['cancelada', 'completada'].includes(cita.estado) ? 'disabled' : ''}>
                                <i class="fas fa-times"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="verDetallesCita(${cita.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#listaCitas').html(html || '<tr><td colspan="6" class="text-center">No hay citas para esta fecha</td></tr>');
        }
    });
}

// Cambiar estado cita
function cambiarEstadoCita(id, nuevoEstado) {
    $.ajax({
        url: API_URL + '/bot/cambiar-estado-cita.php',
        method: 'POST',
        data: {id: id, estado: nuevoEstado},
        success: function(response) {
            if (response.success) {
                Swal.fire('Éxito', 'Estado actualizado', 'success');
                filtrarCitas(); // Recargar lista
                if (calendar) {
                    calendar.refetchEvents();
                }
            }
        }
    });
}

// Ver detalles cita
function verDetallesCita(id) {
    $.get(API_URL + '/bot/detalle-cita.php', {id: id}, function(response) {
        if (response.success) {
            const cita = response.data;
            let html = `
                <table class="table">
                    <tr><th>Paciente:</th><td>${cita.nombre_cliente}</td></tr>
                    <tr><th>Teléfono:</th><td>${cita.numero_cliente}</td></tr>
                    <tr><th>Fecha:</th><td>${cita.fecha_cita}</td></tr>
                    <tr><th>Hora:</th><td>${cita.hora_cita}</td></tr>
                    <tr><th>Servicio:</th><td>${cita.tipo_servicio}</td></tr>
                    <tr><th>Estado:</th><td>${cita.estado}</td></tr>
                    <tr><th>Notas:</th><td>${cita.notas || 'Sin notas'}</td></tr>
                    <tr><th>Creada:</th><td>${cita.fecha_creacion}</td></tr>
                </table>
            `;
            $('#detallesCitaContent').html(html);
            $('#modalDetallesCita').modal('show');
        }
    });
}

// Exportar citas
function exportarCitas() {
    const fecha = $('#filtroFecha').val();
    window.open(API_URL + '/bot/exportar-citas.php?fecha=' + fecha);
}

// Google Calendar
$('#google_client_id, #google_client_secret').on('input', function() {
    const clientId = $('#google_client_id').val().trim();
    const clientSecret = $('#google_client_secret').val().trim();
    
    $('#btnAutorizar').prop('disabled', !clientId || !clientSecret);
});

$('#formGoogleCalendar').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: API_URL + '/bot/guardar-config-google.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                Swal.fire('Éxito', 'Configuración guardada', 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    });
});

function autorizarGoogle() {
    const clientId = $('#google_client_id').val();
    const redirectUri = '<?= url('api/v1/bot/google-callback.php') ?>';
    const scope = 'https://www.googleapis.com/auth/calendar';
    
    const authUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
        `client_id=${clientId}&` +
        `redirect_uri=${encodeURIComponent(redirectUri)}&` +
        `response_type=code&` +
        `scope=${encodeURIComponent(scope)}&` +
        `access_type=offline&` +
        `prompt=consent`;
    
    window.location.href = authUrl;
}

function desconectarGoogle() {
    Swal.fire({
        title: '¿Desconectar Google Calendar?',
        text: 'Las citas ya creadas no se eliminarán, pero no se sincronizarán nuevas',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desconectar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: API_URL + '/bot/desconectar-google.php',
                method: 'POST',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Desconectado', 'Google Calendar ha sido desconectado', 'success');
                        location.reload();
                    }
                }
            });
        }
    });
}

// Cargar calendarios si está conectado
<?php if ($google_config && $google_config['google_refresh_token']): ?>
$(document).ready(function() {
    $.get(API_URL + '/bot/obtener-calendarios.php', function(response) {
        if (response.success) {
            let options = '<option value="">Seleccionar calendario...</option>';
            response.data.forEach(cal => {
                const selected = cal.id === '<?= $google_config['google_calendar_id'] ?>' ? 'selected' : '';
                options += `<option value="${cal.id}" ${selected}>${cal.summary}</option>`;
            });
            $('#google_calendar_id').html(options);
        }
    });
});
<?php endif; ?>
</script>