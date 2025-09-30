<?php
// sistema/cliente/modulos/mi-plan.php
$page_title = "Mi Plan";
$current_page = "mi-plan";

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../../includes/plan-limits.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener información completa con límites
$resumen = obtenerResumenLimites();
$empresa = getDatosEmpresa();

// Obtener información detallada del plan
$stmt = $pdo->prepare("SELECT * FROM planes WHERE id = ?");
$stmt->execute([$empresa['plan_id']]);
$plan_actual = $stmt->fetch();

// Obtener suscripción activa si existe
$stmt = $pdo->prepare("
    SELECT sp.*, s.fecha_inicio, s.fecha_fin 
    FROM suscripciones_pago sp
    LEFT JOIN suscripciones s ON s.empresa_id = sp.empresa_id AND s.estado = 'activa'
    WHERE sp.empresa_id = ? AND sp.estado = 'activa'
    LIMIT 1
");
$stmt->execute([$empresa['id']]);
$suscripcion = $stmt->fetch();

// Obtener todos los planes disponibles
$stmt = $pdo->prepare("SELECT * FROM planes WHERE activo = 1 ORDER BY precio_mensual");
$stmt->execute();
$planes = $stmt->fetchAll();

// Verificar si está en trial
$en_trial = $resumen['plan']['es_trial'];
$trial_activo = $resumen['plan']['trial_activo'];
$dias_restantes_trial = 0;

if ($en_trial && $empresa['fecha_expiracion_trial']) {
    $fecha_expiracion = new DateTime($empresa['fecha_expiracion_trial']);
    $hoy = new DateTime();
    $diff = $hoy->diff($fecha_expiracion);
    $dias_restantes_trial = $diff->invert ? 0 : $diff->days;
}

// Obtener historial de pagos
$stmt = $pdo->prepare("
    SELECT * FROM pagos 
    WHERE empresa_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$empresa['id']]);
$historial_pagos = $stmt->fetchAll();

