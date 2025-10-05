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

    // WhatsApp
    'whatsapp_soporte' => getConfig('whatsapp_soporte'),

    // Pagos
    'mercadopago_access_token' => getConfig('mercadopago_access_token'),
    'mercadopago_public_key' => getConfig('mercadopago_public_key'),
    'paypal_client_id' => getConfig('paypal_client_id'),
    'paypal_secret' => getConfig('paypal_secret'),
    'paypal_mode' => getConfig('paypal_mode', 'sandbox'),
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

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card card-outline card-info">
                                            <div class="card-header">
                                                <h5 class="card-title"><i class="fas fa-credit-card"></i> MercadoPago</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label>Access Token:</label>
                                                    <input type="password" class="form-control"
                                                        name="mercadopago_access_token"
                                                        value="<?= htmlspecialchars($config['mercadopago_access_token']) ?>"
                                                        placeholder="APP_USR-...">
                                                </div>
                                                <div class="form-group">
                                                    <label>Public Key:</label>
                                                    <input type="text" class="form-control"
                                                        name="mercadopago_public_key"
                                                        value="<?= htmlspecialchars($config['mercadopago_public_key']) ?>"
                                                        placeholder="APP_USR-...">
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
                                                        value="<?= htmlspecialchars($config['paypal_client_id']) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Secret:</label>
                                                    <input type="password" class="form-control"
                                                        name="paypal_secret"
                                                        value="<?= htmlspecialchars($config['paypal_secret']) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Modo:</label>
                                                    <select class="form-control" name="paypal_mode">
                                                        <option value="sandbox" <?= $config['paypal_mode'] == 'sandbox' ? 'selected' : '' ?>>Sandbox (Pruebas)</option>
                                                        <option value="live" <?= $config['paypal_mode'] == 'live' ? 'selected' : '' ?>>Live (Producción)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Guardar Configuración de Pagos
                                </button>
                            </form>
                        </div>

                        <!-- Tab Email -->
                        <div class="tab-pane fade" id="email" role="tabpanel">
                            <form id="formEmail">
                                <h4><i class="fas fa-envelope"></i> Configuración de Email</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Email Remitente:</label>
                                            <input type="email" class="form-control" name="email_remitente"
                                                value="<?= htmlspecialchars($config['email_remitente']) ?>"
                                                placeholder="noreply@mensajeropro.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nombre Remitente:</label>
                                            <input type="text" class="form-control" name="email_nombre"
                                                value="<?= htmlspecialchars($config['email_nombre']) ?>"
                                                placeholder="MensajeroPro">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Guardar Configuración de Email
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
            // CORRECCIÓN: Usar url() helper que maneja las rutas correctamente
            url: '<?= url("api/v1/superadmin/guardar-configuracion") ?>',
            method: 'POST',
            dataType: 'json',
            data: datos + '&seccion=' + seccion,
            beforeSend: function() {
                // Opcional: mostrar loading
                console.log('Guardando configuración de ' + seccion + '...');
            },
            success: function(response) {
                if (response.success) {
                    // Usar SweetAlert2 si está disponible, sino alert
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
</script>