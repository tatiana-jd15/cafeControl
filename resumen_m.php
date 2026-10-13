<?php
session_start();
require_once 'assets/inc/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario_sesion = $_SESSION['id_usuario'];

$sql_user = "SELECT nombres, apellidos, foto_perfil FROM usuarios WHERE id_usuario = ?";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id_usuario_sesion);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

if ($row_user = $res_user->fetch_assoc()) {
    $nombre_caficultor = $row_user['nombres'] . ' ' . $row_user['apellidos'];
    $foto_perfil = $row_user['foto_perfil'] ?? null;
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}

$precio_fallback = 0;
$sql_ultimo_precio = "SELECT precio_kilo_aplicado
                      FROM recolecciones
                      WHERE id_registrado_por = ? AND precio_kilo_aplicado > 0
                      ORDER BY id_recoleccion DESC LIMIT 1";
$stmt_p = $con->prepare($sql_ultimo_precio);
if ($stmt_p) {
    $stmt_p->bind_param("i", $id_usuario_sesion);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();

    if ($row_p = $res_p->fetch_assoc()) {
        $precio_fallback = (float)$row_p['precio_kilo_aplicado'];
    }
}

$grafico_fechas = [];
$grafico_kilos = [];

$stmt_barras = $con->prepare(
    'SELECT DATE(fecha) AS fecha_corta, SUM(kilos_recolectados) AS total_dia
     FROM recolecciones
     WHERE id_registrado_por = ?
     GROUP BY DATE(fecha)
     ORDER BY DATE(fecha) ASC
     LIMIT 7'
);
$stmt_barras->bind_param("i", $id_usuario_sesion);
$stmt_barras->execute();
$res_barras = $stmt_barras->get_result();

