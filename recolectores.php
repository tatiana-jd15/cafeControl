<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión - Gestor de Cuentas</title>
    <!-- FontAwesome para los íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Reset y Variables de Color --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        :root {
            --bg-topbar: #033f28;
            --bg-sidebar: #064e33;
            --sidebar-active: #e2decb;
            --sidebar-active-text: #064e33;
            --bg-main: #f5f4ef;
            --card-bg: #ffffff;
            --card-inner-bg: #f9f8f3;
            --text-dark: #1b2e25;
            --text-muted: #5a6e63;
            --border-color: #e2decb;
            --btn-green: #0d5c38;
            --btn-green-hover: #084328;
            --status-active-bg: #d1e7dd;
            --status-active-text: #0f5132;
            --status-inactive-bg: #e2e3e5;
            --status-inactive-text: #41464b;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* --- Barra Superior (Topbar) --- */
        header {
            background-color: var(--bg-topbar);
            color: #ffffff;
            height: 65px;
            padding: 0 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo-circle {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b31919;
            font-size: 1.3rem;
        }

        .brand-title h1 {
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .brand-title p {
            font-size: 0.78rem;
            color: #b0d4c5;
        }

        .top-nav {
            display: flex;
            gap: 15px;
        }

        .top-nav-item {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .top-nav-item.active {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .top-nav-item:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 6px 14px;
            border-radius: 20px;
            cursor: pointer;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background-color: #ffffff;
            color: var(--bg-topbar);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-info .name {
            font-size: 0.82rem;
            font-weight: 700;
        }

        .user-info .role {
            font-size: 0.72rem;
            color: #b0d4c5;
        }

        /* --- Layout Principal --- */
        .app-container {
            display: flex;
            flex: 1;
        }

        /* Sidebar Lateral */
        aside {
            width: 220px;
            background-color: var(--bg-sidebar);
            padding: 15px 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 0 10px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: #d1e7dd;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-item a:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .nav-item.active a {
            background-color: var(--sidebar-active);
            color: var(--sidebar-active-text);
            font-weight: 700;
        }

        .sidebar-illustration {
            padding: 15px;
            text-align: center;
        }

        .sidebar-illustration img {
            width: 100%;
            max-width: 150px;
        }

        /* Area de Contenido Central */
        main {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* --- Estilos Generales de Secciones --- */
        .section-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .title-icon {
            width: 40px;
            height: 40px;
            background-color: var(--btn-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .title-text h2 {
            font-size: 1.1rem;
            color: var(--text-dark);
            font-weight: 700;
        }

        .title-text p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* --- Tabla de Recolectores --- */
        .table-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .btn-add-recollector {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .recollectors-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        .recollectors-table th {
            background-color: var(--card-inner-bg);
            padding: 10px 12px;
            color: var(--text-muted);
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
        }

        .recollectors-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        .status-pill {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }

        .status-pill.active {
            background-color: var(--status-active-bg);
            color: var(--status-active-text);
        }

        .status-pill.inactive {
            background-color: var(--status-inactive-bg);
            color: var(--status-inactive-text);
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-icon.edit { color: var(--btn-green); }
        .btn-icon.delete { color: #dc3545; }

        /* Footer Alert */
        .footer-note {
            background-color: var(--card-inner-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.82rem;
            color: var(--text-dark);
        }

        .footer-note i {
            color: var(--btn-green);
            font-size: 1.1rem;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .top-nav { display: none; }
        }
    </style>
</head>
<body>

    <!-- Header Topbar -->
    <header>
        <div class="header-brand">
            <div class="brand-logo-circle">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <div class="brand-title">
                <h1>Café control</h1>
               
            </div>
        </div>

        <nav class="top-nav">
            <a href="#" class="top-nav-item"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="#" class="top-nav-item"><i class="fa-solid fa-circle-info"></i> Acerca del sistema</a>
            <a href="#" class="top-nav-item"><i class="fa-solid fa-circle-question"></i> ¿Cómo funciona?</a>
        </nav>

        <div class="user-profile">
            <div class="user-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="user-info">
                <div class="name">Fincario</div>
                <div class="role">Administrador</div>
            </div>
            <i class="fa-solid fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i>
        </div>
    </header>

    <!-- Layout Principal -->
    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <aside>
            <ul class="nav-list">
                <li class="nav-item"><a href="#"><i class="fa-solid fa-house"></i> Panel principal</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-basket-shopping"></i> Recolecciones</a></li>
                <li class="nav-item active"><a href="#"><i class="fa-solid fa-users"></i> Recolectores</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-chart-column"></i> Reportes</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-gear"></i> Configuración</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-shield-halved"></i> Usuarios</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-lock"></i> Permisos</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-pulse"></i> Actividad</a></li>
                <li class="nav-item"><a href="cerrar.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
            </ul>

            <div class="sidebar-illustration">
                <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=300&auto=format&fit=crop" style="border-radius: 12px; filter: brightness(0.9);" alt="Café">
            </div>
        </aside>

        <!-- Main Content -->
        <main>

            <!-- GESTIONAR RECOLECTORES -->
            <div class="section-card">
                <div class="table-header-actions">
                    <div class="section-title" style="margin-bottom: 0;">
                        <div class="title-icon"><i class="fa-solid fa-basket-shopping"></i></div>
                        <div class="title-text">
                            <h2>Gestionar recolectores</h2>
                            <p>Administre los recolectores registrados en el sistema.</p>
                        </div>
                    </div>

                    <button class="btn-add-recollector">
                        <i class="fa-solid fa-plus"></i> Nuevo recolector
                    </button>
                </div>

                <table class="recollectors-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre completo</th>
                            <th>Documento</th>
                            <th>Teléfono</th>
                            <th>Vereda / Finca</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>Juan Carlos Ramírez Gómez</strong></td>
                            <td>1075263894</td>
                            <td>312 456 7890</td>
                            <td>Buenos Aires / La Esperanza</td>
                            <td><span class="status-pill active">Activo</span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon edit"><i class="fa-regular fa-pen-to-square"></i></button>
                                    <button class="btn-icon delete"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><strong>María Fernanda López</strong></td>
                            <td>1087654321</td>
                            <td>310 987 6543</td>
                            <td>Buenos Aires / La Esperanza</td>
                            <td><span class="status-pill active">Activo</span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon edit"><i class="fa-regular fa-pen-to-square"></i></button>
                                    <button class="btn-icon delete"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><strong>Luis Alberto Vargas</strong></td>
                            <td>1065432109</td>
                            <td>311 234 5678</td>
                            <td>La Primavera / El Porvenir</td>
                            <td><span class="status-pill active">Activo</span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon edit"><i class="fa-regular fa-pen-to-square"></i></button>
                                    <button class="btn-icon delete"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Pedro Antonio Ruiz</strong></td>
                            <td>1034567890</td>
                            <td>314 678 9012</td>
                            <td>El Roble / Santa Teresa</td>
                            <td><span class="status-pill inactive">Inactivo</span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon edit"><i class="fa-regular fa-pen-to-square"></i></button>
                                    <button class="btn-icon delete"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Informativo -->
            <div class="footer-note">
                <i class="fa-solid fa-circle-info"></i>
                <span><strong>Nota:</strong> Como fincario (administrador) tiene control total del sistema y puede gestionar la información de los recolectores de su finca.</span>
            </div>

        </main>
    </div>

</body>
</html>