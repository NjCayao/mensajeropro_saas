<?php
// public/login.php - Login unificado
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Procesar logout
if (isset($_POST['logout'])) {
    cerrarSesion();
    header('Location: ' . url('login.php'));
    exit;
}
// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    header('Location: ' . url('cliente/dashboard'));
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $resultado = verificarLogin($email, $password);

        if ($resultado['success']) {
            crearSesion($resultado['usuario']);
            header('Location: ' . url('cliente/dashboard'));
            exit;
        } else {
            $error = $resultado['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo APP_NAME; ?> - Iniciar Sesión</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="<?php echo asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css'); ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?php echo asset('dist/css/adminlte.min.css'); ?>">

    <style>
        .login-page {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        }

        .login-logo a {
            color: #fff !important;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: #25D366;
            border-color: #25D366;
        }

        .btn-primary:hover {
            background-color: #128C7E;
            border-color: #128C7E;
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <a href="<?php echo url(); ?>">
                <i class="fab fa-whatsapp"></i> <b><?php echo APP_NAME; ?></b>
            </a>
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Inicia sesión para comenzar</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generarCSRFToken(); ?>">

                    <div class="input-group mb-3">
                        <input type="email"
                            name="email"
                            class="form-control"
                            placeholder="Email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <input type="password"
                            name="password"
                            class="form-control"
                            placeholder="Contraseña"
                            required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">
                                    Recordarme
                                </label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">
                                Ingresar
                            </button>
                        </div>
                    </div>
                </form>

                <div class="social-auth-links text-center mb-3">
                    <p>- O -</p>
                    <a href="#" class="btn btn-block btn-danger">
                        <i class="fab fa-google mr-2"></i> Ingresar con Google
                    </a>
                </div>

                <p class="mb-1">
                    <a href="recuperar-password.php">Olvidé mi contraseña</a>
                </p>
                 <div class="text-center mt-3">
                    <p class="mb-0">
                        ¿No tienes una cuenta?
                        <a href="<?php echo url('registro.php'); ?>" class="text-primary">
                            Regístrate aquí
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="<?php echo asset('plugins/jquery/jquery.min.js'); ?>"></script>
    <!-- Bootstrap 4 -->
    <script src="<?php echo asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
    <!-- AdminLTE App -->
    <script src="<?php echo asset('dist/js/adminlte.min.js'); ?>"></script>
</body>

</html>