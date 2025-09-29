<?php
// sistema/cliente/pago-pendiente.php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!estaLogueado()) {
    header('Location: ' . url('login.php'));
    exit;
}

$page_title = "Pago Pendiente";
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
                            <div class="card card-warning">
                                <div class="card-body text-center p-5">
                                    <div class="mb-4">
                                        <i class="fas fa-clock text-warning" style="font-size: 5rem;"></i>
                                    </div>
                                    
                                    <h2 class="text-warning mb-3">Pago Pendiente</h2>
                                    
                                    <p class="lead mb-4">
                                        Tu pago está siendo procesado. Esto puede tomar algunos minutos.
                                    </p>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>¿Qué significa esto?</strong><br>
                                        Algunos métodos de pago requieren verificación adicional. 
                                        Te notificaremos por email cuando se complete el proceso.
                                    </div>
                                    
                                    <p class="mb-4">
                                        <strong>Tiempo estimado:</strong> Entre 5 minutos y 24 horas<br>
                                        <small class="text-muted">Depende del método de pago utilizado</small>
                                    </p>
                                    
                                    <div class="mt-5">
                                        <a href="<?= url('cliente/dashboard'); ?>" class="btn btn-primary btn-lg">
                                            <i class="fas fa-home"></i> Ir al Dashboard
                                        </a>
                                        
                                        <a href="<?= url('cliente/mi-plan'); ?>" class="btn btn-outline-warning btn-lg ml-2">
                                            <i class="fas fa-history"></i> Ver Estado
                                        </a>
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