<?php
// Cargar límites del plan
require_once __DIR__ . '/../../../includes/plan-limits.php';

// Obtener permisos de módulos
$tiene_escalamiento = tieneEscalamiento();
$tiene_catalogo = tieneCatalogoBot();
$tiene_horarios = tieneHorariosBot();
?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo url('cliente/dashboard'); ?>" class="brand-link">
        <span class="brand-text font-weight-light"><?php echo APP_NAME; ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                <li class="nav-item">
                    <a href="<?php echo url('cliente/dashboard'); ?>" class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-header">GESTIÓN</li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/contactos'); ?>" class="nav-link <?php echo ($current_page == 'contactos') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>Contactos</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/categorias'); ?>" class="nav-link <?php echo ($current_page == 'categorias') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Categorías</p>
                    </a>
                </li>

                <li class="nav-header">MENSAJERÍA</li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/mensajes'); ?>" class="nav-link <?php echo ($current_page == 'mensajes') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-paper-plane"></i>
                        <p>Enviar Mensajes</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/programados'); ?>" class="nav-link <?php echo ($current_page == 'programados') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-clock"></i>
                        <p>Programados</p>
                    </a>
                </li>

                <?php if ($tiene_escalamiento): ?>
                <li class="nav-item">
                    <a href="<?php echo url('cliente/escalados'); ?>" class="nav-link <?php echo ($current_page == 'escalados') ? 'active' : ''; ?>">
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
                <?php endif; ?>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/plantillas'); ?>" class="nav-link <?php echo ($current_page == 'plantillas') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>Plantillas</p>
                    </a>
                </li>

                <li class="nav-header">SISTEMA</li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/whatsapp'); ?>" class="nav-link <?php echo ($current_page == 'whatsapp') ? 'active' : ''; ?>">
                        <i class="nav-icon fab fa-whatsapp"></i>
                        <p>WhatsApp</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/bot-config'); ?>" class="nav-link <?php echo ($current_page == 'bot-config') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-robot"></i>
                        <p>Bot IA</p>
                    </a>
                </li>

                <?php
                // Obtener configuración del bot para verificar el tipo
                $empresa_id = getEmpresaActual();
                $stmt_bot = $pdo->prepare("SELECT tipo_bot FROM configuracion_bot WHERE empresa_id = ?");
                $stmt_bot->execute([$empresa_id]);
                $config_bot = $stmt_bot->fetch();

                // MOSTRAR CATÁLOGO SOLO SI: tiene el módulo Y es bot de ventas
                if ($tiene_catalogo && $config_bot && $config_bot['tipo_bot'] === 'ventas'):
                ?>
                    <li class="nav-item">
                        <a href="<?php echo url('cliente/catalogo-bot'); ?>" class="nav-link <?php echo ($current_page == 'catalogo-bot') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-shopping-cart"></i>
                            <p>Catálogo Bot</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php 
                // MOSTRAR HORARIOS SOLO SI: tiene el módulo Y es bot de citas
                if ($tiene_horarios && $config_bot && $config_bot['tipo_bot'] === 'citas'): 
                ?>
                    <li class="nav-item">
                        <a href="<?php echo url('cliente/horarios-bot'); ?>" class="nav-link <?php echo ($current_page == 'horarios-bot') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Horarios y Citas</p>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/bot-templates'); ?>" class="nav-link <?php echo ($current_page == 'bot-templates') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-file-code"></i>
                        <p>Templates Bot</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/perfil'); ?>" class="nav-link <?php echo ($current_page == 'perfil') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <p>Mi Perfil</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo url('cliente/mi-plan'); ?>" class="nav-link <?php echo ($current_page == 'mi-plan') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <p>Mi Plan</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>