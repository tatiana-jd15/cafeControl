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

$id_finca = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_finca > 0) {
    // 1. Obtener la información de la finca filtrando por id_finca
    $sql_finca = "SELECT id_finca, nombre, ubicacion_vereda, estado FROM fincas WHERE id_finca = ?";
    $stmt_finca = $con->prepare($sql_finca);
    $stmt_finca->bind_param("i", $id_finca);
    $stmt_finca->execute();
    $finca = $stmt_finca->get_result()->fetch_assoc();

    // Redirigir si el ID no pertenece a ninguna finca existente
    if (!$finca) {
        header("Location: fincas.php");
        exit();
    }

    // 2. Obtener la suma total de kilos recolectados en la finca
    $stmt_kilos = $con->prepare("SELECT SUM(kilos_recolectados) AS total_kilos FROM recolecciones WHERE id_finca = ?");
    $stmt_kilos->bind_param("i", $id_finca);
    $stmt_kilos->execute();
    $total_kilos = $stmt_kilos->get_result()->fetch_assoc()['total_kilos'] ?? 0;

    // 3. Obtener trabajadores de la finca y el acumulado de kilos que ha recolectado cada uno en ESTA finca
    $stmt_trabajadores = $con->prepare("
        SELECT 
            u.id_usuario, 
            CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo, 
            u.documento, 
            u.telefono,
            COALESCE(SUM(r.kilos_recolectados), 0) AS kilos_trabajador
        FROM usuarios u
        INNER JOIN recolectores rec ON u.id_usuario = rec.id_usuario
        LEFT JOIN recolecciones r ON u.id_usuario = r.id_recolector AND r.id_finca = rec.id_finca
        WHERE rec.id_finca = ?
        GROUP BY u.id_usuario, u.nombres, u.apellidos, u.documento, u.telefono
    ");
    $stmt_trabajadores->bind_param("i", $id_finca);
    $stmt_trabajadores->execute();
    $resultado_trabajadores = $stmt_trabajadores->get_result();

} else {
    header("Location: fincas.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Café Control</title>
  
  <!-- Fuente Google: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Iconos Lucide -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    :root {
      --bg-main: #f4efe9;
      --bg-sidebar: #eae1d7;
      --bg-card: #ffffff;
      --primary-brown: #3d200d;
      --primary-dark: #261307;
      --text-dark: #1f1712;
      --text-muted: #57504a;
      --border-color: #d8cbd0;
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
      font-size: 18px; /* Tamaño general incrementado */
      line-height: 1.5;
    }

    /* Header Superior */
    .top-header {
      background-color: var(--primary-brown);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 75px;
      padding: 0 30px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-logo {
      width: 42px;
      height: 42px;
      background-color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-brown);
    }

    .brand-text h1 {
      font-size: 20px;
      font-weight: 800;
      letter-spacing: 0.5px;
    }

    .header-center {
      display: flex;
      align-items: center;
      gap: 20px;
      flex: 1;
      padding-left: 30px;
    }

    .menu-toggle {
      background: none;
      border: none;
      color: #fff;
      cursor: pointer;
    }

    .user-welcome h2 {
      font-size: 18px;
      font-weight: 700;
    }

    .user-welcome p {
      font-size: 14px;
      opacity: 0.9;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 24px;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: #fff;
      cursor: pointer;
    }

    .user-profile:hover {
      opacity: 0.9;
    }

    .avatar {
      width: 44px;
      height: 44px;
      background-color: #fff;
      color: var(--primary-brown);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .header-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      border-radius: 50%;
    }

    /* Layout Principal */
    .app-layout {
      display: flex;
      flex: 1;
    }

    /* Sidebar Lateral */
    .sidebar {
      width: 280px;
      background-color: var(--bg-sidebar);
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
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
      padding: 14px 18px;
      border-radius: 10px;
      color: var(--text-dark);
      text-decoration: none;
      font-size: 16px;
      font-weight: 700;
      transition: all 0.2s;
    }

    .nav-item.active a {
      background-color: var(--primary-brown);
      color: #fff;
    }

    .nav-item:not(.active) a:hover {
      background-color: rgba(0, 0, 0, 0.08);
    }

    /* Contenido Principal */
    .contenedor {
      flex: 1;
      max-width: 1500px;
      margin: 30px auto;
      background: white;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.06);
    }

    .encabezado {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 30px;
      margin-bottom: 40px;
      border-bottom: 2px solid #f0ebe4;
      padding-bottom: 30px;
    }

    .info-finca {
      display: flex;
      align-items: center;
      gap: 30px;
    }

    .logo-img {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid var(--primary-brown);
    }

    .etiqueta {
      color: #174d3b;
      font-size: 22px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .nombre-finca {
      font-size: 44px;
      font-weight: 800;
      color: #174d3b;
      line-height: 1.1;
      margin: 6px 0 10px 0;
    }

    .descripcion {
      font-size: 20px;
      color: var(--text-muted);
      line-height: 1.6;
    }

    .descripcion strong {
      color: var(--text-dark);
    }

    /* Tarjeta Total Kilos */
    .tarjeta-total {
      min-width: 380px;
      background: #eaf5ee;
      border: 2px solid #c3e2cd;
      border-radius: 18px;
      padding: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .total-titulo {
      font-size: 20px;
      font-weight: 700;
      color: #1e4a36;
      margin-bottom: 8px;
    }

    .total-numero {
      font-size: 50px;
      font-weight: 800;
      color: #0f382a;
      line-height: 1;
    }

    .kg {
      font-size: 26px;
      font-weight: 700;
    }

    .icono-kilos {
      width: 75px;
      height: 75px;
      background: #d1ead8;
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 38px;
    }

    /* Tabla Visualmente Clara */
    .tabla-contenedor {
      overflow-x: auto;
      border-radius: 14px;
      border: 2px solid #e0e5e2;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }

    thead {
      background: #174d3b;
      color: white;
    }

    th {
      padding: 22px;
      text-align: left;
      font-size: 19px;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    td {
      padding: 22px;
      border-bottom: 1px solid #e3e7e5;
      font-size: 19px;
      font-weight: 600;
    }

    tbody tr:hover {
      background: #f4f8f5;
    }

    .cantidad {
      color: #174d3b;
      font-weight: 800;
      font-size: 20px;
    }

    /* Footer */
    .app-footer {
      text-align: center;
      padding: 24px;
      font-size: 15px;
      color: var(--text-muted);
      background-color: var(--bg-sidebar);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: auto;
    }

    /* Adaptabilidad */
    @media (max-width: 992px) {
      .encabezado { flex-direction: column; align-items: flex-start; }
      .tarjeta-total { width: 100%; min-width: 0; }
      .app-layout { flex-direction: column; }
      .sidebar { width: 100%; }
    }
  </style>
</head>
<body>

  <!-- Encabezado Principal -->
  <header class="top-header">
    <div class="brand">
      <div class="brand-logo">
        <i data-lucide="coffee" size="24"></i>
      </div>
      <div class="brand-text">
        <h1>Café Control</h1>
      </div>
    </div>

    <div class="header-center">
      <button class="menu-toggle" aria-label="Abrir menú">
        <i data-lucide="menu" size="26"></i>
      </button>
      <div class="user-welcome">
        <h2>Bienvenido, <?php echo htmlspecialchars($nombre_caficultor); ?></h2>
        <p>Administra tus fincas y consulta su información</p>
      </div>
    </div>

    <div class="header-actions">
      <a 
        href="panel_caficultor.php" 
        class="user-profile"
        aria-label="Ver perfil"
        title="Ver perfil"
      >
        <div class="avatar">
          <?php if (!empty($foto_perfil)) { ?>
            <img 
              src="<?php echo htmlspecialchars($foto_perfil); ?>" 
              alt="Foto de perfil"
              class="header-avatar"
            >
          <?php } else { ?>
            <i data-lucide="user" size="24"></i>
          <?php } ?>
        </div>

        <i data-lucide="chevron-down" size="20"></i>
      </a>
    </div>
  </header>
 
  <div class="app-layout">
    <!-- Menú Lateral -->
    <aside class="sidebar">
      <ul class="nav-list">
        <li class="nav-item">
          <a href="panel_caficultor.php"><i data-lucide="home" size="22"></i> Perfil</a>
        </li>
        <li class="nav-item active">
          <a href="fincas.php"><i data-lucide="map-pin" size="22"></i> Mis Fincas</a>
        </li>
        <li class="nav-item">
          <a href="recolecciones_c.php"><i data-lucide="archive" size="22"></i> Recolecciones</a>
        </li>
        <li class="nav-item">
          <a href="recolectores_c.php"><i data-lucide="users" size="22"></i> Trabajadores</a>
        </li>
        <li class="nav-item">
          <a href="resumen_c.php"><i data-lucide="bar-chart-2" size="22"></i> Resumen General</a>
        </li>
        <li class="nav-item">
          <a href="precio.php"><i data-lucide="dollar-sign" size="22"></i> Precio del Kilo</a>
        </li>
        <li class="nav-item">
          <a href="asignar_m.php"><i class="fa-solid fa-user-gear"></i> Mayordomo</a>
        </li>
        <li class="nav-item">
          <a href="cerrar.php"><i data-lucide="log-out" size="22"></i> Cerrar Sesión</a>
        </li>
      </ul>
    </aside>

    <!-- Contenido -->
    <main class="contenedor">

      <section class="encabezado">
        <div class="info-finca">
          <div class="logo-container">
            <img src="images/cafef.jpg" alt="Finca" class="logo-img">
          </div>
          <div>
            <div class="etiqueta">Finca</div>
            <h1 class="nombre-finca">
              <?php echo htmlspecialchars($finca['nombre']); ?>
            </h1>
            <p class="descripcion">
              Vereda: <strong><?php echo htmlspecialchars($finca['ubicacion_vereda'] ?? 'No registrada'); ?></strong><br>
              Estado: <strong><?php echo ucfirst(htmlspecialchars($finca['estado'])); ?></strong>
            </p>
          </div>
        </div>

        <!-- Total Kilos -->
        <div class="tarjeta-total">
          <div>
            <div class="total-titulo">Total kilos recolectados</div>
            <div class="total-numero">
              <?php echo number_format($total_kilos, 2); ?> <span class="kg">kg</span>
            </div>
          </div>
          <div class="icono-kilos">⚖️</div>
        </div>
      </section>

      <!-- Tabla de Datos -->
      <div class="tabla-contenedor">
        <table>
          <thead>
            <tr>
              <th>Documento</th>
              <th>Nombre Completo</th>
              <th>Teléfono</th>
              <th>Kilos Recolectados</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($resultado_trabajadores && $resultado_trabajadores->num_rows > 0): ?>
              <?php while ($trabajador = $resultado_trabajadores->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($trabajador['documento']); ?></td>
                  <td><?php echo htmlspecialchars($trabajador['nombre_completo']); ?></td>
                  <td><?php echo htmlspecialchars($trabajador['telefono']); ?></td>
                  <td class="cantidad"><?php echo number_format($trabajador['kilos_trabajador'], 2); ?> kg</td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" style="text-align: center; padding: 30px;">No hay recolectores asignados a esta finca.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>

  <!-- Pie de Página -->
  <footer class="app-footer">
    <i data-lucide="coffee" size="18"></i>
    <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
  </footer>

  <!-- Inicialización de Iconos -->
  <script>
    lucide.createIcons();
  </script>
</body>
</html>