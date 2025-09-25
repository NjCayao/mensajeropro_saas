<?php
$current_page = 'perfil';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener datos del usuario actual
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

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
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
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
                                    <input type="email" class="form-control" value="<?php echo $usuario['email']; ?>" readonly>
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
    // Cambiar email
    $('#formEmail').on('submit', async function(e) {
        e.preventDefault();
        
        try {
            const response = await fetch(API_URL + '/usuarios/cambiar-email.php', {
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
            const response = await fetch(API_URL + '/usuarios/cambiar-password.php', {
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