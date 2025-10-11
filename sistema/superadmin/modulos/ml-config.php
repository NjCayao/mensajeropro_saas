<?php
// sistema/superadmin/modulos/ml-config.php
$current_page = 'ml-config';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener configuración actual
function getConfig($clave, $default = '')
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : $default;
}

$config = [
    'ml_engine_port' => getConfig('ml_engine_port', '5000'),
    'ml_umbral_confianza' => getConfig('ml_umbral_confianza', '0.80'),
    'ml_auto_retrain_examples' => getConfig('ml_auto_retrain_examples', '50'),
];

// Obtener métricas del modelo actual
try {
    $stmt = $pdo->query("
        SELECT * FROM metricas_modelo 
        WHERE estado = 'activo' 
        ORDER BY fecha_entrenamiento DESC 
        LIMIT 1
    ");
    $modelo_actual = $stmt->fetch();
} catch (Exception $e) {
    $modelo_actual = null;
}

// Contar ejemplos pendientes
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as total FROM training_samples 
        WHERE estado = 'pendiente' AND usado_entrenamiento = 0
    ");
    $ejemplos_pendientes = $stmt->fetch()['total'];
} catch (Exception $e) {
    $ejemplos_pendientes = 0;
}

$config_limpieza = [
    'conversaciones' => getConfig('ml_retencion_conversaciones', '3'),
    'descartados' => getConfig('ml_retencion_ejemplos_descartados', '7'),
    'usados' => getConfig('ml_retencion_ejemplos_usados', '30'),
    'logs' => getConfig('ml_retencion_logs_entrenamiento', '90'),
];

