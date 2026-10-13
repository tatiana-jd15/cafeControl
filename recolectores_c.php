<?php
session_start();

// Incluir la conexión a la base de datos
require_once 'assets/inc/conexion.php'; 

// Validar sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del usuario logueado (incluyendo la foto de perfil desde perfiles_usuario)
$sql_user = "
    SELECT 
        u.nombres, 
        u.apellidos, 
        pu.foto_perfil 
    FROM usuarios u
    LEFT JOIN perfiles_usuario pu ON pu.id_usuario = u.id_usuario
    WHERE u.id_usuario = ?
";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id_usuario);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

$foto_perfil = '';

if ($row_user = $res_user->fetch_assoc()) {
    $nombre_caficultor = trim($row_user['nombres'] . ' ' . $row_user['apellidos']);
    $foto_perfil = $row_user['foto_perfil'] ?? '';
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}
$stmt_user->close();

// PROCESAR CAMBIO DE ESTADO DE PAGO (INDIVIDUAL O MASIVO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'] === 'pagado' ? 'pagado' : 'pendiente';
    $recolector_sel = isset($_POST['recolector_sel']) ? intval($_POST['recolector_sel']) : 0;

    // Pago de una entrega individual
    if (isset($_POST['id_recoleccion'])) {
        $id_recoleccion = intval($_POST['id_recoleccion']);
        $sql_update = "UPDATE recolecciones SET estado_pago = ? WHERE id_recoleccion = ?";
        $stmt_u = $con->prepare($sql_update);
        $stmt_u->bind_param("si", $nuevo_estado, $id_recoleccion);
        $stmt_u->execute();
        $stmt_u->close();
    } 
    // Marcar todo como pagado
    elseif (isset($_POST['marcar_todo_pagado']) && $recolector_sel > 0) {
        $sql_update = "UPDATE recolecciones SET estado_pago = ? WHERE id_recolector = ? AND estado_pago = 'pendiente'";
        $stmt_u = $con->prepare($sql_update);
        $stmt_u->bind_param("si", $nuevo_estado, $recolector_sel);
        $stmt_u->execute();
        $stmt_u->close();
    }

    header("Location: recolectores_c.php" . ($recolector_sel > 0 ? "?id=" . $recolector_sel : ""));
    exit();
}

// OBTENER RECOLECTOR SELECCIONADO PARA EL MODAL DE REPORTE
$id_recolector_sel = isset($_GET['id']) ? intval($_GET['id']) : 0;

// CONSULTA DE LISTA GENERAL DE RECOLECTORES
$sql_trabajadores = "SELECT u.id_usuario, u.nombres, u.apellidos, u.documento, u.telefono, u.estado, 
                            f.nombre AS nombre_finca, f.ubicacion_vereda 
                     FROM recolectores r
                     INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
                     INNER JOIN fincas f ON r.id_finca = f.id_finca
                     WHERE u.rol = 'recolector'
                     ORDER BY u.nombres ASC";
$res_trabajadores = $con->query($sql_trabajadores);

// CONSULTAR DATOS Y ENTREGAS PENDIENTES SI HAY UN RECOLECTOR SELECCIONADO
$datos_recolector = null;
$res_pendientes = null;

