<?php
$current_page = 'configuracion';
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/superadmin_session_check.php';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Obtener configuración actual
function getConfig($clave, $default = '')
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : $default;
}

// Configuraciones actuales
$config = [
    // OpenAI
    'openai_api_key' => getConfig('openai_api_key'),
    'openai_modelo' => getConfig('openai_modelo', 'gpt-3.5-turbo'),
    'openai_temperatura' => getConfig('openai_temperatura', '0.7'),
    'openai_max_tokens' => getConfig('openai_max_tokens', '150'),

    // Trial
    'trial_dias' => getConfig('trial_dias', '2'),

    // Email
    'email_remitente' => getConfig('email_remitente', 'noreply@mensajeropro.com'),
    'email_nombre' => getConfig('email_nombre', 'MensajeroPro'),

    // SMTP - AGREGADO
    'smtp_host' => getConfig('smtp_host'),
    'smtp_port' => getConfig('smtp_port', '587'),
    'smtp_secure' => getConfig('smtp_secure', 'tls'),
    'smtp_username' => getConfig('smtp_username'),
    'smtp_password' => getConfig('smtp_password'),

    // WhatsApp
    'whatsapp_soporte' => getConfig('whatsapp_soporte'),

    // Pagos
    'mercadopago_access_token' => getConfig('mercadopago_access_token'),
    'mercadopago_public_key' => getConfig('mercadopago_public_key'),
    'paypal_client_id' => getConfig('paypal_client_id'),
    'paypal_secret' => getConfig('paypal_secret'),
    'paypal_mode' => getConfig('paypal_mode', 'sandbox'),

    // Seguridad
    'recaptcha_site_key' => getConfig('recaptcha_site_key'),
    'recaptcha_secret_key' => getConfig('recaptcha_secret_key'),
    'recaptcha_activo' => getConfig('recaptcha_activo', '0'),
    'honeypot_activo' => getConfig('honeypot_activo', '1'),
    'bloquear_emails_temporales' => getConfig('bloquear_emails_temporales', '1'),
    'dominios_temporales' => getConfig('dominios_temporales', '10minutemail.com,tempmail.com,guerrillamail.com,mailinator.com'),
    'verificacion_email_obligatoria' => getConfig('verificacion_email_obligatoria', '1'),
];
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-cogs"></i> Configuración Global</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= url('superadmin/dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Configuración</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="alert alert-info">
                <i class="icon fas fa-info-circle"></i>
                <strong>Importante:</strong> Esta configuración se aplica a TODAS las empresas del sistema.
            </div>

            <!-- Tabs de configuración -->
            <div class="card card-danger card-tabs">
                <div class="card-header p-0 pt-1">
                    <ul class="nav nav-tabs" id="config-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#openai" role="tab">
                                <i class="fas fa-robot"></i> OpenAI
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pagos" role="tab">
                                <i class="fas fa-credit-card"></i> Pagos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#email" role="tab">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#google" role="tab">
                                <i class="fab fa-google"></i> Google OAuth
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#sistema" role="tab">
                                <i class="fas fa-cog"></i> Sistema
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#seguridad" role="tab">
                                <i class="fas fa-shield-alt"></i> Seguridad
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- Tab OpenAI -->
                        <div class="tab-pane fade show active" id="openai" role="tabpanel">
                            <form id="formOpenAI">
                                <h4><i class="fas fa-robot"></i> Configuración de OpenAI</h4>
                                <p class="text-muted">Esta API Key se usará para TODAS las empresas del sistema</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>API Key de OpenAI: <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="openai_api_key"
                                                    name="openai_api_key"
                                                    value="<?= htmlspecialchars($config['openai_api_key']) ?>"
                                                    placeholder="sk-...">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button"
                                                        onclick="togglePassword('openai_api_key')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                Obtén tu API Key en
                                                <a href="https://platform.openai.com/api-keys" target="_blank">
                                                    OpenAI Platform <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label>Modelo de IA:</label>
                                            <select class="form-control" name="openai_modelo">
                                                <option value="gpt-3.5-turbo" <?= $config['openai_modelo'] == 'gpt-3.5-turbo' ? 'selected' : '' ?>>
                                                    GPT-3.5 Turbo (Más económico - Recomendado)
                                                </option>
                                                <option value="gpt-4" <?= $config['openai_modelo'] == 'gpt-4' ? 'selected' : '' ?>>
                                                    GPT-4 (Más inteligente)
                                                </option>
                                                <option value="gpt-4-turbo-preview" <?= $config['openai_modelo'] == 'gpt-4-turbo-preview' ? 'selected' : '' ?>>
                                                    GPT-4 Turbo (Más rápido)
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>
                                                Temperatura: <span id="tempValue"><?= $config['openai_temperatura'] ?></span>
                                            </label>
                                            <input type="range" class="form-control-range" name="openai_temperatura"
                                                min="0" max="2" step="0.1"
                                                value="<?= $config['openai_temperatura'] ?>"
                                                oninput="document.getElementById('tempValue').textContent = this.value">
                                            <small class="text-muted">0 = Preciso | 2 = Creativo</small>
                                        </div>

                                        <div class="form-group">
                                            <label>Tokens máximos:</label>
                                            <input type="number" class="form-control" name="openai_max_tokens"
                                                value="<?= $config['openai_max_tokens'] ?>" min="50" max="2000">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Guardar Configuración OpenAI
                                </button>
                            </form>
                        </div>

                        <!-- Tab Pagos -->
                        <div class="tab-pane fade" id="pagos" role="tabpanel">
                            <form id="formPagos">
                                <h4><i class="fas fa-credit-card"></i> Pasarelas de Pago</h4>

                                <!-- URLS DE WEBHOOKS -->
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-bell"></i> URLs de Webhooks (Obligatorio configurar)</h5>
                                    <p class="mb-2">Copia estas URLs y configúralas en los paneles de MercadoPago y PayPal para recibir notificaciones de pagos:</p>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <label><strong>MercadoPago Webhook:</strong></label>
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" id="mp_webhook_url"
                                                    value="<?= APP_URL ?>/api/v1/webhooks/mercadopago" readonly>
                                                <div class="input-group-append">
                                                    <button class="btn btn-info" type="button" onclick="copiarURL('mp_webhook_url')">
                                                        <i class="fas fa-copy"></i> Copiar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label><strong>PayPal Webhook:</strong></label>
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" id="pp_webhook_url"
                                                    value="<?= APP_URL ?>/api/v1/webhooks/paypal" readonly>
                                                <div class="input-group-append">
                                                    <button class="btn btn-primary" type="button" onclick="copiarURL('pp_webhook_url')">
                                                        <i class="fas fa-copy"></i> Copiar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card card-outline card-info">
                                            <div class="card-header">
                                                <h5 class="card-title"><i class="fas fa-credit-card"></i> MercadoPago</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Access Token:</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control"
                                                            id="mp_access_token"
                                                            name="mercadopago_access_token"
                                                            value="<?= htmlspecialchars($config['mercadopago_access_token']) ?>"
                                                            placeholder="APP_USR-...">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button"
                                                                onclick="togglePassword('mp_access_token')">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Public Key:</label>
                                                    <input type="text" class="form-control"
                                                        name="mercadopago_public_key"
                                                        value="<?= htmlspecialchars($config['mercadopago_public_key']) ?>"
                                                        placeholder="APP_USR-...">
                                                </div>

                                                <!-- INSTRUCCIONES MP -->
                                                <div class="mt-3">
                                                    <button class="btn btn-sm btn-outline-info" type="button" data-toggle="collapse" data-target="#instruccionesMP">
                                                        <i class="fas fa-question-circle"></i> Ver instrucciones
                                                    </button>
                                                    <div class="collapse mt-2" id="instruccionesMP">
                                                        <div class="card card-body bg-light">
                                                            <h6><i class="fas fa-list-ol"></i> Configurar MercadoPago:</h6>
                                                            <ol class="mb-0 small">
                                                                <li>Ve a <a href="https://www.mercadopago.com.pe/developers/panel/credentials" target="_blank">Credenciales MercadoPago</a></li>
                                                                <li>Copia el <strong>Access Token</strong> y <strong>Public Key</strong></li>
                                                                <li>Pégalos arriba y guarda</li>
                                                                <li>Ve a <a href="https://www.mercadopago.com.pe/developers/panel/webhooks" target="_blank">Webhooks</a></li>
                                                                <li>Haz clic en "Crear webhook"</li>
                                                                <li>Pega la URL del webhook de arriba</li>
                                                                <li>Selecciona eventos:
                                                                    <ul>
                                                                        <li>✅ subscription_preapproval</li>
                                                                        <li>✅ subscription_authorized_payment</li>
                                                                    </ul>
                                                                </li>
                                                                <li>Guarda y copia el ID del webhook</li>
                                                            </ol>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card card-outline card-primary">
                                            <div class="card-header">
                                                <h5 class="card-title"><i class="fab fa-paypal"></i> PayPal</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Client ID:</label>
                                                    <input type="text" class="form-control"
                                                        name="paypal_client_id"
                                                        value="<?= htmlspecialchars($config['paypal_client_id']) ?>"
                                                        placeholder="Tu Client ID">
                                                </div>
                                                <div class="form-group">
                                                    <label>Secret:</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control"
                                                            id="pp_secret"
                                                            name="paypal_secret"
                                                            value="<?= htmlspecialchars($config['paypal_secret']) ?>"
                                                            placeholder="Tu Secret">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button"
                                                                onclick="togglePassword('pp_secret')">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Modo:</label>
                                                    <select class="form-control" name="paypal_mode">
                                                        <option value="sandbox" <?= $config['paypal_mode'] == 'sandbox' ? 'selected' : '' ?>>
                                                            Sandbox (Pruebas)
                                                        </option>
                                                        <option value="live" <?= $config['paypal_mode'] == 'live' ? 'selected' : '' ?>>
                                                            Live (Producción) ⚠️
                                                        </option>
                                                    </select>
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        Cambia a "Live" solo en producción con credenciales reales
                                                    </small>
                                                </div>

                                                <!-- INSTRUCCIONES PAYPAL -->
                                                <div class="mt-3">
                                                    <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#instruccionesPP">
                                                        <i class="fas fa-question-circle"></i> Ver instrucciones
                                                    </button>
                                                    <div class="collapse mt-2" id="instruccionesPP">
                                                        <div class="card card-body bg-light">
                                                            <h6><i class="fas fa-list-ol"></i> Configurar PayPal:</h6>
                                                            <ol class="mb-0 small">
                                                                <li>Ve a <a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank">PayPal Dashboard</a></li>
                                                                <li>Crea o selecciona una app</li>
                                                                <li>Copia <strong>Client ID</strong> y <strong>Secret</strong></li>
                                                                <li>Pégalos arriba y guarda</li>
                                                                <li>Ve a la sección <a href="https://developer.paypal.com/dashboard/webhooks" target="_blank">Webhooks</a></li>
                                                                <li>Haz clic en "Add webhook"</li>
                                                                <li>Pega la URL del webhook de arriba</li>
                                                                <li>Selecciona eventos:
                                                                    <ul>
                                                                        <li>✅ BILLING.SUBSCRIPTION.ACTIVATED</li>
                                                                        <li>✅ BILLING.SUBSCRIPTION.CANCELLED</li>
                                                                        <li>✅ BILLING.SUBSCRIPTION.SUSPENDED</li>
                                                                        <li>✅ PAYMENT.SALE.COMPLETED</li>
                                                                    </ul>
                                                                </li>
                                                                <li>Guarda</li>
                                                            </ol>
                                                            <div class="alert alert-warning mt-2 mb-0">
                                                                <strong>Producción:</strong> Repite estos pasos en
                                                                <a href="https://developer.paypal.com/dashboard/applications/live" target="_blank">modo Live</a>
                                                                cuando cambies a producción.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Guardar Configuración de Pagos
                                </button>
                            </form>
                        </div>

                        <!-- Tab Email -->
                        <div class="tab-pane fade" id="email" role="tabpanel">
                            <form id="formEmail">
                                <h4>Configuración de Email</h4>
                                <p class="text-muted">Configura el servidor SMTP para envío de emails</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card card-outline card-primary mb-3">
                                            <div class="card-header">
                                                <h5 class="card-title">Remitente</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Email Remitente: <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control" name="email_remitente"
                                                        value="<?= htmlspecialchars($config['email_remitente']) ?>"
                                                        placeholder="noreply@mensajeropro.com" required>
                                                    <small class="text-muted">Email que aparecerá como remitente</small>
                                                </div>
                                                <div class="form-group">
                                                    <label>Nombre Remitente:</label>
                                                    <input type="text" class="form-control" name="email_nombre"
                                                        value="<?= htmlspecialchars($config['email_nombre']) ?>"
                                                        placeholder="MensajeroPro">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card card-outline card-success mb-3">
                                            <div class="card-header">
                                                <h5 class="card-title">Servidor SMTP</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Host SMTP: <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="smtp_host"
                                                        value="<?= htmlspecialchars($config['smtp_host']) ?>"
                                                        placeholder="smtp.gmail.com" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Puerto:</label>
                                                    <input type="number" class="form-control" name="smtp_port"
                                                        value="<?= $config['smtp_port'] ?>"
                                                        placeholder="587">
                                                    <small class="text-muted">587 para TLS, 465 para SSL</small>
                                                </div>
                                                <div class="form-group">
                                                    <label>Seguridad:</label>
                                                    <select class="form-control" name="smtp_secure">
                                                        <option value="tls" <?= $config['smtp_secure'] == 'tls' ? 'selected' : '' ?>>TLS (Recomendado)</option>
                                                        <option value="ssl" <?= $config['smtp_secure'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card card-outline card-warning mb-3">
                                            <div class="card-header">
                                                <h5 class="card-title">Autenticación SMTP</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Usuario SMTP:</label>
                                                    <input type="email" class="form-control" name="smtp_username"
                                                        value="<?= htmlspecialchars($config['smtp_username']) ?>"
                                                        placeholder="tu-email@gmail.com">
                                                    <small class="text-muted">Generalmente es el mismo email remitente</small>
                                                </div>
                                                <div class="form-group">
                                                    <label>Contraseña SMTP:</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control"
                                                            id="smtp_password"
                                                            name="smtp_password"
                                                            value="<?= htmlspecialchars($config['smtp_password']) ?>"
                                                            placeholder="Contraseña o App Password">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button"
                                                                onclick="togglePassword('smtp_password')">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="alert alert-info">
                                            <h6>Configuración Gmail</h6>
                                            <ol class="mb-2 small">
                                                <li>Ve a <a href="https://myaccount.google.com/security" target="_blank">Google Security</a></li>
                                                <li>Activa "Verificación en 2 pasos"</li>
                                                <li>Busca "Contraseñas de aplicaciones"</li>
                                                <li>Genera una para "Correo"</li>
                                                <li>Usa esa contraseña aquí</li>
                                            </ol>
                                        </div>

                                        <div class="alert alert-warning">
                                            <strong>Modo desarrollo:</strong> En localhost, los emails solo se registran en logs, no se envían.
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Guardar Configuración de Email
                                </button>
                                <button type="button" class="btn btn-info" onclick="probarEmail()">
                                    <i class="fas fa-envelope"></i> Enviar Email de Prueba
                                </button>
                            </form>
                        </div>

                        <!-- Tab Google OAuth -->
                        <div class="tab-pane fade" id="google" role="tabpanel">
                            <form id="formGoogle">
                                <h4><i class="fab fa-google"></i> Login con Google</h4>
                                <p class="text-muted">Permite a los usuarios registrarse e iniciar sesión con su cuenta de Google</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Google Client ID:</label>
                                            <input type="text" class="form-control"
                                                name="google_client_id"
                                                value="<?= htmlspecialchars(getConfig('google_client_id')) ?>"
                                                placeholder="123456789-abc.apps.googleusercontent.com">
                                            <small class="text-muted">ID de cliente OAuth 2.0</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Google Client Secret:</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control"
                                                    id="google_secret"
                                                    name="google_client_secret"
                                                    value="<?= htmlspecialchars(getConfig('google_client_secret')) ?>"
                                                    placeholder="GOCSPX-...">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button"
                                                        onclick="togglePassword('google_secret')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Cómo obtener las credenciales:</h6>
                                    <ol class="mb-2">
                                        <li>Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                                        <li>Crea o selecciona un proyecto</li>
                                        <li>Ve a "APIs y servicios" → "Credenciales"</li>
                                        <li>Crea "ID de cliente de OAuth 2.0"</li>
                                        <li>Tipo: Aplicación web</li>
                                        <li>URI de redirección autorizada: <br><code><?= APP_URL ?>/api/v1/auth/google-oauth.php</code></li>
                                    </ol>
                                </div>

                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input"
                                        id="google_oauth_activo"
                                        name="google_oauth_activo"
                                        value="1"
                                        <?= getConfig('google_oauth_activo') == '1' ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="google_oauth_activo">
                                        <strong>Activar "Iniciar sesión con Google"</strong>
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-success mt-3">
                                    <i class="fas fa-save"></i> Guardar Configuración de Google
                                </button>
                            </form>
                        </div>

                        <!-- Tab Sistema -->
                        <div class="tab-pane fade" id="sistema" role="tabpanel">
                            <form id="formSistema">
                                <h4><i class="fas fa-cog"></i> Configuración del Sistema</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Días de Trial:</label>
                                            <input type="number" class="form-control" name="trial_dias"
                                                value="<?= $config['trial_dias'] ?>" min="1" max="90">
                                            <small class="text-muted">Periodo de prueba gratuito</small>
                                        </div>

                                        <div class="form-group">
                                            <label>WhatsApp de Soporte:</label>
                                            <input type="text" class="form-control" name="whatsapp_soporte"
                                                value="<?= htmlspecialchars($config['whatsapp_soporte']) ?>"
                                                placeholder="+51999999999">
                                            <small class="text-muted">Número de soporte técnico</small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="alert alert-info">
                                            <h5><i class="fas fa-info-circle"></i> Información</h5>
                                            <ul class="mb-0">
                                                <li>Trial: Periodo de prueba gratuito</li>
                                                <li>Los límites por plan se configuran en "Planes"</li>
                                                <li>Los cambios se aplican inmediatamente</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Guardar Configuración del Sistema
                                </button>
                            </form>
                        </div>

                        <!-- Tab Seguridad -->
                        <div class="tab-pane fade" id="seguridad" role="tabpanel">
                            <form id="formSeguridad">
                                <h4><i class="fas fa-shield-alt"></i> Seguridad y Anti-Spam</h4>
                                <p class="text-muted">Protección contra bots y registros falsos</p>

                                <!-- reCAPTCHA v3 -->
                                <div class="card card-outline card-warning mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-robot"></i> Google reCAPTCHA v3</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Site Key:</label>
                                                    <input type="text" class="form-control"
                                                        name="recaptcha_site_key"
                                                        value="<?= htmlspecialchars($config['recaptcha_site_key']) ?>"
                                                        placeholder="6Lc...">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Secret Key:</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control"
                                                            id="recaptcha_secret"
                                                            name="recaptcha_secret_key"
                                                            value="<?= htmlspecialchars($config['recaptcha_secret_key']) ?>"
                                                            placeholder="6Lc...">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button"
                                                                onclick="togglePassword('recaptcha_secret')">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle"></i> Obtener keys:</h6>
                                            <ol class="mb-0">
                                                <li>Ve a <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a></li>
                                                <li>Registra un nuevo sitio</li>
                                                <li>Selecciona "reCAPTCHA v3"</li>
                                                <li>Agrega tu dominio: <code><?= $_SERVER['HTTP_HOST'] ?></code></li>
                                                <li>Copia las keys aquí</li>
                                            </ol>
                                        </div>

                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input"
                                                id="recaptcha_activo" name="recaptcha_activo" value="1"
                                                <?= $config['recaptcha_activo'] == '1' ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="recaptcha_activo">
                                                <strong>Activar reCAPTCHA en Registro</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Honeypot -->
                                <div class="card card-outline card-success mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-bug"></i> Honeypot (Campo Trampa)</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Campo invisible que solo los bots llenan</p>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input"
                                                id="honeypot_activo" name="honeypot_activo" value="1"
                                                <?= $config['honeypot_activo'] == '1' ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="honeypot_activo">
                                                <strong>Activar Honeypot en Registro</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Recomendado: Siempre activo (protección invisible)</small>
                                    </div>
                                </div>

                                <!-- Emails Temporales -->
                                <div class="card card-outline card-danger mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-envelope-open-text"></i> Bloquear Emails Temporales</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="custom-control custom-switch mb-3">
                                            <input type="checkbox" class="custom-control-input"
                                                id="bloquear_emails_temporales" name="bloquear_emails_temporales" value="1"
                                                <?= $config['bloquear_emails_temporales'] == '1' ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="bloquear_emails_temporales">
                                                <strong>Bloquear dominios de emails temporales</strong>
                                            </label>
                                        </div>

                                        <div class="form-group">
                                            <label>Dominios bloqueados (uno por línea):</label>
                                            <textarea class="form-control" name="dominios_temporales" rows="6"
                                                placeholder="10minutemail.com&#10;tempmail.com&#10;guerrillamail.com"><?= htmlspecialchars(str_replace(',', "\n", $config['dominios_temporales'])) ?></textarea>
                                            <small class="text-muted">
                                                Lista de dominios conocidos de emails temporales/desechables
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Verificación Obligatoria -->
                                <div class="card card-outline card-primary mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-envelope-circle-check"></i> Verificación de Email</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input"
                                                id="verificacion_email_obligatoria" name="verificacion_email_obligatoria" value="1"
                                                <?= $config['verificacion_email_obligatoria'] == '1' ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="verificacion_email_obligatoria">
                                                <strong>Requerir verificación de email antes de activar cuenta</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            Si está activo: Las cuentas se crean inactivas hasta que verifiquen su email.
                                            <br>Si está desactivado: Las cuentas se activan inmediatamente.
                                        </small>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Guardar Configuración de Seguridad
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = event.target;

        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    $('#formOpenAI').on('submit', function(e) {
        e.preventDefault();
        guardarConfiguracion('openai', $(this).serialize());
    });

    $('#formPagos').on('submit', function(e) {
        e.preventDefault();
        guardarConfiguracion('pagos', $(this).serialize());
    });

    $('#formEmail').on('submit', function(e) {
        e.preventDefault();
        guardarConfiguracion('email', $(this).serialize());
    });

    $('#formGoogle').on('submit', function(e) {
        e.preventDefault();
        guardarConfiguracion('google', $(this).serialize());
    });

    $('#formSistema').on('submit', function(e) {
        e.preventDefault();
        guardarConfiguracion('sistema', $(this).serialize());
    });

    function guardarConfiguracion(seccion, datos) {
        $.ajax({
            url: '<?= url("api/v1/superadmin/guardar-configuracion") ?>',
            method: 'POST',
            dataType: 'json',
            data: datos + '&seccion=' + seccion,
            beforeSend: function() {
                console.log('Guardando configuración de ' + seccion + '...');
            },
            success: function(response) {
                if (response.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: response.message,
                            timer: 2000
                        });
                    } else {
                        alert('✓ ' + response.message);
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    } else {
                        alert('✗ ' + response.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', xhr.responseText);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo guardar la configuración. Error: ' + error,
                        footer: 'Revisa la consola para más detalles'
                    });
                } else {
                    alert('Error de conexión: ' + error);
                }
            }
        });
    }

    $('#formSeguridad').on('submit', function(e) {
        e.preventDefault();
        guardarConfiguracion('seguridad', $(this).serialize());
    });

    // Función para probar email
    function probarEmail() {
        Swal.fire({
            title: 'Email de Prueba',
            input: 'email',
            inputLabel: 'Enviar email de prueba a:',
            inputPlaceholder: 'tu-email@ejemplo.com',
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) {
                    return 'Debes ingresar un email';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= url("api/v1/superadmin/test-email") ?>', // ✅ SIN sistema/ y SIN .php
                    method: 'POST',
                    data: {
                        email: result.value
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Éxito', 'Email de prueba enviado. Revisa tu bandeja de entrada.', 'success');
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo enviar el email de prueba', 'error');
                    }
                });
            }
        });
    }

    function copiarURL(inputId) {
        const input = document.getElementById(inputId);
        input.select();
        input.setSelectionRange(0, 99999); // Para móviles

        try {
            document.execCommand('copy');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Copiado!',
                    text: 'URL copiada al portapapeles',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                alert('✓ URL copiada al portapapeles');
            }
        } catch (err) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo copiar. Cópiala manualmente.'
                });
            } else {
                alert('✗ Error al copiar. Cópiala manualmente.');
            }
        }
    }
</script>