// Decodificar características
$caracteristicas = json_decode($plan_actual['caracteristicas_json'] ?? '{}', true);
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Mi Plan de Suscripción</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Mi Plan</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <!-- Plan Actual -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-crown"></i> Plan Actual: <?php echo $plan_actual['nombre']; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($en_trial): ?>
                                <?php if ($trial_activo): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-clock"></i> <strong>Periodo de Prueba Activo</strong><br>
                                        Te quedan <strong><?php echo $dias_restantes_trial; ?> día(s)</strong> para probar TODAS las funciones.
                                        <br>
                                        <small>Después de este periodo, necesitarás elegir un plan de pago.</small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> <strong>Periodo de Prueba Expirado</strong><br>
                                        Tu trial ha finalizado. Selecciona un plan para continuar usando el servicio.
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($suscripcion): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> <strong>Suscripción Activa</strong><br>
                                    Próximo pago: <strong><?php echo date('d/m/Y', strtotime($suscripcion['fecha_proximo_pago'])); ?></strong>
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <!-- Límites y Uso -->
                                <div class="col-md-6">
                                    <h5><i class="fas fa-chart-bar"></i> Límites y Uso</h5>
                                    <ul class="list-group">
                                        <!-- Contactos -->
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-address-book text-primary"></i> Contactos
                                            </span>
                                            <div>
                                                <?php if ($resumen['contactos']['ilimitado']): ?>
                                                    <span class="badge badge-success badge-pill">Ilimitados</span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <?php echo number_format($resumen['contactos']['actual']); ?> / 
                                                    </span>
                                                    <span class="badge badge-<?php echo $resumen['contactos']['alcanzado'] ? 'danger' : 'primary'; ?> badge-pill">
                                                        <?php echo number_format($resumen['contactos']['limite']); ?>
                                                    </span>
                                                    <small class="text-muted">(<?php echo $resumen['contactos']['porcentaje']; ?>%)</small>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                        
                                        <!-- Mensajes -->
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-paper-plane text-info"></i> Mensajes este mes
                                            </span>
                                            <div>
                                                <?php if ($resumen['mensajes']['ilimitado']): ?>
                                                    <span class="badge badge-success badge-pill">Ilimitados</span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <?php echo number_format($resumen['mensajes']['actual']); ?> / 
                                                    </span>
                                                    <span class="badge badge-<?php echo $resumen['mensajes']['alcanzado'] ? 'danger' : 'primary'; ?> badge-pill">
                                                        <?php echo number_format($resumen['mensajes']['limite']); ?>
                                                    </span>
                                                    <small class="text-muted">(<?php echo $resumen['mensajes']['porcentaje']; ?>%)</small>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                        
                                        <!-- Bot IA -->
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-robot text-success"></i> Bot IA
                                            </span>
                                            <span class="badge badge-<?php echo $plan_actual['bot_ia'] ? 'success' : 'danger'; ?> badge-pill">
                                                <?php echo $plan_actual['bot_ia'] ? 'Incluido' : 'No incluido'; ?>
                                            </span>
                                        </li>
                                        
                                        <!-- Soporte -->
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-headset text-warning"></i> Soporte
                                            </span>
                                            <span class="badge badge-info badge-pill">
                                                <?php echo $plan_actual['soporte_prioritario'] ? 'Prioritario' : 'Estándar'; ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                
                                <!-- Módulos Disponibles -->
                                <div class="col-md-6">
                                    <h5><i class="fas fa-puzzle-piece"></i> Módulos Disponibles</h5>
                                    <ul class="list-group">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-shopping-cart"></i> Bot de Ventas
                                            </span>
                                            <span class="badge badge-<?php echo ($caracteristicas['bot_ventas'] ?? false) ? 'success' : 'secondary'; ?>">
                                                <?php echo ($caracteristicas['bot_ventas'] ?? false) ? 'Activo' : 'No disponible'; ?>
                                            </span>
                                        </li>
                                        
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-calendar-check"></i> Bot de Citas
                                            </span>
                                            <span class="badge badge-<?php echo ($caracteristicas['bot_citas'] ?? false) ? 'success' : 'secondary'; ?>">
                                                <?php echo ($caracteristicas['bot_citas'] ?? false) ? 'Activo' : 'No disponible'; ?>
                                            </span>
                                        </li>
                                        
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-user-tie"></i> Escalamiento a Humano
                                            </span>
                                            <span class="badge badge-<?php echo $resumen['modulos']['escalamiento'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $resumen['modulos']['escalamiento'] ? 'Activo' : 'No disponible'; ?>
                                            </span>
                                        </li>
                                        
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-book"></i> Catálogo de Productos
                                            </span>
                                            <span class="badge badge-<?php echo $resumen['modulos']['catalogo_bot'] ? 'success' : 'secondary'; ?>">
                                                <?php 
                                                if ($resumen['modulos']['catalogo_bot']) {
                                                    echo 'Hasta ' . $resumen['limites_especiales']['catalogo_mb'] . ' MB';
                                                } else {
                                                    echo 'No disponible';
                                                }
                                                ?>
                                            </span>
                                        </li>
                                        
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-clock"></i> Horarios y Citas
                                            </span>
                                            <span class="badge badge-<?php echo $resumen['modulos']['horarios_bot'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $resumen['modulos']['horarios_bot'] ? 'Activo' : 'No disponible'; ?>
                                            </span>
                                        </li>
                                        
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fab fa-google"></i> Google Calendar
                                            </span>
                                            <span class="badge badge-<?php echo $resumen['modulos']['google_calendar'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $resumen['modulos']['google_calendar'] ? 'Activo' : 'No disponible'; ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <?php if ($suscripcion): ?>
                                <hr>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <button class="btn btn-danger btn-sm" onclick="cancelarSuscripcion()">
                                            <i class="fas fa-times-circle"></i> Cancelar Suscripción
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Planes Disponibles -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <h3><i class="fas fa-store"></i> Planes Disponibles</h3>
                    <p class="text-muted">Elige el plan que mejor se adapte a tu negocio</p>
                </div>
            </div>
            
            <div class="row mt-3">
                <?php foreach ($planes as $plan): 
                    $plan_caract = json_decode($plan['caracteristicas_json'] ?? '{}', true);
                    $es_plan_actual = ($plan['id'] == $plan_actual['id']);
                ?>
                    <div class="col-md-4">
                        <div class="card <?php echo $es_plan_actual ? 'card-primary' : 'card-outline'; ?>">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php if ($plan['id'] == 1): ?>
                                        <i class="fas fa-gift"></i>
                                    <?php elseif ($plan['id'] == 2): ?>
                                        <i class="fas fa-box"></i>
                                    <?php else: ?>
                                        <i class="fas fa-crown"></i>
                                    <?php endif; ?>
                                    <?php echo $plan['nombre']; ?>
                                </h3>
                                <?php if ($es_plan_actual): ?>
                                    <span class="badge badge-success float-right">Plan Actual</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if ($plan['precio_mensual'] > 0): ?>
                                        <h2 class="text-primary">
                                            $<?php echo number_format($plan['precio_mensual'], 2); ?>
                                        </h2>
                                        <small class="text-muted">por mes</small>
                                        <br>
                                        <small class="text-success">
                                            <i class="fas fa-piggy-bank"></i> 
                                            Ahorra $<?php echo number_format(($plan['precio_mensual'] * 12) - $plan['precio_anual'], 2); ?> al año
                                        </small>
                                    <?php else: ?>
                                        <h2 class="text-success">GRATIS</h2>
                                        <small class="text-muted">por 48 horas</small>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-center text-muted">
                                    <small><?php echo $plan_caract['descripcion'] ?? ''; ?></small>
                                </p>

                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        <strong><?php echo number_format($plan['limite_contactos']); ?></strong> contactos
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        <strong><?php echo number_format($plan['limite_mensajes_mes']); ?></strong> mensajes/mes
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-<?php echo ($plan_caract['escalamiento'] ?? false) ? 'check text-success' : 'times text-danger'; ?>"></i> 
                                        Escalamiento a humano
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-<?php echo ($plan_caract['catalogo_bot'] ?? false) ? 'check text-success' : 'times text-danger'; ?>"></i> 
                                        Catálogo de productos
                                        <?php if (($plan_caract['catalogo_mb'] ?? 0) > 0): ?>
                                            <small class="text-muted">(<?php echo $plan_caract['catalogo_mb']; ?> MB)</small>
                                        <?php endif; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-<?php echo ($plan_caract['horarios_bot'] ?? false) ? 'check text-success' : 'times text-danger'; ?>"></i> 
                                        Horarios y citas
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-<?php echo ($plan_caract['google_calendar'] ?? false) ? 'check text-success' : 'times text-danger'; ?>"></i> 
                                        Google Calendar
                                    </li>
                                </ul>

                                <?php if (!$es_plan_actual && $plan['id'] != 1): ?>
                                    <div class="text-center mt-3">
                                        <button class="btn btn-primary btn-block" 
                                                onclick="seleccionarPlan(<?php echo $plan['id']; ?>)">
                                            <i class="fas fa-arrow-up"></i> 
                                            <?php echo ($plan['id'] > $plan_actual['id']) ? 'Mejorar Plan' : 'Cambiar Plan'; ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Historial de Pagos -->
            <?php if (!empty($historial_pagos)): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-history"></i> Historial de Pagos
                                </h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Monto</th>
                                            <th>Método</th>
                                            <th>Estado</th>
                                            <th>Referencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial_pagos as $pago): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pago['created_at'])); ?></td>
                                                <td><strong>$<?php echo number_format($pago['monto'], 2); ?></strong></td>
                                                <td>
                                                    <?php if ($pago['metodo'] == 'mercadopago'): ?>
                                                        <i class="fas fa-credit-card text-info"></i>
                                                    <?php elseif ($pago['metodo'] == 'paypal'): ?>
                                                        <i class="fab fa-paypal text-primary"></i>
                                                    <?php endif; ?>
                                                    <?php echo ucfirst($pago['metodo']); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge_class = [
                                                        'pendiente' => 'warning',
                                                        'aprobado' => 'success',
                                                        'rechazado' => 'danger',
                                                        'reembolsado' => 'secondary'
                                                    ][$pago['estado']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($pago['estado']); ?>
                                                    </span>
                                                </td>
                                                <td><small class="text-muted"><?php echo $pago['referencia_externa']; ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<!-- Modal de Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card"></i> Seleccionar Método de Pago
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo de pago:</label>
                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                        <label class="btn btn-outline-primary active">
                            <input type="radio" name="tipo_pago" value="mensual" checked> 
                            <i class="fas fa-calendar-alt"></i> Mensual
                        </label>
                        <label class="btn btn-outline-success">
                            <input type="radio" name="tipo_pago" value="anual"> 
                            <i class="fas fa-piggy-bank"></i> Anual (Ahorra 2 meses)
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Método de pago:</label>
                    <div class="row">
                        <div class="col-6">
                            <button class="btn btn-block btn-outline-info" onclick="procesarPago('mercadopago')">
                                <i class="fas fa-credit-card"></i><br>
                                MercadoPago
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-block btn-outline-primary" onclick="procesarPago('paypal')">
                                <i class="fab fa-paypal"></i><br>
                                PayPal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
let planSeleccionado = 0;

function seleccionarPlan(planId) {
    planSeleccionado = planId;
    $('#modalPago').modal('show');
}

function procesarPago(metodo) {
    const tipoPago = $('input[name="tipo_pago"]:checked').val();
    
    Swal.fire({
        title: 'Procesando pago...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: API_URL + '/cliente/pagos/crear-suscripcion.php',
        type: 'POST',
        data: JSON.stringify({
            plan_id: planSeleccionado,
            tipo_pago: tipoPago,
            metodo: metodo
        }),
        contentType: 'application/json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                if (metodo === 'mercadopago' && response.init_point) {
                    window.location.href = response.init_point;
                } else if (metodo === 'paypal' && response.approval_url) {
                    window.location.href = response.approval_url;
                }
            } else {
                Swal.fire('Error', response.message || 'Error al procesar el pago', 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Error al conectar con el servidor', 'error');
        }
    });
}

function cancelarSuscripcion() {
    Swal.fire({
        title: '¿Cancelar suscripción?',
        html: 'Tu suscripción se cancelará al final del periodo actual.<br>Mantendrás acceso hasta <strong><?php echo date('d/m/Y', strtotime($suscripcion['fecha_proximo_pago'] ?? 'now')); ?></strong>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: API_URL + '/cliente/pagos/cancelar-suscripcion.php',
                type: 'POST',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Cancelada', 'Tu suscripción ha sido cancelada', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al cancelar la suscripción', 'error');
                }
            });
        }
    });
}
</script>