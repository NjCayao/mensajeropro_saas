<!-- Main Sidebar Container -->
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo url('sistema/cliente/dashboard.php'); ?>" class="brand-link">
        <span class="brand-text font-weight-light"><?php echo APP_NAME; ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                <li class="nav-item">
                    <a href="app.php" class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-header">GESTIÓN</li>

                <li class="nav-item">
                    <a href="app.php?mod=contactos" class="nav-link <?php echo ($current_page == 'contactos') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>Contactos</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="app.php?mod=categorias" class="nav-link <?php echo ($current_page == 'categorias') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Categorías</p>
                    </a>
                </li>

                <li class="nav-header">MENSAJERÍA</li>

                <li class="nav-item">
                    <a href="app.php?mod=mensajes" class="nav-link <?php echo ($current_page == 'mensajes') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-paper-plane"></i>
                        <p>Enviar Mensajes</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="app.php?mod=programados" class="nav-link <?php echo ($current_page == 'programados') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-clock"></i>
                        <p>Programados</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="app.php?mod=escalados" class="nav-link <?php echo ($current_page == 'escalados') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-user-tie"></i>
                        <p>
                            Escalados
                            <?php
                            // Mostrar badge con pendientes - agregar filtro de empresa
                            $empresa_id = getEmpresaActual();
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM estados_conversacion WHERE estado = 'escalado_humano' AND empresa_id = ?");
                            $stmt->execute([$empresa_id]);
                            $pendientes = $stmt->fetchColumn();
                            if ($pendientes > 0):
                            ?>
                                <span class="badge badge-warning right"><?= $pendientes ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="app.php?mod=plantillas" class="nav-link <?php echo ($current_page == 'plantillas') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>Plantillas</p>
                    </a>
                </li>

                <li class="nav-header">SISTEMA</li>

                <li class="nav-item">
                    <a href="app.php?mod=whatsapp" class="nav-link <?php echo ($current_page == 'whatsapp') ? 'active' : ''; ?>">
                        <i class="nav-icon fab fa-whatsapp"></i>
                        <p>WhatsApp</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="app.php?mod=bot-config" class="nav-link <?php echo ($current_page == 'bot-config') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-robot"></i>
                        <p>Bot IA</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="app.php?mod=perfil" class="nav-link <?php echo ($current_page == 'perfil') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <p>Mi Perfil</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>