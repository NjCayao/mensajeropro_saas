<?php
// public/home.php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

// Si está logueado, redirigir al dashboard
if (estaLogueado()) {
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
?>