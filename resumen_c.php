<?php
session_start();
require_once 'assets/inc/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit(); 
}

// Asignar el ID de sesión a una variable unificada
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

// ------------------------------------------------------------------
// OBTENER EL ÚLTIMO PRECIO DE KILO VÁLIDO REGISTRADO EN LA BD (> 0)
// ------------------------------------------------------------------
$precio_fallback = 0;
$sql_ultimo_precio = "SELECT precio_kilo_aplicado 
                      FROM recolecciones 
                      WHERE id_registrado_por = ? AND precio_kilo_aplicado > 0 
                      ORDER BY id_recoleccion DESC LIMIT 1";
$stmt_p = $con->prepare($sql_ultimo_precio);
if ($stmt_p) {
    // Cambiado $id_usuario_sesion por $id_usuario
    $stmt_p->bind_param("i", $id_usuario);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    if ($row_p = $res_p->fetch_assoc()) {
        $precio_fallback = (float)$row_p['precio_kilo_aplicado'];
    }
}

// ------------------------------------------------------------------
// CONSULTA: GRÁFICO DE BARRAS (Kilos reales desde la BD por fecha)
// ------------------------------------------------------------------
$grafico_fechas = [];
$grafico_kilos = [];

$stmt_barras = $con->prepare('SELECT DATE(fecha) AS fecha_corta, SUM(kilos_recolectados) AS total_dia 
                              FROM recolecciones 
                              WHERE id_registrado_por = ? 
                              GROUP BY DATE(fecha) 
                              ORDER BY DATE(fecha) ASC 
                              LIMIT 7');
// Cambiado $id_usuario_sesion por $id_usuario
$stmt_barras->bind_param("i", $id_usuario);
$stmt_barras->execute();
$res_barras = $stmt_barras->get_result();

while ($row = $res_barras->fetch_assoc()) {
    $grafico_fechas[] = date('d/m/Y', strtotime($row['fecha_corta']));
    $grafico_kilos[] = (float)$row['total_dia'];
}

// ------------------------------------------------------------------
// CONSULTA: ÚLTIMAS RECOLECCIONES
// ------------------------------------------------------------------
$sql_ultimas = "SELECT 
                    r.id_recoleccion,
                    r.fecha,
                    r.numero_entrega,
                    r.kilos_recolectados,
                    r.precio_kilo_aplicado,
                    r.pago_calculado,
                    r.observaciones,
                    CONCAT(u.nombres, ' ', u.apellidos) AS recolector
                FROM recolecciones r
                INNER JOIN usuarios u ON r.id_recolector = u.id_usuario
                WHERE r.id_registrado_por = ?
                ORDER BY r.fecha DESC, r.id_recoleccion DESC
                LIMIT 10";

$stmt_ult = $con->prepare($sql_ultimas);
// Cambiado $id_usuario_sesion por $id_usuario
$stmt_ult->bind_param("i", $id_usuario);
$stmt_ult->execute();
$res_ultimas = $stmt_ult->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resumen General - Café Control</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root {
      --bg-main: #f5f0eb;
      --bg-sidebar: #ece4db;
      --bg-card: #fcfaf7;
      --card-inner-bg: #f3ece4;
      --primary-brown: #4a2810;
      --primary-dark: #2c1608;
      --text-dark: #1f1712;
      --text-muted: #544d46;
      --border-color: #d1c4b6;
      --font-family: 'Plus Jakarta Sans', sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-family); }
    body { background-color: var(--bg-main); color: var(--text-dark); display: flex; flex-direction: column; min-height: 100vh; font-size: 16px; line-height: 1.5; }

    /* Header Superior */
    .top-header { background-color: var(--primary-brown); color: #fff; display: flex; align-items: center; justify-content: space-between; height: 70px; padding: 0 28px; }
    .brand { display: flex; align-items: center; gap: 14px; width: 250px; }
    
    .brand-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      overflow: hidden;
      background-color: #fff;
    }

    .brand-logo .app-logo {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .brand-text h1 { font-size: 18px; font-weight: 800; line-height: 1.1; letter-spacing: 0.3px; }
    
    .header-center { display: flex; align-items: center; gap: 18px; flex: 1; padding-left: 20px; }
    .menu-toggle { background: none; border: none; color: #fff; cursor: pointer; display: flex; align-items: center; }
    .user-welcome h2 { font-size: 16px; font-weight: 700; }
    .user-welcome p { font-size: 13px; opacity: 0.9; }
    
    .header-actions { display: flex; align-items: center; gap: 20px; }
    .icon-btn { background: none; border: none; color: #fff; cursor: pointer; position: relative; display: flex; align-items: center; }
    .badge-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background-color: #4caf50; border: 2px solid var(--primary-brown); border-radius: 50%; }
    
    /* Enlace Perfil */
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
      width: 42px; 
      height: 42px; 
      background-color: #ffffff; 
      border-radius: 50%; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      overflow: hidden;
      border: 1px solid var(--border-color);
      flex-shrink: 0;
      position: relative;
    }

    .avatar img.header-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .avatar svg {
      stroke: var(--primary-brown) !important;
      color: var(--primary-brown) !important;
    }

    /* Layout general */
    .app-layout { display: flex; flex: 1; }
    
    /* Sidebar */
    .sidebar { width: 260px; background-color: var(--bg-sidebar); padding: 24px 16px; display: flex; flex-direction: column; gap: 20px; flex-shrink: 0; border-right: 1px solid var(--border-color); }
    .nav-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .nav-item a { display: flex; align-items: center; gap: 14px; padding: 12px 18px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-size: 15px; font-weight: 700; transition: all 0.2s; }
    .nav-item.active a { background-color: var(--primary-brown); color: #fff; }
    .nav-item:not(.active) a:hover { background-color: rgba(0, 0, 0, 0.07); }

    /* Main Content */
    .main-content { flex: 1; padding: 32px 40px; overflow-y: auto; }
    .page-title { font-size: 24px; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
    .page-subtitle { font-size: 15px; color: var(--text-muted); font-weight: 600; margin-bottom: 28px; }

    /* Tarjetas principales */
    .chart-container-card { background-color: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); padding: 28px; margin-bottom: 28px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); }
    .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .card-header-flex h3 { font-size: 18px; font-weight: 800; color: var(--text-dark); }

    .table-container-card { background-color: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); padding: 28px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); }
    .table-container-card h3 { font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 20px; }

    /* Tabla */
    .table-responsive { width: 100%; overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 15px; }
    .custom-table th { padding: 16px; background-color: #f2e9df; color: var(--text-dark); font-weight: 800; border-bottom: 2px solid var(--border-color); font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
    .custom-table td { padding: 16px; border-bottom: 1px solid var(--border-color); color: var(--text-dark); font-weight: 500; vertical-align: middle; }
    .custom-table tbody tr:hover { background-color: rgba(0, 0, 0, 0.02); }

    .tag-entrega { background-color: var(--primary-brown); color: #fff; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 800; display: inline-block; }
    .empty-state { text-align: center; padding: 36px; color: var(--text-muted); font-size: 16px; font-weight: 600; }

    .app-footer { text-align: center; padding: 20px; font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 8px; border-top: 1px solid var(--border-color); background-color: var(--bg-main); margin-top: auto; }

    @media (max-width: 850px) { .app-layout { flex-direction: column; } .sidebar { width: 100%; } }
  </style>
</head>
<body>

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
    <aside class="sidebar">
      <ul class="nav-list">
        <li class="nav-item">
          <a href="panel_caficultor.php"><i data-lucide="home" size="20"></i> Perfil</a>
        </li>
        <li class="nav-item">
          <a href="fincas.php"><i data-lucide="map-pin" size="20"></i> Fincas</a>
        </li>
        <li class="nav-item">
          <a href="recolecciones_c.php"><i data-lucide="archive" size="20"></i> Recolecciones</a>
        </li>
        <li class="nav-item">
          <a href="recolectores_c.php"><i data-lucide="users" size="20"></i> Trabajadores</a>
        </li>
        <li class="nav-item active">
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

    <main class="main-content">
      <h2 class="page-title">Resumen general 🌱</h2>
      <p class="page-subtitle">Resumen de tu cuenta de café recolectado</p>

      <!-- Gráfica de Barras Dinámica -->
      <div class="chart-container-card">
        <div class="card-header-flex">
          <h3>Café recolectado por fecha (kg)</h3>
        </div>
        <div style="height: 350px; width: 100%;">
          <canvas id="barChart"></canvas>
        </div>
      </div>

      <!-- Tabla de Últimas Recolecciones Dinámica -->
      <div class="table-container-card">
        <h3>Últimas recolecciones</h3>
        
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Fecha y Hora</th>
                <th>Entrega</th>
                <th>Recolector</th>
                <th>Peso (kg)</th>
                <th>Precio / kg</th>
                <th>Total Calculado</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($res_ultimas->num_rows > 0): ?>
                <?php while ($row = $res_ultimas->fetch_assoc()): 
                    $kilos = (float)$row['kilos_recolectados'];
                    $precio_kilo = (float)$row['precio_kilo_aplicado'];
                    $pago = (float)$row['pago_calculado'];

                    if ($precio_kilo <= 0) {
                        $precio_kilo = $precio_fallback;
                    }

                    if ($pago <= 0 && $precio_kilo > 0) {
                        $pago = $kilos * $precio_kilo;
                    }
                ?>
                  <tr>
                    <td><strong><?php echo date('d/m/Y h:i A', strtotime($row['fecha'])); ?></strong></td>
                    <td><span class="tag-entrega">Entrega <?php echo htmlspecialchars($row['numero_entrega'] ?? '1'); ?></span></td>
                    <td><strong style="font-size: 16px;"><?php echo htmlspecialchars($row['recolector']); ?></strong></td>
                    <td><strong style="font-size: 16px; color: #0a4d2e;"><?php echo number_format($kilos, 2, ',', '.'); ?> kg</strong></td>
                    <td>$<?php echo number_format($precio_kilo, 0, ',', '.'); ?></td>
                    <td><strong style="font-size: 16px;">$<?php echo number_format($pago, 0, ',', '.'); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['observaciones'] ?: 'Sin observaciones'); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="empty-state">No hay registros de recolecciones recientes.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <footer class="app-footer">
    <i data-lucide="coffee" size="16"></i>
    <span><strong>Café control</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
  </footer>

  <script>
    lucide.createIcons();

    const fechasDB = <?php echo json_encode($grafico_fechas); ?>;
    const kilosDB = <?php echo json_encode($grafico_kilos); ?>;

    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
      type: 'bar',
      data: {
        labels: fechasDB.length > 0 ? fechasDB : ['Sin datos'],
        datasets: [{
          label: 'Kilos recolectados',
          data: kilosDB.length > 0 ? kilosDB : [0],
          backgroundColor: '#0a4d2e',
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              font: {
                size: 14,
                family: "'Plus Jakarta Sans', sans-serif",
                weight: 'bold'
              }
            },
            grid: { color: '#d1c4b6' }
          },
          x: {
            ticks: {
              font: {
                size: 14,
                family: "'Plus Jakarta Sans', sans-serif",
                weight: 'bold'
              } 
            },
            grid: { display: false }
          }
        }
      }
    });
  </script>
</body>
</html>