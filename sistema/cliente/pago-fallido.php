<?php
// sistema/cliente/pago-fallido.php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!estaLogueado()) {
    header('Location: ' . url('login.php'));
    exit;
}

$page_title = "Pago No Completado";

// Registrar intento fallido
$payment_id = $_GET['payment_id'] ?? $_GET['preference_id'] ?? '';
if ($payment_id) {
    $stmt = $pdo->prepare("
        UPDATE pagos 
        SET estado = 'rechazado', 
            respuesta_gateway = ?
        WHERE empresa_id = ? 
        AND referencia_externa = ?
        AND estado = 'pendiente'
    ");
    $stmt->execute([
        json_encode($_GET),
        $_SESSION['empresa_id'],
        $payment_id
    ]);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> | <?= $page_title ?></title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?= asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?= asset('dist/css/adminlte.min.css'); ?>">
</head>

<body class="hold-transition">
    <div class="wrapper">
        <div class="content-wrapper ml-0">
            <section class="content">
                <div class="container-fluid">
                    <div class="row justify-content-center mt-5">
                        <div class="col-md-6">
                            <div class="card card-danger">
                                <div class="card-body text-center p-5">
                                    <div class="mb-4">
                                        <i class="fas fa-times-circle text-danger" style="font-size: 5rem;"></i>
                                    </div>
                                    
                                    <h2 class="text-danger mb-3">Pago No Completado</h2>
                                    
                                    <p class="lead mb-4">
                                        No pudimos procesar tu pago. Esto puede deberse a:
                                    </p>
                                    
                                    <div class="text-left mb-4">
                                        <ul>
                                            <li>Fondos insuficientes en tu cuenta</li>
                                            <li>Límite de crédito excedido</li>
                                            <li>Datos de pago incorrectos</li>
                                            <li>Cancelaste la operación</li>
                                            <li>Problema temporal con la pasarela de pago</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>No te preocupes</strong>, no se realizó ningún cargo a tu cuenta.
                                    </div>
                                    
                                    <div class="mt-5">
                                        <a href="<?= url('cliente/mi-plan'); ?>" class="btn btn-primary btn-lg">
                                            <i class="fas fa-redo"></i> Intentar Nuevamente
                                        </a>
                                        
                                        <a href="<?= url('cliente/dashboard'); ?>" class="btn btn-outline-secondary btn-lg ml-2">
                                            <i class="fas fa-home"></i> Ir al Dashboard
                                        </a>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <p class="text-muted">
                                            Si el problema persiste, contáctanos por WhatsApp 
                                            para ayudarte con el proceso.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="<?= asset('plugins/jquery/jquery.min.js'); ?>"></script>
    <script src="<?= asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
</body>
</html>