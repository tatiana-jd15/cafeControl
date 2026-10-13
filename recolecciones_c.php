<?php
session_start();
require_once 'assets/inc/conexion.php'; 

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario_sesion = $_SESSION['id_usuario'];

// Obtener datos del usuario logueado
$sql_user = "
    SELECT
        u.nombres,
        u.apellidos,
        u.rol,
        pu.foto_perfil
    FROM usuarios u
    LEFT JOIN perfiles_usuario pu
        ON pu.id_usuario = u.id_usuario
    WHERE u.id_usuario = ?
";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id_usuario_sesion);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

$foto_perfil = '';
$rol_perfil = '';

if ($row_user = $res_user->fetch_assoc()) {
    $nombre_caficultor = trim($row_user['nombres'] . ' ' . $row_user['apellidos']);
    $foto_perfil = $row_user['foto_perfil'] ?? '';
    $rol_perfil = $row_user['rol'] ?? '';
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------------
// API ENDPOINT 1: BUSCAR RECOLECTOR VÍA AJAX
// ------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'buscar_recolector') {
    header('Content-Type: application/json');
    $cedula = trim($_POST['cedula'] ?? '');

    if (empty($cedula)) {
        echo json_encode(['success' => false, 'message' => 'Ingresa un número de cédula.']);
        exit();
    }

    $sql = "SELECT u.id_usuario, u.documento, u.nombres, u.apellidos, u.telefono, u.rol, 
                   f.id_finca, f.nombre AS nombre_finca, f.ubicacion_vereda 
            FROM usuarios u
            INNER JOIN recolectores r ON u.id_usuario = r.id_usuario
            INNER JOIN fincas f ON r.id_finca = f.id_finca
            WHERE u.documento = ? AND u.rol = 'recolector' AND u.estado = 'activo' 
            LIMIT 1";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($recolector = $res->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $recolector]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Recolector no encontrado o inactivo.']);
    }
    exit();
}

