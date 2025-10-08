<?php
$current_page = 'empresas';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Filtros
$filter = $_GET['filter'] ?? 'todas';
$search = $_GET['search'] ?? '';

// Query base
$where = "WHERE 1=1";
$params = [];

if ($filter === 'activas') {
    $where .= " AND e.activo = 1 AND s.estado = 'activa'";
} elseif ($filter === 'suspendidas') {
    $where .= " AND e.activo = 0";
} elseif ($filter === 'trial') {
    $where .= " AND s.tipo = 'trial'";
} elseif ($filter === 'canceladas') {
    $where .= " AND s.auto_renovar = 0 AND s.estado = 'activa'";
} elseif ($filter === 'vencidas') {
    $where .= " AND s.fecha_fin < NOW()";
}

if ($search) {
    $where .= " AND (e.nombre_empresa LIKE ? OR e.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ✅ Query mejorada con información de suscripción
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        p.nombre as plan_nombre,
        p.precio_mensual,
        p.precio_anual,
        (SELECT COUNT(*) FROM contactos WHERE empresa_id = e.id) as total_contactos,
        s.id as suscripcion_id,
        s.tipo as suscripcion_tipo,
        s.fecha_inicio,
        s.fecha_fin,
        s.estado as suscripcion_estado,
        s.auto_renovar,
        DATEDIFF(s.fecha_fin, NOW()) as dias_restantes,
        (SELECT fecha_pago FROM pagos WHERE empresa_id = e.id AND estado = 'aprobado' ORDER BY fecha_pago DESC LIMIT 1) as ultimo_pago
    FROM empresas e
    LEFT JOIN planes p ON e.plan_id = p.id
    LEFT JOIN suscripciones s ON s.empresa_id = e.id 
        AND s.estado = 'activa'
    $where
    ORDER BY e.fecha_registro DESC
");
$stmt->execute($params);
$empresas = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-building"></i> Gestión de Empresas</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Empresas</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Filtros y búsqueda -->
            <div class="card">
                <div class="card-body">
                    <form method="get" class="form-inline">
                        <div class="form-group mr-2">
                            <label class="mr-2">Filtrar:</label>
                            <select name="filter" class="form-control" onchange="this.form.submit()">
                                <option value="todas" <?= $filter === 'todas' ? 'selected' : '' ?>>Todas</option>
                                <option value="activas" <?= $filter === 'activas' ? 'selected' : '' ?>>Activas</option>
                                <option value="suspendidas" <?= $filter === 'suspendidas' ? 'selected' : '' ?>>Suspendidas</option>
                                <option value="trial" <?= $filter === 'trial' ? 'selected' : '' ?>>En Trial</option>
                                <option value="canceladas" <?= $filter === 'canceladas' ? 'selected' : '' ?>>Canceladas</option>
                                <option value="vencidas" <?= $filter === 'vencidas' ? 'selected' : '' ?>>Vencidas</option>
                            </select>
                        </div>

                        <div class="form-group mr-2">
                            <input type="text" name="search" class="form-control"
                                placeholder="Buscar empresa..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>

                        <?php if ($filter !== 'todas' || $search): ?>
                            <a href="<?= url('superadmin/empresas') ?>" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabla de empresas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Total: <?= count($empresas) ?> empresa(s)
                    </h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Empresa</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Suscripción</th>
                                <th>Inicio/Fin</th>
                                <th>Días</th>
                                <th>Contactos</th>
                                <th>Registro</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empresas as $empresa):
                                // Determinar estado visual
                                $dias = $empresa['dias_restantes'] ?? 0;
                                $es_trial = ($empresa['suscripcion_tipo'] == 'trial');
                                $es_cancelada = ($empresa['auto_renovar'] == 0 && !$es_trial);
                                $esta_vencida = ($dias <= 0);

                                // Badge de estado
                                if ($esta_vencida) {
                                    $badge_estado = 'danger';
                                    $icono_estado = 'fa-times-circle';
                                    $texto_estado = 'Vencida';
                                } elseif ($es_cancelada) {
                                    $badge_estado = 'warning';
                                    $icono_estado = 'fa-ban';
                                    $texto_estado = 'Cancelada';
                                } elseif ($es_trial) {
                                    $badge_estado = 'info';
                                    $icono_estado = 'fa-gift';
                                    $texto_estado = 'Trial';
                                } else {
                                    $badge_estado = 'success';
                                    $icono_estado = 'fa-check-circle';
                                    $texto_estado = 'Activa';
                                }

                                // Color de días restantes
                                if ($dias <= 3) {
                                    $color_dias = 'text-danger font-weight-bold';
                                } elseif ($dias <= 7) {
                                    $color_dias = 'text-warning font-weight-bold';
                                } else {
                                    $color_dias = '';
                                }
                            ?>
                                <tr>
                                    <td><?= $empresa['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($empresa['nombre_empresa']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($empresa['email']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $es_trial ? 'warning' : ($empresa['plan_id'] == 2 ? 'info' : 'primary') ?>">
                                            <?= $empresa['plan_nombre'] ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?= ucfirst($empresa['suscripcion_tipo'] ?? 'N/A') ?>
                                            <?php if (!$es_trial && $empresa['precio_mensual'] > 0): ?>
                                                - $<?= number_format($empresa['precio_mensual'], 2) ?>/mes
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $badge_estado ?>">
                                            <i class="fas <?= $icono_estado ?>"></i>
                                            <?= $texto_estado ?>
                                        </span>
                                        <?php if (!$es_trial): ?>
                                            <br>
                                            <small>
                                                <?php if ($empresa['auto_renovar']): ?>
                                                    <i class="fas fa-sync-alt text-success" title="Auto-renovar activo"></i> Renueva
                                                <?php else: ?>
                                                    <i class="fas fa-ban text-warning" title="No renovará"></i> No renueva
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($empresa['fecha_inicio']): ?>
                                            <small>
                                                <i class="fas fa-play text-success"></i> <?= date('d/m/Y', strtotime($empresa['fecha_inicio'])) ?>
                                                <br>
                                                <i class="fas fa-stop text-danger"></i> <?= date('d/m/Y', strtotime($empresa['fecha_fin'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">Sin suscripción</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $color_dias ?>">
                                        <?php if ($dias > 0): ?>
                                            <?= $dias ?> día<?= $dias != 1 ? 's' : '' ?>
                                        <?php elseif ($dias == 0): ?>
                                            <span class="badge badge-danger">Hoy vence</span>
                                        <?php else: ?>
                                            <span class="badge badge-dark">Vencido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($empresa['total_contactos']) ?></td>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($empresa['fecha_registro'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $empresa['activo'] ? 'success' : 'danger' ?>">
                                            <?= $empresa['activo'] ? 'Activo' : 'Suspendido' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($empresa['es_superadmin']): ?>
                                            <!-- SuperAdmin: Solo badge sin acciones -->
                                            <span class="badge badge-dark" title="Cuenta SuperAdmin protegida">
                                                <i class="fas fa-shield-alt"></i> SuperAdmin
                                            </span>
                                        <?php else: ?>
                                            <!-- Empresas normales: Botones de acción -->
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info"
                                                    onclick="verDetalles(<?= $empresa['id'] ?>)"
                                                    title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="cambiarPlan(<?= $empresa['id'] ?>, '<?= addslashes($empresa['nombre_empresa']) ?>')"
                                                    title="Cambiar plan">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                <?php if ($empresa['activo']): ?>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                        onclick="suspenderEmpresa(<?= $empresa['id'] ?>, '<?= addslashes($empresa['nombre_empresa']) ?>')"
                                                        title="Suspender">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success"
                                                        onclick="activarEmpresa(<?= $empresa['id'] ?>, '<?= addslashes($empresa['nombre_empresa']) ?>')"
                                                        title="Activar">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($es_trial): ?>
                                                    <button type="button" class="btn btn-sm btn-primary"
                                                        onclick="extenderTrial(<?= $empresa['id'] ?>, '<?= addslashes($empresa['nombre_empresa']) ?>')"
                                                        title="Extender trial">
                                                        <i class="fas fa-clock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal Detalles -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Detalles de la Empresa</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detallesContent">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Cambiar Plan -->
<div class="modal fade" id="modalCambiarPlan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Cambiar Plan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formCambiarPlan">
                    <input type="hidden" id="cambiar_empresa_id" name="empresa_id">

                    <div class="form-group">
                        <label>Empresa:</label>
                        <input type="text" class="form-control" id="cambiar_empresa_nombre" readonly>
                    </div>

                    <div class="form-group">
                        <label>Nuevo Plan:</label>
                        <select class="form-control" name="plan_id" required>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM planes WHERE activo = 1 ORDER BY precio_mensual");
                            $planes = $stmt->fetchAll();
                            foreach ($planes as $plan):
                            ?>
                                <option value="<?= $plan['id'] ?>">
                                    <?= $plan['nombre'] ?> - $<?= number_format($plan['precio_mensual'], 2) ?>/mes
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Motivo del cambio:</label>
                        <textarea class="form-control" name="motivo" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="confirmarCambioPlan()">
                    <i class="fas fa-exchange-alt"></i> Cambiar Plan
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    function verDetalles(id) {
        $('#modalDetalles').modal('show');
        $('#detallesContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');

        $.get(API_URL + '/superadmin/empresa-detalles', {
            id: id
        }, function(response) {
            if (response.success) {
                const e = response.data;
                let html = `
                <table class="table">
                    <tr><th>ID:</th><td>${e.id}</td></tr>
                    <tr><th>Empresa:</th><td>${e.nombre_empresa}</td></tr>
                    <tr><th>Email:</th><td>${e.email}</td></tr>
                    <tr><th>Teléfono:</th><td>${e.telefono || 'N/A'}</td></tr>
                    <tr><th>Plan Actual:</th><td>${e.plan_nombre}</td></tr>
                    <tr><th>Fecha Registro:</th><td>${e.fecha_registro}</td></tr>
                    <tr><th>Último Acceso:</th><td>${e.ultimo_acceso || 'Nunca'}</td></tr>
                    <tr><th>Total Contactos:</th><td>${e.total_contactos}</td></tr>
                    <tr><th>Mensajes del Mes:</th><td>${e.mensajes_mes || 0}</td></tr>
                    <tr><th>Estado:</th><td><span class="badge badge-${e.activo ? 'success' : 'danger'}">${e.activo ? 'Activo' : 'Suspendido'}</span></td></tr>
                </table>
            `;
                $('#detallesContent').html(html);
            } else {
                $('#detallesContent').html('<div class="alert alert-danger">Error al cargar detalles</div>');
            }
        });
    }

    function cambiarPlan(id, nombre) {
        $('#cambiar_empresa_id').val(id);
        $('#cambiar_empresa_nombre').val(nombre);
        $('#modalCambiarPlan').modal('show');
    }

    function confirmarCambioPlan() {
        const formData = $('#formCambiarPlan').serialize();

        $.post(API_URL + '/superadmin/cambiar-plan', formData, function(response) {
            if (response.success) {
                Swal.fire('Éxito', 'Plan cambiado correctamente', 'success');
                $('#modalCambiarPlan').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    }

    function suspenderEmpresa(id, nombre) {
        Swal.fire({
            title: '¿Suspender empresa?',
            html: `Se suspenderá: <strong>${nombre}</strong><br>La empresa no podrá acceder al sistema.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, suspender',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + '/superadmin/suspender-empresa', {
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Suspendida', 'Empresa suspendida correctamente', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                });
            }
        });
    }

    function activarEmpresa(id, nombre) {
        Swal.fire({
            title: '¿Activar empresa?',
            html: `Se activará: <strong>${nombre}</strong>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Sí, activar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + '/superadmin/activar-empresa', {
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Activada', 'Empresa activada correctamente', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                });
            }
        });
    }

    function extenderTrial(id, nombre) {
        Swal.fire({
            title: 'Extender Trial',
            html: `Empresa: <strong>${nombre}</strong><br>¿Cuántos días agregar?`,
            input: 'number',
            inputAttributes: {
                min: 1,
                max: 90
            },
            inputValue: 30,
            showCancelButton: true,
            confirmButtonText: 'Extender',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                $.post(API_URL + '/superadmin/extender-trial', {
                    empresa_id: id,
                    dias: result.value
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Éxito', 'Trial extendido correctamente', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                });
            }
        });
    }
</script>