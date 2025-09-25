<?php
$current_page = 'categorias';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Obtener categorías
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM contactos WHERE categoria_id = c.id AND empresa_id = ?) as total_contactos
    FROM categorias c 
    WHERE c.empresa_id = ?
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
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
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
                                        <th>Color</th>
                                        <th>Precio</th>
                                        <th>Contactos</th>
                                        <th>Estado</th>
                                        <th>Descripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $cat): ?>
                                        <tr>
                                            <td><?= $cat['id'] ?></td>
                                            <td>
                                                <span class="badge" style="background-color: <?= $cat['color'] ?>">
                                                    <?= htmlspecialchars($cat['nombre']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="width: 30px; height: 30px; background-color: <?= $cat['color'] ?>; 
                                                            border: 1px solid #ccc; border-radius: 4px;">
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($cat['precio'] > 0): ?>
                                                    S/. <?= number_format($cat['precio'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= $cat['total_contactos'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($cat['activo']): ?>
                                                    <span class="badge badge-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cat['descripcion']): ?>
                                                    <small><?= htmlspecialchars(substr($cat['descripcion'], 0, 50)) ?>...</small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editarCategoria(<?= $cat['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="eliminarCategoria(<?= $cat['id'] ?>)">
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

<!-- Modal para Crear/Editar -->
<div class="modal fade" id="modalCategoria">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Nueva Categoría</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formCategoria">
                <div class="modal-body">
                    <input type="hidden" id="categoria_id" name="id">
                    
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" class="form-control" id="color" name="color" value="#17a2b8">
                    </div>
                    
                    <div class="form-group">
                        <label>Precio (Opcional)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">S/.</span>
                            </div>
                            <input type="number" class="form-control" id="precio" name="precio" 
                                   step="0.01" min="0" value="0">
                        </div>
                        <small class="text-muted">
                            Útil si usas la variable {{precio}} en tus plantillas
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Estado</label>
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

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
$(document).ready(function() {
    // DataTable
    $('#tablaCategorias').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "order": [[0, "asc"]]
    });
    
    // Form submit
    $('#formCategoria').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const id = $('#categoria_id').val();
        const url = id ? 
            API_URL + '/categorias/editar.php' : 
            API_URL + '/categorias/crear.php';
        
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#modalCategoria').modal('hide');
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

function nuevaCategoria() {
    $('#modalTitle').text('Nueva Categoría');
    $('#formCategoria')[0].reset();
    $('#categoria_id').val('');
    $('#color').val('#17a2b8');
    $('#modalCategoria').modal('show');
}

function editarCategoria(id) {
    $('#modalTitle').text('Editar Categoría');
    
    $.get(API_URL + '/categorias/obtener.php', { id: id }, function(response) {
        if (response.success) {
            const cat = response.data;
            $('#categoria_id').val(cat.id);
            $('#nombre').val(cat.nombre);
            $('#descripcion').val(cat.descripcion);
            $('#color').val(cat.color);
            $('#precio').val(cat.precio);
            $('#activo').val(cat.activo);
            $('#modalCategoria').modal('show');
        } else {
            Swal.fire('Error', response.message, 'error');
        }
    });
}

function eliminarCategoria(id) {
    Swal.fire({
        title: '¿Eliminar categoría?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(API_URL + '/categorias/eliminar.php', { id: id }, function(response) {
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
</script>