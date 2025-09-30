<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo APP_NAME; ?> - SuperAdmin</title>

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo asset('plugins/fontawesome-free/css/all.min.css'); ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?php echo asset('dist/css/adminlte.min.css'); ?>">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?php echo asset('plugins/sweetalert2/sweetalert2.min.css'); ?>">
    
    <style>
        .main-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .main-header .navbar-nav .nav-link {
            color: rgba(255,255,255,.9);
        }
        .main-header .navbar-nav .nav-link:hover {
            color: #fff;
        }
    </style>
    
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
        const API_URL = '<?php echo APP_URL; ?>/api/v1';
        const EMPRESA_ID = <?php echo $_SESSION['empresa_id'] ?? 0; ?>;
    </script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-danger navbar-dark">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo url('superadmin/dashboard'); ?>" class="nav-link">
                    <i class="fas fa-shield-alt"></i> Panel SuperAdmin
                </a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- User Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-user-shield"></i>
                    <span class="d-none d-md-inline"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">
                        <?php echo $_SESSION['user_name'] ?? 'Administrador'; ?>
                    </span>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo url('cliente/dashboard'); ?>" class="dropdown-item">
                        <i class="fas fa-user mr-2"></i> Ver como Cliente
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo url('cliente/logout'); ?>" class="dropdown-item">
                        <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                    </a>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->