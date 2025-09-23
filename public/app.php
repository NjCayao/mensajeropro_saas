<?php
// public/app.php - Controlador principal
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/multi_tenant.php';

// Verificar login
requireLogin();

// Obtener módulo solicitado
$modulo = $_GET['mod'] ?? 'dashboard';

// Definir rutas disponibles
$rutas = [
    'dashboard' => 'dashboard.php',
    'contactos' => 'modulos/contactos.php',
    'categorias' => 'modulos/categorias.php',
    'mensajes' => 'modulos/mensajes.php',
    'programados' => 'modulos/programados.php',
    'escalados' => 'modulos/escalados.php',
    'plantillas' => 'modulos/plantillas.php',
    'whatsapp' => 'modulos/whatsapp.php',
    'bot-config' => 'modulos/bot-config.php',
    'perfil' => 'modulos/perfil.php',
    'historial' => 'modulos/historial.php'
];

// Verificar si el módulo existe
if (!isset($rutas[$modulo])) {
    $modulo = 'dashboard';
}

// Variables globales para los layouts
$page_title = ucfirst($modulo);
$current_page = $modulo;

// Cargar el módulo
require_once __DIR__ . '/../app/cliente/' . $rutas[$modulo];
?>