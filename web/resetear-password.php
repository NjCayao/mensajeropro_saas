<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php'; 

$security = new SecurityManager($pdo);

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$token_valido = false;

// Verificar token
if (empty($token)) {
    $error = 'Token inválido o expirado';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT id, email, nombre_empresa 
            FROM empresas 
            WHERE password_reset_token = ? 
            AND password_reset_expires > NOW()
            AND activo = 1
        ");
        $stmt->execute([$token]);
        $empresa = $stmt->fetch();
        
        if ($empresa) {
            $token_valido = true;
        } else {
            $error = 'El enlace ha expirado o es inválido';
        }
    } catch (Exception $e) {
        error_log("Error verificando token: " . $e->getMessage());
        $error = 'Error al verificar el enlace';
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!$security->validarCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido';
    } elseif (empty($password) || empty($password_confirm)) {
        $error = 'Por favor completa todos los campos';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE empresas 
                SET password_hash = ?,
                    password_reset_token = NULL,
                    password_reset_expires = NULL
                WHERE id = ?
            ");
            $stmt->execute([$password_hash, $empresa['id']]);
            
            $success = true;
            
        } catch (Exception $e) {
            error_log("Error al resetear password: " . $e->getMessage());
            $error = 'Error al cambiar la contraseña. Intenta nuevamente.';
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
    <title>Cambiar Contraseña - <?php echo APP_NAME; ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="manifest" href="<?php echo asset('img/site.webmanifest'); ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            padding: 50px 40px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .reset-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .reset-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            color: #666;
            font-size: 0.95rem;
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
        
        .success-message {
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .success-icon i {
            font-size: 2.5rem;
            color: #28a745;
        }
        
        .success-message h3 {
            color: #155724;
            margin-bottom: 1rem;
        }
        
        .success-message p {
            color: #666;
            margin-bottom: 2rem;
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
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.3);
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($success): ?>
            <div class="success-message">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3>Contraseña Actualizada</h3>
                <p>Tu contraseña ha sido cambiada exitosamente</p>
                <a href="<?php echo url('login.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
            </div>
        <?php elseif (!$token_valido): ?>
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-times"></i>
                </div>
                <h2>Enlace Inválido</h2>
            </div>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <a href="<?php echo url('recuperar-password.php'); ?>" class="btn btn-primary">
                Solicitar Nuevo Enlace
            </a>
        <?php else: ?>
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2>Nueva Contraseña</h2>
                <p>Ingresa tu nueva contraseña</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                
                <div class="form-group">
                    <label class="form-label">Nueva Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Mínimo 8 caracteres" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirmar Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password_confirm" class="form-control" 
                               placeholder="Repite tu contraseña" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Cambiar Contraseña
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>