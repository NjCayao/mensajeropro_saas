</div>
    <!-- /.content-wrapper -->
    
    <footer class="main-footer">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#"><?php echo APP_NAME; ?></a>.</strong>
        Todos los derechos reservados.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> <?php echo APP_VERSION; ?>
        </div>
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
    </aside>
</div>

<!-- jQuery -->
<script src="<?php echo asset('plugins/jquery/jquery.min.js'); ?>"></script>
<!-- jQuery UI 1.11.4 -->
<script src="<?php echo asset('plugins/jquery-ui/jquery-ui.min.js'); ?>"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<!-- DataTables & Plugins -->
<script src="<?php echo asset('plugins/datatables/jquery.dataTables.min.js'); ?>"></script>
<script src="<?php echo asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js'); ?>"></script>
<script src="<?php echo asset('plugins/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
<!-- SweetAlert2 -->
<script src="<?php echo asset('plugins/sweetalert2/sweetalert2.min.js'); ?>"></script>
<!-- AdminLTE App -->
<script src="<?php echo asset('dist/js/adminlte.js'); ?>"></script>

<!-- Script Global -->
<script>
// IMPORTANTE: Verificar que jQuery está cargado
if (typeof jQuery === 'undefined') {
    console.error('jQuery no está cargado!');
} else {
    console.log('jQuery versión:', jQuery.fn.jquery);
}


// Resolver conflicto jQuery UI tooltip con Bootstrap tooltip
if ($.widget) {
    $.widget.bridge('uibutton', $.ui.button);
}

function logout() {
    Swal.fire({
        title: '¿Cerrar sesión?',
        text: "¿Estás seguro de que deseas salir?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ CORREGIDO: Redirigir directamente a logout.php
            window.location.href = '<?php echo url('cliente/logout'); ?>';
        }
    });
}

// Configuración por defecto para DataTables
if ($.fn.dataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron resultados",
            "sEmptyTable":     "Ningún dato disponible en esta tabla",
            "sInfo":           "Mostrando _START_ al _END_ de _TOTAL_ registros",
            "sInfoEmpty":      "Mostrando 0 al 0 de 0 registros",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sSearch":         "Buscar:",
            "sUrl":            "",
            "sInfoThousands":  ",",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst":    "Primero",
                "sLast":     "Último",
                "sNext":     "Siguiente",
                "sPrevious": "Anterior"
            }
        }
    });
}

// Toast notification helper
function showToast(type, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
    
    Toast.fire({
        icon: type,
        title: message
    });
}
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>

</body>
</html>