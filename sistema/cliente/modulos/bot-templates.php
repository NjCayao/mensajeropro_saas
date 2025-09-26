<?php
$current_page = 'bot-templates';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener todas las plantillas
$stmt = $pdo->query("SELECT * FROM bot_templates ORDER BY tipo_bot, tipo_negocio");
$templates = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Gestión de Templates de Bot</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Templates Bot</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Templates Disponibles</h3>
                    <div class="float-right">
                        <button class="btn btn-primary" onclick="nuevoTemplate()">
                            <i class="fas fa-plus"></i> Nuevo Template
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo Negocio</th>
                                <th>Tipo Bot</th>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><?= $template['id'] ?></td>
                                <td><?= ucfirst($template['tipo_negocio']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $template['tipo_bot'] == 'ventas' ? 'success' : 'info' ?>">
                                        <?= ucfirst($template['tipo_bot']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($template['nombre_template']) ?></td>
                                <td>
                                    <?php if($template['activo']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editarTemplate(<?= $template['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="verTemplate(<?= $template['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="eliminarTemplate(<?= $template['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<!-- Modal para editar template -->
<div class="modal fade" id="modalTemplate">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Nuevo Template</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formTemplate">
                <div class="modal-body">
                    <input type="hidden" id="template_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo de Negocio:</label>
                                <input type="text" class="form-control" name="tipo_negocio" 
                                    placeholder="Ej: restaurante, tienda, clinica" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo de Bot:</label>
                                <select class="form-control" name="tipo_bot" required>
                                    <option value="ventas">Bot de Ventas</option>
                                    <option value="citas">Bot de Citas</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nombre del Template:</label>
                        <input type="text" class="form-control" name="nombre_template" required>
                    </div>

                    <div class="form-group">
                        <label>Prompt del Bot:</label>
                        <textarea class="form-control" name="prompt_template" rows="8" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Respuestas Rápidas (JSON):</label>
                        <textarea class="form-control" name="respuestas_rapidas" rows="6" 
                            placeholder='{"pregunta1": "respuesta1", "pregunta2": "respuesta2"}'></textarea>
                    </div>

                    <div class="form-group">
                        <label>Configuración Adicional (JSON):</label>
                        <textarea class="form-control" name="configuracion_adicional" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="activo" name="activo" checked>
                            <label class="custom-control-label" for="activo">Template Activo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
function nuevoTemplate() {
    $('#modalTitle').text('Nuevo Template');
    $('#formTemplate')[0].reset();
    $('#template_id').val('');
    $('#modalTemplate').modal('show');
}

function editarTemplate(id) {
    $.get(API_URL + "/bot/obtener-template.php", { id: id }, function(response) {
        if (response.success) {
            const template = response.data;
            $('#modalTitle').text('Editar Template');
            $('#template_id').val(template.id);
            $('input[name="tipo_negocio"]').val(template.tipo_negocio);
            $('select[name="tipo_bot"]').val(template.tipo_bot);
            $('input[name="nombre_template"]').val(template.nombre_template);
            $('textarea[name="prompt_template"]').val(template.prompt_template);
            $('textarea[name="respuestas_rapidas"]').val(JSON.stringify(template.respuestas_rapidas_template, null, 2));
            $('textarea[name="configuracion_adicional"]').val(JSON.stringify(template.configuracion_adicional, null, 2));
            $('#activo').prop('checked', template.activo == 1);
            $('#modalTemplate').modal('show');
        }
    });
}

function verTemplate(id) {
    $.get(API_URL + "/bot/obtener-template.php", { id: id }, function(response) {
        if (response.success) {
            const template = response.data;
            Swal.fire({
                title: template.nombre_template,
                html: `
                    <div style="text-align: left;">
                        <p><strong>Tipo:</strong> ${template.tipo_negocio} - ${template.tipo_bot}</p>
                        <hr>
                        <p><strong>Prompt:</strong></p>
                        <pre style="background: #f8f9fa; padding: 10px; white-space: pre-wrap;">${template.prompt_template}</pre>
                        <hr>
                        <p><strong>Respuestas Rápidas:</strong></p>
                        <pre style="background: #f8f9fa; padding: 10px;">${JSON.stringify(template.respuestas_rapidas_template, null, 2)}</pre>
                    </div>
                `,
                width: '800px'
            });
        }
    });
}

$('#formTemplate').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    const url = $('#template_id').val() ? 
        API_URL + "/bot/actualizar-template.php" : 
        API_URL + "/bot/crear-template.php";
    
    $.post(url, formData, function(response) {
        if (response.success) {
            $('#modalTemplate').modal('hide');
            Swal.fire('Éxito', response.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', response.message, 'error');
        }
    });
});

function eliminarTemplate(id) {
    Swal.fire({
        title: '¿Eliminar template?',
        text: "Esta acción no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(API_URL + "/bot/eliminar-template.php", { id: id }, function(response) {
                if (response.success) {
                    Swal.fire('Eliminado', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            });
        }
    });
}
</script>