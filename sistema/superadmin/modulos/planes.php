<?php
$current_page = 'planes';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener planes
$stmt = $pdo->query("
    SELECT 
        p.*,
        COUNT(e.id) as total_empresas
    FROM planes p
    LEFT JOIN empresas e ON e.plan_id = p.id
    GROUP BY p.id
    ORDER BY p.precio_mensual
");
$planes = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-box"></i> Gestión de Planes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Planes</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="alert alert-info">
                <i class="icon fas fa-info-circle"></i>
                Los cambios en planes afectan a nuevas suscripciones. Las empresas actuales mantienen su plan hasta renovación.
            </div>

            <!-- Planes actuales -->
            <div class="row">
                <?php foreach ($planes as $plan): 
                    $caracteristicas = json_decode($plan['caracteristicas_json'] ?? '{}', true);
                ?>
                    <div class="col-md-4">
                        <div class="card <?= !$plan['activo'] ? 'bg-light' : '' ?>">
                            <div class="card-header <?= $plan['id'] == 1 ? 'bg-warning' : ($plan['id'] == 2 ? 'bg-info' : 'bg-success') ?>">
                                <h3 class="card-title">
                                    <?php if ($plan['id'] == 1): ?>
                                        <i class="fas fa-gift"></i>
                                    <?php elseif ($plan['id'] == 2): ?>
                                        <i class="fas fa-box"></i>
                                    <?php else: ?>
                                        <i class="fas fa-crown"></i>
                                    <?php endif; ?>
                                    <?= $plan['nombre'] ?>
                                </h3>
                                <div class="card-tools">
                                    <span class="badge badge-light"><?= $plan['total_empresas'] ?> empresas</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h2 class="text-primary">
                                        $<?= number_format($plan['precio_mensual'], 2) ?>
                                    </h2>
                                    <small class="text-muted">por mes</small>
                                    <br>
                                    <small class="text-success">
                                        $<?= number_format($plan['precio_anual'], 2) ?> al año
                                    </small>
                                </div>

                                <table class="table table-sm">
                                    <tr>
                                        <th>Contactos:</th>
                                        <td><?= number_format($plan['limite_contactos']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Mensajes/mes:</th>
                                        <td><?= number_format($plan['limite_mensajes_mes']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Bot IA:</th>
                                        <td><?= $plan['bot_ia'] ? '✅' : '❌' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Soporte:</th>
                                        <td><?= $plan['soporte_prioritario'] ? 'Prioritario' : 'Estándar' ?></td>
                                    </tr>
                                </table>

                                <hr>

                                <h6>Características:</h6>
                                <ul class="list-unstyled small">
                                    <?php foreach ($caracteristicas as $key => $value): ?>
                                        <li>
                                            <strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong> 
                                            <?= is_bool($value) ? ($value ? 'Sí' : 'No') : $value ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <div class="btn-group w-100 mt-3">
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="editarPlan(<?= $plan['id'] ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <?php if ($plan['activo']): ?>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="desactivarPlan(<?= $plan['id'] ?>)">
                                            <i class="fas fa-ban"></i> Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="activarPlan(<?= $plan['id'] ?>)">
                                            <i class="fas fa-check"></i> Activar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
</div>

<!-- Modal Editar Plan -->
<div class="modal fade" id="modalEditarPlan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Plan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formEditarPlan">
                    <input type="hidden" id="plan_id" name="plan_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Datos Básicos</h5>
                            
                            <div class="form-group">
                                <label>Nombre del Plan:</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Precio Mensual ($):</label>
                                <input type="number" class="form-control" name="precio_mensual" 
                                       step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Precio Anual ($):</label>
                                <input type="number" class="form-control" name="precio_anual" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Límites</h5>
                            
                            <div class="form-group">
                                <label>Límite de Contactos:</label>
                                <input type="number" class="form-control" name="limite_contactos" 
                                       min="0" required>
                                <small class="text-muted">0 = Ilimitado</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Límite Mensajes/Mes:</label>
                                <input type="number" class="form-control" name="limite_mensajes_mes" 
                                       min="0" required>
                                <small class="text-muted">0 = Ilimitado</small>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" 
                                           id="bot_ia" name="bot_ia" value="1">
                                    <label class="custom-control-label" for="bot_ia">
                                        Bot IA Incluido
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" 
                                           id="soporte_prioritario" name="soporte_prioritario" value="1">
                                    <label class="custom-control-label" for="soporte_prioritario">
                                        Soporte Prioritario
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5>Características JSON</h5>
                    <div class="form-group">
                        <label>Configuración Avanzada (JSON):</label>
                        <textarea class="form-control" name="caracteristicas_json" 
                                  rows="6" id="caracteristicas_json"></textarea>
                        <small class="text-muted">
                            Editar solo si sabes lo que haces. Formato JSON válido requerido.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="guardarPlan()">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
function editarPlan(id) {
    $.get(API_URL + '/superadmin/plan-detalles.php', {id: id}, function(response) {
        if (response.success) {
            const plan = response.data;
            
            $('#plan_id').val(plan.id);
            $('input[name="nombre"]').val(plan.nombre);
            $('input[name="precio_mensual"]').val(plan.precio_mensual);
            $('input[name="precio_anual"]').val(plan.precio_anual);
            $('input[name="limite_contactos"]').val(plan.limite_contactos);
            $('input[name="limite_mensajes_mes"]').val(plan.limite_mensajes_mes);
            $('#bot_ia').prop('checked', plan.bot_ia == 1);
            $('#soporte_prioritario').prop('checked', plan.soporte_prioritario == 1);
            $('#caracteristicas_json').val(JSON.stringify(JSON.parse(plan.caracteristicas_json || '{}'), null, 2));
            
            $('#modalEditarPlan').modal('show');
        } else {
            mostrarError('Error al cargar plan');
        }
    });
}

function guardarPlan() {
    const formData = $('#formEditarPlan').serialize();
    
    // Validar JSON
    try {
        const jsonValue = $('#caracteristicas_json').val();
        if (jsonValue) {
            JSON.parse(jsonValue);
        }
    } catch (e) {
        mostrarError('El JSON de características no es válido');
        return;
    }
    
    $.post(API_URL + '/superadmin/guardar-plan.php', formData, function(response) {
        if (response.success) {
            mostrarExito('Plan actualizado correctamente');
            $('#modalEditarPlan').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarError(response.message);
        }
    });
}

function desactivarPlan(id) {
    Swal.fire({
        title: '¿Desactivar plan?',
        text: 'Las empresas con este plan mantendrán su acceso actual',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(API_URL + '/superadmin/toggle-plan.php', {
                id: id,
                activo: 0
            }, function(response) {
                if (response.success) {
                    mostrarExito('Plan desactivado');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mostrarError(response.message);
                }
            });
        }
    });
}

function activarPlan(id) {
    $.post(API_URL + '/superadmin/toggle-plan.php', {
        id: id,
        activo: 1
    }, function(response) {
        if (response.success) {
            mostrarExito('Plan activado');
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarError(response.message);
        }
    });
}
</script>