<?php
$current_page = 'contactos';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../../../includes/plan-limits.php';

// Obtener límites del plan actual
$limite_contactos = obtenerLimite('contactos');
$puede_agregar_contactos = !verificarLimiteAlcanzado('contactos');

// Obtener todos los contactos con su categoría
$empresa_id = getEmpresaActual();
$stmt = $pdo->prepare("
    SELECT c.*, cat.nombre as categoria_nombre, cat.color as categoria_color
    FROM contactos c
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    WHERE c.empresa_id = ?
    ORDER BY c.fecha_registro DESC 
");
$stmt->execute([$empresa_id]);
$contactos = $stmt->fetchAll();

// Contar contactos actuales
$total_contactos = count($contactos);

// Obtener categorías para el select
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
                    <h1 class="m-0">Gestión de Contactos</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Contactos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Mostrar límite de contactos si aplica -->
            <?php if ($limite_contactos != PHP_INT_MAX): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="callout callout-<?php echo $puede_agregar_contactos ? 'info' : 'warning'; ?>">
                            <h5><i class="fas fa-info-circle"></i> Límite de contactos</h5>
                            <p class="mb-0">
                                Contactos: <strong><?php echo number_format($total_contactos); ?></strong> de
                                <strong><?php echo number_format($limite_contactos); ?></strong>
                                <?php if (!$puede_agregar_contactos): ?>
                                    <a href="<?php echo url('cliente/mi-plan'); ?>" class="btn btn-sm btn-warning float-right">
                                        <i class="fas fa-arrow-up"></i> Actualizar Plan
                                    </a>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Listado de Contactos</h3>
                            <div class="float-right">
                                <?php if ($puede_agregar_contactos): ?>
                                    <button class="btn btn-success mr-2" onclick="importarCSV()">
                                        <i class="fas fa-file-import"></i> Importar CSV
                                    </button>
                                    <button class="btn btn-primary" onclick="nuevoContacto()">
                                        <i class="fas fa-plus"></i> Nuevo Contacto
                                    </button>
                                <?php else: ?>
                                    <span class="text-danger">
                                        <i class="fas fa-exclamation-circle"></i> Límite alcanzado
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="tablaContactos" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Número</th>
                                        <th>Categoría</th>
                                        <th>Notas</th>
                                        <th>Estado</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contactos as $contacto): ?>
                                        <tr>
                                            <td><?= $contacto['id'] ?></td>
                                            <td><?= htmlspecialchars($contacto['nombre']) ?></td>
                                            <td>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['numero']) ?>"
                                                    target="_blank" class="text-success">
                                                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($contacto['numero']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($contacto['categoria_nombre']): ?>
                                                    <span class="badge" style="background-color: <?= $contacto['categoria_color'] ?>">
                                                        <?= htmlspecialchars($contacto['categoria_nombre']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Sin categoría</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($contacto['notas']): ?>
                                                    <small><?= htmlspecialchars(substr($contacto['notas'], 0, 50)) ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($contacto['activo']): ?>
                                                    <span class="badge badge-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($contacto['fecha_registro'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editarContacto(<?= $contacto['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="eliminarContacto(<?= $contacto['id'] ?>)">
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
            </div>
        </div>
    </section>
</div>

<!-- Modal para Crear/Editar Contacto -->
<div class="modal fade" id="modalContacto">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Nuevo Contacto</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formContacto">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" id="contacto_id" name="id" value="">

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="numero">Número WhatsApp *</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fab fa-whatsapp text-success"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control" id="numero" name="numero"
                                placeholder="+51999999999" required>
                        </div>
                        <small class="form-text text-muted">
                            Formato: +51999999999 (incluir código de país)
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="categoria_id">Categoría</label>
                        <select class="form-control" id="categoria_id" name="categoria_id">
                            <option value="">-- Sin categoría --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notas">Notas</label>
                        <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="activo">Estado</label>
                        <select class="form-control" id="activo" name="activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
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

<!-- Modal para Importar CSV -->
<div class="modal fade" id="modalImportarCSV">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Importar Contactos desde CSV</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formImportarCSV" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <div class="alert alert-info">
                        <h5><i class="icon fas fa-info"></i> Formato del archivo CSV:</h5>
                        <p>El archivo debe contener las siguientes columnas (en este orden):</p>
                        <ol>
                            <li><strong>nombre</strong> - Nombre del contacto</li>
                            <li><strong>numero</strong> - Número de WhatsApp (con código de país)</li>
                            <li><strong>notas</strong> - (Opcional) Notas adicionales</li>
                        </ol>
                        <p class="mb-0">
                            <a href="<?= APP_URL ?>/assets/plantilla_contactos.csv" download>
                                <i class="fas fa-download"></i> Descargar plantilla de ejemplo
                            </a>
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="archivo_csv">Archivo CSV *</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="archivo_csv" name="archivo_csv"
                                accept=".csv" required>
                            <label class="custom-file-label" for="archivo_csv">Elegir archivo...</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="categoria_import">Asignar categoría a todos</label>
                        <select class="form-control" id="categoria_import" name="categoria_id">
                            <option value="">-- Mantener sin categoría --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="actualizar_existentes"
                                name="actualizar_existentes">
                            <label class="custom-control-label" for="actualizar_existentes">
                                Actualizar contactos existentes (basado en el número)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

<script>
// Variables globales
const puedeAgregarContactos = <?php echo $puede_agregar_contactos ? 'true' : 'false'; ?>;
const limiteContactos = <?php echo $limite_contactos == PHP_INT_MAX ? 'null' : $limite_contactos; ?>;
const contactosActuales = <?php echo $total_contactos; ?>;
const mensajeLimite = <?php echo json_encode(mostrarMensajeLimite("contactos")); ?>;
const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

// Funciones globales
function nuevoContacto() {
    if (!puedeAgregarContactos) {
        Swal.fire({
            icon: 'warning',
            title: 'Límite alcanzado',
            html: mensajeLimite,
            showConfirmButton: false,
            showCloseButton: true
        });
        return;
    }
    $("#modalTitle").text("Nuevo Contacto");
    $("#formContacto")[0].reset();
    $("#contacto_id").val("");
    $("#modalContacto").modal("show");
}

function editarContacto(id) {
    $("#modalTitle").text("Editar Contacto");
    $.get(API_URL + "/contactos/obtener.php", { id: id }, function(response) {
        if (response.success) {
            const contacto = response.data;
            $("#contacto_id").val(contacto.id);
            $("#nombre").val(contacto.nombre);
            $("#numero").val(contacto.numero);
            $("#categoria_id").val(contacto.categoria_id || "");
            $("#notas").val(contacto.notas || "");
            $("#activo").val(contacto.activo);
            $("#modalContacto").modal("show");
        } else {
            Swal.fire("Error", response.message, "error");
        }
    });
}

function eliminarContacto(id) {
    Swal.fire({
        title: "¿Eliminar contacto?",
        text: "Esta acción no se puede deshacer",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: API_URL + "/contactos/eliminar.php",
                method: "POST",
                data: { 
                    id: id,
                    csrf_token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire("Eliminado", response.message, "success");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire("Error", response.message, "error");
                    }
                }
            });
        }
    });
}

