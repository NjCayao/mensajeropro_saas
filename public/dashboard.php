<?php
// public/dashboard.php - Punto de entrada para el dashboard
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar que esté logueado
requireLogin();

// Incluir el dashboard real
require_once __DIR__ . '/../app/cliente/dashboard.php';
?>