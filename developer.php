<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Desarrolladoras - Café Control</title>
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --primary: #2C1810;
      --primary-light: #4A2E2B;
      --accent: #C88A4A;
      --accent-soft: #F4ECE1;
      --brand-green: #2E5A36;
      --bg-body: #FAF7F2;
      --surface: #FFFFFF;
      --text-dark: #1E1E1E;
      --text-muted: #656565;
      --border-color: #E8E2D9;
      --radius-lg: 20px;
      --radius-md: 12px;
      --shadow-soft: 0 10px 30px rgba(44, 24, 16, 0.05);
      --shadow-hover: 0 15px 35px rgba(44, 24, 16, 0.12);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    body {
      background-color: var(--bg-body);
      color: var(--text-dark);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      padding: 20px 0;
    }

    /* CONTENEDOR PRINCIPAL */
    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* HERO / HEADER CON LOGO DE CAFÉ CONTROL */
    .hero-header {
      text-align: center;
      padding: 45px 25px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      border-radius: var(--radius-lg);
      margin-bottom: 30px;
      box-shadow: var(--shadow-soft);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .project-logo-badge {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      overflow: hidden;
      border: 3px solid var(--accent);
      box-shadow: 0 6px 15px rgba(0,0,0,0.3);
      margin-bottom: 18px;
      background-color: #FAF5EB;
    }

    .project-logo-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .hero-header h1 {
      font-size: 2.5rem;
      font-weight: 800;
      letter-spacing: -0.5px;
      margin-bottom: 8px;
    }

    .hero-header p {
      font-size: 1.1rem;
      color: rgba(255, 255, 255, 0.85);
      font-weight: 400;
      max-width: 580px;
      margin: 0 auto;
    }

    /* SECCIONES Y TARJETAS */
    .section {
      background: var(--surface);
      padding: 32px;
      border-radius: var(--radius-lg);
      margin-bottom: 24px;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-soft);
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--primary);
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 18px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--accent-soft);
    }

    .section-title i {
      color: var(--accent);
    }

    .section p {
      color: var(--text-muted);
      font-size: 1.02rem;
      line-height: 1.7;
    }

    /* ENCABEZADO CON LOGOS EN EQUIPO EN ACCIÓN */
    .section-header-logos {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid var(--accent-soft);
      padding-bottom: 12px;
      margin-bottom: 18px;
    }

    .section-header-logos .section-title {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .header-logo {
      height: 52px;
      width: auto;
      object-fit: contain;
    }

    .inst-logo {
      border-radius: 50%;
    }

    /* GALERÍA DE IMÁGENES */
    .gallery {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 10px;
    }

    .gallery-item {
      position: relative;
      border-radius: var(--radius-md);
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      height: 240px;
    }

    .gallery-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .gallery-item:hover img {
      transform: scale(1.04);
    }

    /* FRANJA DE INFORMACIÓN DE PROGRAMA (IZQ) Y FICHA + LOGOS (DER) */
    .team-header-info {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 15px;
      background-color: var(--bg-body);
      padding: 16px 22px;
      border-radius: var(--radius-md);
      margin-bottom: 24px;
      border: 1px solid var(--border-color);
    }

    .team-header-info p {
      font-size: 0.98rem;
      color: var(--text-dark);
      margin: 0;
    }

    /* CONTENEDOR DERECHO: LOGOS Y FICHA */
    .right-info-wrapper {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-left: auto;
    }

    .badge-logo {
      height: 48px; /* Tamaño de logo aumentado */
      width: auto;
      object-fit: contain;
    }

    .badge-single {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background-color: var(--accent-soft);
      color: var(--primary);
      padding: 6px 18px;
      border-radius: 30px;
      font-size: 0.9rem;
      font-weight: 700;
      border: 1px solid rgba(200, 138, 74, 0.3);
    }

    /* GRID Y TARJETAS DE DESARROLLADORAS */
    .team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .developer-card {
      background: var(--surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 25px;
      text-align: center;
      transition: all 0.3s ease;
    }

    .developer-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-hover);
      border-color: var(--accent);
    }

    .avatar-wrapper {
      width: 75px;
      height: 75px;
      margin: 0 auto 15px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent-soft), #FFFFFF);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      color: var(--primary);
      border: 2px solid var(--accent);
    }

    .developer-card h3 {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 6px;
    }

    .developer-role {
      display: inline-block;
      font-size: 0.85rem;
      color: var(--brand-green);
      font-weight: 600;
      background-color: rgba(46, 90, 54, 0.08);
      padding: 4px 12px;
      border-radius: 12px;
      margin-bottom: 16px;
    }

    .email-container {
      display: flex;
      flex-direction: column;
      gap: 10px;
      text-align: left;
      background: var(--bg-body);
      padding: 12px 16px;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-color);
    }

    .email-item {
      display: flex;
      flex-direction: column;
      font-size: 0.88rem;
    }

    .email-label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--primary-light);
      margin-bottom: 2px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .email-value {
      color: var(--text-dark);
      font-weight: 500;
      word-break: break-all;
    }

    /* PIE DE PÁGINA */
    footer {
      text-align: center;
      padding: 30px 20px 10px;
      font-size: 0.92rem;
      color: var(--text-muted);
    }

    footer a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
    }

    footer a:hover {
      color: var(--accent);
    }

    footer p + p {
      margin-top: 6px;
      font-size: 0.85rem;
      opacity: 0.8;
    }

    @media (max-width: 600px) {
      .hero-header h1 {
        font-size: 2rem;
      }
      .section {
        padding: 22px;
      }
      .team-header-info {
        flex-direction: column;
        align-items: flex-start;
      }
      .right-info-wrapper {
        width: 100%;
        justify-content: flex-end;
        flex-wrap: wrap;
      }
      .section-header-logos {
        flex-wrap: wrap;
        gap: 10px;
      }
    }
  </style>
