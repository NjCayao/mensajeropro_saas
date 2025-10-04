<?php
$current_page = 'logs';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Filtros
$modulo = $_GET['modulo'] ?? '';
$accion = $_GET['accion'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$empresa_id = $_GET['empresa_id'] ?? '';
$limit = $_GET['limit'] ?? 100;

// Construir WHERE
$where = ["DATE(l.fecha) BETWEEN ? AND ?"];
$params = [$fecha_inicio, $fecha_fin];

if ($modulo) {
    $where[] = "l.modulo = ?";
    $params[] = $modulo;
}

if ($accion) {
    $where[] = "l.accion LIKE ?";
    $params[] = "%$accion%";
}

if ($empresa_id) {
    $where[] = "l.empresa_id = ?";
    $params[] = $empresa_id;
}

$whereClause = implode(' AND ', $where);

// Obtener logs
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        e.nombre_empresa,
        u.nombre as usuario_nombre
    FROM logs_sistema l
    LEFT JOIN empresas e ON l.empresa_id = e.id
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE $whereClause
    ORDER BY l.fecha DESC
    LIMIT ?
");
$params[] = (int)$limit;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener módulos únicos para filtro
$stmt = $pdo->query("SELECT DISTINCT modulo FROM logs_sistema ORDER BY modulo");
$modulos = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT empresa_id) as empresas_activas,
        COUNT(DISTINCT modulo) as modulos_usados
    FROM logs_sistema
    WHERE DATE(fecha) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats = $stmt->fetch();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-list"></i> Logs del Sistema</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Logs</li>
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
                        <span class="info-box-icon bg-info"><i class="fas fa-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Eventos</span>
                            <span class="info-box-number"><?= number_format($stats['total']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-building"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Empresas Activas</span>
                            <span class="info-box-number"><?= number_format($stats['empresas_activas']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-cogs"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Módulos Usados</span>
                            <span class="info-box-number"><?= number_format($stats['modulos_usados']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> Filtros</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" id="formFiltros">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Fecha Inicio:</label>
                                    <input type="date" class="form-control" name="fecha_inicio" 
                                           value="<?= htmlspecialchars($fecha_inicio) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Fecha Fin:</label>
                                    <input type="date" class="form-control" name="fecha_fin" 
                                           value="<?= htmlspecialchars($fecha_fin) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Módulo:</label>
                                    <select class="form-control" name="modulo">
                                        <option value="">Todos</option>
                                        <?php foreach ($modulos as $mod): ?>
                                            <option value="<?= $mod ?>" <?= $modulo === $mod ? 'selected' : '' ?>>
                                                <?= ucfirst($mod) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Acción:</label>
                                    <input type="text" class="form-control" name="accion" 
                                           placeholder="Buscar acción..." 
                                           value="<?= htmlspecialchars($accion) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Empresa ID:</label>
                                    <input type="number" class="form-control" name="empresa_id" 
                                           placeholder="ID de empresa" 
                                           value="<?= htmlspecialchars($empresa_id) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Límite de resultados:</label>
                                    <select class="form-control" name="limit">
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                                        <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>1000</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>&nbsp;</label><br>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                    <a href="<?= url('superadmin/logs') ?>" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                    <button type="button" class="btn btn-success" onclick="exportarLogs()">
                                        <i class="fas fa-file-excel"></i> Exportar CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de logs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Mostrando <?= count($logs) ?> registros
                    </h3>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 600px;">
                    <table class="table table-hover table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha/Hora</th>
                                <th>Empresa</th>
                                <th>Usuario</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Descripción</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        No hay logs para los filtros seleccionados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td>
                                            <small>
                                                <?= date('d/m/Y', strtotime($log['fecha'])) ?><br>
                                                <?= date('H:i:s', strtotime($log['fecha'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($log['nombre_empresa']): ?>
                                                <a href="<?= url('superadmin/empresas?search=' . urlencode($log['nombre_empresa'])) ?>">
                                                    <?= htmlspecialchars($log['nombre_empresa']) ?>
                                                </a>
                                                <br><small class="text-muted">ID: <?= $log['empresa_id'] ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Sistema</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['usuario_nombre']): ?>
                                                <?= htmlspecialchars($log['usuario_nombre']) ?>
                                                <br><small class="text-muted">ID: <?= $log['usuario_id'] ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= 
                                                $log['modulo'] === 'superadmin' ? 'danger' : 
                                                ($log['modulo'] === 'auth' ? 'warning' : 
                                                ($log['modulo'] === 'bot' ? 'info' : 'secondary')) 
                                            ?>">
                                                <?= htmlspecialchars($log['modulo']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($log['accion']) ?></code>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($log['descripcion'] ?? '-') ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= $log['ip_address'] ?? '-' ?></small>
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

<script>
function exportarLogs() {
    // Obtener parámetros del formulario
    const formData = new FormData(document.getElementById('formFiltros'));
    const params = new URLSearchParams(formData);
    
    // Redirigir a API de exportación
    window.location.href = API_URL + '/superadmin/exportar-logs.php?' + params.toString();
}

// Auto-submit al cambiar fechas rápidas
$(document).ready(function() {
    // Botones de fecha rápida
    $('.btn-fecha-rapida').on('click', function() {
        const dias = $(this).data('dias');
        const hoy = new Date();
        const inicio = new Date();
        inicio.setDate(inicio.getDate() - dias);
        
        $('input[name="fecha_inicio"]').val(inicio.toISOString().split('T')[0]);
        $('input[name="fecha_fin"]').val(hoy.toISOString().split('T')[0]);
        $('#formFiltros').submit();
    });
});
</script>