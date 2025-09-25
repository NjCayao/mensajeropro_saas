
<?php
$current_page = 'whatsapp';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$empresa_id = getEmpresaActual();

// Verificar si existe registro en whatsapp_sesiones_empresa
$stmt = $pdo->prepare("SELECT * FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$whatsapp = $stmt->fetch();

// Si no existe, crear registro
if (!$whatsapp) {
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_sesiones_empresa (empresa_id, estado, puerto, fecha_creacion) 
        VALUES (?, 'desconectado', ?, NOW())
    ");
    
    // Asignar puerto base + empresa_id
    $puerto = 3000 + $empresa_id;
    $stmt->execute([$empresa_id, $puerto]);
    
    // Volver a obtener el registro
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $whatsapp = $stmt->fetch();
}

$whatsappConectado = $whatsapp && $whatsapp['estado'] == 'conectado';
$puerto = $whatsapp['puerto'] ?? 3001;
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Conexión WhatsApp</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="app.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">WhatsApp</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Primera fila - Estado y Control -->
            <div class="row">
                <!-- Estado de conexión -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Estado de Conexión</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" onclick="checkStatus()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="statusContainer" class="text-center">
                                <i class="fas fa-spinner fa-spin fa-3x"></i>
                                <p class="mt-2">Verificando estado...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Control del servicio -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Control del Servicio</h3>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <div class="btn-group-vertical w-100" id="controlButtons">
                                    <button class="btn btn-success btn-lg" onclick="iniciarServicio()" id="btnIniciar">
                                        <i class="fas fa-play"></i> Iniciar WhatsApp Service
                                    </button>
                                    <button class="btn btn-danger btn-lg" onclick="detenerServicio()" id="btnDetener" style="display: none;">
                                        <i class="fas fa-stop"></i> Detener WhatsApp Service
                                    </button>
                                </div>
                                <p class="text-muted mt-2 small">
                                    El servicio se ejecutará en segundo plano
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Segunda fila - QR e Información -->
            <div class="row mt-3">
                <!-- QR Code -->
                <div class="col-md-6" id="qrContainer" style="display: none;">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h3 class="card-title text-dark">
                                <i class="fas fa-qrcode"></i> Escanear código QR
                            </h3>
                        </div>
                        <div class="card-body text-center">
                            <div id="qrcode" class="mb-3"></div>
                            <div class="alert alert-info">
                                <ol class="mb-0 text-left">
                                    <li>Abre WhatsApp en tu teléfono</li>
                                    <li>Toca <strong>Menú</strong> o <strong>Configuración</strong></li>
                                    <li>Selecciona <strong>Dispositivos vinculados</strong></li>
                                    <li>Toca <strong>Vincular dispositivo</strong></li>
                                    <li>Escanea el código</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de conexión -->
                <div class="col-md-6" id="infoContainer" style="display: none;">
                    <div class="card">
                        <div class="card-header bg-success">
                            <h3 class="card-title text-white">
                                <i class="fas fa-check-circle"></i> Cuenta Conectada
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <th width="40%">Número:</th>
                                        <td><span id="numeroConectado" class="font-weight-bold">-</span></td>
                                    </tr>
                                    <tr>
                                        <th>Nombre:</th>
                                        <td><span id="nombreConectado">-</span></td>
                                    </tr>
                                    <tr>
                                        <th>Plataforma:</th>
                                        <td><span id="plataformaConectada" class="badge badge-info">-</span></td>
                                    </tr>
                                    <tr>
                                        <th>Estado:</th>
                                        <td><span class="badge badge-success">Activo</span></td>
                                    </tr>
                                    <tr>
                                        <th>Conectado desde:</th>
                                        <td><span id="tiempoConectado">-</span></td>
                                    </tr>
                                    <tr>
                                        <th>Última actualización:</th>
                                        <td><span id="ultimaActualizacion">-</span></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="mt-3">
                                <button class="btn btn-warning btn-block" onclick="desconectar()">
                                    <i class="fas fa-sign-out-alt"></i> Desconectar WhatsApp
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tercera fila - Estadísticas (solo cuando está conectado) -->
            <div class="row mt-3" id="statsContainer" style="display: none;">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 id="statMensajesHoy">0</h3>
                            <p>Mensajes Hoy</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comments"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 id="statEnviados">0</h3>
                            <p>Enviados</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 id="statPendientes">0</h3>
                            <p>En Cola</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3 id="statErrores">0</h3>
                            <p>Errores</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrucciones -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <h5><i class="icon fas fa-info"></i> Importante:</h5>
                        <ul class="mb-0">
                            <li>El servicio se ejecuta en segundo plano</li>
                            <li>Mantén WhatsApp abierto en tu teléfono</li>
                            <li>No cierres la sesión desde el teléfono</li>
                            <li>Los mensajes se envían con delay automático para evitar bloqueos</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const WHATSAPP_API_URL = 'http://localhost:<?php echo $puerto; ?>';
    console.log('Puerto configurado:', <?php echo $puerto; ?>);
    console.log('API URL:', WHATSAPP_API_URL);
    const API_KEY = 'mensajeroPro2025';

    let checkInterval = null;
    let conectadoDesde = null;

    // Verificar estado al cargar
    $(document).ready(function() {
        verificarServicio();
    });

    async function verificarServicio() {
        const dbResponse = await fetch(API_URL + '/whatsapp/status.php');
        const dbData = await dbResponse.json();

        if (dbData.success && dbData.data.estado === 'desconectado') {
            mostrarServicioNoIniciado();
            return;
        }
        try {
            const response = await fetch(`${WHATSAPP_API_URL}/api/status`, {
                headers: {
                    'X-API-Key': API_KEY
                }
            });

            if (response.ok) {
                $('#btnIniciar').hide();
                $('#btnDetener').show();
                checkStatus();

                if (!checkInterval) {
                    checkInterval = setInterval(checkStatus, 5000);
                }
            } else {
                mostrarServicioNoIniciado();
            }
        } catch (error) {
            mostrarServicioNoIniciado();
        }
    }

    function mostrarServicioNoIniciado() {
        $('#statusContainer').html(`
            <i class="fas fa-power-off fa-3x text-secondary"></i>
            <h4 class="mt-2">Servicio No Iniciado</h4>
            <p>Haz clic en "Iniciar WhatsApp Service"</p>
        `);

        $('#btnIniciar').show();
        $('#btnDetener').hide();
        $('#qrContainer').hide();
        $('#infoContainer').hide();
        $('#statsContainer').hide();
    }

    async function checkStatus() {
        try {
            const response = await fetch(`${WHATSAPP_API_URL}/api/status`, {
                headers: {
                    'X-API-Key': API_KEY
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    updateUI(result.data);
                }
            } else {
                mostrarServicioNoIniciado();
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function updateUI(status) {
        if (status.connected) {
            $('#statusContainer').html(`
                <i class="fas fa-check-circle text-success fa-4x"></i>
                <h4 class="mt-2">WhatsApp Conectado</h4>
                <p class="text-success">Sistema listo para enviar mensajes</p>
            `);

            $('#qrContainer').hide();
            $('#infoContainer').show();
            $('#statsContainer').show();

            if (status.info) {
                $('#numeroConectado').text('+' + status.info.number);
                $('#nombreConectado').text(status.info.pushname || 'Sin nombre');
                $('#plataformaConectada').text(status.info.platform || 'Web');
            } else {
                $('#numeroConectado').text('No identificado');
                $('#nombreConectado').text('No identificado');
                $('#plataformaConectada').text('WPPConnect');
            }

            if (!conectadoDesde) {
                conectadoDesde = new Date();
            }
            actualizarTiempoConectado();

            $('#ultimaActualizacion').text(new Date().toLocaleString('es-PE'));
            cargarEstadisticas();

        } else {
            $('#statusContainer').html(`
                <i class="fas fa-qrcode fa-3x text-warning"></i>
                <h4 class="mt-2">Esperando Conexión</h4>
                <p>Escanea el código QR para conectar</p>
            `);

            $('#qrContainer').show();
            $('#infoContainer').hide();
            $('#statsContainer').hide();

            setTimeout(() => {
                getQRCode();
            }, 2000);
        }

        if (!status.connected && $('#infoContainer').is(':visible')) {
            conectadoDesde = null;
        } else if (status.connected && $('#qrContainer').is(':visible')) {
            setTimeout(() => location.reload(), 1000);
        }
    }

    async function getQRCode() {
        try {
            const response = await fetch(`${WHATSAPP_API_URL}/api/qr`, {
                headers: {
                    'X-API-Key': API_KEY
                }
            });

            const result = await response.json();

            if (result.success && result.qr) {
                document.getElementById('qrcode').innerHTML = '';

                if (result.qr.startsWith('data:image')) {
                    const img = document.createElement('img');
                    img.src = result.qr;
                    img.style.maxWidth = '280px';
                    document.getElementById('qrcode').appendChild(img);
                } else {
                    QRCode.toCanvas(result.qr, {
                        width: 280,
                        margin: 2
                    }, function(err, canvas) {
                        if (!err) {
                            document.getElementById('qrcode').appendChild(canvas);
                        }
                    });
                }
            } else {
                setTimeout(getQRCode, 2000);
            }
        } catch (error) {
            console.error('Error obteniendo QR:', error);
            setTimeout(getQRCode, 2000);
        }
    }

    async function iniciarServicio() {
        let timerInterval;
        let progress = 0;

        Swal.fire({
            title: 'Iniciando WhatsApp Service',
            html: `
            <div class="mb-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         role="progressbar" 
                         style="width: 0%"
                         id="progress-bar">
                        0%
                    </div>
                </div>
            </div>
            <div id="progress-status">Preparando servicio...</div>
            <div class="text-muted small mt-2">Esto tomará aproximadamente 30 segundos</div>
        `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                // Iniciar petición al backend
                fetch(API_URL + '/whatsapp/control-servicio.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'accion=iniciar'
                    }).then(response => response.json())
                    .then(result => {
                        if (!result.success && result.message) {
                            clearInterval(timerInterval);
                            Swal.fire('Error', result.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error al iniciar servicio:', error);
                    });

                // Timer para la barra de progreso
                timerInterval = setInterval(() => {
                    progress += 3.33; // Incremento para llegar a 100% en 30 segundos

                    const progressBar = document.getElementById('progress-bar');
                    const progressStatus = document.getElementById('progress-status');

                    if (progressBar && progressStatus) {
                        progressBar.style.width = Math.min(progress, 100) + '%';
                        progressBar.textContent = Math.round(Math.min(progress, 100)) + '%';

                        // Cambiar mensajes según el progreso
                        if (progress < 20) {
                            progressStatus.innerHTML = '<i class="fas fa-server"></i> Iniciando servicio Node.js...';
                        } else if (progress < 40) {
                            progressStatus.innerHTML = '<i class="fas fa-globe"></i> Cargando WhatsApp Web...';
                        } else if (progress < 60) {
                            progressStatus.innerHTML = '<i class="fas fa-wifi"></i> Conectando con servidores de WhatsApp...';
                        } else if (progress < 80) {
                            progressStatus.innerHTML = '<i class="fas fa-qrcode"></i> Generando código QR...';
                        } else {
                            progressStatus.innerHTML = '<i class="fas fa-cog fa-spin"></i> Finalizando configuración...';
                        }
                    }

                    // Cuando llega al 100%, recargar la página
                    if (progress >= 100) {
                        clearInterval(timerInterval);

                        if (progressStatus) {
                            progressStatus.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ¡Servicio iniciado correctamente!</span>';
                        }

                        // Forzar recarga después de 1 segundo
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 1000);
                    }
                }, 1000); // Actualizar cada segundo
            },
            willClose: () => {
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
            }
        });
    }

    async function detenerServicio() {
        const confirm = await Swal.fire({
            title: '¿Detener servicio?',
            text: 'Se cerrará completamente WhatsApp',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, detener',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            const response = await fetch(API_URL + '/whatsapp/control-servicio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'accion=detener'
            });

            const result = await response.json();

            if (result.success) {
                showToast('success', 'Servicio detenido');
                conectadoDesde = null;

                if (checkInterval) {
                    clearInterval(checkInterval);
                    checkInterval = null;
                }

                mostrarServicioNoIniciado();
            }
        } catch (error) {
            Swal.fire('Error', 'No se pudo detener el servicio', 'error');
        }
    }

    async function desconectar() {
        const confirm = await Swal.fire({
            title: '¿Desconectar WhatsApp?',
            text: 'Tendrás que escanear el QR nuevamente',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desconectar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            await fetch(`${WHATSAPP_API_URL}/api/disconnect`, {
                method: 'POST',
                headers: {
                    'X-API-Key': API_KEY
                }
            });

            showToast('info', 'WhatsApp desconectado');
            conectadoDesde = null;
            checkStatus();
        } catch (error) {
            Swal.fire('Error', 'No se pudo desconectar', 'error');
        }
    }

    function actualizarTiempoConectado() {
        if (conectadoDesde) {
            const ahora = new Date();
            const diff = ahora - conectadoDesde;
            const horas = Math.floor(diff / 3600000);
            const minutos = Math.floor((diff % 3600000) / 60000);

            $('#tiempoConectado').text(`${horas}h ${minutos}m`);
        }
    }

    async function cargarEstadisticas() {
        try {
            const response = await fetch(API_URL + '/whatsapp/estadisticas.php');
            const stats = await response.json();

            if (stats.success) {
                $('#statMensajesHoy').text(stats.data.mensajes_hoy || 0);
                $('#statEnviados').text(stats.data.enviados || 0);
                $('#statPendientes').text(stats.data.pendientes || 0);
                $('#statErrores').text(stats.data.errores || 0);
            }
        } catch (error) {
            console.error('Error cargando estadísticas:', error);
        }
    }

    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Actualizar tiempo conectado cada minuto
    setInterval(actualizarTiempoConectado, 60000);

    // Limpiar al salir
    $(window).on('beforeunload', function() {
        if (checkInterval) {
            clearInterval(checkInterval);
        }
    });
</script>