if ($id_recolector_sel > 0) {
    $sql_rec = "SELECT u.nombres, u.apellidos, u.documento, f.nombre AS nombre_finca 
                FROM recolectores r
                INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
                INNER JOIN fincas f ON r.id_finca = f.id_finca
                WHERE u.id_usuario = ?";
    $stmt_r = $con->prepare($sql_rec);
    $stmt_r->bind_param("i", $id_recolector_sel);
    $stmt_r->execute();
    $datos_recolector = $stmt_r->get_result()->fetch_assoc();
    $stmt_r->close();

    $sql_pendientes = "SELECT id_recoleccion, DATE(fecha_registro) AS fecha, TIME(fecha_registro) AS hora, 
                               kilos_recolectados, pago_calculado 
                       FROM recolecciones 
                       WHERE id_recolector = ? AND estado_pago = 'pendiente' 
                       ORDER BY fecha_registro DESC";
    $stmt_p = $con->prepare($sql_pendientes);
    $stmt_p->bind_param("i", $id_recolector_sel);
    $stmt_p->execute();
    $res_pendientes = $stmt_p->get_result();
    $stmt_p->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Trabajadores - Café Control</title>
  
  <!-- Fuentes e Íconos -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    :root {
      --bg-main: #f4efe9;
      --bg-sidebar: #e8ded1;
      --bg-card: #ffffff;
      --primary-brown: #3d210d;
      --primary-dark: #241206;
      --primary-green: #075e36;
      --primary-green-hover: #054829;
      --badge-active-bg: #c8e6c9;
      --badge-active-text: #1b5e20;
      --text-dark: #1a1410;
      --text-muted: #5c5249;
      --border-color: #c7b9ab;
      --font-family: 'Plus Jakarta Sans', sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-family); }
    
    body { 
      background-color: var(--bg-main); 
      color: var(--text-dark); 
      display: flex; 
      flex-direction: column; 
      min-height: 100vh; 
      font-size: 18px; 
      line-height: 1.6; 
      -webkit-font-smoothing: antialiased;
    }

    /* Top Header */
    .top-header {
      background-color: var(--primary-brown);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 84px;
      padding: 0 36px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    .brand { display: flex; align-items: center; gap: 16px; width: 280px; }
    
    .brand-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      overflow: hidden;
      background-color: #fff;
    }
    .brand-logo .app-logo {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .brand-text h1 { font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }

    .header-center { display: flex; align-items: center; gap: 20px; flex: 1; padding-left: 24px; }
    .menu-toggle { background: none; border: none; color: #fff; cursor: pointer; display: flex; align-items: center; }
    .user-welcome h2 { font-size: 20px; font-weight: 800; }
    .user-welcome p { font-size: 15px; opacity: 0.9; font-weight: 500; }

    .header-actions { display: flex; align-items: center; gap: 24px; }
    .icon-btn { background: none; border: none; color: #fff; cursor: pointer; position: relative; display: flex; align-items: center; }
    .badge-dot {
      position: absolute; top: -2px; right: -2px;
      width: 12px; height: 12px;
      background-color: #4caf50;
      border: 2px solid var(--primary-brown);
      border-radius: 50%;
    }
    
    .user-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; text-decoration: none; color: inherit; }
    .avatar {
      width: 48px; height: 48px;
      background-color: #fff;
      color: var(--primary-brown);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 800;
      overflow: hidden;
    }
    
    .header-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    /* Layout Structure */
    .app-layout { display: flex; flex: 1; }

    /* Sidebar */
    .sidebar { width: 290px; background-color: var(--bg-sidebar); padding: 32px 20px; flex-shrink: 0; }
    .nav-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
    .nav-item a {
      display: flex; align-items: center; gap: 16px;
      padding: 16px 22px; border-radius: 14px;
      color: var(--text-dark); text-decoration: none;
      font-size: 17px; font-weight: 700;
      transition: all 0.2s ease-in-out;
    }
    .nav-item.active a { background-color: var(--primary-brown); color: #fff; box-shadow: 0 4px 12px rgba(61, 33, 13, 0.2); }
    .nav-item:not(.active) a:hover { background-color: rgba(0, 0, 0, 0.08); }

    /* Main Content */
    .main-content { flex: 1; padding: 40px 48px; display: flex; flex-direction: column; gap: 32px; }
    .card { background-color: var(--bg-card); border-radius: 20px; border: 1px solid var(--border-color); padding: 40px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }

    .search-container { display: flex; gap: 16px; margin-bottom: 32px; }
    .search-box { position: relative; flex: 1; }
    .search-box input {
      width: 100%; padding: 18px 20px 18px 56px;
      border-radius: 14px; border: 2px solid var(--border-color);
      font-size: 18px; font-weight: 600; outline: none; background-color: #fff;
      color: var(--text-dark);
      transition: border-color 0.2s;
    }
    .search-box input:focus { border-color: var(--primary-brown); }
    .search-box i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .btn-search {
      background-color: var(--primary-brown); color: #fff;
      border: none; padding: 0 36px; border-radius: 14px;
      font-weight: 800; font-size: 18px; cursor: pointer;
      transition: background-color 0.2s;
    }
    .btn-search:hover { background-color: var(--primary-dark); }

    .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .header-section h2 { font-size: 26px; font-weight: 800; display: flex; align-items: center; gap: 14px; }

    .btn-add-recolector {
      background-color: var(--primary-green);
      color: #ffffff;
      text-decoration: none;
      padding: 14px 24px;
      border-radius: 12px;
      font-size: 17px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: background-color 0.2s, transform 0.1s;
      box-shadow: 0 4px 12px rgba(7, 94, 54, 0.25);
      white-space: nowrap;
    }
    .btn-add-recolector:hover {
      background-color: var(--primary-green-hover);
      transform: translateY(-2px);
    }

    .table-custom { width: 100%; border-collapse: collapse; font-size: 18px; margin-top: 16px; }
    .table-custom th { 
      background-color: #ede3d7; 
      color: var(--primary-dark); 
      font-weight: 800; 
      text-align: left; 
      padding: 20px 22px; 
      border-bottom: 3px solid var(--border-color); 
      font-size: 16px; 
      text-transform: uppercase; 
      letter-spacing: 0.8px; 
    }
    .table-custom td { padding: 22px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-dark); font-weight: 600; }
    .table-custom tr:nth-child(even) { background-color: #faf7f4; }

    .badge-status { background-color: var(--badge-active-bg); color: var(--badge-active-text); padding: 8px 18px; border-radius: 20px; font-weight: 800; font-size: 15px; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px; }
    
    .btn-badge-pending {
      background-color: #ffebee;
      color: #c62828;
      border: 1px solid #ef9a9a;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 800;
      font-size: 14px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .btn-badge-pending:hover {
      background-color: #2e7d32;
      color: #ffffff;
      border-color: #2e7d32;
    }
    .btn-pay-all {
      background-color: #e8f5e9;
      color: #2e7d32;
      border: 1px solid #a5d6a7;
      padding: 8px 16px;
      border-radius: 10px;
      font-weight: 800;
      font-size: 15px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .btn-pay-all:hover {
      background-color: #2e7d32;
      color: #ffffff;
    }

    .btn-report-action {
      background-color: var(--primary-green); color: #fff;
      text-decoration: none; padding: 14px 24px; border-radius: 12px;
      font-size: 16px; font-weight: 800; display: inline-flex;
      align-items: center; gap: 10px; border: none; cursor: pointer;
      transition: background-color 0.2s, transform 0.1s;
      box-shadow: 0 3px 8px rgba(7, 94, 54, 0.2);
    }
    .btn-report-action:hover { background-color: var(--primary-green-hover); transform: translateY(-1px); }

    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background-color: rgba(0, 0, 0, 0.7);
      display: <?php echo ($id_recolector_sel > 0) ? 'flex' : 'none'; ?>;
      justify-content: center; align-items: center;
      z-index: 1000; padding: 24px;
      backdrop-filter: blur(4px);
    }

    .modal-content {
      background-color: var(--bg-card);
      border-radius: 24px;
      width: 100%; max-width: 960px;
      max-height: 90vh; overflow-y: auto;
      position: relative; padding: 48px;
      box-shadow: 0 16px 40px rgba(0,0,0,0.3);
    }

    .modal-close-btn {
      position: absolute; top: 24px; right: 24px;
      background: #e4d7cb; border: none;
      width: 44px; height: 44px; border-radius: 50%;
      cursor: pointer; display: flex; align-items: center;
      justify-content: center; font-weight: 800; font-size: 24px;
      color: var(--text-dark);
      transition: background-color 0.2s;
    }
    .modal-close-btn:hover { background: #d3c2b1; }

    .watermark {
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 55%; max-width: 420px;
      opacity: 0.06; z-index: 0; pointer-events: none;
    }

    .modal-inner { position: relative; z-index: 1; }
    .info-grid { 
      display: grid; grid-template-columns: 1fr 1fr; gap: 20px; 
      background-color: #f5ede4; padding: 28px; border-radius: 16px; 
      margin-bottom: 32px; font-size: 18px; font-weight: 700; 
      border: 2px solid var(--border-color); 
    }
    .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 36px; }
    .btn-back { background-color: #544b43; color: #fff; padding: 16px 28px; border-radius: 12px; text-decoration: none; font-size: 17px; font-weight: 800; }
    .btn-back:hover { background-color: #3d3630; }
    .btn-download { background-color: var(--primary-green); color: #fff; padding: 16px 28px; border-radius: 12px; border: none; font-size: 17px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(7, 94, 54, 0.25); }
    .btn-download:hover { background-color: var(--primary-green-hover); }

    @media print {
      body * { visibility: hidden; }
      .modal-content, .modal-content * { visibility: visible; }
      
      .modal-overlay { 
        position: absolute; left: 0; top: 0; 
        width: 100%; height: auto; 
        background: none; display: block; padding: 0; 
      }
      
      .modal-content { 
        position: absolute; left: 0; top: 0; 
        width: 100%; max-width: 100%; 
        padding: 10px; box-shadow: none; border: none; 
        background: #fff;
      }

      .modal-inner h2 { font-size: 32px !important; color: #000 !important; font-weight: 800 !important; }
      .modal-inner h3 { font-size: 26px !important; color: #075e36 !important; font-weight: 800 !important; margin-top: 20px !important; }
      .modal-inner p { font-size: 20px !important; color: #222 !important; }

      .info-grid { 
        font-size: 22px !important; 
        line-height: 1.8 !important;
        background-color: #f4efe9 !important; 
        border: 2px solid #3d210d !important; 
        padding: 24px !important; 
        margin-bottom: 30px !important;
      }
      .info-grid div { color: #000 !important; }

      .table-custom { font-size: 22px !important; width: 100% !important; border-collapse: collapse !important; }
      .table-custom th { 
        font-size: 20px !important; 
        padding: 18px 16px !important; 
        background-color: #e8ded1 !important; 
        color: #000 !important; 
        border-bottom: 3px solid #000 !important;
      }
      .table-custom td { 
        padding: 18px 16px !important; 
        font-size: 22px !important; 
        color: #000 !important; 
        border-bottom: 2px solid #ccc !important; 
      }
      
      .table-custom tr:last-child td { 
        font-size: 24px !important; 
        font-weight: 800 !important; 
        background-color: #ede3d7 !important; 
        border-top: 3px solid #000 !important;
      }

      .no-print, .modal-close-btn { display: none !important; }
      body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
  </style>
</head>
<body>

  <!-- Encabezado Superior -->
  <header class="top-header">
    <div class="brand">
      <div class="brand-logo">
        <img 
          src="images/logo.jpeg" 
          alt="Café Control Logo" 
          class="app-logo"
        >
      </div>
      <div class="brand-text">
        <h1>Café control</h1>
      </div>
    </div>

    <div class="header-center">
      <button class="menu-toggle" type="button" aria-label="Abrir menú">
        <i data-lucide="menu" size="24"></i>
      </button>
      <div class="user-welcome">
        <h2>Bienvenido, <?php echo htmlspecialchars($nombre_caficultor); ?></h2>
        <p>Administra tus fincas y consulta su información</p>
      </div>
    </div>

    <div class="header-actions">
      <button class="icon-btn" type="button" aria-label="Notificaciones">
        <i data-lucide="bell" size="22"></i>
        <span class="badge-dot"></span>
      </button>
      <a
        href="panel_caficultor.php"
        class="user-profile"
        aria-label="Ver perfil"
      >
        <div class="avatar">
          <?php if (!empty($foto_perfil)) { ?>
            <img
              src="<?php echo htmlspecialchars($foto_perfil); ?>"
              alt="Foto de perfil"
              class="header-avatar"
            >
          <?php } else { ?>
            <i data-lucide="user" size="22"></i>
          <?php } ?>
        </div>

        <i data-lucide="chevron-down" size="18"></i>
      </a>
    </div>
  </header>

  <div class="app-layout">
    <!-- Menú Lateral -->
    <aside class="sidebar">
      <ul class="nav-list">
        <li class="nav-item">
          <a href="panel_caficultor.php"><i data-lucide="home" size="24"></i> Perfil</a>
        </li>
        <li class="nav-item">
          <a href="fincas.php"><i data-lucide="map-pin" size="24"></i> Fincas</a>
        </li>
        <li class="nav-item">
          <a href="recolecciones_c.php"><i data-lucide="archive" size="24"></i> Recolecciones</a>
        </li>
        <li class="nav-item active">
          <a href="recolectores_c.php"><i data-lucide="users" size="24"></i> Trabajadores</a>
        </li>
        <li class="nav-item">
          <a href="resumen_c.php"><i data-lucide="bar-chart-2" size="24"></i> Resumen General</a>
        </li>
        <li class="nav-item">
          <a href="precio.php"><i data-lucide="dollar-sign" size="24"></i> Precio del Kilo</a>
        </li>
        <li class="nav-item">
          <a href="asignar_m.php"><i class="fa-solid fa-user-gear" style="font-size: 22px;"></i> Mayordomo</a>
        </li>
                       <li class="nav-item">
                    <a href="gestionar_usuarios.php">
                        <i class="fa-solid fa-users-gear" style="font-size:18px;"></i>
                        Gestionar Usuarios
                    </a>
             </li>
        <li class="nav-item">
          <a href="cerrar.php"><i data-lucide="log-out" size="24"></i> Cerrar Sesión</a>
        </li>
      </ul>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content">
      <div class="card">
        <div class="search-container">
          <div class="search-box">
            <i data-lucide="search" size="24"></i>
            <input type="text" id="inputBuscar" onkeyup="filtrarTabla()" placeholder="Buscar recolector por nombre o documento...">
          </div>
          <button class="btn-search" onclick="filtrarTabla()">Buscar</button>
        </div>

        <div class="header-section">
          <div>
            <h2><i data-lucide="users" size="30"></i> Gestionar recolectores</h2>
            <p style="font-size: 17px; color: var(--text-muted); font-weight: 600; margin-top: 6px;">
              Administre los recolectores registrados en el sistema.
            </p>
          </div>
          <a href="agregar_recolector.php" class="btn-add-recolector">
            <i data-lucide="user-plus" size="22"></i> Agregar recolector
          </a>
        </div>

        <table class="table-custom" id="tablaTrabajadores">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre completo</th>
              <th>Documento</th>
              <th>Teléfono</th>
              <th>Vereda / Finca</th>
              <th>Estado</th>
              <th style="text-align: right;">Reportes</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res_trabajadores && $res_trabajadores->num_rows > 0): $i = 1; ?>
              <?php while ($t = $res_trabajadores->fetch_assoc()): ?>
                <tr>
                  <td><strong style="font-size: 19px;"><?php echo $i++; ?></strong></td>
                  <td><strong style="font-size: 19px; color: var(--primary-dark);"><?php echo htmlspecialchars($t['nombres'] . ' ' . $t['apellidos']); ?></strong></td>
                  <td><strong style="font-size: 18px; color: var(--text-dark);"><?php echo htmlspecialchars($t['documento']); ?></strong></td>
                  <td><?php echo htmlspecialchars($t['telefono']); ?></td>
                  <td><?php echo htmlspecialchars(($t['ubicacion_vereda'] ? $t['ubicacion_vereda'] . ' / ' : '') . $t['nombre_finca']); ?></td>
                  <td><span class="badge-status"><?php echo ucfirst($t['estado'] ?? 'Activo'); ?></span></td>
                  <td style="text-align: right;">
                    <a href="recolectores_c.php?id=<?php echo $t['id_usuario']; ?>" class="btn-report-action">
                      <i data-lucide="file-text" size="20"></i> Ver Reporte
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" style="text-align:center; font-size: 18px; padding: 32px;">No hay recolectores registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Modal Emergente de Reporte -->
  <?php if ($id_recolector_sel > 0 && $datos_recolector): ?>
  <div class="modal-overlay" id="modalReporte">
    <div class="modal-content">
      <button class="modal-close-btn no-print" onclick="cerrarModal()" aria-label="Cerrar">&times;</button>
      
      <img src="images/logo.jpeg" class="watermark" alt="Logo Marca de Agua">

      <div class="modal-inner">
        <h2 style="margin-bottom: 24px; font-size: 28px; font-weight: 800; display: flex; align-items: center; gap: 14px; color: var(--primary-dark);">
          <i data-lucide="user-check" size="32"></i> Control de Entregas y Pagos
        </h2>
        
        <div class="info-grid">
          <div><strong>Recolector:</strong> <?php echo htmlspecialchars($datos_recolector['nombres'] . ' ' . $datos_recolector['apellidos']); ?></div>
          <div><strong>Cédula:</strong> <?php echo htmlspecialchars($datos_recolector['documento']); ?></div>
          <div><strong>Finca:</strong> <?php echo htmlspecialchars($datos_recolector['nombre_finca']); ?></div>
          <div><strong>Fecha de Consulta:</strong> <?php echo date('Y-m-d'); ?></div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <div>
            <h3 style="color: var(--primary-green); font-size: 22px; font-weight: 800;">
              Reporte de Liquidación Pendiente
            </h3>
            <p style="font-size: 16px; color: var(--text-muted); font-weight: 600;">
              Este reporte únicamente incluye los kilos que se encuentran pendientes de pago.
            </p>
          </div>
          <?php if ($res_pendientes && $res_pendientes->num_rows > 0): ?>
            <form method="POST" action="recolectores_c.php" class="no-print">
              <input type="hidden" name="recolector_sel" value="<?php echo $id_recolector_sel; ?>">
              <input type="hidden" name="nuevo_estado" value="pagado">
              <input type="hidden" name="marcar_todo_pagado" value="1">
              <button type="submit" class="btn-pay-all" onclick="return confirm('¿Deseas marcar TODAS las entregas pendientes como PAGADAS?')">
                <i data-lucide="check-circle-2" size="18"></i> Marcar Todo como Pagado
              </button>
            </form>
          <?php endif; ?>
        </div>

        <table class="table-custom">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Kilos Pendientes</th>
              <th>Total por Pagar</th>
              <th class="no-print" style="text-align: center;">Estado / Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $sum_kilos = 0;
              $sum_pago = 0;
              if ($res_pendientes && $res_pendientes->num_rows > 0):
                while ($p = $res_pendientes->fetch_assoc()):
                  $sum_kilos += $p['kilos_recolectados'];
                  $sum_pago += $p['pago_calculado'];
            ?>
              <tr>
                <td><?php echo $p['fecha']; ?></td>
                <td><?php echo $p['hora']; ?></td>
                <td><strong><?php echo $p['kilos_recolectados']; ?> Kg</strong></td>
                <td><strong>$<?php echo number_format($p['pago_calculado'], 0, ',', '.'); ?></strong></td>
                <td class="no-print" style="text-align: center;">
                  <form method="POST" action="recolectores_c.php" style="display: inline;">
                    <input type="hidden" name="id_recoleccion" value="<?php echo $p['id_recoleccion']; ?>">
                    <input type="hidden" name="nuevo_estado" value="pagado">
                    <input type="hidden" name="recolector_sel" value="<?php echo $id_recolector_sel; ?>">
                    <button type="submit" class="btn-badge-pending" title="Hacer clic para marcar como Pagado">
                      <i data-lucide="clock" size="14"></i> Pendiente
                    </button>
                  </form>
                </td>
              </tr>
            <?php 
                endwhile;
            ?>
              <tr style="font-weight: 800; background-color: #ede3d7;">
                <td colspan="2">TOTAL LIQUIDACIÓN PENDIENTE</td>
                <td><?php echo $sum_kilos; ?> Kg</td>
                <td style="color: var(--primary-green);">$<?php echo number_format($sum_pago, 0, ',', '.'); ?></td>
                <td class="no-print"></td>
              </tr>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 36px; color: var(--text-muted); font-size: 18px;">
                  <strong style="color: var(--primary-green); font-size: 20px;">¡Sin deudas pendientes!</strong><br>Todas las entregas de este recolector han sido pagadas.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="actions-bar no-print">
          <a href="recolectores_c.php" class="btn-back">Cerrar Ventana</a>
          <button onclick="window.print()" class="btn-download">
            <i data-lucide="printer" size="22"></i> Imprimir / Descargar Reporte
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    lucide.createIcons();

    function cerrarModal() {
      window.location.href = 'recolectores_c.php';
    }

    function filtrarTabla() {
      const input = document.getElementById("inputBuscar").value.toUpperCase();
      const trs = document.getElementById("tablaTrabajadores").getElementsByTagName("tr");

      for (let i = 1; i < trs.length; i++) {
        const textoFila = trs[i].textContent || trs[i].innerText;
        trs[i].style.display = textoFila.toUpperCase().indexOf(input) > -1 ? "" : "none";
      }
    }
  </script>
</body>
</html>