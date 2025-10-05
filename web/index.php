<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Obtener días de trial desde BD (sobrescribe la constante)
$trial_dias = TRIAL_DAYS; // Fallback
try {
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_plataforma WHERE clave = 'trial_dias'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $trial_dias = (int)$result['valor'];
    }
} catch (Exception $e) {
    error_log("Error obteniendo trial_dias: " . $e->getMessage());
}

// Obtener planes activos
try {
    $stmt = $pdo->query("SELECT * FROM planes WHERE activo = 1 ORDER BY id ASC");
    $planes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error obteniendo planes: " . $e->getMessage());
    $planes = [];
}

function getCaracteristicas($plan)
{
    $caracteristicas = [];

    if (!empty($plan['caracteristicas_json'])) {
        $json = json_decode($plan['caracteristicas_json'], true);

        if (is_array($json)) {
            if ($plan['limite_contactos']) {
                $caracteristicas[] = number_format($plan['limite_contactos']) . ' contactos';
            }
            if ($plan['limite_mensajes_mes']) {
                $caracteristicas[] = number_format($plan['limite_mensajes_mes']) . ' mensajes/mes';
            }
            if (isset($json['bot_ventas']) && $json['bot_ventas']) {
                $caracteristicas[] = 'Bot de ventas con IA';
            }
            if (isset($json['bot_citas']) && $json['bot_citas']) {
                $caracteristicas[] = 'Agendamiento automático';
            }
            if (isset($json['escalamiento']) && $json['escalamiento']) {
                $caracteristicas[] = 'Escalamiento a humanos';
            }
            if (isset($json['catalogo_bot']) && $json['catalogo_bot']) {
                $caracteristicas[] = 'Catálogo PDF';
            }
            if (isset($json['google_calendar']) && $json['google_calendar']) {
                $caracteristicas[] = 'Google Calendar';
            }
            if (isset($json['plantillas'])) {
                $caracteristicas[] = $json['plantillas'] === 'ilimitadas' ? 'Plantillas ilimitadas' : $json['plantillas'] . ' plantillas';
            }
            if (isset($json['soporte']) && $json['soporte'] === 'prioritario') {
                $caracteristicas[] = 'Soporte prioritario';
            }
            if (isset($json['whatsapp_multiple']) && $json['whatsapp_multiple']) {
                $caracteristicas[] = 'Múltiples números WhatsApp';
            }
            if (isset($json['usuarios_ilimitados']) && $json['usuarios_ilimitados']) {
                $caracteristicas[] = 'Usuarios ilimitados';
            }
            if (isset($json['api_personalizada']) && $json['api_personalizada']) {
                $caracteristicas[] = 'API personalizada';
            }
            if (isset($json['implementacion'])) {
                $caracteristicas[] = $json['implementacion'];
            }
            if (isset($json['personalizacion'])) {
                $caracteristicas[] = $json['personalizacion'];
            }
            if ($plan['limite_contactos'] === null) {
                $caracteristicas[] = 'Contactos ilimitados';
            }
            if ($plan['limite_mensajes_mes'] === null) {
                $caracteristicas[] = 'Mensajes ilimitados';
            }
        }
    }

    return $caracteristicas;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Automatiza tus Ventas por WhatsApp con IA</title>

    <!-- SEO Básico -->
    <title><?php echo APP_NAME; ?> - Automatiza tus Ventas por WhatsApp con IA</title>
    <meta name="description" content="Bot inteligente de WhatsApp con IA que cotiza, agenda citas, cierra ventas 24/7. Envía campañas masivas, programa recordatorios y se integra con tu catálogo. Sin programar.">
    <meta name="keywords" content="bot whatsapp, whatsapp business, automatización ventas, chatbot IA, mensajes masivos whatsapp, chatgpt whatsapp, bot de ventas, agendamiento automático">
    <meta name="author" content="<?php echo APP_NAME; ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="<?php echo APP_URL; ?>">

    <!-- Geo Tags -->
    <meta name="geo.region" content="PE">
    <meta name="geo.placename" content="Perú">
    <meta name="language" content="es">

    <!-- Open Graph (Facebook, LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo APP_URL; ?>">
    <meta property="og:title" content="<?php echo APP_NAME; ?> - Automatiza tus Ventas por WhatsApp con IA">
    <meta property="og:description" content="Bot inteligente que cotiza, agenda citas y cierra ventas 24/7. Envía campañas masivas y programa recordatorios automáticamente.">
    <meta property="og:image" content="<?php echo asset('img/favicon.png'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="<?php echo APP_NAME; ?>">
    <meta property="og:locale" content="es_PE">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo APP_URL; ?>">
    <meta name="twitter:title" content="<?php echo APP_NAME; ?> - Automatiza tus Ventas por WhatsApp">
    <meta name="twitter:description" content="Bot inteligente con IA para WhatsApp. Cotiza, agenda y vende 24/7 automáticamente.">
    <meta name="twitter:image" content="<?php echo asset('img/favicon.png'); ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('img/favicon.png'); ?>">
    <link rel="manifest" href="<?php echo asset('img/site.webmanifest'); ?>">


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="stylesheet" href="assets/css/index.css">

    <!-- Schema.org (JSON-LD) para Google -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "SoftwareApplication",
            "name": "<?php echo APP_NAME; ?>",
            "applicationCategory": "BusinessApplication",
            "operatingSystem": "Web",
            "description": "Bot inteligente de WhatsApp con IA que automatiza ventas, cotiza productos, agenda citas y envía campañas masivas 24/7",
            "offers": {
                "@type": "AggregateOffer",
                "lowPrice": "0",
                "highPrice": "18",
                "priceCurrency": "USD"
            },
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": "4.8",
                "reviewCount": "150"
            },
            "creator": {
                "@type": "Organization",
                "name": "<?php echo APP_NAME; ?>",
                "url": "<?php echo APP_URL; ?>"
            },
            "featureList": [
                "Bot de ventas con ChatGPT",
                "Agendamiento automático de citas",
                "Mensajes masivos",
                "Integración con catálogo PDF/Excel",
                "Google Calendar sincronización",
                "Escalamiento inteligente"
            ]
        }
    </script>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <?php
                $logo_path = WEB_PATH . '/assets/img/logo.png';
                if (file_exists($logo_path)):
                ?>
                    <img src="<?php echo asset('img/logo.png'); ?>"
                        alt="<?php echo APP_NAME; ?>"
                        style="height: 50px; width: auto;">
                <?php else: ?>
                    <i class="fab fa-whatsapp" style="color: var(--primary);"></i>
                    <?php echo APP_NAME; ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#features">Funciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">Cómo Funciona</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Precios</a></li>
                    <li class="nav-item ms-3">
                        <a class="btn btn-primary" href="<?php echo url('login.php'); ?>">
                            Ingresar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content" data-aos="fade-right">
                    <h1>Automatiza tus Ventas por WhatsApp con IA</h1>
                    <p>Bot inteligente que cotiza, agenda citas, cierra ventas 24/7. Envía campañas masivas, programa recordatorios y se integra con tu catálogo. Sin programar.</p>
                    <div class="hero-buttons">
                        <a href="<?php echo url('registro.php'); ?>" class="btn btn-light btn-lg">
                            <i class="fas fa-rocket"></i> Probar Gratis <?php echo $trial_dias; ?> Días
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            Tutoriales
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 hero-image" data-aos="fade-left">
                    <?php
                    $img_path = WEB_PATH . '/assets/img/banner01.png';
                    if (file_exists($img_path)):
                    ?>
                        <img src="<?php echo asset('img/banner01.png'); ?>" alt="WhatsApp Bot IA" style="width: 100%;">
                    <?php else: ?>
                        <!-- Fallback con SVG o ícono -->
                        <div style="width: 100%; height: 500px; background: rgba(255,255,255,0.1); border-radius: 30px; display: flex; align-items: center; justify-content: center;">
                            <i class="fab fa-whatsapp" style="font-size: 10rem; color: rgba(255,255,255,0.3);"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="stats">
        <div class="container">
            <div class="row">
                <div class="col-md-3 stat-item" data-aos="fade-up" data-aos-delay="0">
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Mensajes por día</div>
                </div>
                <div class="col-md-3 stat-item" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Disponibilidad</div>
                </div>
                <div class="col-md-3 stat-item" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-number">5x</div>
                    <div class="stat-label">Más Conversiones</div>
                </div>
                <div class="col-md-3 stat-item" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-number">&lt;30s</div>
                    <div class="stat-label">Tiempo Respuesta</div>
                </div>
            </div>
        </div>
    </section>
    <!-- Features -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Todo lo que Necesitas para Vender Más</h2>
                <p>Un bot inteligente que hace el trabajo pesado por ti</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h4>Bot de Ventas con IA</h4>
                        <p>Cotiza productos, responde preguntas y cierra ventas automáticamente con ChatGPT</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4>Agendamiento Automático</h4>
                        <p>Reserva citas, envía recordatorios y sincroniza con Google Calendar</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h4>Catálogo Inteligente</h4>
                        <p>Sube tu catálogo en PDF o Excel y el bot responde sobre productos automáticamente</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h4>Mensajes Masivos</h4>
                        <p>Envía campañas a miles de contactos simultáneamente con sistema anti-spam inteligente</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4>Mensajes Programados</h4>
                        <p>Programa campañas y recordatorios para enviarse en el momento perfecto</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h4>Cotizaciones Instantáneas</h4>
                        <p>Calcula precios, descuentos y genera cotizaciones profesionales al instante</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Escalamiento Inteligente</h4>
                        <p>El bot detecta cuándo derivar a un humano y notifica a tu equipo al instante</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4>Recordatorios Automáticos</h4>
                        <p>Envía recordatorios de citas, pagos pendientes y seguimiento de clientes</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <h4>Importación Masiva</h4>
                        <p>Importa contactos y productos desde Excel con un solo click</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Cómo Funciona</h2>
                <p>En 3 simples pasos estás vendiendo 24/7</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Conecta tu WhatsApp</h4>
                        <p>Escanea un QR y listo. Sin instalar nada.</p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Sube tu Catálogo</h4>
                        <p>PDF o Excel con tus productos, precios y descripciones.</p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Activa el Bot</h4>
                        <p>Configura horarios y ¡empieza a vender automáticamente!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="pricing">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Planes Simples y Transparentes</h2>
                <p>Elige el plan perfecto para tu negocio</p>
            </div>

            <div class="row g-4">
                <?php foreach ($planes as $index => $plan):
                    $caracteristicas = getCaracteristicas($plan);
                    $is_featured = ($index === 2); // Plan Profesional (índice 2)
                ?>
                    <div class="col-xl-3 col-lg-6 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="pricing-card <?php echo $is_featured ? 'featured' : ''; ?>">
                            <?php if ($is_featured): ?>
                                <div class="pricing-badge">Más Popular</div>
                            <?php endif; ?>

                            <h3><?php echo htmlspecialchars($plan['nombre']); ?></h3>

                            <div class="price">
                                <?php if ($plan['precio_mensual'] > 0): ?>
                                    $<?php echo number_format($plan['precio_mensual'], 0); ?>
                                    <small>/mes</small>
                                <?php elseif (strtolower($plan['nombre']) === 'empresarial' || $plan['limite_contactos'] === null): ?>
                                    <span style="font-size: 2rem;">Consultar</span>
                                <?php else: ?>
                                    Gratis
                                <?php endif; ?>
                            </div>

                            <?php if ($plan['precio_anual'] > 0): ?>
                                <div class="price-annual">
                                    <i class="fas fa-tag"></i>
                                    $<?php echo number_format($plan['precio_anual'], 0); ?>/año
                                    (ahorra <?php echo round((1 - $plan['precio_anual'] / ($plan['precio_mensual'] * 12)) * 100); ?>%)
                                </div>
                            <?php endif; ?>

                            <ul class="features-list">
                                <?php foreach ($caracteristicas as $caracteristica): ?>
                                    <li>
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo htmlspecialchars($caracteristica); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if (strtolower($plan['nombre']) === 'empresarial'): ?>
                                <a href="mailto:nilson.jhonny@gmail.com?subject=Consulta Plan Empresarial"
                                    class="btn btn-primary">
                                    <i class="fas fa-envelope"></i> Contactar Ventas
                                </a>
                            <?php elseif ($plan['precio_mensual'] > 0): ?>
                                <!-- Compra directa de plan pago -->
                                <a href="<?php echo url('registro.php?plan=' . $plan['id']); ?>"
                                    class="btn <?php echo $is_featured ? 'btn-primary' : 'btn-outline-dark'; ?>">
                                    <i class="fas fa-shopping-cart"></i> Comprar Plan
                                </a>
                            <?php else: ?>
                                <!-- Trial gratuito -->
                                <a href="<?php echo url('registro.php'); ?>"
                                    class="btn btn-outline-dark">
                                    Probar Gratis
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <div class="container" data-aos="fade-up">
            <h2>Empieza a Vender en Piloto Automático</h2>
            <p>Únete a cientos de negocios que ya automatizan sus ventas</p>
            <a href="<?php echo url('registro.php'); ?>" class="btn btn-light btn-lg">
                <i class="fas fa-rocket"></i> Comenzar Ahora - Gratis
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center">
                    <div class="footer-links mb-3">
                        <a href="<?php echo url('terminos.php'); ?>">Términos y Condiciones</a>
                        <a href="<?php echo url('privacidad.php'); ?>">Política de Privacidad</a>
                        <a href="mailto:soporte@<?php echo strtolower(str_replace(' ', '', APP_NAME)); ?>.com">Soporte</a>
                    </div>
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
    </script>
</body>

</html>