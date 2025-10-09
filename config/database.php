<?php
// config/database.php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'mensajeropro_saas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    // ✅ Configurar timezone de Perú (UTC-5)
    
    
} catch (PDOException $e) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Error de conexión: " . $e->getMessage());
    } else {
        die("Error de conexión a la base de datos");
    }
}