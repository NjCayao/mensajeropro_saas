<?php
$current_page = 'perfil';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Verificar sesión
if (!isset($_SESSION['empresa_id'])) {
    echo '<div class="content-wrapper">
        <div class="alert alert-danger m-3">
            <h4>Error de Sesión</h4>
            <p>Por favor, <a href="' . url('logout') . '">inicia sesión nuevamente</a>.</p>
        </div>
    </div>';
    require_once __DIR__ . '/../layouts/footer.php';
    exit;
}

// Obtener datos de la empresa (que actúa como usuario)
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$_SESSION['empresa_id']]);
$empresa = $stmt->fetch();

if (!$empresa) {
    echo '<div class="content-wrapper">
        <div class="alert alert-danger m-3">
            <h4>Empresa No Encontrada</h4>
            <p>Por favor, <a href="' . url('logout') . '">inicia sesión nuevamente</a>.</p>
        </div>
    </div>';
    require_once __DIR__ . '/../layouts/footer.php';
    exit;
}
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Mi Perfil</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Mi Perfil</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Información de la Empresa -->
                <div class="col-md-12 mb-3">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Información de la Empresa</h3>
                        </div>
                        <div class="card-body">
                            <form id="formEmpresa">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nombre de la Empresa</label>
                                            <input type="text" name="nombre_empresa" class="form-control" 
                                                value="<?php echo htmlspecialchars($empresa['nombre_empresa']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" class="form-control" 
                                                value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>RUC</label>
                                            <input type="text" name="ruc" class="form-control" 
                                                value="<?php echo htmlspecialchars($empresa['ruc'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Razón Social</label>
                                            <input type="text" name="razon_social" class="form-control" 
                                                value="<?php echo htmlspecialchars($empresa['razon_social'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <textarea name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Actualizar Información
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Cambiar Email -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Cambiar Email</h3>
                        </div>
                        <div class="card-body">
                            <form id="formEmail">
                                <div class="form-group">
                                    <label>Email Actual</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($empresa['email']); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Nuevo Email</label>
                                    <input type="email" name="nuevo_email" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Contraseña Actual</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Actualizar Email</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Cambiar Contraseña -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Cambiar Contraseña</h3>
                        </div>
                        <div class="card-body">
                            <form id="formPassword">
                                <div class="form-group">
                                    <label>Contraseña Actual</label>
                                    <input type="password" name="password_actual" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Nueva Contraseña</label>
                                    <input type="password" name="password_nueva" class="form-control" required minlength="8">
                                    <small class="text-muted">Mínimo 8 caracteres</small>
                                </div>
                                <div class="form-group">
                                    <label>Confirmar Nueva Contraseña</label>
                                    <input type="password" name="password_confirmar" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
$(document).ready(function() {
    // Actualizar información de empresa
    $('#formEmpresa').on('submit', async function(e) {
        e.preventDefault();
        
        try {
            const response = await fetch(API_URL + '/empresas/actualizar-info', {
                method: 'POST',
                body: new FormData(this)
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Éxito', 'Información actualizada correctamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // Cambiar email
    $('#formEmail').on('submit', async function(e) {
        e.preventDefault();
        
        try {
            const response = await fetch(API_URL + '/empresas/cambiar-email', {
                method: 'POST',
                body: new FormData(this)
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Éxito', 'Email actualizado correctamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // Cambiar contraseña
    $('#formPassword').on('submit', async function(e) {
        e.preventDefault();
        
        const nueva = $('[name="password_nueva"]').val();
        const confirmar = $('[name="password_confirmar"]').val();
        
        if (nueva !== confirmar) {
            Swal.fire('Error', 'Las contraseñas no coinciden', 'error');
            return;
        }
        
        try {
            const response = await fetch(API_URL + '/empresas/cambiar-password', {
                method: 'POST',
                body: new FormData(this)
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Éxito', 'Contraseña actualizada correctamente', 'success');
                this.reset();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });
});
</script>