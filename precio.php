<?php
session_start();

require_once 'assets/inc/conexion.php'; 

// ------------------------------------------------------------------
// 1. VALIDAR SESIÓN ACTIVA Y UNIFICAR VARIABLE DE USUARIO
// ------------------------------------------------------------------
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje_estado = '';

// ------------------------------------------------------------------
// 2. OBTENER DATOS DEL USUARIO LOGUEADO
// ------------------------------------------------------------------
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

// ------------------------------------------------------------------
// 3. OBTENER FINCAS DEL CAFICULTOR
// ------------------------------------------------------------------
$stmt_fincas = $con->prepare("SELECT id_finca, nombre FROM fincas WHERE id_caficultor = ?");
$stmt_fincas->bind_param("i", $id_usuario);
$stmt_fincas->execute();
$res_fincas = $stmt_fincas->get_result();
$fincas = $res_fincas->fetch_all(MYSQLI_ASSOC);

// Determinar id_finca activa (seleccionada por POST/GET o la primera por defecto)
$id_finca = 0;
if (isset($_REQUEST['id_finca']) && intval($_REQUEST['id_finca']) > 0) {
    $id_finca = intval($_REQUEST['id_finca']);
} elseif (!empty($fincas)) {
    $id_finca = $fincas[0]['id_finca'];
}

// ------------------------------------------------------------------
// 4. PROCESAR GUARDADO DEL NUEVO PRECIO (POST)
// ------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nuevo_precio'])) {
    $nuevo_precio = filter_var($_POST['nuevo_precio'], FILTER_VALIDATE_FLOAT);
    $id_finca_post = isset($_POST['id_finca']) ? intval($_POST['id_finca']) : 0;

    if ($nuevo_precio !== false && $nuevo_precio > 0 && $id_finca_post > 0) {
        $id_finca = $id_finca_post; // Actualizar finca activa

        $sql_insert = "INSERT INTO historial_precios (id_finca, precio_kilo, fecha_actualizacion, id_actualizado_por) VALUES (?, ?, NOW(), ?)";
        $stmt_insert = $con->prepare($sql_insert);
        $stmt_insert->bind_param("idi", $id_finca, $nuevo_precio, $id_usuario);
        
        if ($stmt_insert->execute()) {
            $mensaje_estado = "<div style='background-color: #c8e6c9; color: #1b5e20; border: 2px solid #81c784; padding: 16px 20px; border-radius: 12px; font-weight: 800; font-size: 18px; margin-bottom: 24px;'>¡Precio actualizado correctamente!</div>";
        } else {
            $mensaje_estado = "<div style='background-color: #ffcdd2; color: #b71c1c; border: 2px solid #ef5350; padding: 16px 20px; border-radius: 12px; font-weight: 800; font-size: 18px; margin-bottom: 24px;'>Error al guardar el precio.</div>";
        }
    } else {
        $mensaje_estado = "<div style='background-color: #ffcdd2; color: #b71c1c; border: 2px solid #ef5350; padding: 16px 20px; border-radius: 12px; font-weight: 800; font-size: 18px; margin-bottom: 24px;'>Ingrese un valor de precio válido o asegúrese de seleccionar una finca.</div>";
    }
}

// ------------------------------------------------------------------
// 5. OBTENER PRECIO ACTUAL DE LA FINCA SELECCIONADA
// ------------------------------------------------------------------
$precio_actual = 0;
if ($id_finca > 0) {
    $stmt_actual = $con->prepare("SELECT precio_kilo FROM historial_precios WHERE id_finca = ? ORDER BY id_precio DESC LIMIT 1");
    $stmt_actual->bind_param("i", $id_finca);
    $stmt_actual->execute();
    $res_actual = $stmt_actual->get_result();

    if ($row_actual = $res_actual->fetch_assoc()) {
        $precio_actual = $row_actual['precio_kilo'];
    }
}

