<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-danger elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo url('superadmin/dashboard'); ?>" class="brand-link bg-danger">
        <i class="fas fa-shield-alt brand-image"></i>
        <span class="brand-text font-weight-light">SuperAdmin</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-user-shield fa-2x text-white"></i>
            </div>
            <div class="info">
                <a href="#" class="d-block"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></a>
                <small class="text-muted">Administrador del Sistema</small>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/dashboard'); ?>"
                        class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-header">GESTIÓN</li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/empresas'); ?>"
                        class="nav-link <?php echo ($current_page == 'empresas') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Empresas</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/planes'); ?>"
                        class="nav-link <?php echo ($current_page == 'planes') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-box"></i>
                        <p>Planes</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/pagos'); ?>"
                        class="nav-link <?php echo ($current_page == 'pagos') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <p>Pagos</p>
                    </a>
                </li>

                <li class="nav-header">CONFIGURACIÓN</li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/configuracion'); ?>"
                        class="nav-link <?php echo ($current_page == 'configuracion') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Configuración Global</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/emails'); ?>"
                        class="nav-link <?php echo ($current_page == 'emails') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-envelope"></i>
                        <p>Plantillas Email</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/bot-templates'); ?>"
                        class="nav-link <?php echo ($current_page == 'bot-templates') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-robot"></i>
                        <p>Plantillas Bot</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/ml-config'); ?>"
                        class="nav-link <?php echo ($current_page == 'ml-config') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-brain"></i>
                        <p>ML Engine</p>
                    </a>
                </li>

                <li class="nav-header">SISTEMA</li>

                <li class="nav-item">
                    <a href="<?php echo url('superadmin/logs'); ?>"
                        class="nav-link <?php echo ($current_page == 'logs') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-list"></i>
                        <p>Logs del Sistema</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/dashboard'); ?>" class="nav-link">
                        <i class="nav-icon fas fa-user"></i>
                        <p>
                            Ver como Cliente
                            <i class="fas fa-external-link-alt right"></i>
                        </p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>