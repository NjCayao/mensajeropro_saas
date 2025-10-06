<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session_check.php';
require_once __DIR__ . '/../../includes/multi_tenant.php';

$page_title = 'Dashboard';
$current_page = 'dashboard';

require_once __DIR__ . '/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

$empresa_id = getEmpresaActual();
$stats = [];

// Total contactos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE activo = 1 AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['total_contactos'] = $stmt->fetchColumn();

// Total categorías
$stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE activo = 1 AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['total_categorias'] = $stmt->fetchColumn();

// Contactos hoy
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE DATE(fecha_registro) = CURDATE() AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['contactos_hoy'] = $stmt->fetchColumn();

// Mensajes hoy
$stmt = $pdo->prepare("SELECT COUNT(*) FROM historial_mensajes WHERE DATE(fecha) = CURDATE() AND tipo = 'saliente' AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['mensajes_hoy'] = $stmt->fetchColumn();

// Mensajes mes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM historial_mensajes WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND tipo = 'saliente' AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['mensajes_mes'] = $stmt->fetchColumn();

// Programados pendientes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM mensajes_programados WHERE estado = 'pendiente' AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['programados_pendientes'] = $stmt->fetchColumn();

// Bot conversaciones
$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversaciones_bot WHERE DATE(fecha_hora) = CURDATE() AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['bot_conversaciones'] = $stmt->fetchColumn();

// Escalados pendientes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM estados_conversacion WHERE estado = 'escalado_humano' AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$stats['escalados_pendientes'] = $stmt->fetchColumn();

// Estado WhatsApp
$stmt = $pdo->prepare("SELECT * FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$whatsapp = $stmt->fetch();
$whatsapp_conectado = $whatsapp && $whatsapp['estado'] == 'conectado';

// Contactos por categoría
$stmt = $pdo->prepare("
    SELECT c.nombre, c.color, COUNT(co.id) as total
    FROM categorias c
    LEFT JOIN contactos co ON c.id = co.categoria_id AND co.activo = 1 AND co.empresa_id = ?
    WHERE c.activo = 1 AND c.empresa_id = ?
    GROUP BY c.id
    ORDER BY total DESC
");
$stmt->execute([$empresa_id, $empresa_id]);
$categorias_stats = $stmt->fetchAll();

// Mensajes últimos 7 días
$stmt = $pdo->prepare("
    SELECT DATE(fecha) as dia, COUNT(*) as total
    FROM historial_mensajes
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND tipo = 'saliente'
    AND empresa_id = ?
    GROUP BY DATE(fecha)
    ORDER BY dia
");
$stmt->execute([$empresa_id]);
$mensajes_semana = $stmt->fetchAll();

// Actividad reciente
$stmt = $pdo->prepare("
    SELECT 
        'contacto' as tipo,
        CONCAT('Nuevo contacto: ', nombre) as descripcion,
        fecha_registro as fecha
    FROM contactos
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND empresa_id = ?
    
    UNION ALL
    
    SELECT 
        'mensaje' as tipo,
        CONCAT('Mensaje programado: ', titulo) as descripcion,
        fecha_creacion as fecha
    FROM mensajes_programados
    WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND empresa_id = ?
    
    UNION ALL
    
    SELECT 
        'bot' as tipo,
        CONCAT('Conversación bot con: ', numero_cliente) as descripcion,
        fecha_hora as fecha
    FROM conversaciones_bot
    WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND empresa_id = ?
    
    ORDER BY fecha DESC
    LIMIT 10
");
$stmt->execute([$empresa_id, $empresa_id, $empresa_id]);
$actividad_reciente = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dashboard</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <span class="text-muted">
                        <i class="fas fa-clock"></i>
                        <?php echo date('d/m/Y H:i'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="alert <?php echo $whatsapp_conectado ? 'alert-success' : 'alert-warning'; ?>">
                        <h5>
                            <i class="icon fas fa-<?php echo $whatsapp_conectado ? 'check' : 'exclamation-triangle'; ?>"></i>
                            Estado WhatsApp
                        </h5>
                        <?php if ($whatsapp_conectado): ?>
                            <p><strong>Conectado:</strong> <?php echo $whatsapp['numero_conectado'] ?? 'No identificado'; ?></p>
                        <?php else: ?>
                            <p>Servicio desconectado. <a href="<?php echo url('cliente/whatsapp'); ?>">Conectar ahora</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo number_format($stats['total_contactos']); ?></h3>
                            <p>Total Contactos</p>
                        </div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <a href="<?php echo url('cliente/contactos'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo number_format($stats['mensajes_hoy']); ?></h3>
                            <p>Mensajes Hoy</p>
                        </div>
                        <div class="icon"><i class="fas fa-paper-plane"></i></div>
                        <a href="<?php echo url('cliente/mensajes'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $stats['programados_pendientes']; ?></h3>
                            <p>Programados Pendientes</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                        <a href="<?php echo url('cliente/programados'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo $stats['escalados_pendientes']; ?></h3>
                            <p>Escalados Pendientes</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <a href="<?php echo url('cliente/escalados'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-purple">
                        <div class="inner">
                            <h3><?php echo number_format($stats['mensajes_mes']); ?></h3>
                            <p>Mensajes Este Mes</p>
                        </div>
                        <div class="icon"><i class="fas fa-chart-bar"></i></div>
                        <a href="<?php echo url('cliente/mensajes'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-teal">
                        <div class="inner">
                            <h3><?php echo $stats['bot_conversaciones']; ?></h3>
                            <p>Conversaciones Bot Hoy</p>
                        </div>
                        <div class="icon"><i class="fas fa-robot"></i></div>
                        <a href="<?php echo url('cliente/bot-config'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?php echo $stats['contactos_hoy']; ?></h3>
                            <p>Contactos Nuevos Hoy</p>
                        </div>
                        <div class="icon"><i class="fas fa-user-plus"></i></div>
                        <a href="<?php echo url('cliente/contactos'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3><?php echo $stats['total_categorias']; ?></h3>
                            <p>Categorías Activas</p>
                        </div>
                        <div class="icon"><i class="fas fa-tags"></i></div>
                        <a href="<?php echo url('cliente/categorias'); ?>" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Distribución de Contactos</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="pieChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line"></i> Mensajes Últimos 7 Días</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="lineChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history"></i> Actividad Reciente (24h)</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="products-list product-list-in-card pl-2 pr-2">
                                <?php foreach ($actividad_reciente as $actividad): ?>
                                    <li class="item">
                                        <div class="product-info ml-0">
                                            <?php
                                            $icono = 'circle';
                                            $color = 'info';
                                            if ($actividad['tipo'] == 'contacto') {
                                                $icono = 'user-plus';
                                                $color = 'success';
                                            } elseif ($actividad['tipo'] == 'mensaje') {
                                                $icono = 'envelope';
                                                $color = 'primary';
                                            } elseif ($actividad['tipo'] == 'bot') {
                                                $icono = 'robot';
                                                $color = 'warning';
                                            }
                                            ?>
                                            <i class="fas fa-<?php echo $icono; ?> text-<?php echo $color; ?>"></i>
                                            <span class="product-title ml-2"><?php echo htmlspecialchars($actividad['descripcion']); ?></span>
                                            <span class="badge badge-secondary float-right"><?php echo date('H:i', strtotime($actividad['fecha'])); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>

                                <?php if (empty($actividad_reciente)): ?>
                                    <li class="item text-center text-muted p-3">No hay actividad reciente</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tags"></i> Contactos por Categoría</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id IS NULL AND activo = 1 AND empresa_id = ?");
                            $stmt->execute([$empresa_id]);
                            $sin_categoria = $stmt->fetchColumn();
                            ?>

                            <?php foreach ($categorias_stats as $cat): ?>
                                <div class="progress-group mb-3">
                                    <span class="progress-text">
                                        <span class="badge" style="background-color: <?php echo $cat['color']; ?>">
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </span>
                                    </span>
                                    <span class="float-right"><b><?php echo $cat['total']; ?></b>/<?php echo $stats['total_contactos']; ?></span>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar" style="background-color: <?php echo $cat['color']; ?>; width: <?php echo ($stats['total_contactos'] > 0) ? ($cat['total'] / $stats['total_contactos'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($sin_categoria > 0): ?>
                                <div class="progress-group">
                                    <span class="progress-text text-muted">Sin categoría</span>
                                    <span class="float-right text-muted"><b><?php echo $sin_categoria; ?></b>/<?php echo $stats['total_contactos']; ?></span>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-gray" style="width: <?php echo ($stats['total_contactos'] > 0) ? ($sin_categoria / $stats['total_contactos'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once 'layouts/footer.php'; ?>

<script src="<?php echo asset('plugins/chart.js/Chart.min.js'); ?>"></script>
<script>
var pieData = {
    labels: [
        <?php
        foreach ($categorias_stats as $cat) {
            echo "'" . addslashes($cat['nombre']) . "',";
        }
        if ($sin_categoria > 0) echo "'Sin categoría'";
        ?>
    ],
    datasets: [{
        data: [
            <?php
            foreach ($categorias_stats as $cat) {
                echo $cat['total'] . ",";
            }
            if ($sin_categoria > 0) echo $sin_categoria;
            ?>
        ],
        backgroundColor: [
            <?php
            foreach ($categorias_stats as $cat) {
                echo "'" . $cat['color'] . "',";
            }
            if ($sin_categoria > 0) echo "'#6c757d'";
            ?>
        ]
    }]
};

var pieChartCanvas = $('#pieChart').get(0).getContext('2d');
new Chart(pieChartCanvas, {
    type: 'pie',
    data: pieData,
    options: {
        maintainAspectRatio: false,
        responsive: true,
        legend: { position: 'bottom' }
    }
});

var dias = {};
<?php
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    echo "dias['" . $fecha . "'] = 0;\n";
}
foreach ($mensajes_semana as $dia) {
    echo "dias['" . $dia['dia'] . "'] = " . $dia['total'] . ";\n";
}
?>

var lineData = {
    labels: [<?php 
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = "'" . date('d/m', strtotime("-$i days")) . "'";
        }
        echo implode(',', $labels);
    ?>],
    datasets: [{
        label: 'Mensajes enviados',
        data: [<?php 
            $valores = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = date('Y-m-d', strtotime("-$i days"));
                $valores[] = isset($dias[$fecha]) ? $dias[$fecha] : 0;
            }
            echo implode(',', $valores);
        ?>],
        borderColor: 'rgb(60,141,188)',
        backgroundColor: 'rgba(60,141,188,0.1)',
        pointRadius: 5,
        pointHoverRadius: 7,
        tension: 0.1
    }]
};

var lineChartCanvas = $('#lineChart').get(0).getContext('2d');
new Chart(lineChartCanvas, {
    type: 'line',
    data: lineData,
    options: {
        maintainAspectRatio: false,
        responsive: true,
        legend: { display: false },
        scales: {
            yAxes: [{
                ticks: { beginAtZero: true, stepSize: 1 }
            }]
        }
    }
});
</script>