<?php
$current_page = 'bot-templates';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
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
                    <h1 class="m-0"><i class="fas fa-robot"></i> Gestión de Templates de Bot</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('superadmin/dashboard'); ?>">Dashboard</a></li>
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
                                        <span class="badge badge-<?= $template['tipo_bot'] == 'ventas' ? 'success' : ($template['tipo_bot'] == 'citas' ? 'info' : 'warning') ?>">
                                            <?= ucfirst($template['tipo_bot']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($template['nombre_template']) ?></td>
                                    <td>
                                        <?php if ($template['activo']): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-warning" onclick="editarTemplate(<?= $template['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-info" onclick="verTemplate(<?= $template['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="eliminarTemplate(<?= $template['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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
                                    <option value="soporte">Bot de Soporte</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nombre del Template:</label>
                        <input type="text" class="form-control" name="nombre_template" required>
                    </div>

                    <div class="form-group">
                        <label>Prompt del Bot (Personalidad):</label>
                        <textarea class="form-control" name="prompt_template" rows="8" required></textarea>
                        <small class="text-muted">Este es el prompt principal que define la personalidad del bot</small>
                    </div>

                    <div class="form-group">
                        <label>Instrucciones de Ventas (opcional):</label>
                        <textarea class="form-control" name="instrucciones_ventas" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Instrucciones de Citas (opcional):</label>
                        <textarea class="form-control" name="instrucciones_citas" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Información de Negocio Ejemplo:</label>
                        <textarea class="form-control" name="informacion_negocio_ejemplo" rows="4"></textarea>
                        <small class="text-muted">Ejemplo de información que el cliente debe proporcionar</small>
                    </div>

                    <div class="form-group">
                        <label>Mensaje Notificación Escalamiento:</label>
                        <textarea class="form-control" name="mensaje_notificacion_escalamiento" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Mensaje Notificación Ventas:</label>
                        <textarea class="form-control" name="mensaje_notificacion_ventas" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Mensaje Notificación Citas:</label>
                        <textarea class="form-control" name="mensaje_notificacion_citas" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Configuración Adicional (JSON):</label>
                        <textarea class="form-control" name="configuracion_adicional" rows="4"></textarea>
                        <small class="text-muted">Opcional. Formato JSON válido</small>
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
        // ✅ CORREGIDO: Sin .php
        $.get(API_URL + "/bot/obtener-template", {
            id: id
        }, function(response) {
            if (response.success) {
                const template = response.data;
                $('#modalTitle').text('Editar Template');
                $('#template_id').val(template.id);
                $('input[name="tipo_negocio"]').val(template.tipo_negocio);
                $('select[name="tipo_bot"]').val(template.tipo_bot);
                $('input[name="nombre_template"]').val(template.nombre_template);
                
                // personalidad_bot almacena el prompt_template
                $('textarea[name="prompt_template"]').val(template.personalidad_bot || '');
                $('textarea[name="instrucciones_ventas"]').val(template.instrucciones_ventas || '');
                $('textarea[name="instrucciones_citas"]').val(template.instrucciones_citas || '');
                $('textarea[name="informacion_negocio_ejemplo"]').val(template.informacion_negocio_ejemplo || '');
                $('textarea[name="mensaje_notificacion_escalamiento"]').val(template.mensaje_notificacion_escalamiento || '');
                $('textarea[name="mensaje_notificacion_ventas"]').val(template.mensaje_notificacion_ventas || '');
                $('textarea[name="mensaje_notificacion_citas"]').val(template.mensaje_notificacion_citas || '');
                
                // Manejar JSON
                if (template.configuracion_adicional) {
                    const config = typeof template.configuracion_adicional === 'string' 
                        ? template.configuracion_adicional 
                        : JSON.stringify(template.configuracion_adicional, null, 2);
                    $('textarea[name="configuracion_adicional"]').val(config);
                }
                
                $('#activo').prop('checked', template.activo == 1);
                $('#modalTemplate').modal('show');
            } else {
                mostrarError(response.message || 'Error al cargar template');
            }
        }).fail(function() {
            mostrarError('Error de conexión al cargar template');
        });
    }

    function verTemplate(id) {
        // ✅ CORREGIDO: Sin .php
        $.get(API_URL + "/bot/obtener-template", {
            id: id
        }, function(response) {
            if (response.success) {
                const template = response.data;
                Swal.fire({
                    title: template.nombre_template,
                    html: `
                    <div style="text-align: left;">
                        <p><strong>Tipo:</strong> ${template.tipo_negocio} - ${template.tipo_bot}</p>
                        <hr>
                        <p><strong>Prompt:</strong></p>
                        <pre style="background: #f8f9fa; padding: 10px; white-space: pre-wrap;">${template.personalidad_bot || 'No configurado'}</pre>
                        <hr>
                        <p><strong>Mensajes de Notificación:</strong></p>
                        <p><strong>Escalamiento:</strong><br>${template.mensaje_notificacion_escalamiento || 'No configurado'}</p>
                        <p><strong>Ventas:</strong><br>${template.mensaje_notificacion_ventas || 'No configurado'}</p>
                        <p><strong>Citas:</strong><br>${template.mensaje_notificacion_citas || 'No configurado'}</p>
                    </div>
                `,
                    width: '800px',
                    confirmButtonText: 'Cerrar'
                });
            } else {
                mostrarError('Error al cargar template');
            }
        });
    }

    $('#formTemplate').on('submit', function(e) {
        e.preventDefault();

        // Validar JSON si existe
        const jsonConfig = $('textarea[name="configuracion_adicional"]').val().trim();
        if (jsonConfig) {
            try {
                JSON.parse(jsonConfig);
            } catch (e) {
                mostrarError('La configuración adicional debe ser JSON válido');
                return;
            }
        }

        const formData = $(this).serialize();
        // ✅ CORREGIDO: Sin .php
        const url = $('#template_id').val() ?
            API_URL + "/bot/actualizar-template" :
            API_URL + "/bot/crear-template";

        $.post(url, formData, function(response) {
            if (response.success) {
                $('#modalTemplate').modal('hide');
                Swal.fire('Éxito', response.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                mostrarError(response.message || 'Error al guardar template');
            }
        }).fail(function() {
            mostrarError('Error de conexión al guardar');
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
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // ✅ CORREGIDO: Sin .php
                $.post(API_URL + "/bot/eliminar-template", {
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Eliminado', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        mostrarError(response.message || 'Error al eliminar');
                    }
                }).fail(function() {
                    mostrarError('Error de conexión al eliminar');
                });
            }
        });
    }
</script>