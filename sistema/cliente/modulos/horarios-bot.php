<?php
$current_page = 'horarios-bot';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../../../includes/plan-limits.php';
verificarAccesoModulo('horarios-bot');

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
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="filtroFecha">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" onclick="filtrarCitas()">
                                                <i class="fas fa-search"></i> Filtrar
                                            </button>
                                            <button class="btn btn-secondary" onclick="limpiarFiltro()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Deja vacío para ver próximas citas</small>
                                </div>
                                <div class="col-md-8 text-right">
                                    <button class="btn btn-success" onclick="exportarCitas()">
                                        <i class="fas fa-download"></i> Exportar
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="130">Fecha/Hora</th>
                                            <th>Cliente</th>
                                            <th>Teléfono</th>
                                            <th>Servicio</th>
                                            <th width="100">Estado</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="listaCitas">
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <div id="paginacionCitas"></div>
                        </div>

                        <!-- Tab Calendario -->
                        <div class="tab-pane fade" id="calendario" role="tabpanel">
                            <h4>Calendario de Citas</h4>
                            <div id="calendar"></div>
                        </div>

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
                                                    <code><?= url('sistema/api/v1/bot/google-callback.php') ?></code>
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
    let paginaActual = 1;
    let calendar;

    $(document).ready(function() {
        // Cargar citas al inicio
        cargarCitas();

        // Habilitar/deshabilitar campos según checkbox
        $('.dia-activo').on('change', function() {
            const dia = $(this).attr('id').replace('activo_', '');
            const row = $(this).closest('tr');
            const inputs = row.find('input[type="time"], select');
            inputs.prop('disabled', !$(this).is(':checked'));
        });

        // Inicializar calendario
        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: 'timeGridWeek',
            slotMinTime: '07:00:00',
            slotMaxTime: '23:00:00',
            slotDuration: '00:30:00',
            allDaySlot: false,
            nowIndicator: true,
            weekends: true, // Mostrar fines de semana
            expandRows: true,
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día'
            },
            events: function(info, successCallback) {
                $.ajax({
                    url: API_URL + '/bot/obtener-citas-fechas.php',
                    data: {
                        start: info.startStr,
                        end: info.endStr
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            const events = response.data.map(cita => ({
                                id: cita.id,
                                title: cita.nombre_cliente + ' - ' + cita.tipo_servicio,
                                start: cita.fecha_cita + 'T' + cita.hora_cita,
                                backgroundColor: cita.estado === 'confirmada' ? '#28a745' : '#007bff',
                                borderColor: cita.estado === 'confirmada' ? '#28a745' : '#007bff'
                            }));
                            successCallback(events);
                        }
                    }
                });
            },
            eventClick: function(info) {
                verDetallesCita(info.event.id);
            }
        });

        $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
            if ($(e.target).attr('href') === '#calendario') {
                calendar.render();
            }
        });
    });

    // Cargar citas con paginación
    function cargarCitas(pagina = 1) {
        const fecha = $('#filtroFecha').val();

        $.ajax({
            url: API_URL + '/bot/obtener-citas-fechas.php',
            data: {
                pagina: pagina,
                fecha: fecha || undefined
            },
            beforeSend: function() {
                $('#listaCitas').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>');
            },
            success: function(response) {
                if (response.success) {
                    mostrarCitas(response.data);
                    mostrarPaginacion(response.paginacion);
                    paginaActual = pagina;
                } else {
                    $('#listaCitas').html('<tr><td colspan="6" class="text-center text-danger">Error: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#listaCitas').html('<tr><td colspan="6" class="text-center text-danger">Error al cargar citas</td></tr>');
            }
        });
    }

    // Mostrar citas en la tabla
    function mostrarCitas(citas) {
        if (!citas || citas.length === 0) {
            $('#listaCitas').html('<tr><td colspan="6" class="text-center text-muted">No hay citas pendientes</td></tr>');
            return;
        }

        let html = '';
        citas.forEach(cita => {
            const badgeClass = cita.estado === 'confirmada' ? 'badge-success' : 'badge-info';
            const fechaFormato = formatearFecha(cita.fecha_cita);
            const horaFormato = cita.hora_cita.substring(0, 5);

            // ✅ LIMPIAR NÚMERO AQUÍ
            const numeroLimpio = limpiarNumero(cita.numero_cliente);

            html += `
            <tr>
                <td><strong>${fechaFormato}</strong><br><small class="text-muted">${horaFormato}</small></td>
                <td>${escapeHtml(cita.nombre_cliente)}</td>
                <td>
                    <a href="https://wa.me/${numeroLimpio}" target="_blank" class="btn btn-sm btn-success">
                        <i class="fab fa-whatsapp"></i> ${numeroLimpio}
                    </a>
                </td>
                <td>${escapeHtml(cita.tipo_servicio)}</td>
                <td><span class="badge ${badgeClass}">${ucfirst(cita.estado)}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="cambiarEstadoCita(${cita.id}, 'confirmada')"
                                title="Confirmar" ${cita.estado !== 'agendada' ? 'disabled' : ''}>
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger" onclick="cambiarEstadoCita(${cita.id}, 'cancelada')"
                                title="Cancelar">
                            <i class="fas fa-times"></i>
                        </button>
                        <button class="btn btn-info" onclick="verDetallesCita(${cita.id})"
                                title="Ver detalles">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        });
        $('#listaCitas').html(html);
    }

    // Mostrar paginación
    function mostrarPaginacion(paginacion) {
        if (!paginacion || paginacion.total_paginas <= 1) {
            $('#paginacionCitas').html('');
            return;
        }

        let html = '<nav><ul class="pagination justify-content-center mb-0">';

        // Anterior
        html += `<li class="page-item ${paginacion.pagina_actual === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cargarCitas(${paginacion.pagina_actual - 1}); return false;">
            <i class="fas fa-chevron-left"></i>
        </a>
    </li>`;

        // Páginas
        for (let i = 1; i <= paginacion.total_paginas; i++) {
            html += `<li class="page-item ${i === paginacion.pagina_actual ? 'active' : ''}">
            <a class="page-link" href="#" onclick="cargarCitas(${i}); return false;">${i}</a>
        </li>`;
        }

        // Siguiente
        html += `<li class="page-item ${paginacion.pagina_actual === paginacion.total_paginas ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cargarCitas(${paginacion.pagina_actual + 1}); return false;">
            <i class="fas fa-chevron-right"></i>
        </a>
    </li>`;

        html += '</ul></nav>';

        // Info de resultados
        const inicio = (paginacion.pagina_actual - 1) * paginacion.por_pagina + 1;
        const fin = Math.min(paginacion.pagina_actual * paginacion.por_pagina, paginacion.total);
        html += `<p class="text-center text-muted mt-2 mb-0">Mostrando ${inicio}-${fin} de ${paginacion.total} citas</p>`;

        $('#paginacionCitas').html(html);
    }

    // Filtrar citas
    function filtrarCitas() {
        cargarCitas(1);
    }

    // Limpiar filtro
    function limpiarFiltro() {
        $('#filtroFecha').val('');
        cargarCitas(1);
    }

    // Cambiar estado cita
    function cambiarEstadoCita(id, nuevoEstado) {
        const textos = {
            'confirmada': '¿Confirmar esta cita?',
            'cancelada': '¿Cancelar esta cita?',
            'completada': '¿Marcar como completada?'
        };

        Swal.fire({
            title: textos[nuevoEstado],
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: API_URL + '/bot/cambiar-estado-cita.php',
                    method: 'POST',
                    data: {
                        id: id,
                        estado: nuevoEstado
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Actualizado', response.message, 'success');
                            cargarCitas(paginaActual);
                            if (calendar) calendar.refetchEvents();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    }

    // Ver detalles
    function verDetallesCita(id) {
        $.ajax({
            url: API_URL + '/bot/detalle-cita.php',
            data: {
                id: id
            },
            success: function(response) {
                if (response.success) {
                    const c = response.data;
                    const numeroLimpio = limpiarNumero(c.numero_cliente); // ✅ AGREGADO

                    const html = `
                    <table class="table table-sm">
                        <tr><th width="40%">Cliente:</th><td>${escapeHtml(c.nombre_cliente)}</td></tr>
                        <tr><th>Teléfono:</th><td><a href="https://wa.me/${numeroLimpio}" target="_blank">${numeroLimpio}</a></td></tr>
                        <tr><th>Fecha:</th><td>${formatearFecha(c.fecha_cita)}</td></tr>
                        <tr><th>Hora:</th><td>${c.hora_cita}</td></tr>
                        <tr><th>Servicio:</th><td>${escapeHtml(c.tipo_servicio)}</td></tr>
                        <tr><th>Estado:</th><td><span class="badge badge-${c.estado === 'confirmada' ? 'success' : 'info'}">${ucfirst(c.estado)}</span></td></tr>
                        <tr><th>Notas:</th><td>${escapeHtml(c.notas) || '<em class="text-muted">Sin notas</em>'}</td></tr>
                        <tr><th>Creada:</th><td>${c.fecha_creacion}</td></tr>
                    </table>
                `;
                    $('#detallesCitaContent').html(html);
                    $('#modalDetallesCita').modal('show');
                }
            }
        });
    }

    // Funciones auxiliares

    function limpiarNumero(numero) {
        if (!numero) return '';
        // Quitar @c.us, @s.whatsapp.net y espacios
        return numero.replace(/@c\.us|@s\.whatsapp\.net|\s+/g, '');
    }

    function formatearFecha(fecha) {
        const f = new Date(fecha + 'T00:00:00');
        const opciones = {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        };
        return f.toLocaleDateString('es-ES', opciones);
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Guardar horarios
    $('#formHorarios').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: API_URL + '/bot/configurar-horarios.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Éxito', response.message, 'success');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
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
        $.get(API_URL + '/bot/obtener-servicio.php', {
            id: id
        }, function(response) {
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
                    data: {
                        id: id
                    },
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

    // Cambiar estado cita
    function cambiarEstadoCita(id, nuevoEstado) {
        $.ajax({
            url: API_URL + '/bot/cambiar-estado-cita.php',
            method: 'POST',
            data: {
                id: id,
                estado: nuevoEstado
            },
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
        $.get(API_URL + '/bot/detalle-cita.php', {
            id: id
        }, function(response) {
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
        const redirectUri = '<?= url('sistema/api/v1/bot/google-callback.php') ?>';
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

<style>
    .text-muted {
        color: #005bad !important;
        font-weight: 700;
    }
</style>