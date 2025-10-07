<?php

/**
 * Sistema de protección contra ataques
 */

class SecurityManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verificar intentos de login fallidos (protección fuerza bruta)
     */
    public function verificarIntentosLogin($email, $ip)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as intentos 
            FROM intentos_login 
            WHERE (email = ? OR ip = ?) 
            AND fecha > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND exitoso = 0
        ");
        $stmt->execute([$email, $ip]);
        $data = $stmt->fetch();

        // Máximo 5 intentos en 15 minutos
        if ($data['intentos'] >= 5) {
            return [
                'bloqueado' => true,
                'mensaje' => 'Demasiados intentos fallidos. Intenta en 15 minutos.',
                'intentos' => $data['intentos']
            ];
        }

        return ['bloqueado' => false, 'intentos' => $data['intentos']];
    }

    /**
     * Registrar intento de login
     */
    public function registrarIntentoLogin($email, $ip, $exitoso, $user_agent = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO intentos_login (email, ip, exitoso, user_agent, fecha)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $email,
            $ip,
            $exitoso ? 1 : 0,
            $user_agent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Limpiar registros antiguos (> 24 horas)
        $this->pdo->query("DELETE FROM intentos_login WHERE fecha < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

    /**
     * Rate limiting genérico
     */
    public function verificarRateLimit($accion, $identificador, $max_intentos = 5, $ventana_minutos = 15)
    {
        // BYPASS para localhost durante desarrollo
        if (defined('IS_LOCALHOST') && IS_LOCALHOST) {
            return ['bloqueado' => false];
        }

        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) as intentos 
        FROM rate_limit 
        WHERE accion = ? 
        AND identificador = ? 
        AND fecha > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
        $stmt->execute([$accion, $identificador, $ventana_minutos]);
        $data = $stmt->fetch();

        if ($data['intentos'] >= $max_intentos) {
            return [
                'bloqueado' => true,
                'mensaje' => "Límite excedido. Intenta en {$ventana_minutos} minutos."
            ];
        }

        // Registrar intento
        $stmt = $this->pdo->prepare("
        INSERT INTO rate_limit (accion, identificador, fecha)
        VALUES (?, ?, NOW())
    ");
        $stmt->execute([$accion, $identificador]);

        // Limpiar antiguos
        $this->pdo->query("DELETE FROM rate_limit WHERE fecha < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

        return ['bloqueado' => false];
    }

    /**
     * Validar CSRF token
     */
    public function validarCSRF($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generar CSRF token
     */
    public function generarCSRF()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
