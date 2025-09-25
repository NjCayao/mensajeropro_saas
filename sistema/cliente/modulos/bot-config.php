<?php
$current_page = 'bot-config';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
$empresa_id = getEmpresaActual();

// Obtener configuración actual
$stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch();

// Si no existe configuración, crear una por defecto
if (!$config) {
    $stmt = $pdo->prepare("INSERT INTO configuracion_bot (empresa_id) VALUES (?)");
    $stmt->execute([$empresa_id]);

    $stmt = $pdo->prepare("SELECT * FROM configuracion_bot WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch();
}

// Obtener archivos de conocimiento
$conocimientos = $pdo->query("SELECT * FROM conocimiento_bot ORDER BY fecha_subida DESC")->fetchAll();

// Obtener estadísticas del bot
$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversaciones_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$total_conversaciones = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversaciones_bot WHERE DATE(fecha_hora) = CURDATE() AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$conversaciones_hoy = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(tokens_usados), 0) FROM conversaciones_bot WHERE DATE(fecha_hora) = CURDATE() AND empresa_id = ?");
$stmt->execute([$empresa_id]);
$tokens_usados_hoy = $stmt->fetchColumn();

$stats = [
    'total_conversaciones' => $total_conversaciones,
    'conversaciones_hoy' => $conversaciones_hoy,
    'tokens_usados_hoy' => $tokens_usados_hoy
];
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bot IA con ChatGPT</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Bot IA</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Estadísticas -->
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($stats['total_conversaciones']) ?></h3>
                            <p>Total Conversaciones</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comments"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($stats['conversaciones_hoy']) ?></h3>
                            <p>Conversaciones Hoy</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($stats['tokens_usados_hoy']) ?></h3>
                            <p>Tokens Usados Hoy</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="small-box <?= $config['activo'] ? 'bg-success' : 'bg-danger' ?>">
                        <div class="inner">
                            <h3><?= $config['activo'] ? 'ACTIVO' : 'INACTIVO' ?></h3>
                            <p>Estado del Bot</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-power-off"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Configuración General -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Configuración General</h3>
                        </div>
                        <form id="formConfiguracion">
                            <div class="card-body">
                                <!-- Estado del Bot -->
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="activo"
                                            name="activo" <?= $config['activo'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="activo">
                                            <strong>Bot Activo</strong> - Responder mensajes automáticamente
                                        </label>
                                    </div>
                                </div>

                                <!-- Delay de respuesta -->
                                <div class="form-group">
                                    <label>Delay de respuesta (segundos):</label>
                                    <input type="number" class="form-control" name="delay_respuesta"
                                        value="<?= $config['delay_respuesta'] ?>" min="1" max="60">
                                    <small class="text-muted">Tiempo de espera antes de responder</small>
                                </div>

                                <!-- Horario de atención -->
                                <div class="form-group">
                                    <label>Horario de atención:</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <input type="time" class="form-control" name="horario_inicio"
                                                value="<?= $config['horario_inicio'] ?>">
                                            <small class="text-muted">Hora inicio</small>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="time" class="form-control" name="horario_fin"
                                                value="<?= $config['horario_fin'] ?>">
                                            <small class="text-muted">Hora fin</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mensaje fuera de horario -->
                                <div class="form-group">
                                    <label>Mensaje fuera de horario:</label>
                                    <textarea class="form-control" name="mensaje_fuera_horario" rows="3"><?= htmlspecialchars($config['mensaje_fuera_horario'] ?? '') ?></textarea>
                                </div>

                                <!-- Responder a no registrados -->
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="responder_no_registrados"
                                            name="responder_no_registrados" <?= $config['responder_no_registrados'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="responder_no_registrados">
                                            Responder a números no registrados
                                        </label>
                                    </div>
                                </div>

                                <!-- Palabras de activación -->
                                <div class="form-group">
                                    <label>Palabras de activación (separadas por coma):</label>
                                    <input type="text" class="form-control" name="palabras_activacion"
                                        value="<?= implode(', ', json_decode($config['palabras_activacion'] ?? '[]', true)) ?>"
                                        placeholder="hola, info, precio, consulta">
                                    <small class="text-muted">Dejar vacío para responder a todos los mensajes</small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Configuración OpenAI -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Configuración OpenAI</h3>
                        </div>
                        <form id="formOpenAI">
                            <div class="card-body">
                                <!-- API Key -->
                                <div class="form-group">
                                    <label>API Key de OpenAI:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="openai_api_key"
                                            name="openai_api_key" value="<?= $config['openai_api_key'] ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey()">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        Obtén tu API Key en
                                        <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                                    </small>
                                </div>

                                <!-- Modelo -->
                                <div class="form-group">
                                    <label>Modelo de IA:</label>
                                    <select class="form-control" name="modelo_ai">
                                        <option value="gpt-3.5-turbo" <?= $config['modelo_ai'] == 'gpt-3.5-turbo' ? 'selected' : '' ?>>
                                            GPT-3.5 Turbo (Más económico)
                                        </option>
                                        <option value="gpt-4" <?= $config['modelo_ai'] == 'gpt-4' ? 'selected' : '' ?>>
                                            GPT-4 (Más inteligente)
                                        </option>
                                        <option value="gpt-4-turbo-preview" <?= $config['modelo_ai'] == 'gpt-4-turbo-preview' ? 'selected' : '' ?>>
                                            GPT-4 Turbo (Más rápido)
                                        </option>
                                    </select>
                                </div>

                                <!-- Temperatura -->
                                <div class="form-group">
                                    <label>Temperatura (Creatividad): <span id="tempValue"><?= $config['temperatura'] ?></span></label>
                                    <input type="range" class="form-control-range" name="temperatura"
                                        min="0" max="2" step="0.1" value="<?= $config['temperatura'] ?>"
                                        oninput="document.getElementById('tempValue').textContent = this.value">
                                    <small class="text-muted">0 = Más preciso | 2 = Más creativo</small>
                                </div>

                                <!-- Max Tokens -->
                                <div class="form-group">
                                    <label>Tokens máximos por respuesta:</label>
                                    <input type="number" class="form-control" name="max_tokens"
                                        value="<?= $config['max_tokens'] ?>" min="50" max="2000">
                                    <small class="text-muted">1 token ≈ 4 caracteres</small>
                                </div>

                                <!-- System Prompt (Personalidad) -->
                                <div class="form-group">
                                    <label>Personalidad del Bot (System Prompt):</label>
                                    <textarea class="form-control" id="system_prompt" rows="3"
                                        placeholder="Ej: Eres un asistente amigable y profesional..."><?= htmlspecialchars($config['system_prompt'] ?? 'Eres un asistente virtual amigable y profesional. Responde de manera clara, concisa y útil. Si no sabes algo, admítelo honestamente.') ?></textarea>
                                    <small class="text-muted">Define cómo debe comportarse el bot (tono, estilo, personalidad)</small>
                                </div>

                                <!-- Información del Negocio (UNIFICADO) -->
                                <div class="form-group">
                                    <label>Información Completa del Negocio:</label>
                                    <textarea class="form-control" id="business_info" name="business_info" rows="12"
                                        placeholder="Organiza aquí toda la información de tu negocio. Ejemplo:

EMPRESA: MensajeroPro
SERVICIOS: Marketing por WhatsApp

HORARIOS:
Lunes a Viernes: 9:00 AM - 6:00 PM
Sábados: 9:00 AM - 1:00 PM

PRECIOS:
- Plan Básico: S/99/mes (1000 mensajes)
- Plan Pro: S/199/mes (5000 mensajes)
- Plan Empresa: S/499/mes (ilimitado)

CONTACTO:
WhatsApp: 999888777
Email: info@mensajeropro.com

PREGUNTAS FRECUENTES:
P: ¿Ofrecen prueba gratuita?
R: Sí, 7 días con 100 mensajes gratis."><?= htmlspecialchars($config['business_info'] ?? '') ?></textarea>
                                    <small class="text-muted">
                                        Incluye todo: datos de contacto, servicios, precios, horarios, políticas, preguntas frecuentes, etc.
                                        El bot usará esta información para responder a tus clientes.
                                    </small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <button class="btn btn-success" onclick="guardarConfiguracion()">
                        <i class="fas fa-save"></i> Guardar Configuración
                    </button>
                    <button class="btn btn-info" onclick="probarBot()">
                        <i class="fas fa-vial"></i> Probar Bot
                    </button>
                    <button class="btn btn-warning" onclick="verificarConfiguracion()">
                        <i class="fas fa-check-circle"></i> Verificar Configuración
                    </button>
                </div>
            </div><br>
        </div>
    </section>
</div>

<!-- Modal Subir Archivo -->
<div class="modal fade" id="modalSubirArchivo">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Agregar Conocimiento</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formSubirArchivo">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Título del conocimiento:</label>
                        <input type="text" class="form-control" id="titulo_conocimiento"
                            placeholder="Ej: Precios y Servicios" required>
                    </div>
                    <div class="form-group">
                        <label>Contenido:</label>
                        <textarea class="form-control" id="contenido_conocimiento" rows="10"
                            placeholder="Pega aquí la información sobre tu negocio..." required></textarea>
                        <small class="text-muted">
                            Puedes pegar información sobre: precios, servicios, horarios, políticas, preguntas frecuentes, etc.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Probar Bot -->
<div class="modal fade" id="modalProbarBot">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Probar Bot</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="direct-chat-messages" id="chatTest" style="height: 400px; overflow-y: auto;">
                    <!-- Mensajes de prueba aparecerán aquí -->
                </div>
                <div class="input-group mt-3">
                    <input type="text" class="form-control" id="mensajePrueba"
                        placeholder="Escribe un mensaje de prueba...">
                    <div class="input-group-append">
                        <button class="btn btn-primary" onclick="enviarMensajePrueba()">
                            <i class="fas fa-paper-plane"></i> Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Preview del nombre del archivo
        $('#archivo_conocimiento').on('change', function() {
            const fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').text(fileName || 'Elegir archivo...');
        });

        // Formulario subir archivo
        $('#formSubirArchivo').on('submit', function(e) {
            e.preventDefault();

            const titulo = $('#titulo_conocimiento').val();
            const contenido = $('#contenido_conocimiento').val();

            if (!titulo || !contenido) {
                Swal.fire('Error', 'Por favor completa todos los campos', 'error');
                return;
            }

            Swal.fire({
                title: 'Guardando conocimiento...',
                html: 'Por favor espera...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: API_URL + "/bot/entrenar.php",
                method: 'POST',
                data: {
                    titulo: titulo,
                    contenido: contenido
                },
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire('Éxito', 'Conocimiento guardado correctamente', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al guardar el conocimiento', 'error');
                }
            });
        });
    });

    function toggleApiKey() {
        const input = document.getElementById('openai_api_key');
        const icon = event.target;

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function guardarConfiguracion() {
        // Obtener todos los datos del formulario
        const formData = {
            // Configuración general
            activo: $('#activo').is(':checked') ? 1 : 0,
            delay_respuesta: $('input[name="delay_respuesta"]').val(),
            horario_inicio: $('input[name="horario_inicio"]').val(),
            horario_fin: $('input[name="horario_fin"]').val(),
            mensaje_fuera_horario: $('textarea[name="mensaje_fuera_horario"]').val(),
            responder_no_registrados: $('#responder_no_registrados').is(':checked') ? 1 : 0,
            palabras_activacion: $('input[name="palabras_activacion"]').val(),

            // Configuración OpenAI
            openai_api_key: $('input[name="openai_api_key"]').val(),
            modelo_ai: $('select[name="modelo_ai"]').val(),
            temperatura: $('input[name="temperatura"]').val(),
            max_tokens: $('input[name="max_tokens"]').val(),

            // Prompts
            system_prompt: $('#system_prompt').val(),
            business_info: $('#business_info').val()
        };

        Swal.fire({
            title: 'Guardando configuración...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: API_URL + "/bot/configurar.php",
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                Swal.close();

                if (response.success) {
                    Swal.fire('Éxito', response.message || 'Configuración guardada correctamente', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message || 'Error al guardar', 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                console.error('Error AJAX:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });

                // Intentar parsear el error
                let errorMessage = 'Error de conexión con el servidor';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // Si no es JSON, mostrar el texto crudo
                    if (xhr.responseText) {
                        errorMessage = 'Error del servidor: ' + xhr.responseText;
                    }
                }

                Swal.fire('Error', errorMessage, 'error');
            }
        });
    }

    function toggleConocimiento(id) {
        $.post(API_URL + "/bot/toggle-conocimiento.php", {
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    }

    function eliminarConocimiento(id) {
        Swal.fire({
            title: '¿Eliminar archivo?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + "/bot/eliminar-conocimiento.php", {
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Eliminado', 'El archivo ha sido eliminado', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                });
            }
        });
    }

    function probarBot() {
        $('#modalProbarBot').modal('show');
        $('#chatTest').html('<div class="text-center text-muted">Escribe un mensaje para probar el bot...</div>');
    }

    function enviarMensajePrueba() {
        const mensaje = $('#mensajePrueba').val().trim();
        if (!mensaje) return;

        // Agregar mensaje del usuario
        $('#chatTest').append(`
        <div class="direct-chat-msg right">
            <div class="direct-chat-text bg-primary">
                ${mensaje}
            </div>
        </div>
    `);

        $('#mensajePrueba').val('');

        // Mostrar typing
        $('#chatTest').append(`
        <div class="direct-chat-msg" id="typing">
            <div class="direct-chat-text">
                <i class="fas fa-ellipsis-h"></i> Bot escribiendo...
            </div>
        </div>
    `);

        // Scroll al fondo
        $('#chatTest').scrollTop($('#chatTest')[0].scrollHeight);

        // Enviar al bot
        $.post(API_URL + "/bot/test.php", {
            mensaje: mensaje
        }, function(response) {
            $('#typing').remove();

            if (response.success) {
                $('#chatTest').append(`
                <div class="direct-chat-msg">
                    <div class="direct-chat-text">
                        ${response.data.respuesta}
                    </div>
                    <small class="text-muted">
                        Tokens: ${response.data.tokens_usados} | 
                        Tiempo: ${response.data.tiempo_respuesta}ms
                    </small>
                </div>
            `);
            } else {
                $('#chatTest').append(`
                <div class="direct-chat-msg">
                    <div class="direct-chat-text bg-danger">
                        Error: ${response.message}
                    </div>
                </div>
            `);
            }

            $('#chatTest').scrollTop($('#chatTest')[0].scrollHeight);
        }).fail(function() {
            $('#typing').remove();
            $('#chatTest').append(`
            <div class="direct-chat-msg">
                <div class="direct-chat-text bg-danger">
                    Error de conexión con el servidor
                </div>
            </div>
        `);
        });
    }

    // Permitir enviar con Enter
    $('#mensajePrueba').on('keypress', function(e) {
        if (e.which === 13) {
            enviarMensajePrueba();
        }
    });

    // Función de test para verificar valores
    function testValues() {
        const values = {
            system_prompt: $('#system_prompt').val(),
            business_info: $('#business_info').val(),
            system_prompt_length: $('#system_prompt').val().length,
            business_info_length: $('#business_info').val().length,
            element_exists: {
                system_prompt: $('#system_prompt').length,
                business_info: $('#business_info').length
            }
        };

        console.log('=== TEST DE VALORES ===', values);
        alert('Revisa la consola para ver los valores actuales');
    }

    function verificarConfiguracion() {
        $.get(API_URL + "/bot/verificar-config.php", function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'Configuración Actual',
                    html: `
                    <div style="text-align: left;">
                        <p><strong>System Prompt:</strong> ${response.data.system_prompt_length} caracteres</p>
                        <p><strong>Info Negocio:</strong> ${response.data.business_info_length} caracteres</p>
                        <p><strong>Tiene emojis:</strong> ${response.data.tiene_emojis ? 'SÍ ✅' : 'NO ❌'}</p>
                        <p><strong>Bot activo:</strong> ${response.data.activo ? 'SÍ ✅' : 'NO ❌'}</p>
                        <p><strong>API Key:</strong> ${response.data.api_key_configurada ? 'Configurada ✅' : 'NO CONFIGURADA ❌'}</p>
                        <p><strong>Palabras activación:</strong> ${response.data.palabras_activacion}</p>
                        <p><strong>Horario configurado:</strong> ${response.data.horario_configurado ? 'SÍ ✅' : 'NO ❌'}</p>
                        <p><strong>Delay respuesta:</strong> ${response.data.delay_respuesta} segundos</p>
                        <p><strong>Responder a no registrados:</strong> ${response.data.responder_no_registrados ? 'SÍ ✅' : 'NO ❌'}</p>
                        <p><strong>Modelo IA:</strong> ${response.data.modelo_ai}</p>
                        <p><strong>Temperatura:</strong> ${response.data.temperatura}</p>
                    </div>
                `,
                    icon: 'info',
                    width: '600px'
                });
            }
        });
    }
</script>