while ($row = $res_barras->fetch_assoc()) {
    $grafico_fechas[] = date('d/m/Y', strtotime($row['fecha_corta']));
    $grafico_kilos[] = (float)$row['total_dia'];
}

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
$stmt_ult->bind_param("i", $id_usuario_sesion);
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
      --bg-sidebar: #1b3818;
      --bg-card: #ffffff;
      --primary-green: #244b20;
      --text-dark: #1f1f1f;
      --text-muted: #5d5d5d;
      --border-color: #d8cec4;
      --active-nav: #445c42;
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

    .top-header {
      background-color: var(--primary-green);
      color: #fff;
      display: flex;
      align-items: center;
      width: 100%;
      height: 76px;
      min-height: 76px;
      padding: 0 30px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      width: 280px;
      flex-shrink: 0;
    }

    .brand-logo {
      width: 44px;
      height: 44px;
      background-color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-green);
    }

    .brand-text h1 {
      font-size: 18px;
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: 0.2px;
      color: #fff;
    }

    .header-center {
      display: flex;
      align-items: center;
      flex: 1;
      min-width: 0;
      padding-left: 30px;
    }

    .user-welcome {
      min-width: 0;
    }

    .user-welcome h2 {
      font-size: 16px;
      font-weight: 800;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .user-welcome p {
      font-size: 13px;
      font-weight: 500;
      color: #fff;
      opacity: 0.9;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .header-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-left: auto;
      padding-left: 18px;
      flex-shrink: 0;
    }

    .user-profile {
      width: 46px;
      height: 46px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .avatar {
      width: 46px;
      height: 46px;
      min-width: 46px;
      min-height: 46px;
      background-color: #fff;
      color: var(--primary-green);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 2px solid rgba(255,255,255,0.95);
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
      transition: transform 0.2s ease;
    }

    .avatar img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
      object-position: center;
      border-radius: 50%;
    }

    .avatar:hover {
      transform: scale(1.04);
    }

    /* =========================
       LAYOUT
    ========================== */

    .app-layout {
      display: flex;
      flex: 1;
      min-width: 0;
    }

    .sidebar {
      width: 260px;
      background-color: var(--bg-sidebar);
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      flex-shrink: 0;
      border-right: 1px solid var(--bg-sidebar);
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
      color: #fff;
      text-decoration: none;
      font-size: 15px;
      font-weight: 700;
      transition: all 0.2s;
    }

    .nav-item.active a {
      background-color: var(--active-nav);
      color: #fff;
      box-shadow: inset 3px 0 0 #fff;
    }

    .nav-item:not(.active) a:hover {
      background-color: rgba(255,255,255,0.10);
    }

    .main-content {
      flex: 1;
      min-width: 0;
      padding: 32px 40px;
      overflow-y: auto;
    }

    .page-title {
      font-size: 24px;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .page-subtitle {
      font-size: 15px;
      color: var(--text-muted);
      font-weight: 600;
      margin-bottom: 28px;
    }

    .chart-container-card {
      background-color: var(--bg-card);
      border-radius: 16px;
      border: 1px solid var(--border-color);
      padding: 28px;
      margin-bottom: 28px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }

    .card-header-flex {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .card-header-flex h3,
    .table-container-card h3 {
      font-size: 18px;
      font-weight: 800;
      color: var(--primary-green);
    }

    .table-container-card {
      background-color: var(--bg-card);
      border-radius: 16px;
      border: 1px solid var(--border-color);
      padding: 28px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }

    .table-container-card h3 {
      margin-bottom: 20px;
    }

    .table-responsive {
      width: 100%;
      overflow-x: auto;
    }

    .custom-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 15px;
    }

    .custom-table th {
      padding: 16px;
      background-color: #edf1ea;
      color: var(--text-dark);
      font-weight: 800;
      border-bottom: 2px solid var(--border-color);
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .custom-table td {
      padding: 16px;
      border-bottom: 1px solid var(--border-color);
      color: var(--text-dark);
      font-weight: 500;
      vertical-align: middle;
    }

    .custom-table tbody tr:hover {
      background-color: rgba(36,75,32,0.04);
    }

    .tag-entrega {
      background-color: var(--primary-green);
      color: #fff;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      display: inline-block;
    }

    .empty-state {
      text-align: center;
      padding: 36px;
      color: var(--text-muted);
      font-size: 16px;
      font-weight: 600;
    }

    .app-footer {
      text-align: center;
      padding: 20px;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border-top: 1px solid var(--border-color);
      background-color: #efe8e0;
      margin-top: auto;
    }

    @media (max-width: 850px) {
      .top-header {
        height: 68px;
        min-height: 68px;
        padding: 0 18px;
      }

      .brand {
        width: 220px;
      }

      .header-center {
        padding-left: 12px;
      }

      .user-welcome h2 {
        font-size: 14px;
      }

      .user-welcome p {
        font-size: 12px;
      }

      .avatar,
      .user-profile {
        width: 42px;
        height: 42px;
        min-width: 42px;
        min-height: 42px;
      }

      .app-layout {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
      }

      .main-content {
        padding: 24px 20px;
      }
    }

    @media (max-width: 600px) {
      .top-header {
        height: 64px;
        min-height: 64px;
        padding: 0 12px;
      }

      .brand {
        width: auto;
      }

      .brand-text {
        display: none;
      }

      .header-center {
        padding-left: 12px;
      }

      .user-welcome h2 {
        font-size: 13px;
      }

      .user-welcome p {
        font-size: 11px;
      }

      .avatar,
      .user-profile {
        width: 40px;
        height: 40px;
        min-width: 40px;
        min-height: 40px;
      }

      .main-content {
        padding: 18px 12px;
      }
    }
  </style>
</head>
<body>

  <!-- Encabezado: únicamente la foto de perfil a la derecha -->
  <header class="top-header">

    <div class="brand">
      <div class="brand-logo">
        <i data-lucide="coffee" size="22"></i>
      </div>
      <div class="brand-text">
        <h1>Café control</h1>
      </div>
    </div>

    <div class="header-center">
      <div class="user-welcome">
        <h2>Bienvenido, <?php echo htmlspecialchars($nombre_caficultor); ?></h2>
        <p>Administra tus fincas y consulta su información</p>
      </div>
    </div>

    <div class="header-actions">
      <a href="panel_m.php" class="user-profile" title="Ver perfil" aria-label="Ver perfil">
        <div class="avatar">
          <?php if (!empty($foto_perfil)): ?>
            <img
              src="<?php echo htmlspecialchars($foto_perfil); ?>?v=<?php echo time(); ?>"
              alt="Foto de perfil"
            >
          <?php else: ?>
            <i data-lucide="user" size="22"></i>
          <?php endif; ?>
        </div>
      </a>
    </div>

  </header>

  <div class="app-layout">

    <aside class="sidebar">
      <ul class="nav-list">
        <li class="nav-item">
          <a href="panel_m.php">
            <i data-lucide="home" size="20"></i>
            Perfil
          </a>
        </li>
        <li class="nav-item">
          <a href="recolecciones_m.php">
            <i data-lucide="archive" size="20"></i>
            Recolecciones
          </a>
        </li>
        <li class="nav-item">
          <a href="recolectores_m.php">
            <i data-lucide="users" size="20"></i>
            Trabajadores
          </a>
        </li>
        <li class="nav-item active">
          <a href="resumen_m.php">
            <i data-lucide="bar-chart-2" size="20"></i>
            Resumen General
          </a>
        </li>
        <li class="nav-item">
          <a href="masignar_m.php">
            <i class="fa-solid fa-user-gear" style="font-size:18px;"></i>
            Mayordomo
          </a>
        </li>
         <li class="nav-item">

                <a href="gestionar_m.php">

                    <i
                        class="fa-solid fa-user-gear"
                    ></i>

                   Gestionar Usuarios

                </a>

            </li>
        <li class="nav-item">
          <a href="cerrar.php">
            <i data-lucide="log-out" size="20"></i>
            Cerrar Sesión
          </a>
        </li>
      </ul>
    </aside>

    <main class="main-content">

      <h2 class="page-title">Resumen general 🌱</h2>
      <p class="page-subtitle">Resumen de tu cuenta de café recolectado</p>

      <div class="chart-container-card">
        <div class="card-header-flex">
          <h3>Café recolectado por fecha (kg)</h3>
        </div>

        <div style="height:350px; width:100%;">
          <canvas id="barChart"></canvas>
        </div>
      </div>

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
                    <td>
                      <strong><?php echo date('d/m/Y h:i A', strtotime($row['fecha'])); ?></strong>
                    </td>

                    <td>
                      <span class="tag-entrega">
                        Entrega <?php echo htmlspecialchars($row['numero_entrega'] ?? '1'); ?>
                      </span>
                    </td>

                    <td>
                      <strong style="font-size:16px;">
                        <?php echo htmlspecialchars($row['recolector']); ?>
                      </strong>
                    </td>

                    <td>
                      <strong style="font-size:16px;color:#244b20;">
                        <?php echo number_format($kilos,2,',','.'); ?> kg
                      </strong>
                    </td>

                    <td>
                      $<?php echo number_format($precio_kilo,0,',','.'); ?>
                    </td>

                    <td>
                      <strong style="font-size:16px;">
                        $<?php echo number_format($pago,0,',','.'); ?>
                      </strong>
                    </td>

                    <td>
                      <?php echo htmlspecialchars($row['observaciones'] ?: 'Sin observaciones'); ?>
                    </td>
                  </tr>

                <?php endwhile; ?>

              <?php else: ?>

                <tr>
                  <td colspan="7" class="empty-state">
                    No hay registros de recolecciones recientes.
                  </td>
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
    <span>
      <strong>Café control</strong>
      © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos
    </span>
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
          backgroundColor: '#244b20',
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
            grid: { color: '#d8cec4' }
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
