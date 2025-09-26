<?php
$current_page = 'mensajes';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Obtener puerto de WhatsApp
$puerto = $whatsapp['puerto'] ?? 3001;

// Verificar estado de WhatsApp
$stmt = $pdo->prepare("SELECT estado FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$whatsapp = $stmt->fetch();
$whatsappConectado = $whatsapp && $whatsapp['estado'] == 'conectado';

// Obtener categorías
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE activo = 1 AND empresa_id = ? ORDER BY nombre");
$stmt->execute([$empresa_id]);
$categorias = $stmt->fetchAll();

// Obtener plantillas
$stmt = $pdo->prepare("SELECT * FROM plantillas_mensajes WHERE empresa_id = ? ORDER BY nombre");
$stmt->execute([$empresa_id]);
$plantillas = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Enviar Mensajes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Enviar Mensajes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if (!$whatsappConectado): ?>
                <div class="alert alert-warning">
                    <h5><i class="icon fas fa-exclamation-triangle"></i> WhatsApp no conectado</h5>
                    Para enviar mensajes, primero debes <a href="whatsapp.php">conectar WhatsApp</a>.
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Formulario de envío -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Crear Mensaje</h3>
                        </div>
                        <form id="formEnviarMensaje">
                            <div class="card-body">
                                <!-- Tipo de envío -->
                                <div class="form-group">
                                    <label>Enviar a:</label>
                                    <select class="form-control" id="tipoEnvio" name="tipo_envio" required>
                                        <option value="">-- Seleccionar --</option>
                                        <option value="individual">Individual (un contacto)</option>
                                        <option value="categoria">Por categoría</option>
                                        <option value="todos">TODOS los contactos</option>
                                    </select>
                                </div>

                                <!-- Selector de contacto individual -->
                                <div class="form-group" id="selectorContacto" style="display: none;">
                                    <label>Seleccionar contacto:</label>
                                    <select class="form-control select2" id="contactoId" name="contacto_id">
                                        <option value="">-- Buscar contacto --</option>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT id, nombre, numero FROM contactos WHERE activo = 1 AND empresa_id = ? ORDER BY nombre");
                                        $stmt->execute([$empresa_id]);
                                        $contactos = $stmt->fetchAll();
                                        foreach ($contactos as $contacto):
                                        ?>
                                            <option value="<?= $contacto['id'] ?>">
                                                <?= htmlspecialchars($contacto['nombre']) ?> - <?= htmlspecialchars($contacto['numero']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Selector de categoría -->
                                <div class="form-group" id="selectorCategoria" style="display: none;">
                                    <label>Seleccionar categoría:</label>
                                    <select class="form-control" id="categoriaId" name="categoria_id">
                                        <option value="">-- Seleccionar categoría --</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE categoria_id = ? AND activo = 1 AND empresa_id = ?");
                                            $stmt->execute([$cat['id'], $empresa_id]);
                                            $total = $stmt->fetchColumn();
                                            ?>
                                            <option value="<?= $cat['id'] ?>">
                                                <?= htmlspecialchars($cat['nombre']) ?> (<?= $total ?> contactos)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Plantillas -->
                                <div class="form-group">
                                    <label>Usar plantilla (opcional):</label>
                                    <select class="form-control" id="plantillaId">
                                        <option value="">-- Sin plantilla --</option>
                                        <?php foreach ($plantillas as $plantilla): ?>
                                            <option value="<?= $plantilla['id'] ?>" data-mensaje="<?= htmlspecialchars($plantilla['mensaje']) ?>">
                                                <?= htmlspecialchars($plantilla['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Mensaje -->
                                <div class="form-group">
                                    <label>Mensaje:</label>
                                    <textarea class="form-control" id="mensaje" name="mensaje" rows="6" required
                                        placeholder="Escribe tu mensaje aquí..."></textarea>
                                    <small class="form-text text-muted">
                                        Variables disponibles: {{nombreWhatsApp}}, {{nombre}}, {{categoria}}, {{fecha}}, {{hora}}
                                    </small>
                                </div>

                                <!-- Tipo de mensaje -->
                                <div class="form-group">
                                    <label>Tipo de mensaje:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_mensaje"
                                            id="tipoTexto" value="texto" checked>
                                        <label class="form-check-label" for="tipoTexto">
                                            Solo texto
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_mensaje"
                                            id="tipoImagen" value="imagen">
                                        <label class="form-check-label" for="tipoImagen">
                                            Imagen con texto
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_mensaje"
                                            id="tipoDocumento" value="documento">
                                        <label class="form-check-label" for="tipoDocumento">
                                            Documento
                                        </label>
                                    </div>
                                </div>

                                <!-- Archivo -->
                                <div class="form-group" id="selectorArchivo" style="display: none;">
                                    <label>Seleccionar archivo:</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="archivo" name="archivo"
                                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx">
                                        <label class="custom-file-label" for="archivo">Elegir archivo...</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Máximo 16MB. Formatos: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX
                                    </small>
                                </div>

                                <!-- Programar envío -->
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="programar" name="programar">
                                        <label class="custom-control-label" for="programar">
                                            Programar envío
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group" id="fechaProgramada" style="display: none;">
                                    <label>Fecha y hora de envío:</label>
                                    <input type="datetime-local" class="form-control" id="fechaEnvio" name="fecha_envio">
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" <?= !$whatsappConectado ? 'disabled' : '' ?>>
                                    <i class="fas fa-paper-plane"></i> Enviar Mensaje
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="guardarPlantilla()">
                                    <i class="fas fa-save"></i> Guardar como plantilla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Vista previa -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Vista Previa</h3>
                        </div>
                        <div class="card-body" style="background-color: #889f86;">
                            <!-- Mensaje de vista previa -->
                            <div id="previewArchivo" class="mt-3" style="display: none;">
                                <img id="previewImagen" src="" class="img-fluid" style="display: none;">
                                <div id="previewDocumento" class="text-center" style="display: none;">
                                    <i class="fas fa-file fa-3x"></i>
                                    <p class="mt-2" id="nombreArchivo"></p>
                                </div>
                            </div>

                            <div class="direct-chat-msg">
                                <div class="direct-chat-text bg-success" id="vistaPrevia">
                                    El mensaje aparecerá aquí...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de envío -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Información</h3>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-6">Destinatarios:</dt>
                                <dd class="col-sm-6" id="totalDestinatarios">0</dd>

                                <dt class="col-sm-6">Tiempo estimado:</dt>
                                <dd class="col-sm-6" id="tiempoEstimado">-</dd>
                            </dl>

                            <div class="alert alert-info">
                                <i class="icon fas fa-info"></i>
                                Los mensajes se envían con un delay aleatorio entre 3-8 segundos para evitar bloqueos.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal Guardar Plantilla -->
<div class="modal fade" id="modalGuardarPlantilla">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Guardar Plantilla</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formGuardarPlantilla">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nombre de la plantilla:</label>
                        <input type="text" class="form-control" id="nombrePlantilla" required>
                    </div>
                    <input type="hidden" id="mensajePlantilla">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<link rel="stylesheet" href="<?php echo asset('plugins/select2/css/select2.min.css'); ?>">
<script src="<?php echo asset('plugins/select2/js/select2.full.min.js'); ?>"></script>

<script>
    const WHATSAPP_API_URL = 'http://localhost:<?php echo $puerto; ?>';
    const API_KEY = 'mensajeroPro2025';

    $(document).ready(function() {
        // Inicializar Select2
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: 'Buscar contacto...',
            allowClear: true
        });

        $('#contactoId').on('change', function() {
            if ($('#tipoEnvio').val() === 'individual') {
                updateDestinatarios();
            }
        });

        // Cambio de tipo de envío
        $('#tipoEnvio').on('change', function() {
            const tipo = $(this).val();
            $('#selectorContacto, #selectorCategoria').hide();

            if (tipo === 'individual') {
                $('#selectorContacto').show();
            } else if (tipo === 'categoria') {
                $('#selectorCategoria').show();
            } else if (tipo === 'todos') {
                // No mostrar ningún selector adicional
            }

            // Llamar a updateDestinatarios después de mostrar/ocultar selectores
            updateDestinatarios();
        });

        // Cambio de categoría
        $('#categoriaId').on('change', updateDestinatarios);

        // Usar plantilla
        $('#plantillaId').on('change', function() {
            const mensaje = $(this).find(':selected').data('mensaje');
            if (mensaje) {
                $('#mensaje').val(mensaje);
                updatePreview();
            }
        });

        // Vista previa en tiempo real
        $('#mensaje').on('input', updatePreview);

        // Tipo de mensaje
        $('input[name="tipo_mensaje"]').on('change', function() {
            if ($(this).val() === 'texto') {
                $('#selectorArchivo').hide();
            } else {
                $('#selectorArchivo').show();
            }
        });

        // Archivo seleccionado
        $('#archivo').on('change', function() {
            const fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').text(fileName);

            // Preview
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();

                if (file.type.startsWith('image/')) {
                    reader.onload = function(e) {
                        $('#previewImagen').attr('src', e.target.result).show();
                        $('#previewDocumento').hide();
                        $('#previewArchivo').show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#previewImagen').hide();
                    $('#nombreArchivo').text(fileName);
                    $('#previewDocumento').show();
                    $('#previewArchivo').show();
                }
            }
        });

        // Programar envío
        $('#programar').on('change', function() {
            if ($(this).is(':checked')) {
                $('#fechaProgramada').show();

                // Obtener la hora actual del servidor PHP
                $.get(API_URL + '/server-time.php', function(response) {
                    if (response.success) {
                        // Usar la hora del servidor + 3 minutos
                        const serverTime = new Date(response.data.datetime);
                        serverTime.setMinutes(serverTime.getMinutes() + 3);

                        // Formatear para datetime-local
                        const year = serverTime.getFullYear();
                        const month = String(serverTime.getMonth() + 1).padStart(2, '0');
                        const day = String(serverTime.getDate()).padStart(2, '0');
                        const hours = String(serverTime.getHours()).padStart(2, '0');
                        const minutes = String(serverTime.getMinutes()).padStart(2, '0');

                        const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                        $('#fechaEnvio').attr('min', minDateTime);
                        $('#fechaEnvio').val(minDateTime);
                        $('#fechaEnvio').prop('required', true);

                        // Mostrar hora del servidor para debug
                        console.log('Hora del servidor:', response.data.time);
                        console.log('Fecha mínima establecida:', minDateTime);
                    } else {
                        // Fallback a hora del navegador si falla
                        const ahora = new Date();
                        ahora.setMinutes(ahora.getMinutes() + 3);

                        const year = ahora.getFullYear();
                        const month = String(ahora.getMonth() + 1).padStart(2, '0');
                        const day = String(ahora.getDate()).padStart(2, '0');
                        const hours = String(ahora.getHours()).padStart(2, '0');
                        const minutes = String(ahora.getMinutes()).padStart(2, '0');

                        const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                        $('#fechaEnvio').attr('min', minDateTime);
                        $('#fechaEnvio').val(minDateTime);
                        $('#fechaEnvio').prop('required', true);
                    }
                });
            } else {
                $('#fechaProgramada').hide();
                $('#fechaEnvio').prop('required', false);
                $('#fechaEnvio').val('');
            }
        });

        // Enviar mensaje
        $('#formEnviarMensaje').on('submit', async function(e) {
            e.preventDefault();

            const tipoEnvio = $('#tipoEnvio').val();
            const mensaje = $('#mensaje').val();
            const tipoMensaje = $('input[name="tipo_mensaje"]:checked').val();
            const programar = $('#programar').is(':checked');

            if (!tipoEnvio) {
                Swal.fire('Error', 'Selecciona a quién enviar', 'error');
                return;
            }

            // Validar fecha si está programado
            if (programar) {
                const fechaEnvio = $('#fechaEnvio').val();
                if (!fechaEnvio) {
                    Swal.fire('Error', 'Selecciona fecha y hora de envío', 'error');
                    return;
                }

                // Validar que la fecha sea futura (mínimo 5 minutos)
                const fechaSeleccionada = new Date(fechaEnvio);
                const ahora = new Date();
                const minimo = new Date(ahora.getTime() + 3 * 60000); // 3 minutos

                if (fechaSeleccionada <= minimo) {
                    Swal.fire('Error', 'La fecha debe ser al menos 4 minutos en el futuro', 'error');
                    return;
                }
            }

            // Confirmación
            const destinatarios = parseInt($('#totalDestinatarios').text()) || 0;
            const confirmResult = await Swal.fire({
                title: '¿Enviar mensaje?',
                html: `Se enviará a <b>${destinatarios}</b> contacto(s)`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: programar ? 'Programar' : 'Enviar ahora',
                cancelButtonText: 'Cancelar'
            });

            if (!confirmResult.isConfirmed) return;

            // Mostrar progreso
            Swal.fire({
                title: programar ? 'Programando...' : 'Enviando...',
                html: 'Por favor espera...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                let result;

                if (programar) {
                    // ENVÍO PROGRAMADO
                    const formData = new FormData($('#formEnviarMensaje')[0]);

                    // Agregar título automático
                    const ahora = new Date();
                    const titulo = `Mensaje programado - ${ahora.toLocaleDateString('es-PE')}`;
                    formData.append('titulo', titulo);

                    // Debug: ver qué se está enviando
                    console.log('=== DATOS ENVIADOS AL SERVIDOR ===');
                    console.log('Fecha del input:', $('#fechaEnvio').val());
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ':', pair[1]);
                    }

                    // Usar API de mensajes programados
                    const response = await fetch(API_URL + '/mensajes/programar.php', {
                        method: 'POST',
                        body: formData
                    });

                    result = await response.json();
                    console.log('Respuesta del servidor:', result);

                    if (!response.ok) {
                        throw new Error(result.message || 'Error al programar');
                    }

                } else if (tipoEnvio === 'individual') {
                    // Envío individual inmediato
                    result = await enviarIndividual();
                } else {
                    // Envío masivo inmediato (por categoría o todos)
                    result = await enviarMasivo();
                }

                if (result.success) {
                    const mensaje = programar ?
                        `Mensaje programado exitosamente. Se enviará a ${destinatarios} contacto(s) en la fecha indicada.` :
                        result.message || 'El mensaje se envió correctamente';

                    Swal.fire({
                        icon: 'success',
                        title: programar ? 'Mensaje Programado' : 'Mensaje Enviado',
                        text: mensaje
                    }).then(() => {
                        // Limpiar formulario completamente
                        $('#formEnviarMensaje')[0].reset();

                        // Resetear select2
                        $('#contactoId').val(null).trigger('change');

                        // Ocultar secciones
                        $('#selectorContacto, #selectorCategoria, #selectorArchivo, #fechaProgramada').hide();

                        // Limpiar preview
                        $('#vistaPrevia').text('El mensaje aparecerá aquí...');
                        $('#previewArchivo').hide();
                        $('#previewImagen').attr('src', '').hide();
                        $('#previewDocumento').hide();

                        // Resetear contadores
                        $('#totalDestinatarios').text('0');
                        $('#tiempoEstimado').text('-');

                        // Resetear label del archivo
                        $('.custom-file-label').text('Elegir archivo...');
                    });
                } else {
                    throw new Error(result.error || 'Error al enviar');
                }

            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        });
    });

    function updatePreview() {
        let mensaje = $('#mensaje').val() || 'El mensaje aparecerá aquí...';

        // Vista previa con datos de ejemplo
        const preview = mensaje
            .replace(/{{nombre}}/g, 'Juan Pérez')
            .replace(/{{nombreWhatsApp}}/g, 'Juan 🚀') // Ejemplo con emoji
            .replace(/{{whatsapp}}/g, 'Juan 🚀')
            .replace(/{{categoria}}/g, 'Plan Premium')
            .replace(/{{precio}}/g, 'S/. 99.00')
            .replace(/{{fecha}}/g, new Date().toLocaleDateString('es-PE'))
            .replace(/{{hora}}/g, new Date().toLocaleTimeString('es-PE', {
                hour: '2-digit',
                minute: '2-digit'
            }));

        $('#vistaPrevia').text(preview);
    }

    function updateDestinatarios() {
        const tipoEnvio = $('#tipoEnvio').val();
        let total = 0;

        if (tipoEnvio === 'individual') {
            total = $('#contactoId').val() ? 1 : 0;
            $('#totalDestinatarios').text(total);
            updateTiempoEstimado(total);
        } else if (tipoEnvio === 'categoria') {
            const selected = $('#categoriaId option:selected');
            if (selected.val()) {
                const match = selected.text().match(/\((\d+)/);
                if (match && match[1]) {
                    total = parseInt(match[1]) || 0;
                }
            }
            $('#totalDestinatarios').text(total);
            updateTiempoEstimado(total);
        } else if (tipoEnvio === 'todos') {
            // Mostrar cargando mientras obtiene el total
            $('#totalDestinatarios').html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: API_URL + '/contactos/count.php',
                method: 'GET',
                success: function(response) {
                    if (response.success && response.data) {
                        total = response.data.total || 0;
                        $('#totalDestinatarios').text(total);
                        updateTiempoEstimado(total);
                    } else {
                        $('#totalDestinatarios').text('0');
                        updateTiempoEstimado(0);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error obteniendo total de contactos:', error);
                    $('#totalDestinatarios').text('Error');
                    updateTiempoEstimado(0);
                }
            });
        } else {
            $('#totalDestinatarios').text(0);
            updateTiempoEstimado(0);
        }
    }

    function updateTiempoEstimado(total) {
        if (total === 0) {
            $('#tiempoEstimado').text('-');
            return;
        }

        // Promedio 5 segundos por mensaje
        const segundos = total * 5;
        const minutos = Math.ceil(segundos / 60);

        if (minutos < 60) {
            $('#tiempoEstimado').text(`~${minutos} minutos`);
        } else {
            const horas = Math.floor(minutos / 60);
            const mins = minutos % 60;
            $('#tiempoEstimado').text(`~${horas}h ${mins}m`);
        }
    }

    async function enviarIndividual() {
        const contactoId = $('#contactoId').val();
        if (!contactoId) {
            throw new Error('Selecciona un contacto');
        }

        // Obtener el texto y limpiarlo
        const contactoTexto = $('#contactoId option:selected').text().trim();
        console.log('Contacto seleccionado:', contactoTexto);

        // Extraer número
        let numero = '';
        const match = contactoTexto.match(/\-\s*(\+?\d+)$/);
        if (match) {
            numero = match[1];
        } else {
            const matchNumero = contactoTexto.match(/(\d{9,})$/);
            if (matchNumero) {
                numero = matchNumero[1];
            }
        }

        if (!numero) {
            throw new Error('No se pudo obtener el número del contacto');
        }

        console.log('Número extraído:', numero);

        const tipoMensaje = $('input[name="tipo_mensaje"]:checked').val();
        let mensaje = $('#mensaje').val();

        // IMPORTANTE: Detectar si el mensaje tiene variables para personalizar
        const tieneVariables = mensaje.includes('{{') && mensaje.includes('}}');

        // Si tiene variables pero NO es nombreWhatsApp, reemplazar las básicas del lado cliente
        if (tieneVariables) {
            // Obtener nombre del contacto para {{nombre}}
            const nombreContacto = contactoTexto.split(' - ')[0].trim();

            // Solo reemplazar variables que NO sean de WhatsApp (esas se reemplazan en el servidor)
            mensaje = mensaje.replace(/{{fecha}}/g, new Date().toLocaleDateString('es-PE'));
            mensaje = mensaje.replace(/{{hora}}/g, new Date().toLocaleTimeString('es-PE', {
                hour: '2-digit',
                minute: '2-digit'
            }));

            // Si NO tiene nombreWhatsApp, reemplazar {{nombre}} aquí
            if (!mensaje.includes('{{nombreWhatsApp}}') && !mensaje.includes('{{whatsapp}}')) {
                mensaje = mensaje.replace(/{{nombre}}/g, nombreContacto);
            }

            // Las variables {{nombreWhatsApp}} y {{whatsapp}} se reemplazarán en el servidor
        }

        try {
            let result;

            if (tipoMensaje === 'texto') {
                const response = await fetch(`${WHATSAPP_API_URL}/api/send`, {
                    method: 'POST',
                    headers: {
                        'X-API-Key': API_KEY,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        numero: numero,
                        mensaje: mensaje,
                        contacto_id: contactoId, // Enviar el ID para obtener más datos si es necesario
                        tiene_variables: tieneVariables // Indicar si tiene variables para procesar
                    })
                });

                result = await response.json();

                if (!response.ok) {
                    throw new Error(result.error || 'Error en el servidor');
                }

            } else {
                // Envío con archivo
                const archivo = $('#archivo')[0].files[0];
                if (!archivo) {
                    throw new Error('Selecciona un archivo');
                }

                const formData = new FormData();
                formData.append('numero', numero);
                formData.append('mensaje', mensaje || '');
                formData.append('archivo', archivo);
                formData.append('tipo', tipoMensaje);
                formData.append('contacto_id', contactoId);
                formData.append('tiene_variables', tieneVariables);

                const response = await fetch(`${WHATSAPP_API_URL}/api/send-media`, {
                    method: 'POST',
                    headers: {
                        'X-API-Key': API_KEY
                    },
                    body: formData
                });

                result = await response.json();

                if (!response.ok) {
                    throw new Error(result.error || 'Error en el servidor');
                }
            }

            // Guardar en historial
            if (result.success) {
                try {
                    await fetch(API_URL + '/contactos/guardar-individual.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            contacto_id: contactoId,
                            mensaje: mensaje
                        })
                    });
                } catch (error) {
                    console.error('Error guardando en historial:', error);
                }
            }

            return result;

        } catch (error) {
            console.error('Error en enviarIndividual:', error);
            throw error;
        }
    }

    async function enviarMasivo() {
        const tipoEnvio = $('#tipoEnvio').val();
        const categoriaId = tipoEnvio === 'categoria' ? $('#categoriaId').val() : null;
        const tipoMensaje = $('input[name="tipo_mensaje"]:checked').val();
        const mensaje = $('#mensaje').val();

        if (!mensaje) {
            throw new Error('El mensaje está vacío');
        }

        const formData = new FormData();
        formData.append('categoria_id', categoriaId || '');
        formData.append('mensaje', mensaje);
        formData.append('tipo', tipoMensaje);

        // Si hay archivo, agregarlo
        if (tipoMensaje !== 'texto') {
            const archivo = $('#archivo')[0].files[0];
            if (!archivo) {
                throw new Error('Selecciona un archivo');
            }
            formData.append('archivo', archivo);
        }

        // Debug: ver qué se está enviando
        console.log('Enviando:', {
            categoria_id: categoriaId,
            mensaje: mensaje,
            tipo: tipoMensaje,
            tieneArchivo: tipoMensaje !== 'texto'
        });

        const response = await fetch(`${WHATSAPP_API_URL}/api/send/bulk`, {
            method: 'POST',
            headers: {
                'X-API-Key': API_KEY
                // NO incluir Content-Type con FormData
            },
            body: formData
        });

        const result = await response.json();
        console.log('Respuesta:', result);

        if (!response.ok) {
            throw new Error(result.error || 'Error en el servidor');
        }

        return result;
    }

    function guardarPlantilla() {
        const mensaje = $('#mensaje').val();
        if (!mensaje) {
            Swal.fire('Error', 'Escribe un mensaje primero', 'error');
            return;
        }

        $('#mensajePlantilla').val(mensaje);
        $('#modalGuardarPlantilla').modal('show');
    }

    // Guardar plantilla
    $('#formGuardarPlantilla').on('submit', function(e) {
        e.preventDefault();

        $.post(API_URL + '/plantillas/crear.php', {
            nombre: $('#nombrePlantilla').val(),
            mensaje: $('#mensajePlantilla').val()
        }, function(response) {
            if (response.success) {
                $('#modalGuardarPlantilla').modal('hide');
                Swal.fire('Guardado', 'Plantilla guardada exitosamente', 'success');
                location.reload();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    });
</script>