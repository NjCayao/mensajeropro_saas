<?php
// includes/email.php - Wrapper simple
require_once __DIR__ . '/functions.php';

/**
 * Función wrapper para compatibilidad con webhooks
 */
function enviarEmail($destinatario, $asunto, $mensaje_html) {
    return enviarEmailSimple($destinatario, $asunto, $mensaje_html);
}