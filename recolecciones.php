<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de entrega de café - Gestor de Cuentas</title>
    <!-- FontAwesome para los íconos de la interfaz -->
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
            --sidebar-active: #ffffff;
            --sidebar-active-text: #064e33;
            --bg-main: #f5f4ef;
            --card-bg: #fcfbf7;
            --card-inner-bg: #f4f2ea;
            --text-dark: #1b2e25;
            --text-muted: #5a6e63;
            --border-color: #e2decb;
            --btn-green: #0a6844;
            --btn-green-hover: #074e33;
            --alert-green-bg: #e6f4ea;
            --alert-green-text: #137333;
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

        /* Menú Central en Topbar */
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
            background-color: #085e3c;
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

        /* Sidebar Navegación Lateral */
        aside {
            width: 230px;
            background-color: var(--bg-sidebar);
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .sidebar-header-badge {
            text-align: center;
            margin-bottom: 20px;
            padding: 0 15px;
        }

        .badge-circle {
            width: 50px;
            height: 50px;
            background-color: #e2c185;
            color: #3d260f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 1.4rem;
        }

        .badge-title {
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 0 12px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            color: #d1e7dd;
            text-decoration: none;
            font-size: 0.9rem;
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
            max-width: 160px;
        }

        /* Area de Contenido Central */
        main {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
        }

        .main-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        /* Panel Title */
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 48px;
            height: 48px;
            background-color: var(--btn-green);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .title-text h2 {
            font-size: 1.35rem;
            color: var(--text-dark);
            font-weight: 800;
        }

        .title-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .location-badge {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .location-badge i {
            color: var(--btn-green);
            font-size: 1.2rem;
        }

        .location-text strong {
            display: block;
            font-size: 0.85rem;
            color: var(--text-dark);
        }

        .location-text span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Secciones del Formulario */
        .section-block {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #ffffff;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .section-header i {
            color: var(--btn-green);
            font-size: 1.1rem;
        }

        /* Paso 1: Buscador e Identificación */
        .search-row {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-group {
            flex: 1;
            min-width: 280px;
        }

        .search-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-dark);
        }

        .search-input-wrapper {
            display: flex;
            gap: 10px;
        }

        .search-input-wrapper input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            background-color: #faf9f5;
        }

        .btn-search {
            background-color: var(--btn-green);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-search:hover {
            background-color: var(--btn-green-hover);
        }

        .status-alert {
            background-color: var(--alert-green-bg);
            border: 1px solid #ceead6;
            border-radius: 10px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 250px;
        }

        .status-alert i {
            font-size: 1.5rem;
            color: var(--alert-green-text);
        }

        .status-alert .alert-text strong {
            display: block;
            font-size: 0.85rem;
            color: var(--alert-green-text);
        }

        .status-alert .alert-text span {
            font-size: 0.75rem;
            color: #1e8e3e;
        }

        /* Tarjeta de Datos del Recolector */
        .recollector-card {
            background-color: var(--card-inner-bg);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border-color);
        }

        .recollector-avatar {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
        }

        .recollector-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .recollector-name {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .recollector-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 30px;
            font-size: 0.85rem;
        }

        .recollector-grid-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
        }

        .recollector-grid-item i {
            color: var(--btn-green);
            width: 16px;
            text-align: center;
        }

        .recollector-grid-item span.label {
            color: var(--text-muted);
        }

        /* Paso 2: Entregas del día */
        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .delivery-card {
            background-color: var(--card-inner-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .delivery-tag {
            background-color: var(--btn-green);
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            align-self: flex-start;
        }

        .input-subgroup {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .input-subgroup label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .datetime-input, .weight-input {
            display: flex;
            align-items: center;
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            gap: 8px;
        }

        .datetime-input input, .weight-input input, .obs-input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.88rem;
            width: 100%;
            color: var(--text-dark);
        }

        .datetime-input i, .weight-input i {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .weight-input .weight-icon-bg {
            background-color: var(--btn-green);
            color: #ffffff;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .unit-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .obs-input {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .obs-input::placeholder {
            color: #aaa;
        }

        /* Botones de Acción Inferiores */
        .actions-bar {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-clean {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save {
            background-color: var(--btn-green);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-save:hover {
            background-color: var(--btn-green-hover);
        }

        /* Nota informativa */
        .info-note {
            background-color: var(--card-inner-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 16px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: var(--text-dark);
        }

        .info-note i {
            color: var(--btn-green);
            font-size: 1.1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .deliveries-grid {
                grid-template-columns: 1fr;
            }
            .recollector-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 850px) {
            .app-container {
                flex-direction: column;
            }
            aside {
                width: 100%;
            }
            .top-nav {
                display: none;
            }
            .panel-header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

    <!-- Header / Topbar Verde -->
    <header>
        <div class="header-brand">
            <div class="brand-logo-circle">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <div class="brand-title">
                <h1>Gestor de cuentas</h1>
                <p>de café recolectado por trabajador</p>
            </div>
        </div>

        <!-- Menú Superior de Navegación -->
        <nav class="top-nav">
            <a href="#" class="top-nav-item active"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="#" class="top-nav-item"><i class="fa-solid fa-circle-info"></i> Acerca del sistema</a>
            <a href="#" class="top-nav-item"><i class="fa-solid fa-circle-question"></i> ¿Cómo funciona?</a>
        </nav>

        <!-- Perfil del usuario actual -->
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="user-info">
                <div class="name">Mayordomo</div>
                <div class="role">Administrador</div>
            </div>
            <i class="fa-solid fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i>
        </div>
    </header>

    <!-- Layout Principal -->
    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <aside>
            <div>
                <div class="sidebar-header-badge">
                    <div class="badge-circle">
                        <i class="fa-solid fa-coffee"></i>
                    </div>
                    <div class="badge-title">Recolección<br>de Café</div>
                </div>

                <ul class="nav-list">
                    <li class="nav-item active"><a href="#"><i class="fa-solid fa-clipboard-list"></i> Nueva Entrega</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-clock-rotate-left"></i> Historial</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-users"></i> Recolecciones</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-sack-dollar"></i> Pagos</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-chart-column"></i> Reportes</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-gear"></i> Configuración</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-user-shield"></i> Usuarios</a></li>
                    <li class="nav-item"><a href="#"><i class="fa-solid fa-right-from-bracket"></i> Salir</a></li>
                </ul>
            </div>

            <div class="sidebar-illustration">
                <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=300&auto=format&fit=crop" style="border-radius: 12px; filter: brightness(0.9);" alt="Planta de café">
            </div>
        </aside>

        <!-- Main Content Panel -->
        <main>
            <div class="main-card">
                
                <!-- Encabezado del Panel y Ubicación -->
                <div class="panel-header">
                    <div class="panel-title">
                        <div class="title-icon">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div class="title-text">
                            <h2>Registro de entrega de café</h2>
                            <p>Registra las entregas de café realizadas por el recolector en el día.</p>
                        </div>
                    </div>

                    <div class="location-badge">
                        <i class="fa-solid fa-location-dot"></i>
                        <div class="location-text">
                            <strong>Teruel, Huila</strong>
                            <span>Colombia</span>
                        </div>
                    </div>
                </div>

                <!-- Paso 1: Identificar Recolector -->
                <div class="section-block">
                    <div class="section-header">
                        <i class="fa-solid fa-user"></i>
                        <span>1. Identificar recolector</span>
                    </div>

                    <div class="search-row">
                        <div class="search-group">
                            <label for="cedula">Número de cédula del recolector</label>
                            <div class="search-input-wrapper">
                                <input type="text" id="cedula" value="1075263894">
                                <button class="btn-search">
                                    <i class="fa-solid fa-magnifying-glass"></i> Buscar
                                </button>
                            </div>
                        </div>

                        <div class="status-alert">
                            <i class="fa-solid fa-circle-check"></i>
                            <div class="alert-text">
                                <strong>Recolector encontrado</strong>
                                <span>Información cargada correctamente</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta con la información del recolector cargado -->
                    <div class="recollector-card">
                        <img class="recollector-avatar" src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=150&auto=format&fit=crop" alt="Foto Recolector">
                        
                        <div class="recollector-details">
                            <div class="recollector-name">Juan Carlos Ramírez Gómez</div>
                            
                            <div class="recollector-grid">
                                <div class="recollector-grid-item">
                                    <i class="fa-solid fa-id-card"></i>
                                    <span class="label">Cédula:</span>
                                    <strong>1075263894</strong>
                                </div>
                                <div class="recollector-grid-item">
                                    <i class="fa-solid fa-location-arrow"></i>
                                    <span class="label">Finca / Vereda:</span>
                                    <strong>Buenos Aires / La Esperanza</strong>
                                </div>
                                <div class="recollector-grid-item">
                                    <i class="fa-solid fa-phone"></i>
                                    <span class="label">Teléfono:</span>
                                    <strong>312 456 7890</strong>
                                </div>
                                <div class="recollector-grid-item">
                                    <i class="fa-solid fa-user-tag"></i>
                                    <span class="label">Rol:</span>
                                    <strong>Recolector</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 2: Registrar Entregas del Día -->
                <div class="section-block">
                    <div class="section-header">
                        <i class="fa-solid fa-basket-shopping"></i>
                        <span>2. Registrar entregas del día (3 entregas)</span>
                    </div>

                    <div class="deliveries-grid">
                        
                        <!-- Entrega 1 -->
                        <div class="delivery-card">
                            <div class="delivery-tag">
                                <i class="fa-solid fa-basket-shopping"></i> Entrega 1
                            </div>

                            <div class="input-subgroup">
                                <label>Fecha y hora</label>
                                <div class="datetime-input">
                                    <i class="fa-regular fa-calendar"></i>
                                    <input type="text" value="16/07/2025  08:30 a. m.">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                            </div>

                            <div class="input-subgroup">
                                <label>Peso entregado (kg)</label>
                                <div class="weight-input">
                                    <div class="weight-icon-bg"><i class="fa-solid fa-scale-balanced"></i></div>
                                    <input type="text" value="12.5">
                                    <span class="unit-label">kg</span>
                                </div>
                            </div>

                            <div class="input-subgroup">
                                <label>Observaciones (opcional)</label>
                                <input type="text" class="obs-input" placeholder="Ej: Café maduro, buena calidad" value="Ej: Café maduro, buena calidad">
                            </div>
                        </div>

                        <!-- Entrega 2 -->
                        <div class="delivery-card">
                            <div class="delivery-tag">
                                <i class="fa-solid fa-basket-shopping"></i> Entrega 2
                            </div>

                            <div class="input-subgroup">
                                <label>Fecha y hora</label>
                                <div class="datetime-input">
                                    <i class="fa-regular fa-calendar"></i>
                                    <input type="text" value="16/07/2025  12:30 p. m.">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                            </div>

                            <div class="input-subgroup">
                                <label>Peso entregado (kg)</label>
                                <div class="weight-input">
                                    <div class="weight-icon-bg"><i class="fa-solid fa-scale-balanced"></i></div>
                                    <input type="text" value="13.0">
                                    <span class="unit-label">kg</span>
                                </div>
                            </div>

                            <div class="input-subgroup">
                                <label>Observaciones (opcional)</label>
                                <input type="text" class="obs-input" placeholder="Ej: Café maduro" value="Ej: Café maduro">
                            </div>
                        </div>

                        <!-- Entrega 3 -->
                        <div class="delivery-card">
                            <div class="delivery-tag">
                                <i class="fa-solid fa-basket-shopping"></i> Entrega 3
                            </div>

                            <div class="input-subgroup">
                                <label>Fecha y hora</label>
                                <div class="datetime-input">
                                    <i class="fa-regular fa-calendar"></i>
                                    <input type="text" value="16/07/2025  04:30 p. m.">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                            </div>

                            <div class="input-subgroup">
                                <label>Peso entregado (kg)</label>
                                <div class="weight-input">
                                    <div class="weight-icon-bg"><i class="fa-solid fa-scale-balanced"></i></div>
                                    <input type="text" value="12.0">
                                    <span class="unit-label">kg</span>
                                </div>
                            </div>

                            <div class="input-subgroup">
                                <label>Observaciones (opcional)</label>
                                <input type="text" class="obs-input" placeholder="Ej: Buena calidad" value="Ej: Buena calidad">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="actions-bar">
                    <button class="btn-clean">
                        <i class="fa-solid fa-broom"></i> Limpiar
                    </button>
                    <button class="btn-save">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar entrega
                    </button>
                </div>

                <!-- Nota Informativa al pie -->
                <div class="info-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><strong>Nota:</strong> Las tres entregas se suman automáticamente para obtener el total de kilos recolectados en el día.</span>
                </div>

            </div>
        </main>
    </div>

</body>
</html>