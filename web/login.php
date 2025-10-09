<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

$security = new SecurityManager($pdo);

if (estaLogueado()) {
    header('Location: ' . url('cliente/dashboard'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$security->validarCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido';
    } elseif (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $check = $security->verificarIntentosLogin($email, $ip);
        
        if ($check['bloqueado']) {
            $error = $check['mensaje'];
        } else {
            $resultado = verificarLogin($email, $password);
            
            if ($resultado['success']) {
                $security->registrarIntentoLogin($email, $ip, true);
                crearSesion($resultado['usuario']);
                
                if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'superadmin') {
                    header('Location: ' . url('superadmin/dashboard'));
                } else {
                    header('Location: ' . url('cliente/dashboard'));
                }
                exit;
            } else {
                $security->registrarIntentoLogin($email, $ip, false);
                $error = 'Email o contraseña incorrectos';
            }
        }
    }
}

$csrf = $security->generarCSRF();

$google_activo = false;
try {
    $stmt = $pdo->query("SELECT valor FROM configuracion_plataforma WHERE clave = 'google_oauth_activo'");
    $config = $stmt->fetch();
    $google_activo = ($config && $config['valor'] == '1');
} catch (Exception $e) {
    error_log("Error verificando Google OAuth: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión - <?php echo APP_NAME; ?></title>

    <link rel="stylesheet" href="assets/css/login.css">

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="manifest" href="<?php echo asset('img/site.webmanifest'); ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>
    <div class="login-container">
        <!-- Panel Izquierdo -->
        <div class="login-left">
            <a href="<?php echo url('index.php'); ?>"><h1><img src="<?php echo asset('img/logo1.png'); ?>" width="50px"> <?php echo APP_NAME; ?></h1></a>
            <p>Sistema de automatización de mensajería empresarial</p>
            
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Bot de ventas con IA</li>
                <li><i class="fas fa-check-circle"></i> Mensajes masivos programados</li>
                <li><i class="fas fa-check-circle"></i> Agendamiento automático</li>
                <li><i class="fas fa-check-circle"></i> Integración con catálogos</li>
                <li><i class="fas fa-check-circle"></i> Soporte 24/7</li>
            </ul>
        </div>
        
        <!-- Panel Derecho - Formulario -->
        <div class="login-right">
            <div class="login-header">
                <h2>Iniciar Sesión</h2>
                <p>Accede a tu cuenta empresarial</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" 
                               placeholder="tu@empresa.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Ingresa tu contraseña" required>
                    </div>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Recordarme en este dispositivo</label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
                
                <?php if ($google_activo): ?>
                    <div class="divider">
                        <span>o continúa con</span>
                    </div>
                    
                    <a href="<?php echo url('api/v1/auth/google-oauth.php'); ?>" class="btn btn-google">
                        <i class="fab fa-google"></i>
                        <span>Continuar con Google</span>
                    </a>
                <?php endif; ?>
            </form>
            
            <div class="links">
                <a href="<?php echo url('recuperar-password.php'); ?>">
                    <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
                </a>
            </div>
            
            <div class="register-link">
                <p>¿No tienes una cuenta? 
                    <a href="<?php echo url('registro.php'); ?>">Regístrate gratis</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>