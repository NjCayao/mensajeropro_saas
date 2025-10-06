<?php
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session_check.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';
// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> | <?= $page_title ?? 'Dashboard' ?></title>
    
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('img/favicon.png'); ?>">    

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <!-- DataTables -->
    <link rel="stylesheet" href="<?php echo asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css'); ?>">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?php echo asset('plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css'); ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?php echo asset('dist/css/adminlte.min.css'); ?>">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/custom.css'); ?>">

    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
        const API_URL = '<?php echo APP_URL; ?>/api/v1';
    </script>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <!-- Preloader -->
        <div class="preloader flex-column justify-content-center align-items-center">
            <img class="animation__shake" src="<?php echo asset('dist/img/AdminLTELogo.png'); ?>" alt="Logo" height="60" width="60">
        </div>

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?php echo url('cliente/dashboard'); ?>" class="nav-link">Inicio</a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <!-- Notifications Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-bell"></i>
                        <span class="badge badge-warning navbar-badge">0</span>
                    </a>
                </li>

                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-user"></i> <?= $_SESSION['user_name'] ?? 'Usuario' ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a href="<?php echo url('cliente/perfil'); ?>" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Mi Perfil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="logout(); return false;">
                            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                        </a>
                    </div>
                </li>
            </ul>
        </nav>