// Últimos entrenamientos
try {
    $stmt = $pdo->query("
        SELECT * FROM log_entrenamientos 
        ORDER BY fecha DESC 
        LIMIT 10
    ");
    $log_entrenamientos = $stmt->fetchAll();
} catch (Exception $e) {
    $log_entrenamientos = [];
}
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-brain"></i> ML Engine - Machine Learning</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">ML Engine</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Estado del Modelo -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $modelo_actual ? 'v' . $modelo_actual['version_modelo'] : 'N/A' ?></h3>
                            <p>Versión Activa</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-code-branch"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $modelo_actual ? number_format($modelo_actual['accuracy'] * 100, 1) . '%' : 'N/A' ?></h3>
                            <p>Precisión (Accuracy)</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($ejemplos_pendientes) ?></h3>
                            <p>Ejemplos Pendientes</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= number_format((float)$config['ml_umbral_confianza'] * 100, 0) ?>%</h3>
                            <p>Umbral Confianza</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== GRÁFICOS ========== -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line"></i> Evolución de Accuracy</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartAccuracy" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-bar"></i> ML vs GPT (últimos 7 días)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartMLvsGPT" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Top 10 Intenciones (últimos 30 días)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartIntenciones" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cogs"></i> Configuración ML Engine</h3>
                        </div>
                        <form id="formMLConfig">
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Nota:</strong> Estos cambios afectan el comportamiento del bot en tiempo real.
                                </div>

                                <div class="form-group">
                                    <label>Puerto ML Engine:</label>
                                    <input type="number" class="form-control" name="ml_engine_port"
                                        value="<?= $config['ml_engine_port'] ?>" min="3000" max="9999">
                                    <small class="text-muted">
                                        Puerto donde corre el servidor Python (default: 5000)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label>
                                        Umbral de Confianza:
                                        <span class="badge badge-info" id="umbralValue">
                                            <?= number_format((float)$config['ml_umbral_confianza'] * 100, 0) ?>%
                                        </span>
                                    </label>
                                    <input type="range" class="form-control-range" name="ml_umbral_confianza"
                                        min="0.5" max="0.95" step="0.05"
                                        value="<?= $config['ml_umbral_confianza'] ?>"
                                        oninput="document.getElementById('umbralValue').textContent = Math.round(this.value * 100) + '%'">
                                    <small class="text-muted">
                                        <strong>Mayor (≥80%):</strong> ML decide más, GPT menos<br>
                                        <strong>Menor (&lt;80%):</strong> GPT decide más, ML menos
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label>Ejemplos para Reentrenamiento Automático:</label>
                                    <input type="number" class="form-control" name="ml_auto_retrain_examples"
                                        value="<?= $config['ml_auto_retrain_examples'] ?>" min="10" max="500">
                                    <small class="text-muted">
                                        El modelo se reentrena automáticamente al alcanzar esta cantidad
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Configuración
                                </button>
                                <button type="button" class="btn btn-success" onclick="verificarEstadoML()">
                                    <i class="fas fa-heartbeat"></i> Verificar Estado ML
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información del Modelo -->
                <div class="col-md-6">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-robot"></i> Modelo Actual</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($modelo_actual): ?>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">Versión:</th>
                                        <td><span class="badge badge-primary">v<?= $modelo_actual['version_modelo'] ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Accuracy:</th>
                                        <td>
                                            <strong><?= number_format($modelo_actual['accuracy'] * 100, 2) ?>%</strong>
                                            <?php if ($modelo_actual['accuracy'] >= 0.90): ?>
                                                <span class="badge badge-success">Excelente</span>
                                            <?php elseif ($modelo_actual['accuracy'] >= 0.80): ?>
                                                <span class="badge badge-info">Bueno</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Regular</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Precisión Promedio:</th>
                                        <td><?= number_format($modelo_actual['precision_avg'] * 100, 2) ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>Recall Promedio:</th>
                                        <td><?= number_format($modelo_actual['recall_avg'] * 100, 2) ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>F1 Score:</th>
                                        <td><?= number_format($modelo_actual['f1_score'] * 100, 2) ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>Ejemplos:</th>
                                        <td><?= number_format($modelo_actual['ejemplos_entrenamiento']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Fecha:</th>
                                        <td><?= date('d/m/Y H:i', strtotime($modelo_actual['fecha_entrenamiento'])) ?></td>
                                    </tr>
                                </table>

                                <hr>

                                <div class="text-center">
                                    <button class="btn btn-warning" onclick="forzarReentrenamiento()">
                                        <i class="fas fa-sync-alt"></i> Forzar Reentrenamiento
                                    </button>
                                </div>

                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No hay modelo entrenado.
                                </div>
                                <button class="btn btn-primary btn-block" onclick="entrenamientoInicial()">
                                    <i class="fas fa-play"></i> Iniciar Primer Entrenamiento
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Estado ML Engine -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-server"></i> Estado ML Engine</h3>
                        </div>
                        <div class="card-body" id="estadoMLEngine">
                            <p class="text-center">
                                <i class="fas fa-spinner fa-spin"></i> Verificando...
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ejemplos Pendientes -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-tasks"></i> Ejemplos Pendientes de Revisión
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-success" onclick="aprobarTodos()">
                                    <i class="fas fa-check-double"></i> Aprobar Todos
                                </button>
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>¿Para qué sirve esto?</strong> Revisa los ejemplos que GPT guardó.
                            </div>
                            <div id="tablaEjemplos">
                                <p class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando...
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Limpieza Automática -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-danger">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-broom"></i> Limpieza Automática de Datos
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-warning" onclick="previsualizarLimpieza()">
                                    <i class="fas fa-eye"></i> Vista Previa
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="ejecutarLimpieza()">
                                    <i class="fas fa-trash-alt"></i> Ejecutar Limpieza
                                </button>
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>¡Importante!</strong> Usa "Vista Previa" primero.
                            </div>

                            <form id="formLimpieza">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Conversaciones (días):</label>
                                            <input type="number" class="form-control"
                                                name="dias_conversaciones"
                                                value="<?= $config_limpieza['conversaciones'] ?>"
                                                min="1" max="365">
                                            <small class="text-muted">Mayores a X días</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Ejemplos Descartados:</label>
                                            <input type="number" class="form-control"
                                                name="dias_descartados"
                                                value="<?= $config_limpieza['descartados'] ?>"
                                                min="1" max="365">
                                            <small class="text-muted">Rechazados</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Ejemplos Usados:</label>
                                            <input type="number" class="form-control"
                                                name="dias_usados"
                                                value="<?= $config_limpieza['usados'] ?>"
                                                min="1" max="365">
                                            <small class="text-muted">Ya entrenaron</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Logs:</label>
                                            <input type="number" class="form-control"
                                                name="dias_logs"
                                                value="<?= $config_limpieza['logs'] ?>"
                                                min="1" max="365">
                                            <small class="text-muted">Histórico</small>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Configuración
                                </button>
                            </form>

                            <hr>
                            <div id="resultadoLimpieza"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historial de Entrenamientos -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history"></i> Historial de Entrenamientos</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Motivo</th>
                                        <th>Ejemplos</th>
                                        <th>Accuracy Anterior</th>
                                        <th>Accuracy Nueva</th>
                                        <th>Mejora</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($log_entrenamientos) > 0): ?>
                                        <?php foreach ($log_entrenamientos as $log): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($log['fecha'])) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $log['tipo'] == 'automatico' ? 'info' : 'warning' ?>">
                                                        <?= ucfirst($log['tipo']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($log['trigger_motivo']) ?></td>
                                                <td><?= number_format($log['ejemplos_usados']) ?></td>
                                                <td><?= number_format($log['accuracy_anterior'] * 100, 2) ?>%</td>
                                                <td><?= number_format($log['accuracy_nueva'] * 100, 2) ?>%</td>
                                                <td>
                                                    <?php if ($log['mejora_porcentaje'] > 0): ?>
                                                        <span class="text-success">
                                                            <i class="fas fa-arrow-up"></i> +<?= number_format($log['mejora_porcentaje'], 2) ?>%
                                                        </span>
                                                    <?php elseif ($log['mejora_porcentaje'] < 0): ?>
                                                        <span class="text-danger">
                                                            <i class="fas fa-arrow-down"></i> <?= number_format($log['mejora_porcentaje'], 2) ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0%</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $log['estado'] == 'exitoso' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($log['estado']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                No hay entrenamientos registrados
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
    // ========== VARIABLES GLOBALES ==========
    let chartAccuracy, chartMLvsGPT, chartIntenciones;
    let ejemplosPendientes = [];
    let intencionesDisponibles = [];

    // ========== GRÁFICOS ==========
    function cargarGraficos() {
        cargarAccuracyHistorico();
        cargarMLvsGPT();
        cargarIntencionesTop();
    }

    function cargarAccuracyHistorico() {
        $.get('<?= url("api/v1/superadmin/ml-stats") ?>?tipo=accuracy_historico', function(response) {
            if (!response.success) return;
            const ctx = document.getElementById('chartAccuracy').getContext('2d');
            if (chartAccuracy) chartAccuracy.destroy();
            chartAccuracy = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: response.labels,
                    datasets: [{
                        label: 'Accuracy (%)',
                        data: response.accuracy,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 50,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        });
    }

    function cargarMLvsGPT() {
        $.get('<?= url("api/v1/superadmin/ml-stats") ?>?tipo=ml_vs_gpt', function(response) {
            if (!response.success) return;
            const ctx = document.getElementById('chartMLvsGPT').getContext('2d');
            if (chartMLvsGPT) chartMLvsGPT.destroy();
            chartMLvsGPT = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: response.labels,
                    datasets: [{
                            label: 'ML Engine (rápido)',
                            data: response.ml,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)'
                        },
                        {
                            label: 'GPT Teacher (inteligente)',
                            data: response.gpt,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    }

    function cargarIntencionesTop() {
        $.get('<?= url("api/v1/superadmin/ml-stats") ?>?tipo=intenciones_top', function(response) {
            if (!response.success) return;
            const ctx = document.getElementById('chartIntenciones').getContext('2d');
            if (chartIntenciones) chartIntenciones.destroy();
            chartIntenciones = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: response.labels,
                    datasets: [{
                        label: 'Cantidad',
                        data: response.totales,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    }

    // ========== EJEMPLOS PENDIENTES ==========
    function cargarEjemplosPendientes() {
        $.get('<?= url("api/v1/superadmin/ml-ejemplos") ?>?accion=listar_pendientes&limite=20', function(response) {
            if (!response.success) {
                $('#tablaEjemplos').html('<p class="text-danger">Error cargando ejemplos</p>');
                return;
            }

            ejemplosPendientes = response.ejemplos;

            if (ejemplosPendientes.length === 0) {
                $('#tablaEjemplos').html(`
                <div class="alert alert-success">
                    <i class="fas fa-check"></i> No hay ejemplos pendientes. ¡Todo revisado!
                </div>
            `);
                return;
            }

            let html = `
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th width="40%">Mensaje</th>
                            <th width="20%">Intención</th>
                            <th width="10%">Confianza</th>
                            <th width="15%">Fecha</th>
                            <th width="15%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

            ejemplosPendientes.forEach(ejemplo => {
                const confianzaColor = ejemplo.confianza >= 0.8 ? 'success' :
                    ejemplo.confianza >= 0.5 ? 'warning' : 'danger';

                html += `
                <tr id="ejemplo-${ejemplo.id}">
                    <td><small>${escapeHtml(ejemplo.texto_usuario)}</small></td>
                    <td>
                        <select class="form-control form-control-sm intencion-select" data-id="${ejemplo.id}">
                            <option value="${ejemplo.intencion_detectada}">${ejemplo.intencion_detectada}</option>
                        </select>
                    </td>
                    <td><span class="badge badge-${confianzaColor}">${(ejemplo.confianza * 100).toFixed(0)}%</span></td>
                    <td><small>${ejemplo.fecha_formateada}</small></td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="aprobarEjemplo(${ejemplo.id})" title="Aprobar">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="rechazarEjemplo(${ejemplo.id})" title="Rechazar">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
            });

            html += `</tbody></table></div>`;
            $('#tablaEjemplos').html(html);
            cargarIntenciones();
        });
    }

    function cargarIntenciones() {
        $.get('<?= url("api/v1/superadmin/ml-ejemplos") ?>?accion=intenciones_disponibles', function(response) {
            if (response.success) {
                intencionesDisponibles = response.intenciones;
                $('.intencion-select').each(function() {
                    const valorActual = $(this).val();
                    $(this).empty();
                    $(this).append(`<option value="${valorActual}">${valorActual}</option>`);
                    intencionesDisponibles.forEach(int => {
                        if (int.clave !== valorActual) {
                            $(this).append(`<option value="${int.clave}">${int.nombre || int.clave}</option>`);
                        }
                    });
                });
            }
        });
    }

    function aprobarEjemplo(id) {
        const intencionCorregida = $(`.intencion-select[data-id="${id}"]`).val();
        $.post('<?= url("api/v1/superadmin/ml-ejemplos") ?>', {
            accion: 'aprobar',
            id: id,
            intencion: intencionCorregida
        }, function(response) {
            if (response.success) {
                $(`#ejemplo-${id}`).fadeOut(300, function() {
                    $(this).remove();
                    if ($('tbody tr').length === 0) cargarEjemplosPendientes();
                });
                Swal.fire({
                    icon: 'success',
                    title: 'Ejemplo aprobado',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        });
    }

    function rechazarEjemplo(id) {
        Swal.fire({
            title: '¿Descartar ejemplo?',
            text: 'No se usará para entrenar',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, descartar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?= url("api/v1/superadmin/ml-ejemplos") ?>', {
                    accion: 'rechazar',
                    id: id
                }, function(response) {
                    if (response.success) {
                        $(`#ejemplo-${id}`).fadeOut(300, function() {
                            $(this).remove();
                            if ($('tbody tr').length === 0) cargarEjemplosPendientes();
                        });
                        Swal.fire({
                            icon: 'info',
                            title: 'Ejemplo descartado',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                });
            }
        });
    }

    function aprobarTodos() {
        const total = $('tbody tr').length;
        if (total === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No hay ejemplos',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
            return;
        }

        Swal.fire({
            title: `¿Aprobar ${total} ejemplos?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const ids = [];
                $('tbody tr').each(function() {
                    ids.push($(this).attr('id').replace('ejemplo-', ''));
                });
                $.post('<?= url("api/v1/superadmin/ml-ejemplos") ?>', {
                    accion: 'aprobar_masivo',
                    ids: ids
                }, function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: `${response.cantidad} ejemplos aprobados`,
                            timer: 2000
                        }).then(() => {
                            cargarEjemplosPendientes();
                        });
                    }
                });
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========== LIMPIEZA ==========
    $('#formLimpieza').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '<?= url("api/v1/superadmin/ml-cleanup") ?>',
            method: 'POST',
            data: $(this).serialize() + '&accion=guardar_config',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Configuración guardada',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar'
                });
            }
        });
    });

    function previsualizarLimpieza() {
        $.get('<?= url("api/v1/superadmin/ml-cleanup") ?>?accion=preview', function(response) {
            if (!response.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error en vista previa'
                });
                return;
            }

            const p = response.preview;
            const c = response.config;

            let html = `
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Vista Previa</h5>
                <p><strong>Total: ${response.total.toLocaleString()} registros</strong></p>
                <ul class="mb-0">
                    <li>Conversaciones (>${c.conversaciones}d): ${p.conversaciones.toLocaleString()}</li>
                    <li>Ejemplos descartados (>${c.ejemplos_descartados}d): ${p.ejemplos_descartados.toLocaleString()}</li>
                    <li>Ejemplos usados (>${c.ejemplos_usados}d): ${p.ejemplos_usados.toLocaleString()}</li>
                    <li>Logs (>${c.logs_entrenamiento}d): ${p.logs_entrenamiento.toLocaleString()}</li>
                    <li>Métricas antiguas: ${p.metricas_antiguas.toLocaleString()}</li>
                </ul>
            </div>
        `;

            $('#resultadoLimpieza').html(html);

            if (response.total === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No hay datos para limpiar',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        });
    }

    function ejecutarLimpieza() {
        $.get('<?= url("api/v1/superadmin/ml-cleanup") ?>?accion=preview', function(response) {
            if (!response.success || response.total === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No hay datos para limpiar'
                });
                return;
            }

            Swal.fire({
                title: '¿Ejecutar Limpieza?',
                html: `
                Se eliminarán <strong>${response.total.toLocaleString()}</strong> registros<br>
                <small>Esta acción no se puede deshacer</small>
            `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Limpiando...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.post('<?= url("api/v1/superadmin/ml-cleanup") ?>', {
                        accion: 'ejecutar'
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Limpieza Completada',
                                text: `${response.total_eliminados.toLocaleString()} registros eliminados`,
                                timer: 3000
                            });
                            $('#resultadoLimpieza').html(`
                            <div class="alert alert-success">
                                <i class="fas fa-check"></i> ${response.total_eliminados.toLocaleString()} registros eliminados
                            </div>
                        `);
                        }
                    });
                }
            });
        });
    }

    // ========== CONFIGURACIÓN ML ==========
    $('#formMLConfig').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '<?= url("api/v1/superadmin/guardar-configuracion") ?>',
            method: 'POST',
            data: $(this).serialize() + '&seccion=ml',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Configuración guardada',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar'
                });
            }
        });
    });

    function verificarEstadoML() {
        $('#estadoMLEngine').html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Verificando...</p>');
        const mlPort = $('input[name="ml_engine_port"]').val();

        $.ajax({
            url: `http://localhost:${mlPort}/health`,
            method: 'GET',
            dataType: 'json',
            timeout: 3000,
            success: function(response) {
                $('#estadoMLEngine').html(`
                <div class="alert alert-success mb-0">
                    <h5><i class="fas fa-check-circle"></i> ML Engine Activo</h5>
                    <ul class="mb-0">
                        <li>Estado: <strong>${response.status}</strong></li>
                        <li>Puerto: <strong>${mlPort}</strong></li>
                    </ul>
                </div>
            `);
            },
            error: function() {
                $('#estadoMLEngine').html(`
                <div class="alert alert-danger mb-0">
                    <h5><i class="fas fa-times-circle"></i> ML Engine No Disponible</h5>
                    <p>Verifica que Python esté corriendo en puerto ${mlPort}</p>
                </div>
            `);
            }
        });
    }

    function forzarReentrenamiento() {
        Swal.fire({
            title: '¿Forzar Reentrenamiento?',
            text: 'Puede tomar varios minutos',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, entrenar'
        }).then((result) => {
            if (result.isConfirmed) {
                const mlPort = $('input[name="ml_engine_port"]').val();
                Swal.fire({
                    title: 'Entrenando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: `http://localhost:${mlPort}/train`,
                    method: 'POST',
                    contentType: 'application/json',
                    timeout: 300000,
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Completado',
                            text: `Accuracy: ${(response.accuracy * 100).toFixed(2)}%`,
                            timer: 3000
                        }).then(() => location.reload());
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo entrenar'
                        });
                    }
                });
            }
        });
    }

    function entrenamientoInicial() {
        forzarReentrenamiento();
    }

    // ========== INICIALIZACIÓN ==========
    $(document).ready(function() {
        cargarGraficos();
        verificarEstadoML();
        cargarEjemplosPendientes();
        setInterval(cargarGraficos, 30000);
    });
</script>