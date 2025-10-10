<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

$security = new SecurityManager($pdo);

if (estaLogueado()) {
    header('Location: ' . url('cliente/dashboard'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $csrf_token = $_POST['csrf_token'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!$security->validarCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido';
    } elseif (empty($email)) {
        $error = 'Por favor ingresa tu email';
    } else {
        // Rate limiting: máximo 3 intentos por hora
        $rateCheck = $security->verificarRateLimit('recuperar_password', $ip, 3, 60);
        if ($rateCheck['bloqueado']) {
            $error = $rateCheck['mensaje'];
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, nombre_empresa FROM empresas WHERE email = ? AND activo = 1");
                $stmt->execute([$email]);
                $empresa = $stmt->fetch();

                if ($empresa) {
                    // Generar token de recuperación (válido 1 hora)
                    $token = bin2hex(random_bytes(32));
                    $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $stmt = $pdo->prepare("
                        UPDATE empresas 
                        SET password_reset_token = ?, 
                            password_reset_expires = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$token, $expiracion, $empresa['id']]);

                    // Enviar email (en desarrollo solo loguea)
                    $reset_link = url("resetear-password.php?token={$token}");

                    if (ENVIRONMENT === 'development') {
                        error_log("=== EMAIL RECUPERACIÓN PASSWORD ===");
                        error_log("Para: {$email}");
                        error_log("Link: {$reset_link}");
                        error_log("Expira: {$expiracion}");
                        error_log("===================================");
                    } else {
                        // TODO: Enviar email real en producción
                        $email_enviado = enviarEmailRecuperacion($email, $empresa['nombre_empresa'], $reset_link);
                        error_log("Email enviado: " . ($email_enviado ? 'SÍ' : 'NO'));
                    }
                }

                // SIEMPRE mostrar el mismo mensaje (seguridad)
                $success = 'Si el email existe, recibirás instrucciones para recuperar tu contraseña.';
            } catch (Exception $e) {
                error_log("Error en recuperación: " . $e->getMessage());
                $error = 'Error al procesar la solicitud. Intenta nuevamente.';
            }
        }
    }
}

$csrf = $security->generarCSRF();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar Contraseña - <?php echo APP_NAME; ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="manifest" href="<?php echo asset('img/site.webmanifest'); ?>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .recovery-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            padding: 50px 40px;
        }

        .recovery-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .recovery-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .recovery-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .recovery-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .recovery-header p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #25D366;
            box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e0e0e0;
        }

        .back-link a {
            color: #25D366;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="recovery-container">
        <div class="recovery-header">
            <div class="recovery-icon">
                <i class="fas fa-key"></i>
            </div>
            <h2>Recuperar Contraseña</h2>
            <p>Ingresa tu email y te enviaremos instrucciones para restablecer tu contraseña</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="form-group">
                    <label class="form-label">Email registrado</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control"
                            placeholder="tu@empresa.com" required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Enviar Instrucciones
                </button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="<?php echo url('login.php'); ?>">
                <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
            </a>
        </div>
    </div>
</body>

</html>