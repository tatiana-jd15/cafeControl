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

        /* --- SECCIÓN 1: Delegar Mayordomo --- */
        .grid-mayordomo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .sub-box {
            background-color: var(--card-inner-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sub-box-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text-dark);
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .input-search-row {
            display: flex;
            gap: 10px;
        }

        .input-search-row input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
            font-size: 0.88rem;
        }

        .btn-search {
            background-color: var(--btn-green);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: #f0eee6;
            outline: none;
            font-size: 0.88rem;
            color: var(--text-dark);
        }

        /* Checkboxes de Permisos */
        .permissions-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .permission-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .permission-item input[type="checkbox"] {
            margin-top: 3px;
            accent-color: var(--btn-green);
            width: 16px;
            height: 16px;
        }

        .permission-info strong {
            display: block;
            font-size: 0.85rem;
            color: var(--text-dark);
        }

        .permission-info span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .btn-save-right {
            align-self: flex-end;
            margin-top: 15px;
            background-color: var(--btn-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* --- SECCIÓN 2: Precio del Kilo --- */
        .grid-price {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        .price-content {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .price-inputs-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .current-price-card {
            background-color: var(--card-inner-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 15px 20px;
            flex: 1;
        }

        .current-price-card label {
            font-size: 0.8rem;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }

        .price-value-display {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .price-value-display i {
            color: var(--btn-green);
            font-size: 1.2rem;
        }

        .new-price-card {
            flex: 1;
        }

        .new-price-card label {
            font-size: 0.8rem;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }

        .input-price-wrapper {
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: white;
            padding: 0 10px;
        }

        .input-price-wrapper span {
            font-weight: 600;
            color: var(--text-muted);
            margin-right: 5px;
        }

        .input-price-wrapper input {
            width: 100%;
            padding: 8px 0;
            border: none;
            outline: none;
            font-weight: 600;
        }

        .history-box {
            border-left: 1px solid var(--border-color);
            padding-left: 20px;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .history-item {
            font-size: 0.82rem;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 6px;
        }

        .history-item strong {
            display: block;
            color: var(--text-dark);
            font-size: 0.88rem;
        }

        .history-item span {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        /* --- SECCIÓN 3: Tabla de Recolectores --- */
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
            .grid-mayordomo, .grid-price {
                grid-template-columns: 1fr;
            }
            .history-box {
                border-left: none;
                border-top: 1px solid var(--border-color);
                padding-left: 0;
                padding-top: 15px;
            }
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
                <h1>Gestor de cuentas</h1>
                <p>de café recolectado por trabajador</p>
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
                <li class="nav-item"><a href="#"><i class="fa-solid fa-users"></i> Recolectores</a></li>
                <li class="nav-item active"><a href="#"><i class="fa-solid fa-user-gear"></i> Mayordomos</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-tags"></i> Precios del café</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-chart-column"></i> Reportes</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-gear"></i> Configuración</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-shield-halved"></i> Usuarios</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-lock"></i> Permisos</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-pulse"></i> Actividad</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
            </ul>

            <div class="sidebar-illustration">
                <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=300&auto=format&fit=crop" style="border-radius: 12px; filter: brightness(0.9);" alt="Café">
            </div>
        </aside>

        <!-- Main Content -->
        <main>

            <!-- 1. DELEGAR MAYORDOMO -->
            <div class="section-card">
                <div class="section-title">
                    <div class="title-icon"><i class="fa-solid fa-user"></i></div>
                    <div class="title-text">
                        <h2>1. Delegar mayordomo</h2>
                        <p>Asigne una persona como mayordomo y configure sus permisos en el sistema.</p>
                    </div>
                </div>

                <div class="grid-mayordomo">
                    <!-- Formulario información mayordomo -->
                    <div class="sub-box">
                        <div class="sub-box-title">Información del mayordomo</div>
                        
                        <div class="form-group">
                            <label>Número de documento</label>
                            <div class="input-search-row">
                                <input type="text" value="1085925030">
                                <button class="btn-search">Buscar</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nombre completo</label>
                            <input type="text" class="form-control" value="Carlos Andrés Martínez" readonly>
                        </div>

                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" class="form-control" value="312 456 7890" readonly>
                        </div>

                        <div class="form-group">
                            <label>Correo electrónico</label>
                            <input type="text" class="form-control" value="carlos.martinez@finca.com" readonly>
                        </div>
                    </div>

                    <!-- Permisos asignados -->
                    <div class="sub-box">
                        <div>
                            <div class="sub-box-title">Permisos asignados</div>
                            <div class="permissions-list">
                                <label class="permission-item">
                                    <input type="checkbox" checked>
                                    <div class="permission-info">
                                        <strong>Registrar recolecciones</strong>
                                        <span>Puede registrar las entregas de café de los recolectores.</span>
                                    </div>
                                </label>

                                <label class="permission-item">
                                    <input type="checkbox" checked>
                                    <div class="permission-info">
                                        <strong>Editar recolecciones</strong>
                                        <span>Puede editar las recolecciones registradas.</span>
                                    </div>
                                </label>

                                <label class="permission-item">
                                    <input type="checkbox" checked>
                                    <div class="permission-info">
                                        <strong>Eliminar recolectores</strong>
                                        <span>Puede eliminar recolectores de la base de datos.</span>
                                    </div>
                                </label>

                                <label class="permission-item">
                                    <input type="checkbox" checked>
                                    <div class="permission-info">
                                        <strong>Ver reportes</strong>
                                        <span>Puede visualizar reportes y resúmenes.</span>
                                    </div>
                                </label>

                                <label class="permission-item">
                                    <input type="checkbox" checked>
                                    <div class="permission-info">
                                        <strong>Gestionar mayordomos</strong>
                                        <span>Puede asignar o modificar mayordomos.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button class="btn-save-right">
                            <i class="fa-solid fa-user-plus"></i> Guardar mayordomo
                        </button>
                    </div>
                </div>
            </div>

        </main>
    </div>

</body>
</html>