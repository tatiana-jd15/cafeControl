<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Control</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Reset y Variables Globales --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        :root {
            --bg-dark-brown: #1a100a;
            --header-bg: #1e130c;
            --primary-green: #3b5323;
            --primary-green-hover: #2d401a;
            --bg-cream: #f9f6f0;
            --card-bg: #f4efe6;
            --card-inner: #eae3d5;
            --text-dark: #2b221a;
            --text-muted: #665b52;
            --text-light: #ffffff;
            --border-color: #e0d6c8;
        }

        body {
            background-color: var(--bg-cream);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 1rem;
            line-height: 1.5;
        }

        /* --- Header / Barra Superior --- */
        header {
            background-color: var(--header-bg);
            color: var(--text-light);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #332317;
        }

        a {
            text-decoration: none;
            color: white;
            font-size: 17px;
            line-height: 1.5;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-badge {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logo-text h1 {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.5;
            margin: 0;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        nav a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 17px;
            font-weight: 500;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        nav a.active {
            background-color: var(--primary-green);
            color: var(--text-light);
        }

        /* Estilos del Carrusel Bootstrap */
        .carousel-item {
            height: 500px;
        }

        .carousel-item img {
            height: 100%;
            object-fit: cover;
            filter: brightness(0.65);
        }

        .carousel-caption {
            text-align: left;
            left: 80px;
            right: 80px;
            bottom: 60px;
            max-width: 650px;
        }

        .carousel-caption h2 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.7);
        }

        .carousel-caption p {
            font-size: 17px;
            line-height: 1.5;
            margin-bottom: 25px;
            color: #e6e0d8;
            text-shadow: 0 1px 3px rgba(0,0,0,0.7);
        }

        .btn-know-more {
            background-color: var(--primary-green);
            color: var(--text-light);
            border: none;
            padding: 14px 28px;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s ease, transform 0.1s ease;
            text-decoration: none;
        }

        .btn-know-more:hover {
            background-color: var(--primary-green-hover);
            transform: translateY(-2px);
            color: var(--text-light);
        }

        /* --- Main Content Section --- */
        main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .cards-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .card-custom {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 35px 30px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .icon-circle {
            width: 75px;
            height: 75px;
            background-color: var(--primary-green);
            color: var(--text-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            flex-shrink: 0;
        }

        .card-header-text h3 {
            font-size: 1.8rem;
            line-height: 1.5;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .card-header-text p {
            color: var(--text-muted);
            font-size: 17px;
            line-height: 1.5;
            margin: 0;
        }

        .login-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: auto;
            gap: 15px;
        }

        .btn-login {
            background-color: var(--primary-green);
            color: var(--text-light);
            width: 100%;
            max-width: 250px;
            padding: 14px;
            border-radius: 8px;
            border: none;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.2s;
            text-decoration: none;
        }

        .btn-login:hover {
            background-color: var(--primary-green-hover);
            color: var(--text-light);
        }

        .forgot-link {
            color: var(--text-dark);
            text-decoration: underline;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 600;
        }

        .register-icon {
            background-color: #3f2a1d;
        }

        .roles-subtitle {
            font-size: 17px;
            line-height: 1.5;
            font-weight: 600;
            color: var(--text-dark);
            margin: 15px 0 12px 0;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .role-card {
            background-color: var(--card-inner);
            border-radius: 12px;
            padding: 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .role-card:hover {
            border-color: var(--primary-green);
            background-color: #e3dbc9;
            color: inherit;
        }

        .role-icon {
            font-size: 1.8rem;
            color: var(--primary-green);
            flex-shrink: 0;
            margin-top: 3px;
        }

        .role-card:last-child .role-icon {
            color: #3f2a1d;
        }

        .role-info h4 {
            font-size: 1.1rem;
            line-height: 1.5;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .role-info p {
            font-size: 17px;
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* --- Features Bar --- */
        .features-bar {
            background-color: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 25px 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .feature-icon {
            width: 42px;
            height: 42px;
            background-color: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .feature-item:last-child .feature-icon {
            background-color: #3f2a1d;
        }

        .feature-text h5 {
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 3px;
        }

        .feature-text p {
            font-size: 17px;
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* --- Estilos Modales Personalizados --- */
        .modal-content {
            background-color: var(--bg-cream);
            border-radius: 12px;
            overflow: hidden;
        }

        .modal-header {
            background-color: var(--header-bg);
            color: var(--text-light);
            border-bottom: 1px solid #332317;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-body-iframe {
            padding: 0;
            height: 70vh;
        }

        .modal-body-iframe iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* --- Footer --- */
        footer {
            margin-top: auto;
            background-color: var(--header-bg);
            color: var(--text-light);
            text-align: center;
            padding: 20px;
            font-size: 15px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .cards-container {
                grid-template-columns: 1fr;
            }
            .features-bar {
                grid-template-columns: 1fr 1fr;
                gap: 25px;
            }
        }

        @media (max-width: 600px) {
            header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }
            .carousel-caption {
                left: 20px;
                right: 20px;
                bottom: 30px;
            }
            .carousel-caption h2 {
                font-size: 1.8rem;
            }
            .roles-grid {
                grid-template-columns: 1fr;
            }
            .features-bar {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div class="logo-container">
            <div class="logo-badge">
                <img src="images/logo.jpeg" alt="Logo Café Control" class="logo-img">
            </div>
            <div class="logo-text">
                <h1>Café control</h1>
            </div>
        </div>

        <nav>
            <a href="index.php" class="active"><i class="fa-solid fa-house"></i> Inicio</a>
            <!-- Atributos data-bs para abrir los modales -->
            <a href="#" data-bs-toggle="modal" data-bs-target="#developerModal"><i class="fa-solid fa-circle-info"></i> Developers</a>
            <a href="#" data-bs-toggle="modal" data-bs-target="#funcionaModal"><i class="fa-solid fa-circle-question"></i> ¿Cómo funciona?</a>
        </nav>
    </header>

    <!-- Carrusel de Bootstrap 5 -->
    <div id="carouselExampleInterval" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active" data-bs-interval="10000">
                <img src="images/imagen1.jpg" class="d-block w-100" alt="Recolección de Café">
                <div class="carousel-caption d-none d-md-block">
                    <h2>Lleva el control de tu café,<br>tu esfuerzo vale.</h2>
                    <p>Registra tus recolecciones, consulta tus pagos y lleva el control de tu trabajo de forma fácil, segura y transparente.</p>
                  
                </div>
            </div>
            <div class="carousel-item" data-bs-interval="2000">
                <img src="images/cafe.jpg" class="d-block w-100" alt="Gestión de Finca">
                <div class="carousel-caption d-none d-md-block">
                    <h2>Gestiona tu finca<br>en tiempo real.</h2>
                    <p>Obtén resúmenes detallados sobre kilos recolectados, liquidaciones y avance de jornales en un solo lugar.</p>
                  
                </div>
            </div>
            <div class="carousel-item">
                <img src="images/cafe2.jpg" class="d-block w-100" alt="Transparencia">
                <div class="carousel-caption d-none d-md-block">
                    <h2>Transparencia para<br>caficultores y recolectores.</h2>
                    <p>Lleva cuentas claras en cada jornada y optimiza los procesos de pago de forma rápida.</p>
                   
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleInterval" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleInterval" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
    </div>

    <!-- Contenido Principal -->
    <main>
        <div class="cards-container">
            <!-- Tarjeta: Iniciar Sesión -->
            <div class="card-custom">
                <div class="login-header">
                    <div class="icon-circle">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Iniciar sesión</h3>
                        <p>Accede a tu cuenta para gestionar tus recolecciones y pagos.</p>
                    </div>
                </div>

                <div class="login-action">
                    <a href="iniciar_sesion.php" class="btn-login">
                        <i class="fa-solid fa-user"></i> Iniciar sesión
                    </a>
                    <a href="recuperar_contraseña.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
                </div>
            </div>

            <!-- Tarjeta: Crear Cuenta -->
            <div class="card-custom">
                <div class="login-header">
                    <div class="icon-circle register-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Crear cuenta</h3>
                        <p>Únete al sistema y comienza a gestionar tu café recolectado.</p>
                    </div>
                </div>

                <p class="roles-subtitle">Selecciona tu rol para registrarte:</p>

                <div class="roles-grid">
                    <a href="registro_a.php" class="role-card">
                        <div class="role-icon">
                            <i class="fa-solid fa-wheat-awn"></i>
                        </div>
                        <div class="role-info">
                            <h4>Caficultor</h4>
                            <p>Administra tu finca, trabajadores y pagos.</p>
                        </div>
                    </a>

                    <a href="registro_r.php" class="role-card">
                        <div class="role-icon">
                            <i class="fa-solid fa-basket-shopping"></i>
                        </div>
                        <div class="role-info">
                            <h4>Recolector</h4>
                            <p>Registra tus recolecciones y consulta tus pagos.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Características -->
        <section class="features-bar">
            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="feature-text">
                    <h5>Seguro y confiable</h5>
                    <p>Tu información está protegida con los más altos estándares.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-chart-column"></i></div>
                <div class="feature-text">
                    <h5>Reportes al instante</h5>
                    <p>Visualiza datos y reportes actualizados en tiempo real.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-leaf"></i></div>
                <div class="feature-text">
                    <h5>Apoyo al campo</h5>
                    <p>Herramienta diseñada para facilitar el trabajo en las fincas cafeteras.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-lock"></i></div>
                <div class="feature-text">
                    <h5>Acceso seguro</h5>
                    <p>Solo usuarios autorizados pueden acceder al sistema.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal Developers -->
    <div class="modal fade" id="developerModal" tabindex="-1" aria-labelledby="developerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="developerModalLabel"><i class="fa-solid fa-circle-info"></i> Desarrolladores</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-iframe">
                    <iframe src="developer.php" title="Developers"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ¿Cómo funciona? -->
    <div class="modal fade" id="funcionaModal" tabindex="-1" aria-labelledby="funcionaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="funcionaModalLabel"><i class="fa-solid fa-circle-question"></i> ¿Cómo funciona?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-iframe">
                    <iframe src="funciona.php" title="¿Cómo funciona?"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; 2026 Café Control - Sistema de Gestión de Recolección de Café</p>
    </footer>

    <!-- Script de Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>