<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Control - Sistema de Gestión Cafetera</title>
    <!-- FontAwesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --nav-bg: #1a100c; /* Color café tomado directamente de la imagen */
            --accent-green: #3d632f;
            --accent-green-hover: #2e4d23;
            --bg-body: #f8f4ef;
            --card-bg: #ffffff;
            --cream-card: #f3ece3;
            --border-color: #d8ceb8;
            --text-dark: #1e1814;
            --text-muted: #574c43;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* NAVEGADOR ESTILIZADO */
        header {
            background-color: var(--nav-bg);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.4);
            border-bottom: 1px solid #2b1c15;
            padding: 12px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--accent-green);
            background: #fff;
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logo-text h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--white);
            margin: 0;
        }

        .navbar {
            display: flex;
            align-items: center;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
            list-style: none;
        }

        .nav-links a {
            color: var(--white);
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            padding: 9px 18px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, color 0.2s;
        }

        .nav-links a.btn-inicio {
            background-color: var(--accent-green);
        }

        .nav-links a.btn-inicio:hover {
            background-color: var(--accent-green-hover);
        }

        .nav-links a:hover:not(.btn-inicio) {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* HERO SECTION */
        .hero {
            background: linear-gradient(180deg, var(--nav-bg) 0%, #2b1a11 100%);
            color: var(--white);
            padding: 70px 24px;
            text-align: center;
        }

        .hero-inner {
            max-width: 900px;
            margin: 0 auto;
        }

        .hero-tag {
            background-color: rgba(255, 255, 255, 0.12);
            color: #e3d7cb;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hero h1 {
            font-size: 40px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 18px;
            color: #d1c5b8;
            max-width: 780px;
            margin: 0 auto 30px;
        }

        /* SECCIONES Y CONTENIDOS */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 24px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .section-header p {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 750px;
            margin: 0 auto;
        }

        /* BLOQUES INFORMATIVOS */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 28px;
            margin-bottom: 60px;
        }

        .info-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .card-icon {
            width: 55px;
            height: 55px;
            background: var(--cream-card);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--accent-green);
            margin-bottom: 20px;
        }

        .info-card h3 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 14px;
            color: var(--text-dark);
        }

        .info-card p {
            color: var(--text-muted);
            font-size: 16px;
            line-height: 1.65;
        }

        /* PASO A PASO */
        .flow-section {
            background-color: var(--cream-card);
            border-radius: 24px;
            padding: 50px 36px;
            border: 2px solid var(--border-color);
            margin-bottom: 60px;
        }

        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .step-box {
            background: var(--card-bg);
            padding: 28px;
            border-radius: 16px;
            position: relative;
            border-top: 5px solid var(--accent-green);
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
        }

        .step-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--accent-green);
            line-height: 1;
            margin-bottom: 10px;
        }

        .step-box h4 {
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .step-box p {
            font-size: 15px;
            color: var(--text-muted);
        }

        /* ROLES */
        .roles-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .role-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 28px;
        }

        .role-card h4 {
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .role-card h4 i {
            color: var(--accent-green);
            font-size: 22px;
        }

        .role-card p {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* PIE DE PÁGINA */
       /* PIE DE PÁGINA (ESTILO MODO CLARO) */
footer {
    background-color: var(--cream-card); /* Fondo crema claro de la imagen */
    color: var(--text-dark);
    padding: 30px 24px 25px;
    border-top: 1px solid var(--border-color);
}

.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    text-align: center;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-muted);
}

.footer-logo i {
    color: var(--text-muted);
    font-size: 18px;
}

.footer-credits {
    font-size: 15px;
    color: var(--text-muted);
}

.footer-credits strong {
    color: var(--text-dark);
    font-weight: 800;
}



        @media (max-width: 768px) {
            header { flex-direction: column; gap: 15px; padding: 20px; }
            .nav-links { width: 100%; justify-content: center; flex-wrap: wrap; }
            .hero h1 { font-size: 28px; }
            .hero p { font-size: 16px; }
            .flow-section { padding: 30px 18px; }
        }
    </style>
