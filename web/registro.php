<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['empresa_id'])) {
    header('Location: ' . url('app.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $aceptar_terminos = isset($_POST['aceptar_terminos']);

    // Validaciones
    if (empty($nombre_empresa) || empty($email) || empty($password)) {
        $error = 'Todos los campos obligatorios deben completarse';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!$aceptar_terminos) {
        $error = 'Debes aceptar los Términos y Condiciones';
    } else {
        try {
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id FROM empresas WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Este email ya está registrado';
            } else {
                try {
                    // Verificar si el email ya existe
                    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE email = ?");
                    $stmt->execute([$email]);

                    if ($stmt->fetch()) {
                        $error = 'Este email ya está registrado';
                    } else {
                        // Iniciar transacción
                        $pdo->beginTransaction();

                        // Hash de contraseña
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);

                        // PASO 1: Insertar empresa 
                        $stmt = $pdo->prepare("
                            INSERT INTO empresas 
                            (nombre_empresa, email, password_hash, telefono, metodo_registro, 
                            plan_id, fecha_registro, activo) 
                            VALUES (?, ?, ?, ?, 'email', ?, NOW(), 1)
                        ");

                        $stmt->execute([
                            $nombre_empresa,
                            $email,
                            $password_hash,
                            $telefono,
                            DEFAULT_PLAN_ID
                        ]);

                        $empresa_id = $pdo->lastInsertId();

                        // PASO 2: Crear suscripción TRIAL automáticamente
                        $stmt = $pdo->prepare("
                            INSERT INTO suscripciones 
                            (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado) 
                            VALUES (?, ?, 'trial', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'activa')
                        ");
                        $stmt->execute([$empresa_id, DEFAULT_PLAN_ID, TRIAL_DAYS]);

                        // PASO 3: Crear categoría por defecto
                        $stmt = $pdo->prepare("
                            INSERT INTO categorias (nombre, descripcion, color, activo, empresa_id) 
                            VALUES ('General', 'Categoría por defecto', '#17a2b8', 1, ?)
                        ");
                        $stmt->execute([$empresa_id]);

                        // PASO 4: Crear sesión de WhatsApp
                        $stmt = $pdo->prepare("
                            INSERT INTO whatsapp_sesiones_empresa (empresa_id, estado) 
                            VALUES (?, 'desconectado')
                        ");
                        $stmt->execute([$empresa_id]);

                        // PASO 5: Crear configuración del bot
                        $stmt = $pdo->prepare("
                            INSERT INTO configuracion_bot (empresa_id, activo) 
                            VALUES (?, 0)
                        ");
                        $stmt->execute([$empresa_id]);

                        // PASO 6: Crear configuración del negocio
                        $stmt = $pdo->prepare("
                            INSERT INTO configuracion_negocio (empresa_id, nombre_negocio) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$empresa_id, $nombre_empresa]);

                        // PASO 7: Generar código de verificación
                        $codigo_verificacion = bin2hex(random_bytes(16));

                        $stmt = $pdo->prepare("
                            UPDATE empresas 
                            SET token_verificacion = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$codigo_verificacion, $empresa_id]);
                        $stmt->execute([$codigo_verificacion, $empresa_id]);

                        // Commit de la transacción
                        $pdo->commit();

                        // TODO: Enviar email de verificación
                        // enviarEmailVerificacion($email, $codigo_verificacion);

                        // Redirigir a página de verificación
                        $_SESSION['registro_exitoso'] = true;
                        $_SESSION['email_verificar'] = $email;
                        header('Location: ' . url('verificar-email.php'));
                        exit;
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Error en registro: " . $e->getMessage());
                    $error = 'Error en el registro: ' . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en registro: " . $e->getMessage());
            $error = 'Error en el registro: ' . $e->getMessage(); // Mostrar error específico
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('dist/css/adminlte.min.css'); ?>">
    <style>
        body {
            background: linear-gradient(135deg, #075e54 0%, #128c7e 100%);
        }

        .register-box {
            width: 450px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background-color: #25d366;
            border-color: #25d366;
        }

        .btn-primary:hover {
            background-color: #128c7e;
            border-color: #128c7e;
        }
    </style>
</head>

<body class="hold-transition register-page">
    <div class="register-box">
        <div class="card">
            <div class="card-header text-center">
                <h1 class="h3 mb-0 font-weight-bold"><?php echo APP_NAME; ?></h1>
                <p class="text-muted">Crea tu cuenta empresarial</p>
            </div>
            <div class="card-body register-card-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="nombre_empresa"
                            placeholder="Nombre de la empresa"
                            value="<?php echo htmlspecialchars($nombre_empresa ?? ''); ?>"
                            required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-building"></span></div>
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <input type="email" class="form-control" name="email"
                            placeholder="Email empresarial"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <input type="tel" class="form-control" name="telefono"
                            placeholder="Teléfono (opcional)"
                            value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-phone"></span></div>
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <input type="password" class="form-control" name="password"
                            placeholder="Contraseña (mínimo 8 caracteres)" required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>

                    <div class="input-group mb-4">
                        <input type="password" class="form-control" name="password_confirm"
                            placeholder="Confirmar contraseña" required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>

                    <!-- NUEVO: Checkbox de términos -->
                    <div class="form-group mb-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="aceptar_terminos"
                                name="aceptar_terminos" required>
                            <label class="custom-control-label" for="aceptar_terminos">
                                Acepto los
                                <a href="<?php echo url('terminos.php'); ?>" target="_blank">Términos y Condiciones</a>
                                y la
                                <a href="<?php echo url('privacidad.php'); ?>" target="_blank">Política de Privacidad</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Crear Cuenta
                    </button>
                </form>

                <?php
                // Verificar si Google OAuth está activo
                $google_activo = false;
                try {
                    $stmt = $pdo->query("SELECT valor FROM configuracion_plataforma WHERE clave = 'google_oauth_activo'");
                    $config = $stmt->fetch();
                    $google_activo = ($config && $config['valor'] == '1');
                } catch (Exception $e) {
                    error_log("Error verificando Google OAuth: " . $e->getMessage());
                }
                ?>

                <?php if ($google_activo): ?>
                    <div class="social-auth-links text-center mb-3 mt-3">
                        <p>- O -</p>
                        <a href="<?php echo url('api/v1/auth/google-oauth.php'); ?>" class="btn btn-block btn-danger">
                            <i class="fab fa-google mr-2"></i> Registrarse con Google
                        </a>
                    </div>
                <?php endif; ?>

                <hr class="my-4">

                <p class="text-center mb-0">
                    ¿Ya tienes una cuenta?
                    <a href="<?php echo url('login.php'); ?>">Inicia sesión aquí</a>
                </p>
            </div>
        </div>
    </div>

    <script src="<?php echo asset('plugins/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo asset('dist/js/adminlte.min.js'); ?>"></script>
</body>

</html>