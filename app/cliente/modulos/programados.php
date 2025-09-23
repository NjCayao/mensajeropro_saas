<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Obtener mensajes programados
$stmt = $pdo->prepare("
    SELECT mp.*, u.nombre as usuario_nombre, c.nombre as categoria_nombre,
           (SELECT COUNT(*) FROM contactos WHERE categoria_id = mp.categoria_id AND activo = 1 AND empresa_id = ?) as total_contactos_actual
    FROM mensajes_programados mp
    LEFT JOIN usuarios u ON mp.usuario_id = u.id
    LEFT JOIN categorias c ON mp.categoria_id = c.id
    WHERE mp.empresa_id = ?
    ORDER BY 
        CASE 
            WHEN mp.estado = 'pendiente' THEN 1
            WHEN mp.estado = 'procesando' THEN 2
            ELSE 3
        END,
        mp.fecha_programada ASC
");
$stmt->execute([$empresa_id, $empresa_id]);
$programados = $stmt->fetchAll();

// Obtener categorías activas
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE activo = 1 AND empresa_id = ? ORDER BY nombre");
$stmt->execute([$empresa_id]);
$categorias = $stmt->fetchAll();

// Obtener plantillas
$stmt = $pdo->prepare("SELECT * FROM plantillas_mensajes WHERE empresa_id = ? ORDER BY nombre");
$stmt->execute([$empresa_id]);
$plantillas = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Mensajes Programados</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Mensajes Programados</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Resumen de estados -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <?php
                            $pendientes = count(array_filter($programados, function ($p) {
                                return $p['estado'] == 'pendiente';
                            }));
                            ?>
                            <h3><?= $pendientes ?></h3>
                            <p>Pendientes</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <?php
                            $procesando = count(array_filter($programados, function ($p) {
                                return $p['estado'] == 'procesando';
                            }));
                            ?>
                            <h3><?= $procesando ?></h3>
                            <p>Procesando</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <?php
                            $completados = count(array_filter($programados, function ($p) {
                                return $p['estado'] == 'completado';
                            }));
                            ?>
                            <h3><?= $completados ?></h3>
                            <p>Completados</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <?php
                            $cancelados = count(array_filter($programados, function ($p) {
                                return $p['estado'] == 'cancelado';
                            }));
                            ?>
                            <h3><?= $cancelados ?></h3>
                            <p>Cancelados</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de mensajes programados -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Listado de Mensajes Programados</h3>
                            <button class="btn btn-primary float-right" onclick="nuevoProgramado()">
                                <i class="fas fa-plus"></i> Nuevo Mensaje Programado
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="tablaProgramados" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Destinatarios</th>
                                        <th>Fecha Programada</th>
                                        <th>Estado</th>
                                        <th>Progreso</th>
                                        <th>Creado por</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($programados as $prog): ?>
                                        <tr>
                                            <td><?= $prog['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($prog['titulo']) ?></strong>
                                                <?php if ($prog['imagen_path']): ?>
                                                    <i class="fas fa-paperclip text-muted ml-1" title="Contiene archivo adjunto"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($prog['enviar_a_todos']): ?>
                                                    <span class="badge badge-primary">TODOS</span>
                                                    <small>(<?= $prog['total_destinatarios'] ?>)</small>
                                                <?php elseif ($prog['categoria_nombre']): ?>
                                                    <span class="badge badge-info"><?= htmlspecialchars($prog['categoria_nombre']) ?></span>
                                                    <small>(<?= $prog['total_destinatarios'] ?>)</small>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Sin categoría</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-sort="<?= strtotime($prog['fecha_programada']) ?>">
                                                <?= date('d/m/Y H:i', strtotime($prog['fecha_programada'])) ?>
                                                <?php
                                                $ahora = time();
                                                $programado = strtotime($prog['fecha_programada']);
                                                $diff = $programado - $ahora;

                                                if ($prog['estado'] == 'pendiente' && $diff > 0):
                                                    if ($diff < 3600): // menos de 1 hora
                                                ?>
                                                        <br><small class="text-warning">En <?= ceil($diff / 60) ?> minutos</small>
                                                    <?php elseif ($diff < 86400): // menos de 1 día 
                                                    ?>
                                                        <br><small class="text-info">En <?= ceil($diff / 3600) ?> horas</small>
                                                    <?php endif; ?>
                                                <?php elseif ($prog['estado'] == 'pendiente' && $diff <= 0): ?>
                                                    <br><small class="text-danger">Atrasado</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = [
                                                    'pendiente' => 'badge-info',
                                                    'procesando' => 'badge-warning',
                                                    'completado' => 'badge-success',
                                                    'cancelado' => 'badge-danger'
                                                ][$prog['estado']] ?? 'badge-secondary';
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($prog['estado']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($prog['estado'] == 'procesando' || $prog['estado'] == 'completado'): ?>
                                                    <div class="progress progress-sm">
                                                        <?php
                                                        $porcentaje = $prog['total_destinatarios'] > 0
                                                            ? round(($prog['mensajes_enviados'] / $prog['total_destinatarios']) * 100)
                                                            : 0;
                                                        ?>
                                                        <div class="progress-bar bg-success" style="width: <?= $porcentaje ?>%"></div>
                                                    </div>
                                                    <small><?= $prog['mensajes_enviados'] ?>/<?= $prog['total_destinatarios'] ?> (<?= $porcentaje ?>%)</small>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($prog['usuario_nombre']) ?></td>
                                            <td>
                                                <?php if ($prog['estado'] == 'pendiente'): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="editarProgramado(<?= $prog['id'] ?>)"
                                                        title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="cancelarProgramado(<?= $prog['id'] ?>)"
                                                        title="Cancelar">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-info" onclick="verDetalles(<?= $prog['id'] ?>)"
                                                    title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
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

<!-- Modal para Crear/Editar -->
<div class="modal fade" id="modalProgramado">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Nuevo Mensaje Programado</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formProgramado">
                <div class="modal-body">
                    <input type="hidden" id="programado_id" name="id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Título *</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fecha y hora de envío *</label>
                                <input type="datetime-local" class="form-control" id="fecha_programada" name="fecha_programada" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Enviar a:</label>
                        <select class="form-control" id="tipo_destinatarios" name="tipo_destinatarios" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="categoria">Por categoría</option>
                            <option value="todos">TODOS los contactos</option>
                        </select>
                    </div>

                    <div class="form-group" id="selectorCategoria" style="display: none;">
                        <label>Categoría:</label>
                        <select class="form-control" id="categoria_id" name="categoria_id">
                            <option value="">-- Seleccionar categoría --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <?php
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id = ? AND activo = 1 AND empresa_id = ?");
                                $stmt->execute([$cat['id'], $empresa_id]);
                                $total = $stmt->fetchColumn();
                                ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['nombre']) ?> (<?= $total ?> contactos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Usar plantilla (opcional):</label>
                        <select class="form-control" id="plantilla_id">
                            <option value="">-- Sin plantilla --</option>
                            <?php foreach ($plantillas as $plantilla): ?>
                                <option value="<?= $plantilla['id'] ?>" data-mensaje="<?= htmlspecialchars($plantilla['mensaje']) ?>">
                                    <?= htmlspecialchars($plantilla['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Mensaje *</label>
                        <textarea class="form-control" id="mensaje" name="mensaje" rows="5" required></textarea>
                        <small class="form-text text-muted">
                            Variables disponibles: {{nombre}}, {{categoria}}, {{fecha}}, {{hora}}
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Adjuntar imagen (opcional):</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="imagen" name="imagen" accept=".jpg,.jpeg,.png">
                            <label class="custom-file-label" for="imagen">Elegir archivo...</label>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="icon fas fa-info"></i>
                        El mensaje se enviará automáticamente en la fecha y hora especificada.
                        <strong>Total de destinatarios: <span id="totalDestinatarios">0</span></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Programar Envío</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Detalles -->
<div class="modal fade" id="modalDetalles">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Detalles del Mensaje Programado</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="detallesContent">
                <!-- Se llenará dinámicamente -->
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    $(document).ready(function() {
        // DataTable
        $('#tablaProgramados').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "order": [
                [3, "asc"]
            ],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
            }
        });

        // Establecer fecha mínima (ahora + 5 minutos)
        const ahora = new Date();
        ahora.setMinutes(ahora.getMinutes() + 5);
        const minDateTime = ahora.toISOString().slice(0, 16);
        $('#fecha_programada').attr('min', minDateTime);

        // Cambio de tipo de destinatarios
        $('#tipo_destinatarios').on('change', function() {
            const tipo = $(this).val();
            $('#selectorCategoria').hide();

            if (tipo === 'categoria') {
                $('#selectorCategoria').show();
                $('#categoria_id').prop('required', true);
            } else {
                $('#categoria_id').prop('required', false);
            }

            updateTotalDestinatarios();
        });

        // Cambio de categoría
        $('#categoria_id').on('change', updateTotalDestinatarios);

        // Usar plantilla
        $('#plantilla_id').on('change', function() {
            const mensaje = $(this).find(':selected').data('mensaje');
            if (mensaje) {
                $('#mensaje').val(mensaje);
            }
        });

        // Archivo seleccionado
        $('#imagen').on('change', function() {
            const fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').text(fileName || 'Elegir archivo...');
        });

        // Enviar formulario
        $('#formProgramado').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const id = $('#programado_id').val();
            const url = id ?
                API_URL + '/programados/editar.php' :
                API_URL + '/programados/crear.php';

            $.ajax({
                url: url,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#modalProgramado').modal('hide');
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

    function nuevoProgramado() {
        $('#modalTitle').text('Nuevo Mensaje Programado');
        $('#formProgramado')[0].reset();
        $('#programado_id').val('');
        $('.custom-file-label').text('Elegir archivo...');
        $('#selectorCategoria').hide();
        $('#totalDestinatarios').text('0');

        // Establecer fecha por defecto (mañana a las 9:00)
        const manana = new Date();
        manana.setDate(manana.getDate() + 1);
        manana.setHours(9, 0, 0, 0);
        $('#fecha_programada').val(manana.toISOString().slice(0, 16));

        $('#modalProgramado').modal('show');
    }

    function editarProgramado(id) {
        $('#modalTitle').text('Editar Mensaje Programado');

        $.get(API_URL + '/programados/obtener.php', { id: id }, function(response) {
            id: id
        }, function(response) {
            if (response.success) {
                const data = response.data;
                $('#programado_id').val(data.id);
                $('#titulo').val(data.titulo);
                $('#mensaje').val(data.mensaje);
                $('#fecha_programada').val(data.fecha_programada.slice(0, 16));

                if (data.enviar_a_todos) {
                    $('#tipo_destinatarios').val('todos');
                    $('#selectorCategoria').hide();
                } else if (data.categoria_id) {
                    $('#tipo_destinatarios').val('categoria');
                    $('#selectorCategoria').show();
                    $('#categoria_id').val(data.categoria_id);
                }

                updateTotalDestinatarios();
                $('#modalProgramado').modal('show');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    }

    function cancelarProgramado(id) {
        Swal.fire({
            title: '¿Cancelar mensaje programado?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + '/programados/cancelar.php', { id: id }, function(response) {
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Cancelado', response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                });
            }
        });
    }

    function verDetalles(id) {
        $.get(API_URL + '/programados/detalles.php', { id: id }, function(response) {
            id: id
        }, function(response) {
            if (response.success) {
                const data = response.data;
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Título:</strong> ${data.titulo}</p>
                        <p><strong>Estado:</strong> <span class="badge badge-${data.estado_class}">${data.estado}</span></p>
                        <p><strong>Creado por:</strong> ${data.usuario_nombre}</p>
                        <p><strong>Fecha creación:</strong> ${data.fecha_creacion}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Fecha programada:</strong> ${data.fecha_programada}</p>
                        <p><strong>Destinatarios:</strong> ${data.destinatarios_texto}</p>
                        <p><strong>Total:</strong> ${data.total_destinatarios}</p>
                        <p><strong>Enviados:</strong> ${data.mensajes_enviados}</p>
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <label>Mensaje:</label>
                    <div class="border p-3 bg-light">
                        ${data.mensaje.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;

                if (data.imagen_path) {
                    // Usar ruta relativa al APP_URL
                    const imageUrl = APP_URL + '/uploads/mensajes/' + data.imagen_path;
                    html += `
                    <div class="form-group">
                        <label>Imagen adjunta:</label>
                        <div class="text-center">
                            <img src="${imageUrl}" 
                                 class="img-thumbnail" style="max-width: 300px;">
                        </div>
                    </div>
                `;
                }

                $('#detallesContent').html(html);
                $('#modalDetalles').modal('show');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    }

    function updateTotalDestinatarios() {
        const tipo = $('#tipo_destinatarios').val();

        if (tipo === 'todos') {
           $.get(API_URL + '/contactos/count.php', function(response) {
                if (response.success) {
                    $('#totalDestinatarios').text(response.data.total);
                }
            });
        } else if (tipo === 'categoria') {
            const selected = $('#categoria_id option:selected');
            if (selected.val()) {
                const match = selected.text().match(/\((\d+)/);
                if (match && match[1]) {
                    $('#totalDestinatarios').text(match[1]);
                }
            } else {
                $('#totalDestinatarios').text('0');
            }
        } else {
            $('#totalDestinatarios').text('0');
        }
    }
</script>