<?php
// sistema/cliente/modulos/mi-plan.php
$page_title = "Mi Plan";
$current_page = "mi-plan";

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener información de la empresa y plan actual
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

// Eliminar debug
// echo "<!-- DEBUG: Total planes encontrados: " . count($planes) . " -->";
// if (count($planes) == 0) {
//     $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM planes");
//     $stmt2->execute();
//     $total = $stmt2->fetch();
//     echo "<!-- DEBUG: Total planes en BD sin filtros: " . $total['total'] . " -->";
//     
//     $stmt3 = $pdo->query("SELECT id, nombre, activo FROM planes");
//     echo "<!-- DEBUG planes: ";
//     while ($row = $stmt3->fetch()) {
//         echo "ID:{$row['id']} Nombre:{$row['nombre']} Activo:{$row['activo']} | ";
//     }
//     echo " -->";
// }

// Verificar si está en trial
$en_trial = ($plan_actual['id'] == 1);
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
                            <h3 class="card-title">Plan Actual: <?php echo $plan_actual['nombre']; ?></h3>
                        </div>
                        <div class="card-body">
                            <?php if ($en_trial): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Estás en periodo de prueba. 
                                    <?php if ($dias_restantes_trial > 0): ?>
                                        Te quedan <strong><?php echo $dias_restantes_trial; ?> días</strong> de trial.
                                    <?php else: ?>
                                        Tu periodo de prueba ha expirado.
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($suscripcion): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Suscripción activa hasta: 
                                    <strong><?php echo date('d/m/Y', strtotime($suscripcion['fecha_proximo_pago'])); ?></strong>
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Límites del Plan</h5>
                                    <ul class="list-group">
                                        <?php 
                                        // Obtener uso actual
                                        $stmt_contactos = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE empresa_id = ?");
                                        $stmt_contactos->execute([$empresa['id']]);
                                        $contactos_actuales = $stmt_contactos->fetchColumn();
                                        
                                        $stmt_mensajes = $pdo->prepare("
                                            SELECT COUNT(*) FROM historial_mensajes 
                                            WHERE empresa_id = ? AND tipo = 'saliente'
                                            AND MONTH(fecha) = MONTH(CURRENT_DATE())
                                        ");
                                        $stmt_mensajes->execute([$empresa['id']]);
                                        $mensajes_mes = $stmt_mensajes->fetchColumn();
                                        ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Contactos
                                            <div>
                                                <span class="text-muted"><?php echo number_format($contactos_actuales); ?> / </span>
                                                <span class="badge badge-primary badge-pill">
                                                    <?php echo $plan_actual['limite_contactos'] ? number_format($plan_actual['limite_contactos']) : '∞'; ?>
                                                </span>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Mensajes/mes
                                            <div>
                                                <span class="text-muted"><?php echo number_format($mensajes_mes); ?> / </span>
                                                <span class="badge badge-primary badge-pill">
                                                    <?php echo $plan_actual['limite_mensajes_mes'] ? number_format($plan_actual['limite_mensajes_mes']) : '∞'; ?>
                                                </span>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Bot IA
                                            <span class="badge badge-<?php echo $plan_actual['bot_ia'] ? 'success' : 'danger'; ?> badge-pill">
                                                <?php echo $plan_actual['bot_ia'] ? 'Incluido' : 'No incluido'; ?>
                                            </span>
                                        </li>
                                        <?php if ($caracteristicas['catalogo_mb'] ?? 0): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Tamaño Catálogo
                                            <span class="badge badge-info badge-pill">
                                                <?php echo $caracteristicas['catalogo_mb']; ?> MB
                                            </span>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5>Características</h5>
                                    <?php 
                                    $caracteristicas = !empty($plan_actual['caracteristicas_json']) 
                                        ? json_decode($plan_actual['caracteristicas_json'], true) 
                                        : [];
                                    ?>
                                    <ul class="list-group">
                                        <?php foreach ($caracteristicas as $key => $value): ?>
                                            <li class="list-group-item">
                                                <i class="fas fa-check text-success"></i> 
                                                <?php echo ucfirst(str_replace('_', ' ', $key)) . ': ' . $value; ?>
                                            </li>
                                        <?php endforeach; ?>
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
                    <h3>Planes Disponibles</h3>
                </div>
            </div>
            <div class="row mt-3">
                <?php foreach ($planes as $plan): ?>
                    <div class="col-md-6">
                        <div class="card <?php echo ($plan['id'] == $plan_actual['id']) ? 'card-primary' : 'card-default'; ?>">
                            <div class="card-header">
                                <h3 class="card-title"><?php echo $plan['nombre']; ?></h3>
                                <?php if ($plan['id'] == $plan_actual['id']): ?>
                                    <span class="badge badge-success float-right">Plan Actual</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h2>$ <?php echo number_format($plan['precio_mensual'], 2); ?></h2>
                                    <small class="text-muted">por mes</small>
                                    <br>
                                    <small class="text-success">
                                        Ahorra $ <?php echo number_format(($plan['precio_mensual'] * 12) - $plan['precio_anual'], 2); ?> al año
                                    </small>
                                </div>

                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> 
                                        <?php echo $plan['limite_contactos'] ? number_format($plan['limite_contactos']) . ' contactos' : 'Contactos ilimitados'; ?>
                                    </li>
                                    <li><i class="fas fa-check text-success"></i> 
                                        <?php echo $plan['limite_mensajes_mes'] ? number_format($plan['limite_mensajes_mes']) . ' mensajes/mes' : 'Mensajes ilimitados'; ?>
                                    </li>
                                    <?php if ($plan['bot_ia']): ?>
                                        <li><i class="fas fa-check text-success"></i> Bot IA incluido</li>
                                    <?php endif; ?>
                                    <?php if ($plan['soporte_prioritario']): ?>
                                        <li><i class="fas fa-check text-success"></i> Soporte prioritario</li>
                                    <?php endif; ?>
                                </ul>

                                <?php if ($plan['id'] != $plan_actual['id']): ?>
                                    <div class="text-center mt-3">
                                        <button class="btn btn-primary btn-block" 
                                                onclick="seleccionarPlan(<?php echo $plan['id']; ?>)">
                                            Seleccionar Plan
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
                                <h3 class="card-title">Historial de Pagos</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-striped">
                                    <thead>
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
                                                <td>$ <?php echo number_format($pago['monto'], 2); ?></td>
                                                <td><?php echo ucfirst($pago['metodo']); ?></td>
                                                <td>
                                                    <?php
                                                    $badge_class = [
                                                        'pendiente' => 'warning',
                                                        'aprobado' => 'success',
                                                        'rechazado' => 'danger'
                                                    ][$pago['estado']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($pago['estado']); ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo $pago['referencia_externa']; ?></small></td>
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
            <div class="modal-header">
                <h5 class="modal-title">Seleccionar Método de Pago</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo de pago:</label>
                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                        <label class="btn btn-outline-primary active">
                            <input type="radio" name="tipo_pago" value="mensual" checked> Mensual
                        </label>
                        <label class="btn btn-outline-primary">
                            <input type="radio" name="tipo_pago" value="anual"> Anual (Ahorra 2 meses)
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Método de pago:</label>
                    <div class="row">
                        <div class="col-6">
                            <button class="btn btn-block btn-outline-primary" onclick="procesarPago('mercadopago')">
                                <img src="<?php echo asset('img/mercado-pago.png'); ?>" alt="MercadoPago" style="height: 30px;">
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-block btn-outline-primary" onclick="procesarPago('paypal')">
                                <img src="<?php echo asset('img/paypal.png'); ?>" alt="PayPal" style="height: 30px;">
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
            Swal.fire('Error', 'Error al conectar con el servidor', 'error');
        }
    });
}

function cancelarSuscripcion() {
    Swal.fire({
        title: '¿Cancelar suscripción?',
        text: 'Tu suscripción se cancelará al final del periodo actual',
        icon: 'warning',
        showCancelButton: true,
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
                }
            });
        }
    });
}
</script>