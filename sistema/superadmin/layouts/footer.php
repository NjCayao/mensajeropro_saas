</div>
    <!-- /.content-wrapper -->

    <footer class="main-footer">
        <strong>Copyright &copy; 2024-2025 <a href="#"><?php echo APP_NAME; ?></a>.</strong>
        Todos los derechos reservados.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> <?php echo APP_VERSION; ?> | <span class="text-danger">SuperAdmin Panel</span>
        </div>
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
    </aside>

</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="<?php echo asset('plugins/jquery/jquery.min.js'); ?>"></script>
<!-- jQuery UI 1.11.4 -->
<script src="<?php echo asset('plugins/jquery-ui/jquery-ui.min.js'); ?>"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo asset('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<!-- AdminLTE App -->
<script src="<?php echo asset('dist/js/adminlte.js'); ?>"></script>
<!-- SweetAlert2 -->
<script src="<?php echo asset('plugins/sweetalert2/sweetalert2.min.js'); ?>"></script>

<script>
// Toast helper
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});

// Helper para mostrar errores
function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje
    });
}

// Helper para mostrar éxito
function mostrarExito(mensaje) {
    Toast.fire({
        icon: 'success',
        title: mensaje
    });
}
</script>

</body>
</html>