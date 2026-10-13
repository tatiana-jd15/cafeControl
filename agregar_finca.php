<?php
session_start();
require_once 'assets/inc/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario_sesion = $_SESSION['id_usuario'];

// Obtener información del usuario para el encabezado
$sql_user = "SELECT nombres, apellidos FROM usuarios WHERE id_usuario = ?";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id_usuario_sesion);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

if ($row_user = $res_user->fetch_assoc()) {
    $nombre_caficultor = $row_user['nombres'] . ' ' . $row_user['apellidos'];
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrar Finca - Café Control</title>
  
  <!-- Fuentes e Íconos -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --bg-main: #f4efe9;
      --bg-sidebar: #e8ded1;
      --bg-card: #ffffff;
      --bg-card-inner: #faf7f4;
      --primary-brown: #3d210d;
      --primary-dark: #241206;
      --primary-green: #075e36;
      --primary-green-hover: #054829;
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
      width: 48px; height: 48px;
      background-color: #fff;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: var(--primary-brown);
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
    .user-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; }
    .avatar {
      width: 48px; height: 48px;
      background-color: #fff;
      color: var(--primary-brown);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 800;
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
    .main-content { flex: 1; padding: 40px 48px; display: flex; flex-direction: column; gap: 32px; justify-content: flex-start; align-items: center; }
    
    .panel-card {
      background-color: var(--bg-card);
      border-radius: 20px;
      border: 1px solid var(--border-color);
      padding: 40px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.06);
      width: 100%;
      max-width: 800px;
    }

    .panel-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 32px;
      border-bottom: 2px solid var(--border-color);
      padding-bottom: 20px;
    }

    .panel-icon-circle {
      width: 56px;
      height: 56px;
      background-color: var(--primary-green);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      flex-shrink: 0;
    }

    .panel-header-text h2 {
      font-size: 26px;
      color: var(--primary-dark);
      font-weight: 800;
    }

    .panel-header-text p {
      font-size: 17px;
      color: var(--text-muted);
      font-weight: 600;
      margin-top: 4px;
    }

    .form-container-inner {
      background-color: var(--bg-card-inner);
      border: 2px solid var(--border-color);
      border-radius: 16px;
      padding: 32px;
    }

    .form-grid {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-group label {
      font-size: 18px;
      font-weight: 800;
      color: var(--text-dark);
    }

    span.required {
      color: #c62828;
    }

    .custom-input, .custom-select {
      width: 100%;
      padding: 16px 20px;
      border-radius: 14px;
      border: 2px solid var(--border-color);
      background-color: #ffffff;
      font-size: 18px;
      font-weight: 700;
      outline: none;
      color: var(--text-dark);
      transition: border-color 0.2s;
    }

    .custom-input:focus, .custom-select:focus {
      border-color: var(--primary-brown);
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 16px;
      margin-top: 32px;
    }

    .btn-reset {
      background-color: #e0d6cb;
      color: var(--primary-dark);
      border: none;
      padding: 16px 28px;
      border-radius: 14px;
      font-weight: 800;
      font-size: 18px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .btn-reset:hover {
      background-color: #d1c3b3;
    }

    .btn-submit {
      background-color: var(--primary-green);
      color: #ffffff;
      border: none;
      padding: 16px 32px;
      border-radius: 14px;
      font-weight: 800;
      font-size: 18px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: background-color 0.2s, transform 0.1s;
      box-shadow: 0 4px 12px rgba(7, 94, 54, 0.2);
    }

    .btn-submit:hover {
      background-color: var(--primary-green-hover);
      transform: translateY(-1px);
    }

    .app-footer {
      text-align: center;
      padding: 24px;
      font-size: 15px;
      font-weight: 600;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background-color: var(--bg-main);
      border-top: 1px solid var(--border-color);
      margin-top: auto;
    }

    @media (max-width: 992px) {
      .app-layout { flex-direction: column; }
      .sidebar { width: 100%; }
      .main-content { padding: 24px; }
      .form-actions { flex-direction: column-reverse; }
      .btn-submit, .btn-reset { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

  <!-- Encabezado Superior -->
  <header class="top-header">
    <div class="brand">
      <div class="brand-logo">
        <i data-lucide="coffee" size="28"></i>
      </div>
      <div class="brand-text">
        <h1>Café Control</h1>
      </div>
    </div>

    <div class="header-center">
      <button class="menu-toggle" aria-label="Abrir Menú">
        <i data-lucide="menu" size="28"></i>
      </button>
      <div class="user-welcome">
        <h2>Bienvenido, <?php echo htmlspecialchars($nombre_caficultor); ?></h2>
        <p>Administra tus fincas y consulta su información</p>
      </div>
    </div>

    <div class="header-actions">
      <button class="icon-btn" aria-label="Notificaciones">
        <i data-lucide="bell" size="26"></i>
        <span class="badge-dot"></span>
      </button>
      <div class="user-profile">
        <div class="avatar">
          <i data-lucide="user" size="26"></i>
        </div>
        <i data-lucide="chevron-down" size="22"></i>
      </div>
    </div>
  </header>

  <div class="app-layout">
    
    <!-- Menú Lateral -->
    <aside class="sidebar">
      <ul class="nav-list">
        <li class="nav-item">
          <a href="panel_caficultor.php"><i data-lucide="home" size="24"></i> Perfil</a>
        </li>
        <li class="nav-item active">
          <a href="fincas.php"><i data-lucide="map-pin" size="24"></i> Fincas</a>
        </li>
        <li class="nav-item">
          <a href="recolecciones_c.php"><i data-lucide="archive" size="24"></i> Recolecciones</a>
        </li>
        <li class="nav-item">
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
          <a href="cerrar.php"><i data-lucide="log-out" size="24"></i> Cerrar Sesión</a>
        </li>
      </ul>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content">
      <div class="panel-card">
        
        <div class="panel-header">
          <div class="panel-icon-circle">
            <i class="fa-solid fa-map-location-dot"></i>
          </div>
          <div class="panel-header-text">
            <h2>Registro de nueva finca cafetera</h2>
            <p>Complete los datos para agregar el predio a su cuenta.</p>
          </div>
        </div>

        <div class="form-container-inner">
          <form action="guardar_finca.php" method="POST">
            <div class="form-grid">
              
              <div class="form-group">
                <label for="nombre">Nombre de la finca <span class="required">*</span></label>
                <input type="text" id="nombre" name="nombre" class="custom-input" placeholder="Ej. El Paraíso" required>
              </div>

              <div class="form-group">
                <label for="ubicacion_vereda">Ubicación / Vereda <span class="required">*</span></label>
                <input type="text" id="ubicacion_vereda" name="ubicacion_vereda" class="custom-input" placeholder="Ej. Vereda La Esmeralda" required>
              </div>

              <div class="form-group">
                <label for="estado">Estado inicial <span class="required">*</span></label>
                <select id="estado" name="estado" class="custom-select" required>
                  <option value="activa" selected>Activa</option>
                  <option value="en_pausa">En Pausa</option>
                  <option value="inactiva">Inactiva</option>
                </select>
              </div>

            </div>

            <div class="form-actions">
              <button type="reset" class="btn-reset">Limpiar</button>
              <button type="submit" class="btn-submit">
                <i class="fa-solid fa-floppy-disk"></i> Guardar finca
              </button>
            </div>
          </form>
        </div>

      </div>
    </main>
  </div>

  <footer class="app-footer">
    <i data-lucide="coffee" size="18"></i>
    <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
  </footer>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>