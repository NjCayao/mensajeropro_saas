<?php
// public/index.php - Landing page
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Sistema de Mensajería WhatsApp</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-card {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .pricing-card {
            border: 2px solid #f0f0f0;
            transition: all 0.3s;
        }
        .pricing-card.featured {
            border-color: #25D366;
            transform: scale(1.05);
        }
        .pricing-card:hover {
            border-color: #25D366;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fab fa-whatsapp text-success"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Características</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Precios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contacto</a>
                    </li>
                    <li class="nav-item ms-3">
                        <a class="btn btn-success" href="login.php">Iniciar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">Mensajería WhatsApp Profesional</h1>
            <p class="lead mb-5">Gestiona tus mensajes masivos, automatiza respuestas con IA y haz crecer tu negocio</p>
            <a href="registro.php" class="btn btn-light btn-lg me-3">
                <i class="fas fa-rocket"></i> Prueba Gratis 30 Días
            </a>
            <a href="login.php" class="btn btn-outline-light btn-lg">
                <i class="fas fa-sign-in-alt"></i> Acceder
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Características Principales</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-paper-plane text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title text-center">Mensajes Masivos</h5>
                        <p class="card-text">Envía mensajes a todos tus contactos o por categorías con sistema anti-spam inteligente.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-robot text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title text-center">Bot IA Integrado</h5>
                        <p class="card-text">Responde automáticamente con inteligencia artificial y escala conversaciones cuando sea necesario.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-clock text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title text-center">Mensajes Programados</h5>
                        <p class="card-text">Programa tus campañas para enviarlas en el momento perfecto.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Planes y Precios</h2>
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="card pricing-card p-4 text-center h-100">
                        <h4>Trial</h4>
                        <h2 class="my-3">Gratis</h2>
                        <p class="text-muted">30 días</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success"></i> 100 contactos</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> 500 mensajes/mes</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> Bot IA básico</li>
                        </ul>
                        <a href="registro.php" class="btn btn-outline-success mt-auto">Empezar</a>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card pricing-card p-4 text-center h-100">
                        <h4>Básico</h4>
                        <h2 class="my-3">$29.90</h2>
                        <p class="text-muted">por mes</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success"></i> 500 contactos</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> 3,000 mensajes/mes</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> Bot IA completo</li>
                        </ul>
                        <a href="registro.php" class="btn btn-success mt-auto">Elegir Plan</a>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card pricing-card featured p-4 text-center h-100">
                        <span class="badge bg-success mb-2">Más Popular</span>
                        <h4>Profesional</h4>
                        <h2 class="my-3">$59.90</h2>
                        <p class="text-muted">por mes</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success"></i> 2,000 contactos</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> 10,000 mensajes/mes</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> Múltiples usuarios</li>
                        </ul>
                        <a href="registro.php" class="btn btn-success mt-auto">Elegir Plan</a>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card pricing-card p-4 text-center h-100">
                        <h4>Empresarial</h4>
                        <h2 class="my-3">$99.90</h2>
                        <p class="text-muted">por mes</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success"></i> Contactos ilimitados</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> Mensajes ilimitados</li>
                            <li class="mb-2"><i class="fas fa-check text-success"></i> Soporte prioritario</li>
                        </ul>
                        <a href="registro.php" class="btn btn-success mt-auto">Elegir Plan</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>