function importarCSV() {
    if (!puedeAgregarContactos) {
        Swal.fire({
            icon: 'warning',
            title: 'Límite alcanzado',
            html: mensajeLimite,
            showConfirmButton: false,
            showCloseButton: true
        });
        return;
    }
    $("#formImportarCSV")[0].reset();
    $(".custom-file-label").removeClass("selected").html("Elegir archivo...");
    $("#modalImportarCSV").modal("show");
}

// Document ready
$(document).ready(function() {
    $("#tablaContactos").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "order": [[6, "desc"]],
        "pageLength": 25,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
        }
    });

    $(".custom-file-input").on("change", function() {
        var fileName = $(this).val().split("\\").pop();
        $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });

    $("#formContacto").on("submit", function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const esEdicion = $("#contacto_id").val() !== "";
        
        if (!esEdicion && !puedeAgregarContactos) {
            Swal.fire({
                icon: 'warning',
                title: 'Límite alcanzado',
                html: mensajeLimite
            });
            return;
        }

        const url = esEdicion ? API_URL + "/contactos/editar.php" : API_URL + "/contactos/crear.php";

        $.ajax({
            url: url,
            method: "POST",
            data: formData,
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    $("#modalContacto").modal("hide");
                    Swal.fire({
                        icon: "success",
                        title: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    if (response.limite_alcanzado) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Límite alcanzado',
                            html: response.message
                        });
                    } else {
                        Swal.fire("Error", response.message, "error");
                    }
                }
            },
            error: function(xhr) {
                console.error("Error:", xhr.responseText);
                Swal.fire("Error", "Error al procesar la solicitud", "error");
            }
        });
    });

    $("#formImportarCSV").on("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        Swal.fire({
            title: "Importando...",
            text: "Por favor espere mientras se procesan los contactos",
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: API_URL + "/contactos/importar.php",
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $("#modalImportarCSV").modal("hide");
                    Swal.fire({
                        icon: "success",
                        title: "Importación completada",
                        html: response.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    if (response.limite_alcanzado) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Límite alcanzado',
                            html: response.message
                        });
                    } else {
                        Swal.fire("Error", response.message, "error");
                    }
                }
            },
            error: function(xhr) {
                console.error("Error:", xhr.responseText);
                Swal.fire("Error", "Error al procesar el archivo", "error");
            }
        });
    });

    $("#numero").on("input", function() {
        let valor = $(this).val();
        valor = valor.replace(/[^0-9+]/g, "");
        $(this).val(valor);
    });
});
</script>