// ------------------------------------------------------------------
// 6. OBTENER HISTORIAL DE PRECIOS
// ------------------------------------------------------------------
$res_historial = false;
if ($id_finca > 0) {
    $sql_historial = "SELECT precio_kilo, fecha_actualizacion FROM historial_precios WHERE id_finca = ? ORDER BY id_precio DESC LIMIT 5";
    $stmt_historial = $con->prepare($sql_historial);
    $stmt_historial->bind_param("i", $id_finca);
    $stmt_historial->execute();
    $res_historial = $stmt_historial->get_result();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Precio del Kilo - Café Control</title>
  
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

    /* Normalización general de imágenes */
    img {
      max-width: 100%;
      height: auto;
      display: block;
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
      width: 48px; 
      height: 48px;
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

    a.user-profile,
    a.user-profile:visited,
    a.user-profile:hover,
    a.user-profile:active {
      display: flex;
      align-items: center;
      gap: 12px;
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
    
    .section-card {
      background-color: var(--bg-card);
      border-radius: 20px;
      border: 1px solid var(--border-color);
      padding: 40px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.06);
      width: 100%;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 32px;
    }

    .title-icon {
      width: 56px;
      height: 56px;
      background-color: var(--primary-brown);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .title-text h2 {
      font-size: 26px;
      color: var(--primary-dark);
      font-weight: 800;
    }

    .title-text p {
      font-size: 17px;
      color: var(--text-muted);
      font-weight: 600;
      margin-top: 4px;
    }

    /* Form & Controls */
    .finca-select-box {
      margin-bottom: 32px;
      max-width: 450px;
    }

    .finca-select-box label {
      font-size: 18px;
      font-weight: 800;
      color: var(--text-dark);
      display: block;
      margin-bottom: 10px;
    }

    .select-style {
      width: 100%;
      padding: 16px 20px;
      border-radius: 14px;
      border: 2px solid var(--border-color);
      background-color: #fff;
      font-size: 18px;
      font-weight: 700;
      color: var(--text-dark);
      outline: none;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    .select-style:focus { border-color: var(--primary-brown); }

    .grid-price {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 40px;
    }

    .price-content {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .price-inputs-row {
      display: flex;
      gap: 20px;
      align-items: stretch;
    }

    .current-price-card {
      background-color: var(--bg-card-inner);
      border: 2px solid var(--border-color);
      border-radius: 16px;
      padding: 24px;
      flex: 1;
    }

    .current-price-card label,
    .new-price-card label {
      font-size: 16px;
      font-weight: 800;
      color: var(--text-muted);
      display: block;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .price-value-display {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--primary-green);
    }

    .price-value-display i {
      color: var(--primary-green);
    }

    .new-price-card {
      flex: 1;
    }

    .input-price-wrapper {
      display: flex;
      align-items: center;
      border: 2px solid var(--border-color);
      border-radius: 14px;
      background-color: #ffffff;
      padding: 0 20px;
      height: 68px;
      transition: border-color 0.2s;
    }
    .input-price-wrapper:focus-within {
      border-color: var(--primary-brown);
    }

    .input-price-wrapper span {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--text-muted);
      margin-right: 10px;
    }

    .input-price-wrapper input {
      width: 100%;
      border: none;
      outline: none;
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--text-dark);
      background: transparent;
    }

    .btn-save-right {
      align-self: flex-start;
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

    .btn-save-right:hover {
      background-color: var(--primary-green-hover);
      transform: translateY(-1px);
    }
    .btn-save-right:disabled {
      background-color: #a5d6a7;
      cursor: not-allowed;
    }

    /* Historial Side Box */
    .history-box {
      border-left: 3px solid var(--border-color);
      padding-left: 36px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .history-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 18px;
      font-weight: 800;
      color: var(--primary-dark);
      border-bottom: 2px solid var(--border-color);
      padding-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .history-list {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .history-item {
      border-bottom: 1px dashed var(--border-color);
      padding-bottom: 12px;
    }

    .history-item strong {
      display: block;
      color: var(--text-dark);
      font-size: 20px;
      font-weight: 800;
    }

    .history-item span {
      color: var(--text-muted);
      font-size: 15px;
      font-weight: 600;
    }

    .footer-note {
      background-color: #ede3d7;
      border: 2px solid var(--border-color);
      border-radius: 14px;
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      font-size: 17px;
      font-weight: 600;
      color: var(--text-dark);
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
    }

    @media (max-width: 992px) {
      .grid-price {
        grid-template-columns: 1fr;
      }
      .price-inputs-row {
        flex-direction: column;
      }
      .history-box {
        border-left: none;
        border-top: 3px solid var(--border-color);
        padding-left: 0;
        padding-top: 28px;
      }
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
        <li class="nav-item">
          <a href="recolectores_c.php"><i data-lucide="users" size="24"></i> Trabajadores</a>
        </li>
        <li class="nav-item">
          <a href="resumen_c.php"><i data-lucide="bar-chart-2" size="24"></i> Resumen General</a>
        </li>
        <li class="nav-item active">
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

      <section class="section-card">
        <div class="section-title">
          <div class="title-icon">
            <i data-lucide="tags" size="30"></i>
          </div>
          <div class="title-text">
            <h2>Precio del kilo de café</h2>
            <p>Asigne y actualice la tarifa vigente por kilo recolectado en sus fincas.</p>
          </div>
        </div>

        <?php echo $mensaje_estado; ?>

        <!-- Selector de Finca -->
        <div class="finca-select-box">
          <label for="finca_select">Seleccionar Finca:</label>
          <form method="GET" action="precio.php" id="form_finca">
            <select name="id_finca" id="finca_select" class="select-style" onchange="this.form.submit()">
              <?php if (!empty($fincas)): ?>
                <?php foreach ($fincas as $f): ?>
                  <option value="<?php echo $f['id_finca']; ?>" <?php echo ($f['id_finca'] == $id_finca) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($f['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              <?php else: ?>
                <option value="0">No tiene fincas registradas</option>
              <?php endif; ?>
            </select>
          </form>
        </div>

        <div class="grid-price">
          
          <!-- Formulario Actualizar Precio -->
          <form method="POST" action="precio.php" class="price-content">
            <input type="hidden" name="id_finca" value="<?php echo $id_finca; ?>">
            <div class="price-inputs-row">
              <div class="current-price-card">
                <label>Precio actual por kilo (COP)</label>
                <div class="price-value-display">
                  <i data-lucide="dollar-sign" size="34"></i>
                  <span><?php echo number_format($precio_actual, 0, ',', '.'); ?></span> 
                </div>
              </div>

              <div class="new-price-card">
                <label>Nuevo precio por kilo (COP)</label>
                <div class="input-price-wrapper">
                  <span>$</span>
                  <input type="number" step="0.01" min="0" placeholder="0" name="nuevo_precio" required>
                </div>
              </div>
            </div>

            <button type="submit" class="btn-save-right" <?php echo ($id_finca == 0) ? 'disabled' : ''; ?>>
              <i data-lucide="save" size="24"></i> Guardar Precio
            </button>
          </form>

          <!-- Historial de Cambios -->
          <div class="history-box">
            <div class="history-header">
              <span>Historial de cambios</span>
              <i data-lucide="rotate-cw" size="22" style="cursor: pointer; color: var(--text-muted);" onclick="location.reload();"></i>
            </div>

            <div class="history-list">
              <?php if ($res_historial && $res_historial->num_rows > 0): ?>
                <?php while ($row_h = $res_historial->fetch_assoc()): ?>
                  <div class="history-item">
                    <strong>$ <?php echo number_format($row_h['precio_kilo'], 0, ',', '.'); ?> COP</strong>
                    <span><?php echo date('d/m/Y H:i', strtotime($row_h['fecha_actualizacion'])); ?> • Por <?php echo htmlspecialchars($nombre_caficultor); ?></span>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <div class="history-item">
                  <span>No hay registros en el historial para esta finca.</span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <div class="footer-note">
        <i data-lucide="info" size="26" style="color: var(--primary-brown); flex-shrink: 0;"></i>
        <span><strong>Nota importante:</strong> Como administrador tiene control total del sistema y puede actualizar la tarifa vigente según los cambios del mercado.</span>
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