</head>
<body>

    <!-- BARRA DE NAVEGACIÓN -->
    <!-- <header>
        <div class="logo-container">
            <div class="logo-badge">
                <img src="images/logo.jpeg" alt="Logo Café Control" class="logo-img">
            </div>
            <div class="logo-text">
                <h1>Café control</h1>
            </div>
        </div>
        <nav class="navbar">
            <ul class="nav-links">
                <li><a href="panel_r.php" class="btn-inicio"><i class="fa-solid fa-house"></i> Inicio</a></li>
                <li><a href="developer.php"><i class="fa-solid fa-circle-info"></i> Developers</a></li>
                <li><a href="#como-funciona"><i class="fa-solid fa-circle-question"></i> ¿Cómo funciona?</a></li>
            </ul>
        </nav>
    </header> -->

    <!-- HERO / BIENVENIDA -->
    <section class="hero">
        <div class="hero-inner">
            <span class="hero-tag"><i class="fa-solid fa-seedling"></i> Gestión Cafetera Digital</span>
            <h1>Administración Eficiente y Transparente para la Cosecha de Café</h1>
            <p>Café Control es la plataforma creada para organizar la recolección de café, llevar la contabilidad clara de los kilos pesados en el recibo y calcular las liquidaciones de pago de forma exacta y sin complicaciones.</p>
        </div>
    </section>

    <!-- SECCIÓN: ACERCA DEL SISTEMA -->
    <main class="container" id="acerca">
        <div class="section-header">
            <h2>Acerca del Sistema</h2>
            <p>Conoce a fondo las soluciones que Café Control ofrece para modernizar las jornadas de recolección y mejorar la relación entre caficultores, mayordomos y recolectores.</p>
        </div>

        <div class="info-grid">
            <article class="info-card">
                <div class="card-icon"><i class="fa-solid fa-weight-hanging"></i></div>
                <h3>Control de Kilos y Entregas</h3>
                <p>Sustituye las anotaciones en cuadernos de papel por un registro digital seguro. Cada entrega pesada durante la jornada queda guardada inmediatamente con la fecha, la hora exacta y el nombre del recolector correspondiente.</p>
            </article>

            <article class="info-card">
                <div class="card-icon"><i class="fa-solid fa-calculator"></i></div>
                <h3>Liquidación Transparente</h3>
                <p>El sistema calcula en tiempo real el valor a pagar a cada trabajador multiplicando los kilos recolectados por la tarifa oficial estipulada por el caficultor. Esto evita confusiones y garantiza cuentas claras al momento del pago.</p>
            </article>

            <article class="info-card">
                <div class="card-icon"><i class="fa-solid fa-calendar-week"></i></div>
                <h3>Organización por Semanas</h3>
                <p>Las entregas se agrupan automáticamente por periodos semanales. Tanto el administrador como el recolector pueden evaluar el desempeño diario, revisar el acumulado del periodo y verificar pagos pasados.</p>
            </article>

            <article class="info-card">
                <div class="card-icon"><i class="fa-solid fa-id-card"></i></div>
                <h3>Perfiles Personalizados</h3>
                <p>Cada integrante de la finca cuenta con su perfil donde puede actualizar sus nombres, apellidos y foto de perfil. Esto facilita la identificación visual de los recolectores en el panel del administrador.</p>
            </article>

            <article class="info-card">
                <div class="card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <h3>Historial de Pagos y Pesajes</h3>
                <p>Acceso permanente a las lecturas anteriores. La información se almacena para que cualquier duda sobre un día específico de recolección pueda ser verificada de manera rápida en las planillas digitales.</p>
            </article>

            <article class="info-card">
                <div class="card-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
                <h3>Acceso Móvil e Intuitivo</h3>
                <p>Diseñado para ser utilizado directamente en teléfonos celulares o tabletas en el centro de acopio o pesaje. Interfaz rápida, con botones visibles y tipografía clara pensada para el trabajo en el campo.</p>
            </article>
        </div>

        <!-- SECCIÓN: ¿CÓMO FUNCIONA? -->
        <section class="flow-section" id="como-funciona">
            <div class="section-header">
                <h2>¿Cómo Funciona Café Control?</h2>
                <p>Un flujo de trabajo sencillo estructurado en 4 pasos para garantizar rapidez en el pesado y exactitud en el pago.</p>
            </div>

            <div class="steps-container">
                <div class="step-box">
                    <div class="step-number">01</div>
                    <h4>Configuración del Precio</h4>
                    <p>El caficultor fija la tarifa actual que se pagará por cada kilogramo de café recolectado en la finca.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">02</div>
                    <h4>Pesaje y Registro</h4>
                    <p>En el punto de acopio o recibo, el encargado asigna los kilos pesados al usuario del recolector en el sistema.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">03</div>
                    <h4>Cálculo en Tiempo Real</h4>
                    <p>La aplicación multiplica el peso por la tarifa fijada y actualiza el saldo a favor del recolector de forma automática.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">04</div>
                    <h4>Consulta y Liquidación</h4>
                    <p>El recolector verifica sus kilos desde su celular y el caficultor realiza el pago semanal con el reporte listo.</p>
                </div>
            </div>
        </section>

        <!-- ROLES EN LA PLATAFORMA -->
        <div class="section-header">
            <h2>Gestión Basada en Roles</h2>
            <p>Cada usuario accede a las opciones específicas que necesita dentro de la finca.</p>
        </div>

        <div class="roles-container">
            <div class="role-card">
                <h4><i class="fa-solid fa-user-tie"></i> Caficultor</h4>
                <p>Supervisa la producción global de la finca, ajusta los precios del kilo de café, da de alta o inactiva usuarios y consulta los estados financieros acumulados de las nóminas.</p>
            </div>

            <div class="role-card">
                <h4><i class="fa-solid fa-user-gear"></i> Mayordomo</h4>
                <p>Gestiona el pesado en la báscula durante las jornadas diarias. Es el encargado de seleccionar al recolector, ingresar el peso de la tula y guardar la entrega en el sistema.</p>
            </div>

            <div class="role-card">
                <h4><i class="fa-solid fa-basket-shopping"></i> Recolector</h4>
                <p>Ingresa a su panel privado para revisar el total de kilos entregados en la semana, consultar el total acumulado en dinero y mantener actualizados sus datos personales.</p>
            </div>
        </div>
    </main>

    <!-- PIE DE PÁGINA -->
 <!-- PIE DE PÁGINA -->
<!-- PIE DE PÁGINA -->
<footer>
    <div class="footer-inner">
        <div class="footer-logo">
            <i class="fa-solid fa-mug-hot"></i>
            <span>Café Control</span>
        </div>
        <p class="footer-credits">
            <strong>Café Control</strong> © 2026 | Desarrolladores: <strong>Gloria Tatiana Sanchez &amp; Tatiana Silva Penagos</strong>
        </p>
    </div>
</footer>
</body>
</html>