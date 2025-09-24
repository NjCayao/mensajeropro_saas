<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Obtener plantillas
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre as categoria_nombre,
           COALESCE(p.variables, '[]') as variables
    FROM plantillas_mensajes p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.empresa_id = ?
    ORDER BY p.veces_usado DESC, p.nombre ASC
");
$stmt->execute([$empresa_id]);
$plantillas = $stmt->fetchAll();

// Obtener categorías
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE activo = 1 AND empresa_id = ? ORDER BY nombre");
$stmt->execute([$empresa_id]);
$categorias = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Plantillas de Mensajes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Plantillas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Listado de Plantillas</h3>
                            <button class="btn btn-primary float-right" onclick="nuevaPlantilla()">
                                <i class="fas fa-plus"></i> Nueva Plantilla
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="tablaPlantillas" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Vista Previa</th>
                                        <th>Variables</th>
                                        <th>Categoría</th>
                                        <th>Uso</th>
                                        <th>Veces Usado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plantillas as $plantilla): ?>
                                        <tr>
                                            <td><?= $plantilla['id'] ?></td>
                                            <td><strong><?= htmlspecialchars($plantilla['nombre']) ?></strong></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($plantilla['mensaje'], 0, 100)) ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $variables = json_decode($plantilla['variables'] ?: '[]', true);
                                                foreach ($variables as $var):
                                                ?>
                                                    <span class="badge badge-secondary"><?= $var ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php if ($plantilla['categoria_nombre']): ?>
                                                    <span class="badge badge-info"><?= htmlspecialchars($plantilla['categoria_nombre']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-primary">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($plantilla['uso_general']): ?>
                                                    <span class="badge badge-success">General</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Específico</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-pill badge-primary"><?= $plantilla['veces_usado'] ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verPlantilla(<?= $plantilla['id'] ?>)"
                                                    title="Ver completa">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editarPlantilla(<?= $plantilla['id'] ?>)"
                                                    title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="copiarPlantilla(<?= $plantilla['id'] ?>)"
                                                    title="Duplicar">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="eliminarPlantilla(<?= $plantilla['id'] ?>)"
                                                    title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Card de Variables Disponibles -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Variables Disponibles</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Variables de Contacto</h5>
                                    <ul>
                                        <li><code>{{nombre}}</code> - Nombre del contacto (base de datos)</li>
                                        <li><code>{{nombreWhatsApp}}</code> - Nombre en WhatsApp del contacto</li>
                                        <li><code>{{whatsapp}}</code> - Alias corto para nombre de WhatsApp</li>
                                        <li><code>{{categoria}}</code> - Categoría del contacto</li>
                                        <li><code>{{precio}}</code> - Precio de la categoría</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5>Variables de Sistema</h5>
                                    <ul>
                                        <li><code>{{fecha}}</code> - Fecha actual (formato: dd/mm/aaaa)</li>
                                        <li><code>{{hora}}</code> - Hora actual (formato: HH:mm)</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="icon fas fa-info"></i>
                                <strong>Nota:</strong> La variable <code>{{nombreWhatsApp}}</code> obtiene el nombre que el contacto tiene configurado en su WhatsApp.
                                Si no se puede obtener, se usará el nombre de tu base de datos automáticamente.
                            </div>
                            <div class="alert alert-warning mt-2">
                                <i class="icon fas fa-exclamation-triangle"></i>
                                <strong>Importante:</strong> Obtener el nombre de WhatsApp puede hacer el envío ligeramente más lento ya que requiere consultar el perfil de cada contacto.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal para Crear/Editar -->
<div class="modal fade" id="modalPlantilla">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Nueva Plantilla</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formPlantilla">
                <div class="modal-body">
                    <input type="hidden" id="plantilla_id" name="id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nombre de la plantilla *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo de uso</label>
                                <select class="form-control" id="uso_general" name="uso_general">
                                    <option value="1">General (todas las categorías)</option>
                                    <option value="0">Específico (una categoría)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="selectorCategoriaPlantilla" style="display: none;">
                        <label>Categoría específica</label>
                        <select class="form-control" id="categoria_id" name="categoria_id">
                            <option value="">-- Seleccionar categoría --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Mensaje *</label>
                        <textarea class="form-control" id="mensaje" name="mensaje" rows="6" required
                            placeholder="Hola {{nombre}}, te recordamos que..."></textarea>
                        <small class="form-text text-muted">
                            Usa las variables disponibles para personalizar el mensaje
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Variables detectadas:</label>
                                <div id="variablesDetectadas">
                                    <span class="text-muted">Ninguna variable detectada</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Vista previa:</label>
                                <div class="border p-2 bg-light" id="vistaPrevia" style="min-height: 60px;">
                                    El mensaje aparecerá aquí...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Plantilla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Plantilla -->
<div class="modal fade" id="modalVerPlantilla">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Vista de Plantilla</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="verPlantillaContent">
                <!-- Se llenará dinámicamente -->
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    $(document).ready(function() {
        // DataTable
        $('#tablaPlantillas').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "order": [
                [6, "desc"]
            ], // Ordenar por veces usado
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
            }
        });

        // Cambio de tipo de uso
        $('#uso_general').on('change', function() {
            if ($(this).val() == '0') {
                $('#selectorCategoriaPlantilla').show();
            } else {
                $('#selectorCategoriaPlantilla').hide();
                $('#categoria_id').val('');
            }
        });

        // Detección de variables en tiempo real
        $('#mensaje').on('input', function() {
            detectarVariables();
            actualizarVistaPrevia();
        });

        // Enviar formulario
        $('#formPlantilla').on('submit', function(e) {
            e.preventDefault();

            const formData = $(this).serialize();
            const id = $('#plantilla_id').val();
            const url = id ?
                API_URL + '/plantillas/editar.php' :
                API_URL + '/plantillas/crear.php';

            $.ajax({
                url: url,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#modalPlantilla').modal('hide');
                        Swal.fire('Éxito', response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al procesar la solicitud', 'error');
                }
            });
        });
    });

    function nuevaPlantilla() {
        $('#modalTitle').text('Nueva Plantilla');
        $('#formPlantilla')[0].reset();
        $('#plantilla_id').val('');
        $('#selectorCategoriaPlantilla').hide();
        $('#variablesDetectadas').html('<span class="text-muted">Ninguna variable detectada</span>');
        $('#vistaPrevia').text('El mensaje aparecerá aquí...');
        $('#modalPlantilla').modal('show');
    }

    function editarPlantilla(id) {
        $('#modalTitle').text('Editar Plantilla');

        $.get(API_URL + '/plantillas/obtener.php', {
            id: id
        }, function(response) {
            if (response.success) {
                const plantilla = response.data;
                $('#plantilla_id').val(plantilla.id);
                $('#nombre').val(plantilla.nombre);
                $('#mensaje').val(plantilla.mensaje);
                $('#uso_general').val(plantilla.uso_general);

                if (plantilla.uso_general == '0' && plantilla.categoria_id) {
                    $('#selectorCategoriaPlantilla').show();
                    $('#categoria_id').val(plantilla.categoria_id);
                }

                detectarVariables();
                actualizarVistaPrevia();
                $('#modalPlantilla').modal('show');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    }

    function verPlantilla(id) {
        $.get(API_URL + '/plantillas/obtener.php', {
            id: id
        }, function(response) {
            if (response.success) {
                const plantilla = response.data;
                const variables = JSON.parse(plantilla.variables || '[]');

                let html = `
                <h5>${plantilla.nombre}</h5>
                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Mensaje completo:</label>
                            <div class="border p-3 bg-light" style="white-space: pre-wrap;">
${plantilla.mensaje}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Información:</label>
                            <ul class="list-unstyled">
                                <li><strong>Tipo:</strong> ${plantilla.uso_general == '1' ? 'General' : 'Específico'}</li>
                                <li><strong>Veces usado:</strong> ${plantilla.veces_usado}</li>
                                <li><strong>Variables:</strong><br>
                                    ${variables.map(v => `<span class="badge badge-secondary">${v}</span>`).join(' ')}
                                </li>
                            </ul>
                        </div>
                        <div class="form-group">
                            <label>Ejemplo con datos reales:</label>
                            <div class="border p-2 bg-white">
                                ${aplicarEjemplo(plantilla.mensaje)}
                            </div>
                        </div>
                    </div>
                </div>
            `;

                $('#verPlantillaContent').html(html);
                $('#modalVerPlantilla').modal('show');
            }
        });
    }

    function copiarPlantilla(id) {
        $.get(API_URL + '/plantillas/obtener.php', {
            id: id
        }, function(response) {
            if (response.success) {
                const plantilla = response.data;
                $('#modalTitle').text('Nueva Plantilla (copiada)');
                $('#plantilla_id').val('');
                $('#nombre').val(plantilla.nombre + ' (copia)');
                $('#mensaje').val(plantilla.mensaje);
                $('#uso_general').val(plantilla.uso_general);

                if (plantilla.uso_general == '0' && plantilla.categoria_id) {
                    $('#selectorCategoriaPlantilla').show();
                    $('#categoria_id').val(plantilla.categoria_id);
                }

                detectarVariables();
                actualizarVistaPrevia();
                $('#modalPlantilla').modal('show');
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
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + '/plantillas/eliminar.php', {
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Eliminada', response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                });
            }
        });
    }

    function detectarVariables() {
        const mensaje = $('#mensaje').val();
        const variables = [];

        // Buscar todas las variables incluyendo las nuevas
        const variablesDisponibles = ['nombre', 'nombreWhatsApp', 'whatsapp', 'categoria', 'precio', 'fecha', 'hora'];

        variablesDisponibles.forEach(variable => {
            const regex = new RegExp(`{{${variable}}}`, 'g');
            if (mensaje.match(regex)) {
                variables.push(variable);
            }
        });

        // Mostrar variables detectadas
        if (variables.length > 0) {
            const badges = variables.map(v => {
                let tipo = 'info';
                if (v === 'nombreWhatsApp' || v === 'whatsapp') tipo = 'success';
                else if (v === 'fecha' || v === 'hora') tipo = 'warning';

                return `<span class="badge badge-${tipo} mr-1">${v}</span>`;
            }).join('');
            $('#variablesDetectadas').html(badges);
        } else {
            $('#variablesDetectadas').html('<span class="text-muted">Ninguna variable detectada</span>');
        }
    }

    function actualizarVistaPrevia() {
        const mensaje = $('#mensaje').val();
        if (mensaje) {
            $('#vistaPrevia').html(aplicarEjemplo(mensaje).replace(/\n/g, '<br>'));
        } else {
            $('#vistaPrevia').text('El mensaje aparecerá aquí...');
        }
    }

    function aplicarEjemplo(mensaje) {
        return mensaje
            .replace(/\{\{nombre\}\}/g, 'Juan Pérez')
            .replace(/\{\{nombreWhatsApp\}\}/g, 'Juan 🚀') // Ejemplo con emoji
            .replace(/\{\{whatsapp\}\}/g, 'Juan 🚀')
            .replace(/\{\{categoria\}\}/g, 'Premium')
            .replace(/\{\{precio\}\}/g, 'S/. 99.00')
            .replace(/\{\{fecha\}\}/g, new Date().toLocaleDateString('es-PE'))
            .replace(/\{\{hora\}\}/g, new Date().toLocaleTimeString('es-PE', {
                hour: '2-digit',
                minute: '2-digit'
            }));
    }
</script>