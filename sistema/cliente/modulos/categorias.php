<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();
// Obtener todas las categorías
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(co.id) as total_contactos 
    FROM categorias c 
    LEFT JOIN contactos co ON c.id = co.categoria_id AND co.empresa_id = ?
    WHERE c.empresa_id = ?
    GROUP BY c.id 
    ORDER BY c.id ASC
");
$stmt->execute([$empresa_id, $empresa_id]);
$categorias = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Gestión de Categorías</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Categorías</li>
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
                            <h3 class="card-title">Listado de Categorías</h3>
                            <button class="btn btn-primary float-right" onclick="nuevaCategoria()">
                                <i class="fas fa-plus"></i> Nueva Categoría
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="tablaCategorias" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Precio</th>
                                        <th>Color</th>
                                        <th>Contactos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <tr>
                                            <td><?= $categoria['id'] ?></td>
                                            <td>
                                                <span class="badge" style="background-color: <?= $categoria['color'] ?>">
                                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($categoria['descripcion']) ?></td>
                                            <td>S/. <?= number_format($categoria['precio'], 2) ?></td>
                                            <td>
                                                <div style="width: 30px; height: 30px; background-color: <?= $categoria['color'] ?>; border: 1px solid #ccc; border-radius: 4px;"></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= $categoria['total_contactos'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($categoria['activo']): ?>
                                                    <span class="badge badge-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editarCategoria(<?= $categoria['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php
                                                ?>
                                                <button class="btn btn-sm btn-danger" onclick="eliminarCategoria(<?= $categoria['id'] ?>, <?= $categoria['total_contactos'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php // endif; ?>
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
<div class="modal fade" id="modalCategoria">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Nueva Categoría</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCategoria">
                <div class="modal-body">
                    <input type="hidden" id="categoria_id" name="id">

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="50">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="precio">Precio (S/.)</label>
                        <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0">
                    </div>

                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="color" class="form-control" id="color" name="color" value="#17a2b8">
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

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

<script>
    // Funciones
    function nuevaCategoria() {
        $("#modalTitle").text("Nueva Categoría");
        $("#formCategoria")[0].reset();
        $("#categoria_id").val("");
        $("#modalCategoria").modal("show");
    }

    function editarCategoria(id) {
        $("#modalTitle").text("Editar Categoría");

        $.get(API_URL + "/categorias/obtener.php", {
            id: id
        }, function(response) {
            if (response.success) {
                const cat = response.data;
                $("#categoria_id").val(cat.id);
                $("#nombre").val(cat.nombre);
                $("#descripcion").val(cat.descripcion);
                $("#precio").val(cat.precio);
                $("#color").val(cat.color);
                $("#activo").val(cat.activo);
                $("#modalCategoria").modal("show");
            } else {
                Swal.fire("Error", response.message, "error");
            }
        });
    }

    function eliminarCategoria(id, totalContactos) {
        if (totalContactos > 0) {
            Swal.fire({
                icon: "warning",
                title: "No se puede eliminar",
                text: `Esta categoría tiene ${totalContactos} contacto(s) asignado(s). Primero debe reasignar los contactos a otra categoría.`
            });
            return;
        }

        Swal.fire({
            title: "¿Eliminar categoría?",
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
                    url: API_URL + "/categorias/eliminar.php",
                    method: "POST",
                    data: {
                        id: id
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

    // Código que se ejecuta cuando el DOM está listo
    $(document).ready(function() {
        // DataTable para CATEGORÍAS
        $("#tablaCategorias").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "order": [
                [0, "asc"]
            ]
        });

        // Manejar envío del formulario de CATEGORÍA
        $("#formCategoria").on("submit", function(e) {
            e.preventDefault();

            const formData = $(this).serialize();
            const url = $("#categoria_id").val() ?
                API_URL + "/categorias/eliminar.php" :
                API_URL + "/categorias/crear.php";

            $.ajax({
                url: url,
                method: "POST",
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $("#modalCategoria").modal("hide");
                        Swal.fire({
                            icon: "success",
                            title: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire("Error", response.message, "error");
                    }
                },
                error: function() {
                    Swal.fire("Error", "Error de conexión", "error");
                }
            });
        });
    });
</script>