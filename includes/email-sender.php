<?php
// includes/email-sender.php
declare(strict_types=1);

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    private PHPMailer $mail;
    private array $config;

    public function __construct()
    {
        $this->config = getEmailConfig();
        $this->mail = new PHPMailer(true);
        
        if ($this->config['enabled'] && !empty($this->config['smtp_host'])) {
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['smtp_host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config['smtp_username'];
            $this->mail->Password = $this->config['smtp_password'];
            $this->mail->SMTPSecure = $this->config['smtp_secure'];
            $this->mail->Port = $this->config['smtp_port'];
        }
        
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom(
            $this->config['from'] ?: 'noreply@' . $_SERVER['HTTP_HOST'],
            $this->config['from_name']
        );
        
        if ($this->config['debug']) {
            $this->mail->SMTPDebug = 2;
        }
    }

    public function enviarDesdePlantilla(string $codigo_plantilla, string $email_destino, array $variables = []): bool
    {
        global $pdo;

        if (!$this->config['enabled']) {
            error_log("=== EMAIL (DEV MODE) ===");
            error_log("Plantilla: {$codigo_plantilla}");
            error_log("Para: {$email_destino}");
            error_log("Variables: " . json_encode($variables));
            return true;
        }

        try {
            $stmt = $pdo->prepare("SELECT asunto, contenido_html FROM plantillas_email WHERE codigo = ? AND activa = 1");
            $stmt->execute([$codigo_plantilla]);
            $plantilla = $stmt->fetch();

            if (!$plantilla) {
                error_log("Plantilla '{$codigo_plantilla}' no encontrada");
                return false;
            }

            $variables['app_name'] = APP_NAME;
            $variables['app_url'] = APP_URL;

            $asunto = $this->reemplazarVariables($plantilla['asunto'], $variables);
            $contenido = $this->reemplazarVariables($plantilla['contenido_html'], $variables);

            $this->mail->clearAddresses();
            $this->mail->addAddress($email_destino);
            $this->mail->Subject = $asunto;
            $this->mail->isHTML(true);
            $this->mail->Body = $contenido;
            $this->mail->AltBody = strip_tags($contenido);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando email: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function enviarSimple(string $email_destino, string $asunto, string $mensaje_html): bool
    {
        if (!$this->config['enabled']) {
            error_log("=== EMAIL SIMPLE (DEV) ===");
            error_log("Para: {$email_destino}");
            error_log("Asunto: {$asunto}");
            return true;
        }

        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email_destino);
            $this->mail->Subject = $asunto;
            $this->mail->isHTML(true);
            $this->mail->Body = $mensaje_html;
            $this->mail->AltBody = strip_tags($mensaje_html);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando email: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function reemplazarVariables(string $texto, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $texto = str_replace('{{' . $key . '}}', $value, $texto);
        }
        return $texto;
    }
}