<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: ' . url('app.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $nombre_admin = trim($_POST['nombre_admin'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    
    // Validaciones
    if (empty($nombre_empresa) || empty($nombre_admin) || empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'El email ya está registrado';
            } else {
                // Iniciar transacción
                $pdo->beginTransaction();
                
                // 1. Crear empresa
                $stmt = $pdo->prepare("
                    INSERT INTO empresas (nombre_empresa, telefono, fecha_registro, activo, plan) 
                    VALUES (?, ?, NOW(), 1, 'basico')
                ");
                $stmt->execute([$nombre_empresa, $telefono]);
                $empresa_id = $pdo->lastInsertId();
                
                // 2. Crear usuario administrador
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nombre, email, password, rol, empresa_id, activo) 
                    VALUES (?, ?, ?, 'admin', ?, 1)
                ");
                $stmt->execute([$nombre_admin, $email, $password_hash, $empresa_id]);
                
                // 3. Crear categoría General
                $stmt = $pdo->prepare("
                    INSERT INTO categorias (nombre, descripcion, color, activo, empresa_id) 
                    VALUES ('General', 'Categoría por defecto', '#17a2b8', 1, ?)
                ");
                $stmt->execute([$empresa_id]);
                
                // 4. Crear registro en whatsapp_sesiones_empresa
                $stmt = $pdo->prepare("
                    INSERT INTO whatsapp_sesiones_empresa (empresa_id, estado) 
                    VALUES (?, 'desconectado')
                ");
                $stmt->execute([$empresa_id]);
                
                // 5. Crear configuración del bot
                $stmt = $pdo->prepare("
                    INSERT INTO configuracion_bot 
                    (empresa_id, activo, delay_respuesta, responder_no_registrados, 
                     temperatura, max_tokens, modelo_ai) 
                    VALUES (?, 0, 5, 0, 0.7, 150, 'gpt-3.5-turbo')
                ");
                $stmt->execute([$empresa_id]);
                
                // Confirmar transacción
                $pdo->commit();
                
                $success = '¡Registro exitoso! Ya puedes iniciar sesión.';
                
                // Limpiar formulario
                $nombre_empresa = $nombre_admin = $email = $telefono = '';
                
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en registro: " . $e->getMessage());
            $error = 'Error al registrar. Por favor intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - MensajeroPro</title>
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            background: transparent;
            border-bottom: 0;
            text-align: center;
            padding-bottom: 0;
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
        <div class="card-header">
            <h1 class="h3 mb-0 font-weight-bold">MensajeroPro</h1>
            <p class="text-muted">Crea tu cuenta empresarial</p>
        </div>
        <div class="card-body register-card-body">
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <h5 class="text-muted mb-3">Datos de la Empresa</h5>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="nombre_empresa" 
                           placeholder="Nombre de la empresa" 
                           value="<?php echo htmlspecialchars($nombre_empresa ?? ''); ?>" 
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-building"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="tel" class="form-control" name="telefono" 
                           placeholder="Teléfono (opcional)" 
                           value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-phone"></span>
                        </div>
                    </div>
                </div>
                
                <h5 class="text-muted mb-3 mt-4">Datos del Administrador</h5>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="nombre_admin" 
                           placeholder="Tu nombre completo" 
                           value="<?php echo htmlspecialchars($nombre_admin ?? ''); ?>" 
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="email" class="form-control" name="email" 
                           placeholder="Email" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="password" class="form-control" name="password" 
                           placeholder="Contraseña (mínimo 8 caracteres)" 
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group mb-4">
                    <input type="password" class="form-control" name="password_confirm" 
                           placeholder="Confirmar contraseña" 
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-user-plus"></i> Crear Cuenta
                        </button>
                    </div>
                </div>
            </form>

            <hr class="my-4">
            
            <p class="text-center mb-0">
                ¿Ya tienes una cuenta? 
                <a href="<?php echo url('login.php'); ?>" class="text-primary font-weight-bold">
                    Inicia sesión aquí
                </a>
            </p>
        </div>
    </div>
    
    <div class="text-center mt-3">
        <small class="text-white">
            &copy; 2024 MensajeroPro. Todos los derechos reservados.
        </small>
    </div>
</div>

<script src="<?php echo asset('plugins/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo asset('dist/js/adminlte.min.js'); ?>"></script>
</body>
</html>