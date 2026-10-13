<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café control</title>
    <!-- FontAwesome para los íconos de la interfaz -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Reset y Variables --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        :root {
            --bg-dark-brown: #23160c;
            --bg-sidebar: #1d1209;
            --sidebar-active: #a67c52;
            --bg-content: #f5eedf;
            --card-bg: #fbf7f0;
            --table-header: #412917;
            --text-dark: #2b1f17;
            --text-muted: #726458;
            --border-color: #e5dbcc;
            --btn-brown: #4a2e1b;
        }

        body {
            background-color: var(--bg-content);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* --- Barra Superior (Topbar) --- */
        header {
            background-color: var(--bg-dark-brown);
            color: #ffffff;
            height: 60px;
            padding: 0 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #362214;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-logo-circle {
            width: 38px;
            height: 38px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #702821;
            font-size: 1.2rem;
        }

        .brand-title h1 {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .brand-title p {
            font-size: 0.75rem;
            color: #c9b8a8;
        }

        .menu-toggle-btn {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 1.2rem;
            cursor: pointer;
            margin-left: 10px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background-color: #ffffff;
            color: var(--text-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-info .name {
            font-size: 0.85rem;
            font-weight: 700;
        }

        .user-info .role {
            font-size: 0.75rem;
            color: #c9b8a8;
        }

        /* --- Layout Principal --- */
        .app-container {
            display: flex;
            flex: 1;
        }

        /* Sidebar Navegación Lateral */
        aside {
            width: 240px;
            background-color: var(--bg-sidebar);
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 0 10px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 18px;
            color: #e2d7cd;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-item a:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active a {
            background-color: var(--sidebar-active);
            color: #ffffff;
            font-weight: 700;
        }

        .sidebar-illustration {
            padding: 15px;
            text-align: center;
        }

        .sidebar-illustration img {
            width: 100%;
            max-width: 170px;
            border-radius: 12px;
        }

        /* Area de Contenido Central */
        main {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
        }

        .content-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        /* Cabecera del Panel e Insumos */
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .title-icon {
            width: 42px;
            height: 42px;
            background-color: var(--table-header);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .title-text h2 {
            font-size: 1.3rem;
            color: var(--text-dark);
        }

        .title-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .panel-controls {
            display: flex;
            gap: 10px;
        }

        .control-btn {
            background-color: #ffffff;
            border: 1px solid #d8ccbc;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        /* Estilos de Tabla */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
        }

        thead {
            background-color: var(--table-header);
            color: #ffffff;
        }

        th {
            padding: 12px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: left;
        }

        tbody tr {
            border-bottom: 1px solid var(--border-color);
            background-color: #ffffff;
            transition: background 0.15s;
        }

        tbody tr:nth-child(even) {
            background-color: #fdfaf5;
        }

        tbody tr:hover {
            background-color: #f5ede0;
        }

        td {
            padding: 12px 16px;
            font-size: 0.9rem;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border-color);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar-sm {
            width: 30px;
            height: 30px;
            background-color: #d1c0ae;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            font-size: 0.8rem;
        }

        .bold-text {
            font-weight: 700;
        }

        .action-arrow {
            color: var(--text-dark);
            text-align: right;
            cursor: pointer;
        }

        /* --- Sección Inferior: Detalle del Trabajador --- */
        .worker-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .worker-profile-summary {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .worker-profile-avatar {
            width: 55px;
            height: 55px;
            background-color: #c2b09c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #ffffff;
        }

        .worker-profile-summary h3 {
            font-size: 1.4rem;
            color: var(--text-dark);
        }

        .stats-cards {
            display: flex;
            gap: 12px;
        }

        .stat-card {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-icon {
            width: 35px;
            height: 35px;
            background-color: var(--card-bg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: var(--table-header);
        }

        .stat-info .label {
            font-size: 0.72rem;
            color: var(--text-muted);
            display: block;
        }

        .stat-info .value {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .btn-view-payments {
            background-color: var(--btn-brown);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }

        .page-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            cursor: pointer;
            color: var(--text-dark);
        }

        .page-btn.active {
            background-color: var(--table-header);
            color: white;
            border-color: var(--table-header);
            font-weight: bold;
        }

        /* --- Footer Bottom Bar --- */
        footer {
            background-color: #e5dacb;
            text-align: center;
            padding: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .app-container {
                flex-direction: column;
            }
            aside {
                width: 100%;
            }
            .sidebar-illustration {
                display: none;
            }
            .worker-detail-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-cards {
                flex-wrap: wrap;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Header / Barra Superior -->
    <header>
        <div class="header-brand">
            <div class="brand-logo-circle">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <div class="brand-title">
                <h1>Café control</h1>
            </div>
            <button class="menu-toggle-btn"><i class="fa-solid fa-bars"></i></button>
        </div>

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

    <!-- App Main Layout -->
    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <aside>
            <ul class="nav-list">
                <li class="nav-item"><a href="#"><i class="fa-solid fa-gauge-high"></i> Resumen general</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-users"></i> Trabajadores</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-basket-shopping"></i> Recolecciones</a></li>
                <li class="nav-item active"><a href="#"><i class="fa-solid fa-clock-rotate-left"></i> Historial</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-sack-dollar"></i> Pagos</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-chart-column"></i> Reportes</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-house-chimney"></i> Fincas</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-gear"></i> Configuración</a></li>
                <li class="nav-item"><a href="#"><i class="fa-solid fa-user-shield"></i> Usuarios</a></li>
            </ul>

            <!-- Imagen ilustrativa al final del Sidebar -->
            <div class="sidebar-illustration">
                <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=300&auto=format&fit=crop" alt="Saco de café">
            </div>
        </aside>

        <!-- Main Content Area -->
        <main>
            
            <!-- TARJETA 1: Tabla General de Historial por Trabajador -->
            <div class="content-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <div class="title-icon">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <div class="title-text">
                            <h2>Historial de trabajadores</h2>
                            <p>Consulta el historial de kilos recolectados por cada trabajador.</p>
                        </div>
                    </div>

                    <div class="panel-controls">
                        <button class="control-btn"><i class="fa-regular fa-calendar"></i> 01/05/2024 - 31/05/2024 <i class="fa-solid fa-chevron-down"></i></button>
                        <button class="control-btn"><i class="fa-solid fa-download"></i> Exportar</button>
                        <button class="control-btn"><i class="fa-solid fa-filter"></i> Filtros</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Cédula</th>
                                <th>Recolector</th>
                                <th>Kilos recolectados</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1052389476</td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><i class="fa-solid fa-user"></i></div>
                                        <span>Juan Pérez</span>
                                    </div>
                                </td>
                                <td class="bold-text">125.5 kg</td>
                                <td><i class="fa-regular fa-calendar"></i> 01/05/2024 - 31/05/2024</td>
                                <td class="action-arrow"><i class="fa-solid fa-chevron-right"></i></td>
                            </tr>
                            <tr>
                                <td>1045698231</td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><i class="fa-solid fa-user"></i></div>
                                        <span>María García</span>
                                    </div>
                                </td>
                                <td class="bold-text">98.7 kg</td>
                                <td><i class="fa-regular fa-calendar"></i> 01/05/2024 - 31/05/2024</td>
                                <td class="action-arrow"><i class="fa-solid fa-chevron-right"></i></td>
                            </tr>
                            <tr>
                                <td>1032456789</td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><i class="fa-solid fa-user"></i></div>
                                        <span>Carlos López</span>
                                    </div>
                                </td>
                                <td class="bold-text">87.3 kg</td>
                                <td><i class="fa-regular fa-calendar"></i> 01/05/2024 - 31/05/2024</td>
                                <td class="action-arrow"><i class="fa-solid fa-chevron-right"></i></td>
                            </tr>
                            <tr>
                                <td>1009876543</td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><i class="fa-solid fa-user"></i></div>
                                        <span>Ana Torres</span>
                                    </div>
                                </td>
                                <td class="bold-text">76.2 kg</td>
                                <td><i class="fa-regular fa-calendar"></i> 01/05/2024 - 31/05/2024</td>
                                <td class="action-arrow"><i class="fa-solid fa-chevron-right"></i></td>
                            </tr>
                            <tr>
                                <td>1076543210</td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><i class="fa-solid fa-user"></i></div>
                                        <span>Luis Hernández</span>
                                    </div>
                                </td>
                                <td class="bold-text">65.4 kg</td>
                                <td><i class="fa-regular fa-calendar"></i> 01/05/2024 - 31/05/2024</td>
                                <td class="action-arrow"><i class="fa-solid fa-chevron-right"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TARJETA 2: Detalle del Trabajador Seleccionado (Juan Pérez) -->
            <div class="content-card">
                
                <div class="worker-de