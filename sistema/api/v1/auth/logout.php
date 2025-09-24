<?php
session_start();
require_once '/../../../../config/database.php';
require_once '/../../../../includes/functions.php';

// Log antes de cerrar sesión
if (isset($_SESSION['user_id'])) {
    logActivity($pdo, 'auth', 'logout', 'Cierre de sesión');
}

// Destruir sesión
session_destroy();

jsonResponse(true, 'Sesión cerrada exitosamente');
?>