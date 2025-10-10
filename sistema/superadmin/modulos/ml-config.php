<?php
// sistema/superadmin/modulos/ml-config.php
$current_page = 'ml-config';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener configuración actual
function getConfig($clave, $default = '') {
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
                                        <strong>Mayor (≥80%):</strong> ML decide más, GPT menos (más rápido, menos tokens)<br>
                                        <strong>Menor (&lt;80%):</strong> GPT decide más, ML menos (más inteligente, más tokens)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label>Ejemplos para Reentrenamiento Automático:</label>
                                    <input type="number" class="form-control" name="ml_auto_retrain_examples"
                                        value="<?= $config['ml_auto_retrain_examples'] ?>" min="10" max="500">
                                    <small class="text-muted">
                                        El modelo se reentrena automáticamente al alcanzar esta cantidad de ejemplos nuevos
                                    </small>
                                </div>

                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-lightbulb"></i> Recomendaciones:</h6>
                                    <ul class="mb-0 small">
                                        <li><strong>Umbral 80-85%:</strong> Balance ideal (recomendado)</li>
                                        <li><strong>Umbral 90%+:</strong> Solo si el modelo ya tiene >90% accuracy</li>
                                        <li><strong>Reentrenamiento:</strong> 50 ejemplos es óptimo (no muy seguido, no muy raro)</li>
                                    </ul>
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
                                            <?php elseif ($modelo_actual['accuracy'] >= 0.70): ?>
                                                <span class="badge badge-warning">Regular</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Necesita mejorar</span>
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
                                        <th>Ejemplos de Entrenamiento:</th>
                                        <td><?= number_format($modelo_actual['ejemplos_entrenamiento']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Fecha Entrenamiento:</th>
                                        <td><?= date('d/m/Y H:i', strtotime($modelo_actual['fecha_entrenamiento'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Duración:</th>
                                        <td><?= $modelo_actual['duracion_segundos'] ?> segundos</td>
                                    </tr>
                                </table>

                                <hr>

                                <div class="text-center">
                                    <button class="btn btn-warning" onclick="forzarReentrenamiento()">
                                        <i class="fas fa-sync-alt"></i> Forzar Reentrenamiento
                                    </button>
                                    <p class="text-muted mt-2 small">
                                        Esto iniciará un reentrenamiento manual con todos los ejemplos disponibles
                                    </p>
                                </div>

                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No hay modelo entrenado. El ML Engine debe entrenarse por primera vez.
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

<script>
// Guardar configuración
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
                    title: 'Éxito',
                    text: response.message,
                    timer: 2000
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        },
        error: function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo guardar la configuración'
            });
        }
    });
});

// Verificar estado ML Engine
function verificarEstadoML() {
    $('#estadoMLEngine').html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Verificando...</p>');
    
    const mlPort = $('input[name="ml_engine_port"]').val();
    const mlUrl = `http://localhost:${mlPort}/health`;
    
    $.ajax({
        url: mlUrl,
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
                        <li>Versión Modelo: <strong>v${response.modelo_version || 'N/A'}</strong></li>
                    </ul>
                </div>
            `);
        },
        error: function() {
            $('#estadoMLEngine').html(`
                <div class="alert alert-danger mb-0">
                    <h5><i class="fas fa-times-circle"></i> ML Engine No Disponible</h5>
                    <p>No se pudo conectar al ML Engine en el puerto ${mlPort}.</p>
                    <p class="mb-0"><strong>Solución:</strong> Verifica que el servicio Python esté corriendo.</p>
                </div>
            `);
        }
    });
}

// Verificar al cargar
$(document).ready(function() {
    verificarEstadoML();
});

// Forzar reentrenamiento
function forzarReentrenamiento() {
    Swal.fire({
        title: '¿Forzar Reentrenamiento?',
        text: 'Esto puede tomar varios minutos. El modelo se entrenará con todos los ejemplos disponibles.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, entrenar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const mlPort = $('input[name="ml_engine_port"]').val();
            
            Swal.fire({
                title: 'Entrenando...',
                html: 'Por favor espera. Esto puede tomar varios minutos.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: `http://localhost:${mlPort}/train`,
                method: 'POST',
                data: JSON.stringify({ trigger: 'manual' }),
                contentType: 'application/json',
                timeout: 300000, // 5 minutos
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Entrenamiento Completado',
                        html: `
                            <strong>Nueva Accuracy:</strong> ${(response.accuracy * 100).toFixed(2)}%<br>
                            <strong>Ejemplos usados:</strong> ${response.ejemplos_usados}
                        `,
                        timer: 3000
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo completar el entrenamiento. Verifica los logs.'
                    });
                }
            });
        }
    });
}

// Entrenamiento inicial
function entrenamientoInicial() {
    forzarReentrenamiento();
}
</script>