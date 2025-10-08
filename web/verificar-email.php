<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar que venga del registro
if (!isset($_SESSION['email_verificar'])) {
    header('Location: ' . url('login.php'));
    exit;
}

$email = $_SESSION['email_verificar'];
$error = '';
$success = '';

// Procesar código de verificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    
    if (empty($codigo)) {
        $error = 'Por favor ingresa el código de verificación';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM empresas 
                WHERE email = ? AND token_verificacion = ? AND email_verificado = 0
            ");
            $stmt->execute([$email, $codigo]);
            $empresa = $stmt->fetch();
            
            if (!$empresa) {
                $error = 'Código de verificación inválido';
            } else {
                // Activar cuenta
                $stmt = $pdo->prepare("
                    UPDATE empresas 
                    SET email_verificado = 1, activo = 1, token_verificacion = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$empresa['id']]);
                
                // Crear sesión automáticamente
                crearSesion($empresa);
                
                // Limpiar sesión temporal
                unset($_SESSION['email_registro']);
                unset($_SESSION['empresa_id_temporal']);
                
                // Redirigir al dashboard
                header('Location: ' . url('cliente/dashboard'));
                exit;
            }
        } catch (Exception $e) {
            error_log("Error en verificación: " . $e->getMessage());
            $error = 'Error al verificar el código. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Email - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('dist/css/adminlte.min.css'); ?>">
    <style>
        body { background: linear-gradient(135deg, #075e54 0%, #128c7e 100%); }
        .verification-box { width: 450px; }
        .card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .btn-primary { background-color: #25d366; border-color: #25d366; }
        .btn-primary:hover { background-color: #128c7e; border-color: #128c7e; }
        .code-input { font-size: 2rem; text-align: center; letter-spacing: 1rem; }
    </style>
</head>
<body class="hold-transition login-page">
<div class="verification-box">
    <div class="card">
        <div class="card-header text-center">
            <h1 class="h3 mb-0 font-weight-bold"><?php echo APP_NAME; ?></h1>
            <p class="text-muted">Verifica tu email</p>
        </div>
        <div class="card-body">
            
            <div class="alert alert-info">
                <i class="fas fa-envelope"></i> 
                Hemos enviado un código de 6 dígitos a <strong><?php echo htmlspecialchars($email); ?></strong>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>Código de verificación</label>
                    <input type="text" 
                           name="codigo" 
                           class="form-control code-input" 
                           placeholder="000000" 
                           maxlength="6"
                           pattern="[0-9]{6}"
                           required
                           autofocus>
                    <small class="form-text text-muted">Ingresa el código de 6 dígitos</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-check"></i> Verificar
                </button>
            </form>

            <hr class="my-4">
            
            <p class="text-center mb-0">
                ¿No recibiste el código? 
                <a href="#" class="text-primary" id="resendCode">Reenviar código</a>
            </p>
        </div>
    </div>
</div>

<script src="<?php echo asset('plugins/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo asset('dist/js/adminlte.min.js'); ?>"></script>
<script>
$('#resendCode').click(function(e) {
    e.preventDefault();
    alert('Funcionalidad de reenvío en desarrollo');
    // TODO: Implementar reenvío de código
});

// Auto-format código
$('.code-input').on('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>
</body>
</html>