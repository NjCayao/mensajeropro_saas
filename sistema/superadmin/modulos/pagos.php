<?php
$current_page = 'pagos';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Filtros
$filter = $_GET['filter'] ?? 'todos';
$search = $_GET['search'] ?? '';

// Query base
$where = "WHERE 1=1";
$params = [];

if ($filter === 'aprobado') {
    $where .= " AND p.estado = 'aprobado'";
} elseif ($filter === 'pendiente') {
    $where .= " AND p.estado = 'pendiente'";
} elseif ($filter === 'rechazado') {
    $where .= " AND p.estado = 'rechazado'";
}

if ($search) {
    $where .= " AND (e.nombre_empresa LIKE ? OR p.referencia_externa LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Obtener pagos
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        e.nombre_empresa,
        pl.nombre as plan_nombre
    FROM pagos p
    INNER JOIN empresas e ON p.empresa_id = e.id
    LEFT JOIN planes pl ON p.plan_id = pl.id
    $where
    ORDER BY p.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$pagos = $stmt->fetchAll();

// Estadísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_pagos,
        COALESCE(SUM(CASE WHEN estado = 'aprobado' THEN monto ELSE 0 END), 0) as total_aprobado,
        COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END), 0) as total_pendiente
    FROM pagos
");
$stats = $stmt->fetch();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-credit-card"></i> Gestión de Pagos</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Pagos</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Estadísticas -->
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Pagos</span>
                            <span class="info-box-number"><?= number_format($stats['total_pagos']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Aprobado</span>
                            <span class="info-box-number">$<?= number_format($stats['total_aprobado'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pendientes</span>
                            <span class="info-box-number"><?= number_format($stats['total_pendiente']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card">
                <div class="card-body">
                    <form method="get" class="form-inline">
                        <div class="form-group mr-2">
                            <label class="mr-2">Filtrar:</label>
                            <select name="filter" class="form-control" onchange="this.form.submit()">
                                <option value="todos" <?= $filter === 'todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="aprobado" <?= $filter === 'aprobado' ? 'selected' : '' ?>>Aprobados</option>
                                <option value="pendiente" <?= $filter === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                <option value="rechazado" <?= $filter === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
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

                        <?php if ($filter !== 'todos' || $search): ?>
                            <a href="<?= url('superadmin/pagos') ?>" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabla de pagos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Listado de Pagos</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Empresa</th>
                                <th>Plan</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Referencia</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pagos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        No hay pagos registrados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr>
                                        <td><?= $pago['id'] ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($pago['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($pago['nombre_empresa']) ?></td>
                                        <td>
                                            <?php if ($pago['plan_nombre']): ?>
                                                <span class="badge badge-info"><?= $pago['plan_nombre'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>$<?= number_format($pago['monto'], 2) ?></strong></td>
                                        <td>
                                            <?php if ($pago['metodo'] == 'mercadopago'): ?>
                                                <i class="fas fa-credit-card text-info"></i> MercadoPago
                                            <?php elseif ($pago['metodo'] == 'paypal'): ?>
                                                <i class="fab fa-paypal text-primary"></i> PayPal
                                            <?php else: ?>
                                                <i class="fas fa-exchange-alt"></i> <?= ucfirst($pago['metodo']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= $pago['referencia_externa'] ?? '-' ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'aprobado' => 'success',
                                                'pendiente' => 'warning',
                                                'rechazado' => 'danger',
                                                'reembolsado' => 'secondary'
                                            ];
                                            $class = $badge_class[$pago['estado']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-<?= $class ?>">
                                                <?= ucfirst($pago['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>