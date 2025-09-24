<?php
// sistema/cliente/logout.php
session_start();
require_once __DIR__ . '/../../includes/auth.php';

// Cerrar sesión
cerrarSesion();

// Redirigir al login
header('Location: ' . url('login.php'));
exit;
?>