// ------------------------------------------------------------------
// API ENDPOINT 2: GUARDAR RECOLECCIONES VÍA AJAX
// ------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'guardar_recolecciones') {
    header('Content-Type: application/json');

    $id_recolector = intval($_POST['id_recolector'] ?? 0);
    $id_finca = intval($_POST['id_finca'] ?? 0);
    $entregas = $_POST['entregas'] ?? [];

    if ($id_recolector === 0 || $id_finca === 0 || empty($entregas)) {
        echo json_encode(['success' => false, 'message' => 'Selecciona un recolector válido.']);
        exit();
    }

    // Consultar el precio vigente del kilo para la finca
    $sql_precio = "SELECT precio_kilo FROM historial_precios WHERE id_finca = ? ORDER BY fecha_actualizacion DESC LIMIT 1";
    $stmt_p = $con->prepare($sql_precio);
    $stmt_p->bind_param("i", $id_finca);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    $precio_kilo = 0;
    if ($row_p = $res_p->fetch_assoc()) {
        $precio_kilo = floatval($row_p['precio_kilo']);
    }

    $con->begin_transaction();

    try {
        $sql_ins = "INSERT INTO recolecciones 
                    (id_recolector, id_finca, id_registrado_por, numero_entrega, fecha, kilos_recolectados, precio_kilo_aplicado, pago_calculado, observaciones) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $con->prepare($sql_ins);

        $insertados = 0;
        foreach ($entregas as $item) {
            $kilos = floatval($item['kilos'] ?? 0);
            if ($kilos > 0) {
                $num_entrega = intval($item['num_entrega'] ?? 1);
                
                $datetime_input = $item['fecha_hora'] ?? date('Y-m-d H:i:s');
                $fecha_hora_mysql = date('Y-m-d H:i:s', strtotime($datetime_input));
                
                $pago_calculado = $kilos * $precio_kilo;
                $obs = trim($item['observaciones'] ?? '');

                $stmt_ins->bind_param("iiiisddds", 
                    $id_recolector, 
                    $id_finca, 
                    $id_usuario_sesion, 
                    $num_entrega, 
                    $fecha_hora_mysql, 
                    $kilos, 
                    $precio_kilo, 
                    $pago_calculado, 
                    $obs
                );
                
                $stmt_ins->execute();
                $insertados++;
            }
        }

        if ($insertados > 0) {
            $con->commit();
            echo json_encode(['success' => true, 'message' => "Se registraron $insertados entregas exitosamente con su hora e identificador."]);
        } else {
            $con->rollback();
            echo json_encode(['success' => false, 'message' => 'Ingresa al menos el peso de una entrega.']);
        }
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestor de Cuentas - Café Recolectado</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root {
      --bg-main: #f5f0eb;
      --bg-sidebar: #ece4db;
      --bg-card: #fcfaf7;
      --card-inner-bg: #f3ece4;
      --primary-brown: #4a2810;
      --primary-dark: #331a0a;
      --text-dark: #1a110b;
      --text-muted: #5a4d41;
      --status-active: #1b5e20;
      --border-color: #d8c8b8;
      --font-family: 'Plus Jakarta Sans', sans-serif;
      --btn-green: #2e7d32;
      --btn-green-hover: #1b5e20;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-family); }
    body { background-color: var(--bg-main); color: var(--text-dark); display: flex; flex-direction: column; min-height: 100vh; font-size: 16px; }

    /* Header */
    .top-header { background-color: var(--primary-brown); color: #fff; display: flex; align-items: center; justify-content: space-between; height: 70px; padding: 0 28px; }
    .brand { display: flex; align-items: center; gap: 14px; width: 250px; }
    .brand-logo { width: 40px; height: 40px; background-color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-brown); }
    .brand-text h1 { font-size: 18px; font-weight: 800; line-height: 1.1; letter-spacing: 0.3px; }
    .header-center { display: flex; align-items: center; gap: 20px; flex: 1; padding-left: 20px; }
    .menu-toggle { background: none; border: none; color: #fff; cursor: pointer; }
    .user-welcome h2 { font-size: 16px; font-weight: 700; }
    .user-welcome p { font-size: 13px; opacity: 0.9; }
    .header-actions { display: flex; align-items: center; gap: 24px; }
    .icon-btn { background: none; border: none; color: #fff; cursor: pointer; position: relative; }
    .badge-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background-color: #4caf50; border-radius: 50%; border: 2px solid var(--primary-brown); }
    .user-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      text-decoration: none;
      color: #fff;
    }

    .avatar {
      width: 42px;
      height: 42px;
      background-color: #fff;
      color: var(--primary-brown);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 2px solid rgba(255,255,255,0.7);
    }

    .header-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .profile-arrow {
      width: 19px;
      height: 19px;
    }

    /* Layout & Sidebar */
    .app-layout { display: flex; flex: 1; }
    .sidebar { width: 270px; background-color: var(--bg-sidebar); padding: 24px 18px; display: flex; flex-direction: column; gap: 20px; flex-shrink: 0; border-right: 1px solid var(--border-color); }
    .nav-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .nav-item a { display: flex; align-items: center; gap: 14px; padding: 12px 18px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-size: 15px; font-weight: 700; transition: all 0.2s; }
    .nav-item.active a { background-color: var(--primary-brown); color: #fff; }
    .nav-item:not(.active) a:hover { background-color: rgba(0, 0, 0, 0.07); }

    /* Main Content */
    .main-content { flex: 1; padding: 32px 36px; overflow-y: auto; }
    .main-card { background-color: var(--bg-card); border-radius: 20px; border: 1px solid var(--border-color); padding: 32px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03); }
    .panel-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
    .panel-title { display: flex; align-items: center; gap: 18px; }
    .title-icon { width: 56px; height: 56px; background-color: var(--primary-brown); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
    .title-text h2 { font-size: 1.6rem; color: var(--text-dark); font-weight: 800; letter-spacing: -0.3px; }
    .title-text p { font-size: 1rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }
    .location-badge { background-color: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 10px 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
    .location-text strong { display: block; font-size: 0.95rem; color: var(--text-dark); font-weight: 800; }
    .location-text span { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

    /* Section Blocks */
    .section-block { border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; margin-bottom: 24px; background-color: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.01); }
    .section-header { display: flex; align-items: center; gap: 12px; font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin-bottom: 20px; border-bottom: 2px solid #f3ece4; padding-bottom: 10px; }
    .section-header svg { color: var(--primary-brown); }

    /* Search Section */
    .search-row { display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; }
    .search-group { flex: 1; min-width: 300px; }
    .search-group label { display: block; font-size: 0.98rem; font-weight: 800; margin-bottom: 8px; color: var(--text-dark); }
    .search-input-wrapper { display: flex; gap: 12px; }
    .search-input-wrapper input { flex: 1; padding: 14px 18px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 1.05rem; font-weight: 600; outline: none; background-color: #fff; color: var(--text-dark); }
    .search-input-wrapper input:focus { border-color: var(--primary-brown); }
    .btn-search { background-color: var(--primary-brown); color: #ffffff; border: none; border-radius: 10px; padding: 14px 24px; font-size: 1rem; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
    .btn-search:hover { background-color: var(--primary-dark); }

    .status-alert { background-color: #e8f5e9; border: 2px solid #a5d6a7; border-radius: 12px; padding: 12px 20px; display: none; align-items: center; gap: 14px; min-width: 280px; }
    .status-alert svg { color: var(--status-active); }
    .status-alert .alert-text strong { display: block; font-size: 0.95rem; color: var(--status-active); font-weight: 800; }
    .status-alert .alert-text span { font-size: 0.85rem; color: #1b5e20; font-weight: 600; }

    /* Recollector Card & Default Avatar */
    .recollector-card { background-color: var(--card-inner-bg); border-radius: 14px; padding: 20px 24px; display: none; align-items: center; gap: 24px; border: 1px solid var(--border-color); }
    .recollector-avatar-box { width: 75px; height: 75px; border-radius: 50%; background-color: #ffffff; border: 3px solid var(--primary-brown); display: flex; align-items: center; justify-content: center; color: var(--primary-brown); flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .recollector-details { display: flex; flex-direction: column; gap: 10px; flex: 1; }
    .recollector-name { font-size: 1.3rem; font-weight: 800; color: var(--text-dark); }
    .recollector-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 40px; font-size: 0.98rem; }
    .recollector-grid-item { display: flex; align-items: center; gap: 10px; color: var(--text-dark); font-weight: 500; }
    .recollector-grid-item strong { font-weight: 800; }
    .recollector-grid-item svg { color: var(--primary-brown); width: 20px; height: 20px; }

    /* Deliveries Grid */
    .deliveries-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 15px; }
    .delivery-card { background-color: var(--card-inner-bg); border: 2px solid var(--border-color); border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
    .delivery-tag { background-color: var(--primary-brown); color: #ffffff; font-size: 0.9rem; font-weight: 800; padding: 6px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; align-self: flex-start; letter-spacing: 0.3px; }

    .input-subgroup { display: flex; flex-direction: column; gap: 6px; }
    .input-subgroup label { font-size: 0.9rem; font-weight: 800; color: var(--text-dark); }
    .datetime-input, .weight-input { display: flex; align-items: center; background-color: #ffffff; border: 2px solid var(--border-color); border-radius: 10px; padding: 10px 14px; gap: 10px; }
    .datetime-input:focus-within, .weight-input:focus-within, .obs-input:focus { border-color: var(--primary-brown); }
    .datetime-input input, .weight-input input, .obs-input { border: none; outline: none; background: transparent; font-size: 1rem; font-weight: 700; width: 100%; color: var(--text-dark); }
    .datetime-input svg { color: var(--text-muted); width: 20px; height: 20px; }
    .weight-input .weight-icon-bg { background-color: var(--primary-brown); color: #ffffff; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .unit-label { font-size: 1rem; font-weight: 800; color: var(--text-dark); }
    .obs-input { background-color: #ffffff; border: 2px solid var(--border-color); border-radius: 10px; padding: 12px 14px; font-weight: 600; }

    /* Actions Bar */
    .actions-bar { display: flex; justify-content: flex-end; gap: 16px; margin-top: 25px; }
    .btn-clean { background-color: #ffffff; border: 2px solid var(--border-color); border-radius: 12px; padding: 14px 28px; font-size: 1rem; font-weight: 800; color: var(--text-dark); cursor: pointer; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
    .btn-clean:hover { background-color: #f0e6dd; }
    .btn-save { background-color: var(--btn-green); border: none; border-radius: 12px; padding: 14px 34px; font-size: 1rem; font-weight: 800; color: #ffffff; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: background 0.2s; box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2); }
    .btn-save:hover { background-color: var(--btn-green-hover); }

    /* Info Note & Footer */
    .info-note { background-color: var(--card-inner-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 20px; margin-top: 20px; display: flex; align-items: center; gap: 12px; font-size: 0.92rem; color: var(--text-dark); font-weight: 600; }
    .info-note svg { color: var(--primary-brown); width: 22px; height: 22px; flex-shrink: 0; }
    .app-footer { text-align: center; padding: 20px; font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 8px; border-top: 1px solid var(--border-color); background-color: var(--bg-main); }

    @media (max-width: 1100px) { .deliveries-grid { grid-template-columns: 1fr; } .recollector-grid { grid-template-columns: 1fr; } }
    @media (max-width: 850px) { .app-layout { flex-direction: column; } .sidebar { width: 100%; } .panel-header { flex-direction: column; gap: 18px; } }
  </style>
</head>
<body>

 <header class="top-header">
  <style>
    /* Estilos para el contenedor e imagen del logo */
    .brand-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      overflow: hidden;
    }

    .brand-logo .app-logo {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    /* Solución para que el enlace del perfil no se vea morado o azul */
    a.user-profile,
    a.user-profile:visited,
    a.user-profile:hover,
    a.user-profile:active {
      color: inherit;
      text-decoration: none;
    }

    /* Asegura que el icono y texto dentro del perfil se mantengan blancos */
    a.user-profile,
    a.user-profile * {
      color: #ffffff;
      stroke: #ffffff;
    }
  </style>

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
   <aside class="sidebar">
      <ul class="nav-list">
        <li class="nav-item">
          <a href="panel_caficultor.php"><i data-lucide="home" size="20"></i> Perfil</a>
        </li>
        <li class="nav-item">
          <a href="fincas.php"><i data-lucide="map-pin" size="20"></i> Fincas</a>
        </li>
        <li class="nav-item active">
          <a href="recolecciones_c.php"><i data-lucide="archive" size="20"></i> Recolecciones</a>
        </li>
        <li class="nav-item">
          <a href="recolectores_c.php"><i data-lucide="users" size="20"></i> Trabajadores</a>
        </li>
        <li class="nav-item">
          <a href="resumen_c.php"><i data-lucide="bar-chart-2" size="20"></i> Resumen General</a>
        </li>
        <li class="nav-item">
          <a href="precio.php"><i data-lucide="dollar-sign" size="20"></i> Precio del Kilo</a>
        </li>
        <li class="nav-item"><a href="asignar_m.php"><i class="fa-solid fa-user-gear"></i> Mayordomo</a></li>
                       <li class="nav-item">
                    <a href="gestionar_usuarios.php">
                        <i class="fa-solid fa-users-gear" style="font-size:18px;"></i>
                        Gestionar Usuarios
                    </a>
             </li>
        <li class="nav-item">
          <a href="cerrar.php"><i data-lucide="log-out" size="20"></i> Cerrar Sesión</a>
        </li>
      </ul>
    </aside>

    <main class="main-content">
      <div class="main-card">
        
        <div class="panel-header">
          <div class="panel-title">
            <div class="title-icon"><i data-lucide="clipboard-check" size="28"></i></div>
            <div class="title-text">
              <h2>Registro de entrega de café</h2>
              <p>Registra las entregas de café realizadas por el recolector en el día.</p>
            </div>
          </div>
          <div class="location-badge">
            <i data-lucide="map-pin" size="20"></i>
            <div class="location-text">
              <strong id="label-finca-top">Teruel, Huila</strong>
              <span>Colombia</span>
            </div>
          </div>
        </div>

        <!-- Paso 1: Identificar Recolector -->
        <div class="section-block">
          <div class="section-header">
            <i data-lucide="user" size="22"></i>
            <span>1. Identificar recolector</span>
          </div>

          <div class="search-row">
            <div class="search-group">
              <label for="cedula">Número de cédula del recolector</label>
              <div class="search-input-wrapper">
                <input type="text" id="cedula" placeholder="Ingrese la cédula">
                <button type="button" class="btn-search" id="btn-buscar">
                  <i data-lucide="search" size="18"></i> Buscar
                </button>
              </div>
            </div>

            <div class="status-alert" id="status-alert">
              <i data-lucide="check-circle-2" size="24"></i>
              <div class="alert-text">
                <strong>Recolector encontrado</strong>
                <span>Información cargada correctamente</span>
              </div>
            </div>
          </div>

          <!-- Campos Ocultos de Control -->
          <input type="hidden" id="id_recolector_val">
          <input type="hidden" id="id_finca_val">

          <!-- Tarjeta de Datos del Recolector con Avatar en Ícono -->
          <div class="recollector-card" id="recollector-card">
            <div class="recollector-avatar-box">
              <i data-lucide="user-check" size="38"></i>
            </div>
            
            <div class="recollector-details">
              <div class="recollector-name" id="rec-nombre">--</div>
              
              <div class="recollector-grid">
                <div class="recollector-grid-item">
                  <i data-lucide="id-card"></i>
                  <span class="label">Cédula:</span>
                  <strong id="rec-cedula">--</strong>
                </div>
                <div class="recollector-grid-item">
                  <i data-lucide="navigation"></i>
                  <span class="label">Finca / Vereda:</span>
                  <strong id="rec-finca">--</strong>
                </div>
                <div class="recollector-grid-item">
                  <i data-lucide="phone"></i>
                  <span class="label">Teléfono:</span>
                  <strong id="rec-telefono">--</strong>
                </div>
                <div class="recollector-grid-item">
                  <i data-lucide="tag"></i>
                  <span class="label">Rol:</span>
                  <strong id="rec-rol">Recolector</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Paso 2: Entregas del Día -->
        <div class="section-block">
          <div class="section-header">
            <i data-lucide="shopping-bag" size="22"></i>
            <span>2. Registrar entregas del día (3 entregas)</span>
          </div>

          <div class="deliveries-grid">
            
            <!-- Entrega 1 -->
            <div class="delivery-card">
              <div class="delivery-tag">
                <i data-lucide="shopping-bag" size="16"></i> Entrega 1
              </div>
              <div class="input-subgroup">
                <label for="fecha_1">Fecha y hora</label>
                <div class="datetime-input">
                  <i data-lucide="calendar"></i>
                  <input type="datetime-local" id="fecha_1">
                </div>
              </div>
              <div class="input-subgroup">
                <label for="kilos_1">Peso entregado (kg)</label>
                <div class="weight-input">
                  <div class="weight-icon-bg"><i data-lucide="scale" size="16"></i></div>
                  <input type="number" step="0.1" id="kilos_1" placeholder="0.0">
                  <span class="unit-label">kg</span>
                </div>
              </div>
              <div class="input-subgroup">
                <label for="obs_1">Observaciones (opcional)</label>
                <input type="text" class="obs-input" id="obs_1" placeholder="Ej: Café maduro, buena calidad">
              </div>
            </div>

            <!-- Entrega 2 -->
            <div class="delivery-card">
              <div class="delivery-tag">
                <i data-lucide="shopping-bag" size="16"></i> Entrega 2
              </div>
              <div class="input-subgroup">
                <label for="fecha_2">Fecha y hora</label>
                <div class="datetime-input">
                  <i data-lucide="calendar"></i>
                  <input type="datetime-local" id="fecha_2">
                </div>
              </div>
              <div class="input-subgroup">
                <label for="kilos_2">Peso entregado (kg)</label>
                <div class="weight-input">
                  <div class="weight-icon-bg"><i data-lucide="scale" size="16"></i></div>
                  <input type="number" step="0.1" id="kilos_2" placeholder="0.0">
                  <span class="unit-label">kg</span>
                </div>
              </div>
              <div class="input-subgroup">
                <label for="obs_2">Observaciones (opcional)</label>
                <input type="text" class="obs-input" id="obs_2" placeholder="Ej: Café maduro">
              </div>
            </div>

            <!-- Entrega 3 -->
            <div class="delivery-card">
              <div class="delivery-tag">
                <i data-lucide="shopping-bag" size="16"></i> Entrega 3
              </div>
              <div class="input-subgroup">
                <label for="fecha_3">Fecha y hora</label>
                <div class="datetime-input">
                  <i data-lucide="calendar"></i>
                  <input type="datetime-local" id="fecha_3">
                </div>
              </div>
              <div class="input-subgroup">
                <label for="kilos_3">Peso entregado (kg)</label>
                <div class="weight-input">
                  <div class="weight-icon-bg"><i data-lucide="scale" size="16"></i></div>
                  <input type="number" step="0.1" id="kilos_3" placeholder="0.0">
                  <span class="unit-label">kg</span>
                </div>
              </div>
              <div class="input-subgroup">
                <label for="obs_3">Observaciones (opcional)</label>
                <input type="text" class="obs-input" id="obs_3" placeholder="Ej: Buena calidad">
              </div>
            </div>

          </div>
        </div>

        <div class="actions-bar">
          <button type="button" class="btn-clean" id="btn-limpiar">
            <i data-lucide="eraser" size="18"></i> Limpiar
          </button>
          <button type="button" class="btn-save" id="btn-guardar">
            <i data-lucide="save" size="18"></i> Guardar entrega
          </button>
        </div>

        <div class="info-note">
          <i data-lucide="info"></i>
          <span><strong>Nota:</strong> Cada una de las 3 entregas se guardará por separado indicando su hora exacta y su número correspondiente (1, 2 o 3).</span>
        </div>

      </div>
    </main>
  </div>

  <footer class="app-footer">
    <i data-lucide="coffee" size="16"></i>
    <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
  </footer>

  <script>
    lucide.createIcons();

    function inicializarFechas() {
      const ahora = new Date();
      const offset = ahora.getTimezoneOffset() * 60000;
      const fechaHoraLocal = new Date(ahora.getTime() - offset).toISOString().slice(0, 16);

      document.getElementById('fecha_1').value = fechaHoraLocal;
      document.getElementById('fecha_2').value = fechaHoraLocal;
      document.getElementById('fecha_3').value = fechaHoraLocal;
    }

    inicializarFechas();

    document.getElementById('btn-buscar').addEventListener('click', buscarRecolector);
    document.getElementById('cedula').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') buscarRecolector();
    });

    function buscarRecolector() {
      const cedula = document.getElementById('cedula').value.trim();
      if (!cedula) {
        Swal.fire('Atención', 'Por favor ingresa la cédula del recolector.', 'warning');
        return;
      }

      const formData = new FormData();
      formData.append('cedula', cedula);

      fetch('recolecciones_c.php?action=buscar_recolector', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const rec = data.data;
          document.getElementById('id_recolector_val').value = rec.id_usuario;
          document.getElementById('id_finca_val').value = rec.id_finca;
          
          document.getElementById('rec-nombre').textContent = `${rec.nombres} ${rec.apellidos}`;
          document.getElementById('rec-cedula').textContent = rec.documento;
          document.getElementById('rec-finca').textContent = `${rec.nombre_finca} / ${rec.ubicacion_vereda}`;
          document.getElementById('rec-telefono').textContent = rec.telefono || 'Sin teléfono';
          document.getElementById('rec-rol').textContent = rec.rol;
          document.getElementById('label-finca-top').textContent = rec.nombre_finca;

          document.getElementById('status-alert').style.display = 'flex';
          document.getElementById('recollector-card').style.display = 'flex';
        } else {
          Swal.fire('No encontrado', data.message, 'error');
          ocultarDetalles();
        }
      })
      .catch(() => Swal.fire('Error', 'Ocurrió un error al consultar el servidor.', 'error'));
    }

    function ocultarDetalles() {
      document.getElementById('id_recolector_val').value = '';
      document.getElementById('id_finca_val').value = '';
      document.getElementById('status-alert').style.display = 'none';
      document.getElementById('recollector-card').style.display = 'none';
    }

    document.getElementById('btn-guardar').addEventListener('click', function() {
      const idRecolector = document.getElementById('id_recolector_val').value;
      const idFinca = document.getElementById('id_finca_val').value;

      if (!idRecolector || !idFinca) {
        Swal.fire('Atención', 'Primero debes buscar y seleccionar un recolector.', 'warning');
        return;
      }

      const entregas = [
        {
          num_entrega: 1,
          fecha_hora: document.getElementById('fecha_1').value,
          kilos: document.getElementById('kilos_1').value,
          observaciones: document.getElementById('obs_1').value
        },
        {
          num_entrega: 2,
          fecha_hora: document.getElementById('fecha_2').value,
          kilos: document.getElementById('kilos_2').value,
          observaciones: document.getElementById('obs_2').value
        },
        {
          num_entrega: 3,
          fecha_hora: document.getElementById('fecha_3').value,
          kilos: document.getElementById('kilos_3').value,
          observaciones: document.getElementById('obs_3').value
        }
      ];

      const formData = new FormData();
      formData.append('id_recolector', idRecolector);
      formData.append('id_finca', idFinca);

      entregas.forEach((e, idx) => {
        formData.append(`entregas[${idx}][num_entrega]`, e.num_entrega);
        formData.append(`entregas[${idx}][fecha_hora]`, e.fecha_hora);
        formData.append(`entregas[${idx}][kilos]`, e.kilos);
        formData.append(`entregas[${idx}][observaciones]`, e.observaciones);
      });

      fetch('recolecciones_c.php?action=guardar_recolecciones', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire('¡Éxito!', data.message, 'success').then(() => limpiarCampos());
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      })
      .catch(() => Swal.fire('Error', 'No se pudieron guardar las entregas.', 'error'));
    });

    document.getElementById('btn-limpiar').addEventListener('click', limpiarCampos);

    function limpiarCampos() {
      document.getElementById('cedula').value = '';
      document.getElementById('kilos_1').value = '';
      document.getElementById('kilos_2').value = '';
      document.getElementById('kilos_3').value = '';
      document.getElementById('obs_1').value = '';
      document.getElementById('obs_2').value = '';
      document.getElementById('obs_3').value = '';
      ocultarDetalles();
      inicializarFechas();
    }
  </script>
</body>
</html>