<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$security = new SecurityManager($pdo);

if (isset($_SESSION['empresa_id'])) {
    header('Location: ' . url('app.php'));
    exit;
}

// Obtener configs de seguridad
$stmt = $pdo->prepare("SELECT clave, valor FROM configuracion_plataforma WHERE clave IN (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute(['recaptcha_activo', 'recaptcha_site_key', 'recaptcha_secret_key', 'honeypot_activo', 'bloquear_emails_temporales', 'dominios_temporales', 'verificacion_email_obligatoria']);
$configs_seguridad = [];
while ($row = $stmt->fetch()) {
    $configs_seguridad[$row['clave']] = $row['valor'];
}

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $aceptar_terminos = isset($_POST['aceptar_terminos']);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // 1. Validar CSRF
    if (!$security->validarCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido';
    }
    // 2. Rate limit
    else {
        $rateCheck = $security->verificarRateLimit('registro', $ip, 3, 60);
        if ($rateCheck['bloqueado']) {
            $error = $rateCheck['mensaje'];
        }
        // 3. Validar honeypot
        elseif (($configs_seguridad['honeypot_activo'] ?? '1') == '1' && !empty($_POST['website'])) {
            error_log("Bot detectado en registro (honeypot): IP {$ip}");
            $error = 'Error en el registro. Intenta nuevamente.';
        }
        // 4. Validar reCAPTCHA
        elseif (($configs_seguridad['recaptcha_activo'] ?? '0') == '1') {
            $recaptcha_token = $_POST['recaptcha_token'] ?? '';
            $recaptcha_secret = $configs_seguridad['recaptcha_secret_key'] ?? '';

            if (!empty($recaptcha_secret)) {
                $recaptcha_response = @file_get_contents(
                    "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_token}"
                );
                $recaptcha_data = json_decode($recaptcha_response);

                if (!$recaptcha_data->success || ($recaptcha_data->score ?? 0) < 0.5) {
                    error_log("reCAPTCHA fallido: score " . ($recaptcha_data->score ?? 'N/A'));
                    $error = 'Verificación de seguridad fallida. Intenta nuevamente.';
                }
            }
        }
        // 5. Validaciones de campos
        elseif (empty($nombre_empresa) || empty($email) || empty($password)) {
            $error = 'Todos los campos obligatorios deben completarse';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        }
        // 6. Validar emails temporales
        elseif (($configs_seguridad['bloquear_emails_temporales'] ?? '1') == '1') {
            $dominios_bloqueados = array_map('trim', explode(',', $configs_seguridad['dominios_temporales'] ?? ''));
            $dominio_email = strtolower(substr(strrchr($email, "@"), 1));

            if (in_array($dominio_email, $dominios_bloqueados)) {
                $error = 'No se permiten emails temporales o desechables';
            }
        }
        // 7. Resto de validaciones
        elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden';
        } elseif (!$aceptar_terminos) {
            $error = 'Debes aceptar los Términos y Condiciones';
        }
        // 8. Procesar registro
        else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM empresas WHERE email = ?");
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = 'Este email ya está registrado';
                } else {
                    $pdo->beginTransaction();

                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $requiere_verificacion = ($configs_seguridad['verificacion_email_obligatoria'] ?? '1') == '1';
                    $activo_inicial = $requiere_verificacion ? 0 : 1;

                    // Insertar empresa
                    $stmt = $pdo->prepare("
                        INSERT INTO empresas 
                        (nombre_empresa, email, password_hash, telefono, metodo_registro, 
                        plan_id, fecha_registro, activo) 
                        VALUES (?, ?, ?, ?, 'email', ?, NOW(), ?)
                    ");
                    $stmt->execute([$nombre_empresa, $email, $password_hash, $telefono, DEFAULT_PLAN_ID, $activo_inicial]);
                    $empresa_id = $pdo->lastInsertId(); // ← AQUÍ SE DEFINE

                    // Crear suscripción
                    $stmt = $pdo->prepare("
                        INSERT INTO suscripciones 
                        (empresa_id, plan_id, tipo, fecha_inicio, fecha_fin, estado) 
                        VALUES (?, ?, 'trial', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'activa')
                    ");
                    $stmt->execute([$empresa_id, DEFAULT_PLAN_ID, TRIAL_DAYS]);

                    // Categoría por defecto
                    $stmt = $pdo->prepare("
                        INSERT INTO categorias (nombre, descripcion, color, activo, empresa_id) 
                        VALUES ('General', 'Categoría por defecto', '#17a2b8', 1, ?)
                    ");
                    $stmt->execute([$empresa_id]);

                    // WhatsApp
                    $stmt = $pdo->prepare("
                        INSERT INTO whatsapp_sesiones_empresa (empresa_id, estado) 
                        VALUES (?, 'desconectado')
                    ");
                    $stmt->execute([$empresa_id]);

                    // Bot
                    $stmt = $pdo->prepare("
                        INSERT INTO configuracion_bot (empresa_id, activo) 
                        VALUES (?, 0)
                    ");
                    $stmt->execute([$empresa_id]);

                    // Negocio
                    $stmt = $pdo->prepare("
                        INSERT INTO configuracion_negocio (empresa_id, nombre_negocio) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$empresa_id, $nombre_empresa]);

                    // Token verificación
                    $codigo_verificacion = bin2hex(random_bytes(16));
                    $stmt = $pdo->prepare("UPDATE empresas SET token_verificacion = ? WHERE id = ?");
                    $stmt->execute([$codigo_verificacion, $empresa_id]);

                    $pdo->commit();

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
                $error = 'Error en el registro. Intenta nuevamente.';
            }
        }
    }
}

$csrf = $security->generarCSRF();

// Google OAuth
$google_activo = false;
try {
    $stmt = $pdo->query("SELECT valor FROM configuracion_plataforma WHERE clave = 'google_oauth_activo'");
    $config = $stmt->fetch();
    $google_activo = ($config && $config['valor'] == '1');
} catch (Exception $e) {
    error_log("Error verificando Google OAuth: " . $e->getMessage());
}

// Trial días
$stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'trial_dias'");
$stmt->execute();
$result = $stmt->fetch();
$trial_dias = $result ? (int)$result['valor'] : TRIAL_DAYS;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear Cuenta - <?php echo APP_NAME; ?></title>

    <link rel="stylesheet" href="assets/css/registro.css">

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
    </style>
</head>

<body>
    <div class="register-container">
        <!-- Panel Izquierdo -->
        <div class="register-left">
            <!-- <img src="<?php echo asset('img/logo1.png'); ?>" style="width: 80px; position: relative; left: 30%;"> -->
            <h1><img src="<?php echo asset('img/logo1.png'); ?>" width="50px"> <?php echo APP_NAME; ?></h1>
            <p>Empieza a automatizar tus ventas hoy mismo</p>

            <ul class="benefit-list">
                <li><i class="fas fa-check-circle"></i> Sin tarjeta de crédito</li>
                <li><i class="fas fa-check-circle"></i> Configuración en 5 minutos</li>
                <li><i class="fas fa-check-circle"></i> Acceso completo a todas las funciones</li>
                <li><i class="fas fa-check-circle"></i> Soporte técnico incluido</li>
                <li><i class="fas fa-check-circle"></i> Cancela cuando quieras</li>
            </ul>

            <div class="trial-badge">
                <h3><?php echo $trial_dias; ?> Días Gratis</h3>
                <p>Prueba completa sin compromisos</p>
            </div>
        </div>

        <!-- Panel Derecho - Formulario -->
        <div class="register-right">
            <div class="register-header">
                <h2>Crear Cuenta</h2>
                <p>Completa el formulario para comenzar tu prueba gratuita</p>
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
                    <label class="form-label">Nombre de la Empresa <span style="color: #e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-building"></i>
                        <input type="text" name="nombre_empresa" class="form-control"
                            placeholder="Mi Empresa S.A.C."
                            value="<?php echo htmlspecialchars($nombre_empresa ?? ''); ?>"
                            required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Empresarial <span style="color: #e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control"
                            placeholder="contacto@miempresa.com"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="telefono" class="form-control"
                            placeholder="+51 999 999 999"
                            value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contraseña <span style="color: #e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control"
                            placeholder="Mínimo 8 caracteres" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirmar Contraseña <span style="color: #e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password_confirm" class="form-control"
                            placeholder="Repite tu contraseña" required>
                    </div>
                </div>

                <div class="checkbox-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="aceptar_terminos" name="aceptar_terminos" required>
                        <label for="aceptar_terminos">
                            Acepto los <a href="<?php echo url('terminos.php'); ?>" target="_blank">Términos y Condiciones</a>
                            y la <a href="<?php echo url('privacidad.php'); ?>" target="_blank">Política de Privacidad</a>
                        </label>
                    </div>
                </div>

                <!-- Honeypot -->
                <?php if (($configs_seguridad['honeypot_activo'] ?? '1') == '1'): ?>
                    <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
                <?php endif; ?>

                <!-- reCAPTCHA -->
                <?php if (($configs_seguridad['recaptcha_activo'] ?? '0') == '1'): ?>
                    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-rocket"></i> Comenzar Prueba Gratuita
                </button>

                <?php if ($google_activo): ?>
                    <div class="divider">
                        <span>o regístrate con</span>
                    </div>

                    <a href="<?php echo url('api/v1/auth/google-oauth.php'); ?>" class="btn btn-google">
                        <i class="fab fa-google"></i>
                        <span>Continuar con Google</span>
                    </a>
                <?php endif; ?>
            </form>

            <div class="login-link">
                <p>¿Ya tienes una cuenta?
                    <a href="<?php echo url('login.php'); ?>">Inicia sesión aquí</a>
                </p>
            </div>
        </div>
    </div>
</body>

<?php if (($configs_seguridad['recaptcha_activo'] ?? '0') == '1' && !empty($configs_seguridad['recaptcha_site_key'])): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= $configs_seguridad['recaptcha_site_key'] ?>"></script>
    <script>
        grecaptcha.ready(function() {
            grecaptcha.execute('<?= $configs_seguridad['recaptcha_site_key'] ?>', {
                action: 'registro'
            }).then(function(token) {
                document.getElementById('recaptcha_token').value = token;
            });
        });
    </script>
<?php endif; ?>

</html>