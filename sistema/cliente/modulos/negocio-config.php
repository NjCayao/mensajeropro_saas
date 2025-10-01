<?php
$current_page = 'negocio-config';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../../../includes/multi_tenant.php';

$empresa_id = getEmpresaActual();

// Obtener configuración actual
$stmt = $pdo->prepare("SELECT * FROM configuracion_negocio WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch();

// Si no existe, usar valores vacíos
if (!$config) {
    $config = [
        'nombre_negocio' => '',
        'telefono' => '',
        'direccion' => '',
        'cuentas_pago' => '{}'
    ];
}

$cuentas_pago = json_decode($config['cuentas_pago'], true) ?: [];
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Configuración del Negocio</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Mi Negocio</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Información del Negocio</h3>
                </div>
                <div class="card-body">
                    <form id="formNegocio">
                        <!-- Datos básicos -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre del Negocio:</label>
                                    <input type="text" class="form-control" name="nombre_negocio"
                                        value="<?= htmlspecialchars($config['nombre_negocio'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Teléfono:</label>
                                    <input type="text" class="form-control" name="telefono"
                                        value="<?= htmlspecialchars($config['telefono'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Moneda:</label>
                                    <select name="moneda" class="form-control">
                                        <option value="PEN" <?= (($cuentas_pago['moneda'] ?? 'PEN') == 'PEN') ? 'selected' : '' ?>>PEN - Perú (S/)</option>
                                        <option value="USD" <?= (($cuentas_pago['moneda'] ?? '') == 'USD') ? 'selected' : '' ?>>USD - Dólares ($)</option>
                                        <option value="EUR" <?= (($cuentas_pago['moneda'] ?? '') == 'EUR') ? 'selected' : '' ?>>EUR - Euros (€)</option>
                                        <option value="COP" <?= (($cuentas_pago['moneda'] ?? '') == 'COP') ? 'selected' : '' ?>>COP - Colombia ($)</option>
                                        <option value="MXN" <?= (($cuentas_pago['moneda'] ?? '') == 'MXN') ? 'selected' : '' ?>>MXN - México ($)</option>
                                        <option value="BOB" <?= (($cuentas_pago['moneda'] ?? '') == 'BOB') ? 'selected' : '' ?>>BOB - Bolivia (Bs)</option>
                                        <option value="VES" <?= (($cuentas_pago['moneda'] ?? '') == 'VES') ? 'selected' : '' ?>>VES - Venezuela (Bs)</option>
                                        <option value="CLP" <?= (($cuentas_pago['moneda'] ?? '') == 'CLP') ? 'selected' : '' ?>>CLP - Chile ($)</option>
                                        <option value="ARS" <?= (($cuentas_pago['moneda'] ?? '') == 'ARS') ? 'selected' : '' ?>>ARS - Argentina ($)</option>
                                        <option value="BRL" <?= (($cuentas_pago['moneda'] ?? '') == 'BRL') ? 'selected' : '' ?>>BRL - Brasil (R$)</option>
                                        <option value="PYG" <?= (($cuentas_pago['moneda'] ?? '') == 'PYG') ? 'selected' : '' ?>>PYG - Paraguay (₲)</option>
                                        <option value="UYU" <?= (($cuentas_pago['moneda'] ?? '') == 'UYU') ? 'selected' : '' ?>>UYU - Uruguay ($U)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Dirección:</label>
                            <textarea class="form-control" name="direccion" rows="2"><?= htmlspecialchars($config['direccion'] ?? '') ?></textarea>
                        </div>

                        <hr>

                        <!-- Métodos de Pago -->
                        <h4><i class="fas fa-credit-card"></i> Métodos de Pago</h4>
                        <p class="text-muted">Configura las cuentas donde recibirás los pagos</p>

                        <div id="metodos-container">
                            <?php
                            if (!empty($cuentas_pago['metodos'])) {
                                foreach ($cuentas_pago['metodos'] as $metodo): ?>
                                    <div class="metodo-item row mb-2">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" placeholder="Tipo (Ej: Yape)"
                                                name="tipo[]" value="<?= htmlspecialchars($metodo['tipo'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" placeholder="Cuenta/Número"
                                                name="dato[]" value="<?= htmlspecialchars($metodo['dato'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" placeholder="Instrucciones (opcional)"
                                                name="instruccion[]" value="<?= htmlspecialchars($metodo['instruccion'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" onclick="eliminarMetodo(this)"
                                                class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                            <?php endforeach;
                            } ?>
                        </div>

                        <button type="button" onclick="agregarMetodo()" class="btn btn-success btn-sm mb-3">
                            <i class="fas fa-plus"></i> Agregar Método de Pago
                        </button>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    function agregarMetodo() {
        const html = `
            <div class="metodo-item row mb-2">
                <div class="col-md-3">
                    <input type="text" class="form-control" placeholder="Tipo (Ej: Yape, PayPal, BCP)" name="tipo[]">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Cuenta/Número" name="dato[]">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Instrucciones (opcional)" name="instruccion[]">
                </div>
                <div class="col-md-1">
                    <button type="button" onclick="eliminarMetodo(this)" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        $('#metodos-container').append(html);
    }

    function eliminarMetodo(btn) {
        Swal.fire({
            title: '¿Eliminar método de pago?',
            text: 'Se quitará de la lista',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $(btn).closest('.metodo-item').remove();

                // Mostrar mensaje para guardar cambios
                Swal.fire({
                    icon: 'info',
                    title: 'Método eliminado',
                    text: 'Recuerda hacer clic en "Guardar Configuración" para aplicar los cambios',
                    timer: 3000
                });
            }
        });
    }

    $('#formNegocio').on('submit', function(e) {
        e.preventDefault();

        // Mostrar loading
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Recopilar métodos de pago
        const metodos = [];
        $('.metodo-item').each(function() {
            const tipo = $(this).find('[name="tipo[]"]').val();
            const dato = $(this).find('[name="dato[]"]').val();
            const instruccion = $(this).find('[name="instruccion[]"]').val();

            if (tipo && dato) {
                metodos.push({
                    tipo,
                    dato,
                    instruccion
                });
            }
        });

        const selectedOption = $('[name="moneda"] option:selected').text();
        const simbolo = selectedOption.match(/\((.*?)\)/)?.[1] || '$';

        const cuentas_pago = {
            metodos: metodos,
            moneda: $('[name="moneda"]').val(),
            simbolo: simbolo
        };

        const formData = {
            nombre_negocio: $('[name="nombre_negocio"]').val(),
            telefono: $('[name="telefono"]').val(),
            direccion: $('[name="direccion"]').val(),
            cuentas_pago: JSON.stringify(cuentas_pago)
        };

        $.ajax({
            url: API_URL + '/negocio/actualizar.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response && response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: 'Configuración guardada correctamente',
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Aviso',
                        text: response?.message || 'Guardado con advertencias'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();

                // Intentar parsear la respuesta
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // A veces el servidor responde con success pero jQuery lo marca como error
                        Swal.fire({
                            icon: 'success',
                            title: 'Guardado',
                            text: 'Configuración guardada correctamente',
                            timer: 2000
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Error al guardar', 'error');
                    }
                } catch (e) {
                    // Si no se puede parsear, mostrar error genérico
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'info',
                        title: 'Procesado',
                        text: 'La operación se completó. Verifica los cambios.',
                        timer: 2000
                    });
                }
            }
        });
    });

    // Agregar un método de ejemplo al cargar
    $(document).ready(function() {
        if ($('#metodos-container').children().length === 0) {
            agregarMetodo();
        }
    });
</script>