<?php
// config/email.php
declare(strict_types=1);

function getEmailConfig(): array
{
    global $pdo;
    
    $configs = [
        'enabled' => !IS_LOCALHOST,
        'debug' => IS_LOCALHOST,
        'from' => '',
        'from_name' => APP_NAME,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_username' => '',
        'smtp_password' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("
            SELECT clave, valor 
            FROM configuracion_plataforma 
            WHERE clave IN ('email_remitente', 'email_nombre', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_username', 'smtp_password')
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            switch ($row['clave']) {
                case 'email_remitente': $configs['from'] = $row['valor']; break;
                case 'email_nombre': $configs['from_name'] = $row['valor'] ?: APP_NAME; break;
                case 'smtp_host': $configs['smtp_host'] = $row['valor']; break;
                case 'smtp_port': $configs['smtp_port'] = (int)$row['valor']; break;
                case 'smtp_secure': $configs['smtp_secure'] = $row['valor']; break;
                case 'smtp_username': $configs['smtp_username'] = $row['valor']; break;
                case 'smtp_password': $configs['smtp_password'] = $row['valor']; break;
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo configuración de email: " . $e->getMessage());
    }
    
    return $configs;
}