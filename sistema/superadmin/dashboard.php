<?php
$current_page = 'dashboard';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/superadmin_session_check.php';
require_once __DIR__ . '/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

// Estadísticas generales
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_empresas,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as empresas_activas,
        SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as empresas_suspendidas,
        SUM(CASE WHEN plan_id = 1 THEN 1 ELSE 0 END) as empresas_trial
    FROM empresas
");
$stats = $stmt->fetch();

// Ingresos del mes
$stmt = $pdo->query("
    SELECT COALESCE(SUM(monto), 0) as ingresos_mes
    FROM pagos
    WHERE estado = 'aprobado'
    AND MONTH(fecha_pago) = MONTH(CURRENT_DATE())
    AND YEAR(fecha_pago) = YEAR(CURRENT_DATE())
");
$ingresos = $stmt->fetch();

// Empresas recientes
$stmt = $pdo->query("
    SELECT e.*, p.nombre as plan_nombre
    FROM empresas e
    LEFT JOIN planes p ON e.plan_id = p.id
    ORDER BY e.fecha_registro DESC
    LIMIT 10
");
$empresas_recientes = $stmt->fetchAll();

// Pagos recientes
$stmt = $pdo->query("
    SELECT p.*, e.nombre_empresa
    FROM pagos p
    INNER JOIN empresas e ON p.empresa_id = e.id
    WHERE p.estado = 'aprobado'
    ORDER BY p.fecha_pago DESC
    LIMIT 10
");
$pagos_recientes = $stmt->fetchAll();

// Distribución de planes
$stmt = $pdo->query("
    SELECT p.nombre, COUNT(e.id) as total
    FROM planes p
    LEFT JOIN empresas e ON e.plan_id = p.id
    GROUP BY p.id, p.nombre
    ORDER BY total DESC
");
$distribucion_planes = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-shield-alt"></i> Dashboard SuperAdmin
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            <!-- Estadísticas principales -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($stats['total_empresas']) ?></h3>
                            <p>Total Empresas</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <a href="<?= url('superadmin/empresas') ?>" class="small-box-footer">
                            Ver más <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($stats['empresas_activas']) ?></h3>
                            <p>Empresas Activas</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <a href="<?= url('superadmin/empresas?filter=activas') ?>" class="small-box-footer">
                            Ver más <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($stats['empresas_trial']) ?></h3>
                            <p>En Trial</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <a href="<?= url('superadmin/empresas?filter=trial') ?>" class="small-box-footer">
                            Ver más <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>$<?= number_format($ingresos['ingresos_mes'], 2) ?></h3>
                            <p>Ingresos del Mes</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <a href="<?= url('superadmin/pagos') ?>" class="small-box-footer">
                            Ver más <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Gráficos y tablas -->
            <div class="row">
                <!-- Distribución de Planes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie"></i> Distribución de Planes
                            </h3>
                        </div>
                        <div class="card-body">
                            <canvas id="pieChartPlanes" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Empresas Recientes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-building"></i> Empresas Recientes
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Plan</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empresas_recientes as $empresa): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= url('superadmin/empresas?id=' . $empresa['id']) ?>">
                                                    <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?= htmlspecialchars($empresa['plan_nombre']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($empresa['fecha_registro'])) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $empresa['activo'] ? 'success' : 'danger' ?>">
                                                    <?= $empresa['activo'] ? 'Activo' : 'Suspendido' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagos Recientes -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card"></i> Pagos Recientes
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Empresa</th>
                                        <th>Monto</th>
                                        <th>Método</th>
                                        <th>Referencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos_recientes as $pago): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></td>
                                            <td><?= htmlspecialchars($pago['nombre_empresa']) ?></td>
                                            <td><strong>$<?= number_format($pago['monto'], 2) ?></strong></td>
                                            <td>
                                                <?php if ($pago['metodo'] == 'mercadopago'): ?>
                                                    <i class="fas fa-credit-card text-info"></i>
                                                <?php elseif ($pago['metodo'] == 'paypal'): ?>
                                                    <i class="fab fa-paypal text-primary"></i>
                                                <?php endif; ?>
                                                <?= ucfirst($pago['metodo']) ?>
                                            </td>
                                            <td><small><?= $pago['referencia_externa'] ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

<!-- ChartJS -->
<script src="<?= asset('plugins/chart.js/Chart.min.js') ?>"></script>

<script>
// Gráfico de distribución de planes
const pieData = {
    labels: [
        <?php foreach ($distribucion_planes as $plan): ?>
            '<?= $plan['nombre'] ?>',
        <?php endforeach; ?>
    ],
    datasets: [{
        data: [
            <?php foreach ($distribucion_planes as $plan): ?>
                <?= $plan['total'] ?>,
            <?php endforeach; ?>
        ],
        backgroundColor: ['#f39c12', '#00a65a', '#dd4b39', '#00c0ef'],
    }]
};

const pieOptions = {
    maintainAspectRatio: false,
    responsive: true,
};

new Chart(document.getElementById('pieChartPlanes'), {
    type: 'pie',
    data: pieData,
    options: pieOptions
});
</script>