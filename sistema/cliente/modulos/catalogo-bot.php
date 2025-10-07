<?php
$current_page = 'catalogo-bot';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../../../includes/plan-limits.php';
verificarAccesoModulo('catalogo-bot');

$empresa_id = getEmpresaActual();

// Obtener catálogo actual
$stmt = $pdo->prepare("SELECT * FROM catalogo_bot WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$catalogo = $stmt->fetch();

// Decodificar datos JSON si existen
$datos_catalogo = [];
if ($catalogo && $catalogo['datos_json']) {
    $datos_catalogo = json_decode($catalogo['datos_json'], true);
}
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Catálogo del Bot de Ventas</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/dashboard'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('cliente/bot-config'); ?>">Bot IA</a></li>
                        <li class="breadcrumb-item active">Catálogo</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Alerta informativa -->
            <div class="alert alert-info">
                <h5><i class="icon fas fa-info"></i> Importante!</h5>
                <ul class="mb-0">
                    <li>El archivo Excel debe tener 3 hojas: PRODUCTOS, PROMOCIONES y DELIVERY</li>
                    <li>El PDF será el catálogo visual que el bot enviará a los clientes</li>
                    <li>Actualizar el catálogo reemplaza toda la información anterior</li>
                    <li>El bot usará esta información para responder preguntas sobre precios y productos</li>
                </ul>
            </div>

            <!-- Tarjeta de carga de archivos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-upload"></i> Cargar/Actualizar Catálogo
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Sección Excel -->
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-excel"></i> Catálogo Excel
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form id="formExcel" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label>Archivo Excel con productos:</label>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="archivo_excel"
                                                    name="archivo_excel" accept=".xlsx,.xls">
                                                <label class="custom-file-label" for="archivo_excel">
                                                    Seleccionar Excel...
                                                </label>
                                            </div>
                                            <?php if ($catalogo && $catalogo['archivo_excel']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-check"></i> Archivo actual: <?= basename($catalogo['archivo_excel']) ?>
                                                    <br>Última actualización: <?= date('d/m/Y H:i', strtotime($catalogo['fecha_actualizacion'])) ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-danger">
                                                    <i class="fas fa-times"></i> No hay archivo Excel cargado
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <button type="submit" class="btn btn-success btn-block">
                                            <i class="fas fa-upload"></i> Subir Excel
                                        </button>

                                        <?php if ($catalogo && $catalogo['archivo_excel']): ?>
                                            <a href="<?= url('api/v1/bot/descargar-catalogo?tipo=excel') ?>"
                                                class="btn btn-outline-success btn-block mt-2">
                                                <i class="fas fa-download"></i> Descargar Excel Actual
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Sección PDF -->
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-pdf"></i> Catálogo Visual (PDF)
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form id="formPDF" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label>Catálogo PDF (opcional):</label>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="archivo_pdf"
                                                    name="archivo_pdf" accept=".pdf">
                                                <label class="custom-file-label" for="archivo_pdf">
                                                    Seleccionar PDF...
                                                </label>
                                            </div>
                                            <?php if ($catalogo && $catalogo['archivo_pdf']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-check"></i> Archivo actual: <?= basename($catalogo['archivo_pdf']) ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle"></i> El bot enviará este PDF cuando pidan el catálogo
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <button type="submit" class="btn btn-danger btn-block">
                                            <i class="fas fa-upload"></i> Subir PDF
                                        </button>

                                        <?php if ($catalogo && $catalogo['archivo_pdf']): ?>
                                            <a href="<?= url('api/v1/bot/descargar-catalogo?tipo=pdf') ?>"
                                                class="btn btn-outline-danger btn-block mt-2" target="_blank">
                                                <i class="fas fa-eye"></i> Ver PDF Actual
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón plantilla -->
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-info" onclick="descargarPlantilla()">
                            <i class="fas fa-file-download"></i> Descargar Plantilla Excel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vista previa del catálogo -->
            <?php if ($catalogo && !empty($datos_catalogo)): ?>
                <div class="row mt-4">
                    <!-- Productos -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-primary">
                                <h3 class="card-title">
                                    <i class="fas fa-box"></i> Productos (<?= count($datos_catalogo['productos'] ?? []) ?>)
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($datos_catalogo['productos'])): ?>
                                    <?php
                                    // Paginación para productos
                                    $productos_por_pagina = 10;
                                    $total_productos = count($datos_catalogo['productos']);
                                    $productos_mostrar = array_slice($datos_catalogo['productos'], 0, $productos_por_pagina);
                                    ?>
                                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Categoría</th>
                                                    <th>Producto</th>
                                                    <th>Precio</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productos-tbody">
                                                <?php foreach ($productos_mostrar as $producto): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($producto['categoria'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($producto['producto'] ?? '') ?></td>
                                                        <td>S/ <?= number_format($producto['precio'] ?? 0, 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if ($total_productos > $productos_por_pagina): ?>
                                        <div class="text-center mt-2">
                                            <button class="btn btn-sm btn-primary" onclick="verTodosProductos()">
                                                Ver todos (<?= $total_productos ?> productos)
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Modal para ver todos los productos -->
                                    <div class="modal fade" id="modalProductos" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Todos los Productos (<?= $total_productos ?>)</h5>
                                                    <button type="button" class="close" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="text" class="form-control mb-3" id="buscarProducto"
                                                        placeholder="Buscar producto...">
                                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                                        <table class="table table-sm" id="tablaProductosCompleta">
                                                            <thead>
                                                                <tr>
                                                                    <th>Categoría</th>
                                                                    <th>Producto</th>
                                                                    <th>Precio</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($datos_catalogo['productos'] as $producto): ?>
                                                                    <tr>
                                                                        <td><?= htmlspecialchars($producto['categoria'] ?? '') ?></td>
                                                                        <td><?= htmlspecialchars($producto['producto'] ?? '') ?></td>
                                                                        <td>S/ <?= number_format($producto['precio'] ?? 0, 2) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No hay productos cargados</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Promociones -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success">
                                <h3 class="card-title">
                                    <i class="fas fa-tags"></i> Promociones (<?= count($datos_catalogo['promociones'] ?? []) ?>)
                                </h3>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (!empty($datos_catalogo['promociones'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Tipo</th>
                                                    <th>Precio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($datos_catalogo['promociones'] as $promo): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($promo['producto'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($promo['tipo'] ?? '') ?></td>
                                                        <td>S/ <?= number_format($promo['precio_promo'] ?? 0, 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No hay promociones activas</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h3 class="card-title">
                                    <i class="fas fa-truck"></i> Zonas de Delivery
                                </h3>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (!empty($datos_catalogo['delivery']['zonas'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Zona</th>
                                                    <th>Costo</th>
                                                    <th>Tiempo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($datos_catalogo['delivery']['zonas'] as $zona): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($zona['zona'] ?? '') ?></td>
                                                        <td>S/ <?= number_format($zona['costo'] ?? 0, 2) ?></td>
                                                        <td><?= htmlspecialchars($zona['tiempo'] ?? '') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if (isset($datos_catalogo['delivery']['gratis_desde'])): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle"></i>
                                            Delivery gratis en compras desde:
                                            <strong>S/ <?= number_format($datos_catalogo['delivery']['gratis_desde'], 2) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted">No hay zonas de delivery configuradas</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="alert alert-success">
                            <h5><i class="icon fas fa-check"></i> Catálogo Activo</h5>
                            <p class="mb-0">
                                Última actualización: <?= date('d/m/Y H:i', strtotime($catalogo['fecha_actualizacion'])) ?>
                                <br>
                                El bot está usando esta información para responder consultas sobre productos y precios.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
    // Actualizar nombre del archivo seleccionado
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });

    // Manejar envío del formulario Excel
    $('#formExcel').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const btnSubmit = $(this).find('button[type="submit"]');
        const textOriginal = btnSubmit.html();

        // Validar que se haya seleccionado archivo
        if (!$('#archivo_excel')[0].files[0]) {
            Swal.fire('Error', 'Debes seleccionar un archivo Excel', 'error');
            return;
        }

        // Mostrar loading
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Subiendo...');

        $.ajax({
            url: API_URL + '/bot/subir-catalogo',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btnSubmit.prop('disabled', false).html(textOriginal);

                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Excel cargado',
                        text: `Se procesaron ${response.data.productos} productos`,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                btnSubmit.prop('disabled', false).html(textOriginal);
                Swal.fire('Error', xhr.responseJSON?.message || 'Error al cargar el archivo', 'error');
            }
        });
    });

    // Manejar envío del formulario PDF
    $('#formPDF').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const btnSubmit = $(this).find('button[type="submit"]');
        const textOriginal = btnSubmit.html();

        // Validar que se haya seleccionado archivo
        if (!$('#archivo_pdf')[0].files[0]) {
            Swal.fire('Error', 'Debes seleccionar un archivo PDF', 'error');
            return;
        }

        // Mostrar loading
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Subiendo...');

        $.ajax({
            url: API_URL + '/bot/subir-catalogo',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btnSubmit.prop('disabled', false).html(textOriginal);

                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'PDF cargado',
                        text: 'El catálogo visual se actualizó correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                btnSubmit.prop('disabled', false).html(textOriginal);
                Swal.fire('Error', xhr.responseJSON?.message || 'Error al cargar el archivo', 'error');
            }
        });
    });

    // Función para ver todos los productos
    function verTodosProductos() {
        $('#modalProductos').modal('show');
    }

    // Búsqueda en la tabla de productos
    $('#buscarProducto').on('keyup', function() {
        const valor = $(this).val().toLowerCase();
        $('#tablaProductosCompleta tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(valor) > -1);
        });
    });

    // Descargar plantilla Excel
    function descargarPlantilla() {
        // Crear un Excel de ejemplo con la estructura correcta
        const wb = XLSX.utils.book_new();

        // Hoja 1: PRODUCTOS
        const productos = [
            ['Categoría', 'Producto', 'Precio', 'Disponible', 'Descripción'],
            ['Pizzas', 'Pizza Familiar Americana', '35.90', 'SI', 'Queso, jamón y tocino'],
            ['Planes Internet', 'Internet 20 Mbps', '49.90', 'SI', 'Instalación S/100 (incluye router)'],
            ['Planes Internet', 'Internet 50 Mbps', '79.90', 'SI', 'Instalación: S/100 al instalar (incluye router). Mensualidad: S/79.90 a fin de mes'],
            ['Planes SaaS', 'Plan Básico', '99.00', 'SI', 'Hasta 5 usuarios']
        ];
        const ws1 = XLSX.utils.aoa_to_sheet(productos);
        XLSX.utils.book_append_sheet(wb, ws1, 'PRODUCTOS');

        // Hoja 2: PROMOCIONES
        const promociones = [
            ['Producto', 'Tipo', 'Descripción', 'Precio Promo'],
            ['Pizza Familiar Americana', '2x1', 'Martes y Jueves', '35.90'],
            ['Internet 50 Mbps', 'Descuento', 'Primer mes 50% OFF', '40.00']
        ];
        const ws2 = XLSX.utils.aoa_to_sheet(promociones);
        XLSX.utils.book_append_sheet(wb, ws2, 'PROMOCIONES');

        // Hoja 3: DELIVERY
        const delivery = [
            ['Zona', 'Costo', 'Tiempo'],
            ['Centro', '5.00', '20-30 min'],
            ['Ventanilla', '0.00', 'Tecnico Agenda contigo'],
            ['Sur', '8.00', '30-40 min'],
            ['GRATIS DESDE:', '50.00', ''],
            ['PARA BOT DE SOPORTE NO APLICA ELIMINAR TODO Y DEJAR EN BLANCO (NO ELIMINAR LOS ENCABEZADOS):', '00.00', '']
        ];
        const ws3 = XLSX.utils.aoa_to_sheet(delivery);
        XLSX.utils.book_append_sheet(wb, ws3, 'DELIVERY');

        // Descargar
        XLSX.writeFile(wb, 'plantilla_catalogo_bot.xlsx');

        Swal.fire({
            icon: 'info',
            title: 'Plantilla descargada',
            html: `
            <p>Modifica la plantilla con tus productos y vuelve a subirla.</p>
            <ul class="text-left">
                <li><strong>PRODUCTOS:</strong> Lista todos tus productos con precio</li>
                <li><strong>PROMOCIONES:</strong> Ofertas especiales activas</li>
                <li><strong>DELIVERY:</strong> Zonas y costos de entrega</li>
            </ul>
        `,
            width: 600
        });
    }
</script>

<!-- Incluir SheetJS para generar Excel -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>