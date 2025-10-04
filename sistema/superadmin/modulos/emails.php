<?php
$current_page = 'emails';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener plantillas
$filter = $_GET['filter'] ?? 'todas';
$where = "WHERE 1=1";

if ($filter === 'activas') {
    $where .= " AND activa = 1";
} elseif ($filter === 'inactivas') {
    $where .= " AND activa = 0";
}

$stmt = $pdo->query("
    SELECT * FROM plantillas_email
    $where
    ORDER BY categoria, codigo
");
$plantillas = $stmt->fetchAll();

// Agrupar por categoría
$plantillas_por_categoria = [];
foreach ($plantillas as $plantilla) {
    $plantillas_por_categoria[$plantilla['categoria']][] = $plantilla;
}
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-envelope"></i> Plantillas de Email</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Plantillas Email</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="alert alert-info">
                <i class="icon fas fa-info-circle"></i>
                Las plantillas usan variables como <code>{{nombre_empresa}}</code>, <code>{{email}}</code>, etc. 
                Estas se reemplazan automáticamente al enviar el email.
            </div>

            <!-- Filtros -->
            <div class="card">
                <div class="card-body">
                    <div class="btn-group">
                        <a href="?filter=todas" class="btn btn-sm <?= $filter === 'todas' ? 'btn-primary' : 'btn-default' ?>">
                            Todas
                        </a>
                        <a href="?filter=activas" class="btn btn-sm <?= $filter === 'activas' ? 'btn-success' : 'btn-default' ?>">
                            Activas
                        </a>
                        <a href="?filter=inactivas" class="btn btn-sm <?= $filter === 'inactivas' ? 'btn-secondary' : 'btn-default' ?>">
                            Inactivas
                        </a>
                    </div>
                    
                    <button class="btn btn-primary float-right" onclick="nuevaPlantilla()">
                        <i class="fas fa-plus"></i> Nueva Plantilla
                    </button>
                </div>
            </div>

            <!-- Plantillas por categoría -->
            <?php foreach ($plantillas_por_categoria as $categoria => $items): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-folder"></i> 
                            <?= ucfirst($categoria) ?> 
                            <span class="badge badge-secondary"><?= count($items) ?></span>
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 40px">#</th>
                                    <th>Código</th>
                                    <th>Asunto</th>
                                    <th>Variables</th>
                                    <th>Estado</th>
                                    <th style="width: 200px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $plantilla): 
                                    $variables = json_decode($plantilla['variables'] ?? '[]', true);
                                ?>
                                    <tr>
                                        <td><?= $plantilla['id'] ?></td>
                                        <td>
                                            <code><?= htmlspecialchars($plantilla['codigo']) ?></code>
                                            <?php if (!$plantilla['editable']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-lock"></i> Sistema
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($plantilla['asunto']) ?></td>
                                        <td>
                                            <small>
                                                <?php foreach (array_slice($variables, 0, 3) as $var): ?>
                                                    <span class="badge badge-light">{{<?= $var ?>}}</span>
                                                <?php endforeach; ?>
                                                <?php if (count($variables) > 3): ?>
                                                    <span class="badge badge-secondary">+<?= count($variables) - 3 ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $plantilla['activa'] ? 'success' : 'secondary' ?>">
                                                <?= $plantilla['activa'] ? 'Activa' : 'Inactiva' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info" 
                                                        onclick="verPreview(<?= $plantilla['id'] ?>)"
                                                        title="Vista previa">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-warning" 
                                                        onclick="editarPlantilla(<?= $plantilla['id'] ?>)"
                                                        title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($plantilla['activa']): ?>
                                                    <button class="btn btn-secondary" 
                                                            onclick="togglePlantilla(<?= $plantilla['id'] ?>, 0)"
                                                            title="Desactivar">
                                                        <i class="fas fa-toggle-on"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-success" 
                                                            onclick="togglePlantilla(<?= $plantilla['id'] ?>, 1)"
                                                            title="Activar">
                                                        <i class="fas fa-toggle-off"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($plantilla['editable']): ?>
                                                    <button class="btn btn-danger" 
                                                            onclick="eliminarPlantilla(<?= $plantilla['id'] ?>)"
                                                            title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>
</div>

<!-- Modal Editar/Nueva Plantilla -->
<div class="modal fade" id="modalPlantilla" data-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="modalTitle">Nueva Plantilla</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="formPlantilla">
                <div class="modal-body">
                    <input type="hidden" id="plantilla_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Código Único: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="codigo" 
                                       placeholder="ej: mi_plantilla_custom" required>
                                <small class="text-muted">Solo letras, números y guiones bajos</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Categoría:</label>
                                <select class="form-control" name="categoria" required>
                                    <option value="bienvenida">Bienvenida</option>
                                    <option value="pago">Pago</option>
                                    <option value="trial">Trial</option>
                                    <option value="recordatorio">Recordatorio</option>
                                    <option value="soporte">Soporte</option>
                                    <option value="notificacion">Notificación</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Asunto del Email: <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="asunto" 
                               placeholder="Puede usar variables como {{nombre_empresa}}" required>
                    </div>

                    <div class="form-group">
                        <label>Descripción:</label>
                        <input type="text" class="form-control" name="descripcion" 
                               placeholder="Descripción breve de la plantilla">
                    </div>

                    <div class="form-group">
                        <label>Contenido HTML: <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="contenido_html" 
                                  rows="15" id="contenido_html" required></textarea>
                        <small class="text-muted">
                            HTML completo del email. Usa variables como {{nombre_empresa}}, {{email}}, etc.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Variables Disponibles (JSON):</label>
                        <input type="text" class="form-control" name="variables" 
                               placeholder='["nombre_empresa","email","plan"]'>
                        <small class="text-muted">
                            Array JSON con las variables que usa esta plantilla
                        </small>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" 
                                   id="activa" name="activa" checked>
                            <label class="custom-control-label" for="activa">
                                Plantilla Activa
                            </label>
                        </div>
                    </div>

                    <!-- Variables comunes de referencia -->
                    <div class="alert alert-secondary">
                        <strong>Variables comunes:</strong><br>
                        <code>{{nombre_empresa}}</code>
                        <code>{{email}}</code>
                        <code>{{plan_nombre}}</code>
                        <code>{{fecha_expiracion}}</code>
                        <code>{{url_login}}</code>
                        <code>{{monto}}</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Preview -->
<div class="modal fade" id="modalPreview">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Vista Previa</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Asunto:</strong> <span id="preview_asunto"></span>
                    </div>
                </div>
                <hr>
                <iframe id="preview_iframe" style="width: 100%; height: 500px; border: 1px solid #ddd;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
function nuevaPlantilla() {
    $('#modalTitle').text('Nueva Plantilla');
    $('#formPlantilla')[0].reset();
    $('#plantilla_id').val('');
    $('#modalPlantilla').modal('show');
}

function editarPlantilla(id) {
    $.get(API_URL + '/superadmin/email-detalles.php', {id: id}, function(response) {
        if (response.success) {
            const p = response.data;
            $('#modalTitle').text('Editar Plantilla');
            $('#plantilla_id').val(p.id);
            $('input[name="codigo"]').val(p.codigo);
            $('select[name="categoria"]').val(p.categoria);
            $('input[name="asunto"]').val(p.asunto);
            $('input[name="descripcion"]').val(p.descripcion);
            $('#contenido_html').val(p.contenido_html);
            $('input[name="variables"]').val(p.variables || '[]');
            $('#activa').prop('checked', p.activa == 1);
            
            // Deshabilitar código si no es editable
            if (p.editable == 0) {
                $('input[name="codigo"]').prop('readonly', true);
            }
            
            $('#modalPlantilla').modal('show');
        } else {
            mostrarError('Error al cargar plantilla');
        }
    });
}

function verPreview(id) {
    $.get(API_URL + '/superadmin/email-detalles.php', {id: id}, function(response) {
        if (response.success) {
            const p = response.data;
            $('#preview_asunto').text(p.asunto);
            
            const iframe = document.getElementById('preview_iframe');
            iframe.srcdoc = p.contenido_html;
            
            $('#modalPreview').modal('show');
        }
    });
}

function togglePlantilla(id, activa) {
    $.post(API_URL + '/superadmin/toggle-email.php', {
        id: id,
        activa: activa
    }, function(response) {
        if (response.success) {
            mostrarExito(activa ? 'Plantilla activada' : 'Plantilla desactivada');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarError(response.message);
        }
    });
}

function eliminarPlantilla(id) {
    Swal.fire({
        title: '¿Eliminar plantilla?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(API_URL + '/superadmin/eliminar-email.php', {id: id}, function(response) {
                if (response.success) {
                    mostrarExito('Plantilla eliminada');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarError(response.message);
                }
            });
        }
    });
}

$('#formPlantilla').on('submit', function(e) {
    e.preventDefault();
    
    // Validar JSON de variables
    const variables = $('input[name="variables"]').val();
    if (variables) {
        try {
            JSON.parse(variables);
        } catch (e) {
            mostrarError('El formato de variables debe ser JSON válido. Ejemplo: ["nombre","email"]');
            return;
        }
    }
    
    const formData = $(this).serialize();
    const url = $('#plantilla_id').val() ? 
        API_URL + '/superadmin/guardar-email.php' : 
        API_URL + '/superadmin/crear-email.php';
    
    $.post(url, formData, function(response) {
        if (response.success) {
            mostrarExito('Plantilla guardada correctamente');
            $('#modalPlantilla').modal('hide');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarError(response.message);
        }
    });
});
</script>