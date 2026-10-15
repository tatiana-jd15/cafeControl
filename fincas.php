<?php
//  Iniciar sesión
session_start();

// Incluir la conexión 
require_once 'assets/inc/conexion.php'; 

//  Validar sesión de usuario
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del usuario logueado para mostrar 
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
$stmt_user->bind_param("i", $id_usuario);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

$nombre_caficultor = "Usuario";
$foto_perfil = "";
$rol_perfil = "";

if ($row_user = $res_user->fetch_assoc()) {
    $nombre_caficultor = trim($row_user['nombres'] . ' ' . $row_user['apellidos']);
    $foto_perfil = $row_user['foto_perfil'] ?? "";
    $rol_perfil = ucfirst($row_user['rol'] ?? '');
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}

$stmt_user->close();

// Consultar todas las fincas pertenecientes a este caficultor
$sql_fincas = "SELECT id_finca, nombre, ubicacion_vereda, estado FROM fincas WHERE id_caficultor = ?";
$stmt_fincas = $con->prepare($sql_fincas);
$stmt_fincas->bind_param("i", $id_usuario);
$stmt_fincas->execute();
$fincas_result = $stmt_fincas->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Café control</title>
  
  <!-- Fuente Google: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Iconos Lucide -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    :root {
      --bg-main: #f5f0eb;
      --bg-sidebar: #ece4db;
      --bg-card: #ffffff;
      --primary-brown: #3d200d;
      --primary-dark: #241307;
      --text-dark: #1f1813;
      --text-muted: #574e47;
      --status-active: #1b5e20;
      --status-paused: #e65100;
      --status-inactive: #424242;
      --border-color: #d6cabe;
      --font-family: 'Plus Jakarta Sans', sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: var(--font-family);
    }

    body {
      background-color: var(--bg-main);
      color: var(--text-dark);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      font-size: 16px;
      line-height: 1.5;
    }

    /* Normalización general de imágenes */
    img {
      max-width: 100%;
      height: auto;
      display: block;
    }

    /* Header Superior */
    .top-header {
      background-color: var(--primary-brown);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 72px;
      padding: 0 28px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      width: 250px;
    }

    .brand-logo {
      width: 40px;
      height: 40px;
      background-color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
    }

    .brand-logo img,
    .brand-logo .app-logo {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .brand-text h1 {
      font-size: 1.15rem;
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: 0.3px;
    }

    .header-center {
      display: flex;
      align-items: center;
      gap: 20px;
      flex: 1;
      padding-left: 20px;
    }

    .menu-toggle {
      background: none;
      border: none;
      color: #fff;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .user-welcome h2 {
      font-size: 1.1rem;
      font-weight: 700;
    }

    .user-welcome p {
      font-size: 0.9rem;
      opacity: 0.9;
      font-weight: 500;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 24px;
    }

    .icon-btn {
      background: none;
      border: none;
      color: #fff;
      cursor: pointer;
      position: relative;
    }

    .badge-dot {
      position: absolute;
      top: -2px;
      right: -2px;
      width: 10px;
      height: 10px;
      background-color: #4caf50;
      border-radius: 50%;
      border: 2px solid var(--primary-brown);
    }

    a.user-profile,
    a.user-profile:visited,
    a.user-profile:hover,
    a.user-profile:active {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      color: inherit;
      text-decoration: none;
    }

    .avatar {
      width: 48px;
      height: 48px;
      background-color: #fff;
      color: var(--primary-brown);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
      border: 1px solid var(--border-color);
    }

    .avatar img,
    .avatar .header-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
      display: block;
    }

    .avatar svg {
      stroke: var(--primary-brown) !important;
      color: var(--primary-brown) !important;
    }

    /* Layout Principal */
    .app-layout {
      display: flex;
      flex: 1;
    }

    /* Sidebar Lateral */
    .sidebar {
      width: 270px;
      background-color: var(--bg-sidebar);
      padding: 24px 18px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      border-right: 1px solid var(--border-color);
    }

    .nav-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .nav-item a {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 12px 18px;
      border-radius: 10px;
      color: var(--text-dark);
      text-decoration: none;
      font-size: 0.98rem;
      font-weight: 700;
      transition: all 0.2s ease-in-out;
    }

    .nav-item.active a {
      background-color: var(--primary-brown);
      color: #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }

    .nav-item:not(.active) a:hover {
      background-color: rgba(0, 0, 0, 0.08);
    }

    /* Contenido Principal */
    .main-content {
      flex: 1;
      padding: 40px;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
    }

    .page-title-area h2 {
      font-size: 1.8rem;
      font-weight: 800;
      margin-bottom: 4px;
      color: var(--text-dark);
    }

    .page-title-area p {
      font-size: 1rem;
      color: var(--text-muted);
      font-weight: 600;
    }

    .btn-primary {
      background-color: var(--primary-brown);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 12px 22px;
      font-size: 0.95rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      text-decoration: none;
      transition: background-color 0.2s;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    /* Grid de Fincas */
    .farms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 28px;
    }

    /* Tarjeta de Finca */
    .farm-card {
      background-color: var(--bg-card);
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid var(--border-color);
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    .farm-image-wrapper {
      position: relative;
      height: 160px;
      background-color: var(--bg-sidebar);
    }

    .farm-image-wrapper img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .status-badge {
      position: absolute;
      top: 14px;
      left: 14px;
      padding: 6px 14px;
      border-radius: 20px;
      color: #fff;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: capitalize;
      letter-spacing: 0.3px;
    }

    .status-badge.activa { background-color: var(--status-active); }
    .status-badge.en_pausa { background-color: var(--status-paused); }
    .status-badge.inactiva { background-color: var(--status-inactive); }

    .farm-icon-badge {
      position: absolute;
      bottom: -20px;
      left: 20px;
      width: 44px;
      height: 44px;
      background-color: var(--primary-brown);
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 4px solid var(--bg-card);
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .farm-body {
      padding: 28px 24px 24px 24px;
      display: flex;
      flex-direction: column;
      flex: 1;
    }

    .farm-title {
      font-size: 1.35rem;
      font-weight: 800;
      margin-bottom: 20px;
      color: var(--text-dark);
    }

    .btn-card {
      width: 100%;
      background-color: var(--primary-brown);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 12px;
      font-size: 0.95rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      cursor: pointer;
      margin-top: auto;
      text-decoration: none;
      transition: background-color 0.2s;
    }

    .btn-card:hover {
      background-color: var(--primary-dark);
    }

    /* Tarjeta para agregar nueva finca */
    .add-farm-card {
      background-color: rgba(236, 228, 219, 0.4);
      border: 2px dashed #b8a695;
      border-radius: 16px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 24px;
      text-align: center;
      min-height: 290px;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      transition: border-color 0.2s, background-color 0.2s;
    }

    .add-farm-card:hover {
      border-color: var(--primary-brown);
      background-color: rgba(236, 228, 219, 0.7);
    }

    .add-icon-circle {
      width: 56px;
      height: 56px;
      background-color: var(--primary-brown);
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .add-farm-card h3 {
      font-size: 1.2rem;
      font-weight: 800;
      margin-bottom: 8px;
    }

    .add-farm-card p {
      font-size: 0.92rem;
      color: var(--text-muted);
      font-weight: 600;
      max-width: 240px;
    }

    /* Footer */
    .app-footer {
      text-align: center;
      padding: 20px;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border-top: 1px solid var(--border-color);
      background-color: #efe8e0;
    }

    /* Responsividad Móvil */
    @media (max-width: 768px) {
      .main-content {
        padding: 24px 16px;
      }
      .page-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
      }
      .btn-primary {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

  <!-- Encabezado Principal -->
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
          <a href="panel_caficultor.php"><i data-lucide="home" size="20"></i> Perfil</a>
        </li>
        <li class="nav-item active">
          <a href="fincas.php"><i data-lucide="map-pin" size="20"></i> Fincas</a>
        </li>
        <li class="nav-item">
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
        <li class="nav-item">
          <a href="asignar_m.php"><i class="fa-solid fa-user-gear" style="font-size: 18px;"></i> Mayordomo</a>
        </li>
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

    <!-- Contenido Principal -->
    <main class="main-content">
      <div class="page-header">
        <div class="page-title-area">
          <h2>Mis Fincas</h2>
          <p>Gestión y monitoreo de los predios cafeteros registrados</p>
        </div>
        <a href="agregar_finca.php" class="btn-primary">
          <i data-lucide="plus" size="18"></i> Nueva Finca
        </a>
      </div>

      <!-- card de fincas -->
      <div class="farms-grid">

        <?php
        if ($fincas_result && $fincas_result->num_rows > 0) {
            while ($finca = $fincas_result->fetch_assoc()) {
                
                $estado_clase = $finca['estado']; 
                $estado_texto = ucfirst(str_replace('_', ' ', $finca['estado']));
        ?>
                <!-- Tarjeta de Finca Registrada -->
                <article class="farm-card">
                  <div class="farm-image-wrapper">
                    <img src="images/finca.jpg" alt="Finca <?php echo htmlspecialchars($finca['nombre']); ?>">
                    <span class="status-badge <?php echo htmlspecialchars($estado_clase); ?>">
                      <?php echo htmlspecialchars($estado_texto); ?>
                    </span>
                    <div class="farm-icon-badge">
                      <i data-lucide="coffee" size="20"></i>
                    </div>
                  </div>

                  <div class="farm-body">
                    <h3 class="farm-title"><?php echo htmlspecialchars($finca['nombre']); ?></h3>

                    <a href="detalles.php?id=<?php echo $finca['id_finca']; ?>" class="btn-card">
                      Ver Detalles <i data-lucide="arrow-right" size="16"></i>
                    </a>
                  </div>
                </article>
        <?php
            }
        }
        $stmt_fincas->close();
        ?>

        <!-- Tarjeta Botón para Agregar Nueva Finca -->
        <a href="agregar_finca.php" class="add-farm-card">
          <div class="add-icon-circle">
            <i data-lucide="plus" size="26"></i>
          </div>
          <h3>Agregar Nueva Finca</h3>
          <p>Registra un nuevo predio para iniciar la gestión de recolección.</p>
        </a>

      </div>
    </main>
  </div>

  <!-- Pie de Página -->
  <footer class="app-footer">
    <i data-lucide="coffee" size="16"></i>
    <span><strong>Café control</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
  </footer>

  <!-- Inicialización de Iconos Lucide -->
  <script>
    lucide.createIcons();
  </script>
</body>
</html>