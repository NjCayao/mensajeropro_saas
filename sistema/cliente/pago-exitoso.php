<?php
// sistema/cliente/pago-exitoso.php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar login
if (!estaLogueado()) {
    header('Location: ' . url('login.php'));
    exit;
}

$page_title = "Pago Exitoso";

// Obtener parámetros según la pasarela
$payment_id = $_GET['payment_id'] ?? $_GET['preference_id'] ?? '';
$status = $_GET['status'] ?? $_GET['collection_status'] ?? '';
$external_reference = $_GET['external_reference'] ?? '';

// Si viene de MercadoPago
if (isset($_GET['collection_status']) && $_GET['collection_status'] === 'approved') {
    $metodo = 'mercadopago';
} 
// Si viene de PayPal
elseif (isset($_GET['subscription_id'])) {
    $metodo = 'paypal';
    $payment_id = $_GET['subscription_id'];
}

// Verificar el pago en la base de datos
$stmt = $pdo->prepare("
    SELECT p.*, pl.nombre as plan_nombre 
    FROM pagos p
    JOIN planes pl ON p.plan_id = pl.id
    WHERE p.empresa_id = ? 
    AND (p.referencia_externa = ? OR p.referencia_externa = ?)
    ORDER BY p.id DESC
    LIMIT 1
");
$stmt->execute([$_SESSION['empresa_id'], $payment_id, $external_reference]);
$pago = $stmt->fetch();

// Obtener información de la empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$_SESSION['empresa_id']]);
$empresa = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> | <?= $page_title ?></title>
    
    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?= asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?= asset('dist/css/adminlte.min.css'); ?>">
</head>

<body class="hold-transition">
    <div class="wrapper">
        <div class="content-wrapper ml-0">
            <section class="content">
                <div class="container-fluid">
                    <div class="row justify-content-center mt-5">
                        <div class="col-md-6">
                            <div class="card card-success">
                                <div class="card-body text-center p-5">
                                    <div class="mb-4">
                                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                                    </div>
                                    
                                    <h2 class="text-success mb-3">¡Pago Exitoso!</h2>
                                    
                                    <?php if ($pago): ?>
                                        <p class="lead mb-4">
                                            Tu suscripción al plan <strong><?= htmlspecialchars($pago['plan_nombre']); ?></strong> 
                                            ha sido activada correctamente.
                                        </p>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Referencia de pago:</strong> <?= htmlspecialchars($pago['referencia_externa']); ?><br>
                                            <strong>Monto:</strong> S/ <?= number_format($pago['monto'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="lead mb-4">
                                            Tu pago está siendo procesado. Recibirás un correo de confirmación 
                                            cuando se complete la activación.
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-4">
                                        <p class="mb-3">
                                            <i class="fas fa-envelope"></i> 
                                            Se ha enviado un correo de confirmación a: 
                                            <strong><?= htmlspecialchars($empresa['email']); ?></strong>
                                        </p>
                                        
                                        <p class="text-muted">
                                            Tu suscripción se renovará automáticamente cada mes. 
                                            Puedes cancelarla en cualquier momento desde tu panel.
                                        </p>
                                    </div>
                                    
                                    <div class="mt-5">
                                        <a href="<?= url('cliente/dashboard'); ?>" class="btn btn-success btn-lg">
                                            <i class="fas fa-arrow-right"></i> Ir al Dashboard
                                        </a>
                                        
                                        <a href="<?= url('cliente/mi-plan'); ?>" class="btn btn-outline-success btn-lg ml-2">
                                            <i class="fas fa-credit-card"></i> Ver Mi Plan
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Información adicional -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5>¿Qué sigue ahora?</h5>
                                    <ul class="mb-0">
                                        <li>Tu plan ya está activo y puedes usar todas las funciones</li>
                                        <li>Configura tu Bot IA para automatizar las ventas</li>
                                        <li>Importa tus contactos para comenzar a enviar mensajes</li>
                                        <li>Si necesitas ayuda, contáctanos por WhatsApp</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- jQuery -->
    <script src="<?= asset('plugins/jquery/jquery.min.js'); ?>"></script>
    <!-- Bootstrap 4 -->
    <script src="<?= asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
    
    <script>
        // Auto-redirigir después de 10 segundos
        setTimeout(function() {
            window.location.href = '<?= url('cliente/dashboard'); ?>';
        }, 10000);
    </script>
</body>
</html>