</head>
<body>

  <!-- CONTENEDOR PRINCIPAL -->
  <div class="container">
    <header class="hero-header">
      <!-- LOGO DEL PROYECTO -->
      <div class="project-logo-badge">
        <img src="images/logo.jpeg" alt="Logo Café Control" class="project-logo-img">
      </div>
      <h1>Café Control</h1>
      <p>Equipo de Desarrollo & Programación</p>
    </header>

    <!-- Resumen general -->
    <section class="section">
      <h2 class="section-title">
        <i class="fa-solid fa-circle-info"></i>
        Acerca de la Plataforma
      </h2>
      <p>
        <strong>Café Control</strong> es una plataforma web optimizada diseñada para facilitar la administración, el monitoreo y el seguimiento eficiente de los procesos relacionados con la gestión cafetera. A través de una interfaz intuitiva, brinda herramientas clave que permiten mejorar la productividad y el control operativo en cada fase de trabajo.
      </p>
    </section>

    <!-- Fotos del equipo trabajando -->
    <section class="section">
      <div class="section-header-logos">
        <h2 class="section-title">
          <i class="fa-solid fa-users-gear"></i>
          Equipo en Acción
        </h2>
        <img src="images/lcole.png" alt="Logo IE Misael Pastrana Borrero" class="header-logo inst-logo">
      </div>
      <div class="gallery">
        <div class="gallery-item">
          <img src="images/1.jpg" alt="Desarrolladoras programando">
        </div>
        <div class="gallery-item">
          <img src="images/2.jpg" alt="Desarrolladoras en el aula de cómputo">
        </div>
      </div>
    </section>

    <!-- Propósito -->
    <section class="section">
      <h2 class="section-title">
        <i class="fa-solid fa-bullseye"></i>
        Propósito de la Aplicación
      </h2>
      <p>
        El propósito de la aplicación es brindar una solución tecnológica integral que simplifique la recolección, sistematización y análisis de datos en la industria del café, garantizando una toma de decisiones más precisa y optimizando la trazabilidad del producto.
      </p>
    </section>

    <!-- Creadoras / Desarrolladoras -->
    <section class="section">
      <h2 class="section-title">
        <i class="fa-solid fa-laptop-code"></i>
        Equipo de Creadoras
      </h2>
      
      <!-- Franja: Programa a la izquierda | Logos + Ficha a la derecha -->
      <div class="team-header-info">
        <p><strong>Programa:</strong> Técnico en Programación de Software</p>
        
        <div class="right-info-wrapper">
          <span class="badge-single"><i class="fa-solid fa-bookmark"></i> Ficha 3161419</span>
          <img src="images/lsena.png" alt="Logo SENA" class="badge-logo">
        </div>
      </div>
      
      <div class="team-grid">
        <!-- Desarrolladora 1 -->
        <div class="developer-card">
          <div class="avatar-wrapper">
            <i class="fa-solid fa-user-gear"></i>
          </div>
          <h3>Gloria Tatiana Sánchez Zapata</h3>
          <span class="developer-role">Programadora de Software</span>
          
          <div class="email-container">
            <div class="email-item">
              <span class="email-label"><i class="fa-regular fa-envelope"></i> Correo Personal</span>
              <span class="email-value">zsancheztatiana25@gmail.com</span>
            </div>
            <div class="email-item">
              <span class="email-label"><i class="fa-solid fa-graduation-cap"></i> Correo Institucional</span>
              <span class="email-value">Gtsz34942342@soy.sena.edu.co</span>
            </div>
          </div>
        </div>

        <!-- Desarrolladora 2 -->
        <div class="developer-card">
          <div class="avatar-wrapper">
            <i class="fa-solid fa-user-gear"></i>
          </div>
          <h3>Tatiana Silva Penagos</h3>
          <span class="developer-role">Programadora de Software</span>
          
          <div class="email-container">
            <div class="email-item">
              <span class="email-label"><i class="fa-regular fa-envelope"></i> Correo Personal</span>
              <span class="email-value">tatianasilvapenagos@gmail.com</span>
            </div>
            <div class="email-item">
              <span class="email-label"><i class="fa-solid fa-graduation-cap"></i> Correo Institucional</span>
              <span class="email-value">tsp34952452@soy.sena.edu.co</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Contacto general -->
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

  </div>
</body>
</html>