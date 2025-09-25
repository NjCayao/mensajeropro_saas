<?php
$current_page = 'escalados';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Obtener conversaciones escaladas
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        c.nombre as nombre_contacto,
        REPLACE(e.numero_cliente, '@c.us', '') as numero_limpio,
        u.nombre as nombre_resuelto_por,
        (SELECT COUNT(*) FROM conversaciones_bot cb 
         WHERE cb.numero_cliente = e.numero_cliente 
         AND cb.fecha_hora > e.fecha_escalado
         AND cb.empresa_id = ?) as mensajes_desde_escalado
    FROM estados_conversacion e
    LEFT JOIN contactos c ON c.numero = REPLACE(e.numero_cliente, '@c.us', '') AND c.empresa_id = ?
    LEFT JOIN usuarios u ON u.id = e.resuelto_por
    WHERE e.estado = 'escalado_humano' AND e.empresa_id = ?
    ORDER BY e.fecha_escalado DESC
");
$stmt->execute([$empresa_id, $empresa_id, $empresa_id]);
$escalados = $stmt->fetchAll();

// Estadísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN estado = 'escalado_humano' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'resuelto' AND DATE(fecha_resuelto) = CURDATE() THEN 1 END) as resueltos_hoy,
        COUNT(CASE WHEN estado = 'escalado_humano' AND fecha_escalado < DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as pendientes_mas_1h
    FROM estados_conversacion
    WHERE empresa_id = ?
");
$stmt->execute([$empresa_id]);
$stats = $stmt->fetch();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Conversaciones Escaladas a Humano</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Escalados</li>
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
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pendientes</span>
                            <span class="info-box-number"><?= $stats['pendientes'] ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Más de 1 hora</span>
                            <span class="info-box-number"><?= $stats['pendientes_mas_1h'] ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Resueltos Hoy</span>
                            <span class="info-box-number"><?= $stats['resueltos_hoy'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de escalados -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Conversaciones Pendientes de Atención</h3>
                    <div class="card-tools">
                        <button class="btn btn-sm btn-info" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Actualizar
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($escalados) > 0): ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Motivo Escalamiento</th>
                                    <th>Tiempo Esperando</th>
                                    <th>Mensajes</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($escalados as $escalado): ?>
                                    <?php
                                    $tiempoEspera = (time() - strtotime($escalado['fecha_escalado'])) / 60;
                                    $urgente = $tiempoEspera > 60; // Más de 1 hora
                                    ?>
                                    <tr class="<?= $urgente ? 'table-warning' : '' ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($escalado['nombre_contacto'] ?? 'Sin nombre') ?></strong><br>
                                            <small><?= htmlspecialchars($escalado['numero_limpio']) ?></small>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars(substr($escalado['motivo_escalado'], 0, 100)) ?>...</small>
                                            <?php if ($urgente): ?>
                                                <br><span class="badge badge-danger">URGENTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tiempoEspera < 60): ?>
                                                <?= round($tiempoEspera) ?> min
                                            <?php else: ?>
                                                <?= round($tiempoEspera / 60, 1) ?> horas
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= $escalado['mensajes_desde_escalado'] ?> nuevos</span>
                                        </td>
                                        <td>
                                            <a href="https://wa.me/<?= $escalado['numero_limpio'] ?>" 
                                               target="_blank" 
                                               class="btn btn-success btn-sm">
                                                <i class="fab fa-whatsapp"></i> Atender
                                            </a>
                                            <button onclick="verHistorial('<?= $escalado['numero_cliente'] ?>')" 
                                                    class="btn btn-info btn-sm">
                                                <i class="fas fa-history"></i> Historial
                                            </button>
                                            <button onclick="marcarResuelto(<?= $escalado['id'] ?>)" 
                                                    class="btn btn-primary btn-sm">
                                                <i class="fas fa-check"></i> Resuelto
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No hay conversaciones escaladas pendientes.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal Historial -->
<div class="modal fade" id="modalHistorial">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Historial de Conversación</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="contenidoHistorial">
                <!-- Se llenará dinámicamente -->
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
// Auto-refresh cada 30 segundos
setInterval(function() {
    location.reload();
}, 30000);

function marcarResuelto(id) {
    Swal.fire({
        title: '¿Marcar como resuelto?',
        input: 'textarea',
        inputLabel: 'Notas (opcional)',
        inputPlaceholder: 'Agregar notas sobre la resolución...',
        showCancelButton: true,
        confirmButtonText: 'Marcar resuelto',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(API_URL + '/bot/marcar-resuelto.php', {
                id: id,
                notas: result.value || ''
            }, function(response) {
                if (response.success) {
                    Swal.fire('¡Listo!', 'Conversación marcada como resuelta', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            });
        }
    });
}

function verHistorial(numeroCliente) {
    $('#modalHistorial').modal('show');
    $('#contenidoHistorial').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');

    $.get(API_URL + '/bot/historial-conversacion.php', {
        numero: numeroCliente
    }, function(response) {
        if (response.success) {
            let html = '<div class="direct-chat-messages" style="height: 400px; overflow-y: auto;">';
            
            response.data.forEach(function(msg) {
                if (msg.mensaje_cliente) {
                    html += `
                        <div class="direct-chat-msg right">
                            <div class="direct-chat-text bg-primary">
                                ${msg.mensaje_cliente}
                            </div>
                            <div class="direct-chat-info clearfix">
                                <span class="direct-chat-timestamp float-right">${msg.fecha_hora}</span>
                            </div>
                        </div>
                    `;
                }
                
                if (msg.respuesta_bot) {
                    html += `
                        <div class="direct-chat-msg">
                            <div class="direct-chat-text">
                                ${msg.respuesta_bot}
                            </div>
                            <div class="direct-chat-info clearfix">
                                <span class="direct-chat-timestamp float-left">Bot - ${msg.fecha_hora}</span>
                            </div>
                        </div>
                    `;
                }
            });
            
            html += '</div>';
            $('#contenidoHistorial').html(html);
        } else {
            $('#contenidoHistorial').html('<div class="alert alert-danger">Error cargando historial</div>');
        }
    });
}
</script>