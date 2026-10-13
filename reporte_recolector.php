<?php
session_start();
require_once 'assets/inc/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_recolector = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_recolector <= 0) {
    header("Location: recolectores_m.php");
    exit();
}

// Información del recolector
$sql_rec = "SELECT u.nombres, u.apellidos, u.documento, u.telefono, f.nombre AS nombre_finca 
            FROM recolectores r
            INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
            INNER JOIN fincas f ON r.id_finca = f.id_finca
            WHERE u.id_usuario = ?";
$stmt_rec = $con->prepare($sql_rec);
$stmt_rec->bind_param("i", $id_recolector);
$stmt_rec->execute();
$res_rec = $stmt_rec->get_result();

if ($row_rec = $res_rec->fetch_assoc()) {
    $nombre_recolector = $row_rec['nombres'] . ' ' . $row_rec['apellidos'];
    $documento_recolector = $row_rec['documento'];
    $finca_recolector = $row_rec['nombre_finca'];
} else {
    header("Location: recolectores_c.php");
    exit();
}

// Consulta de TODAS las entregas
$sql_todas = "SELECT id_recoleccion, DATE(fecha_registro) AS fecha, TIME(fecha_registro) AS hora, kilos_recolectados, pago_calculado, estado_pago 
              FROM recolecciones 
              WHERE id_recolector = ? 
              ORDER BY fecha_registro DESC";
$stmt_t = $con->prepare($sql_todas);
$stmt_t->bind_param("i", $id_recolector);
$stmt_t->execute();
$res_todas = $stmt_t->get_result();

// Consulta SOLO de entregas PENDIENTES DE PAGO (para la liquidación / PDF)
$sql_pendientes = "SELECT id_recoleccion, DATE(fecha_registro) AS fecha, TIME(fecha_registro) AS hora, kilos_recolectados, pago_calculado 
                  FROM recolecciones 
                  WHERE id_recolector = ? AND estado_pago = 'pendiente' 
                  ORDER BY fecha_registro DESC";
$stmt_p = $con->prepare($sql_pendientes);
$stmt_p->bind_param("i", $id_recolector);
$stmt_p->execute();
$res_pendientes = $stmt_p->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Pagos y Reporte - <?php echo htmlspecialchars($nombre_recolector); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    :root {
      --bg-main: #f5f0eb;
      --bg-card: #fcfaf7;
      --primary-brown: #4a2810;
      --primary-green: #0a4d2e;
      --primary-green-hover: #073822;
      --status-pending-bg: #fff3cd;
      --status-pending-text: #664d03;
      --status-paid-bg: #d1e7dd;
      --status-paid-text: #0f5132;
      --text-dark: #2c2119;
      --text-muted: #736b63;
      --border-color: #e2d7cd;
      --font-family: 'Plus Jakarta Sans', sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-family); }
    body { background-color: var(--bg-main); color: var(--text-dark); padding: 30px; }

    .container { max-width: 950px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px; }
    .card { background-color: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color); padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
    
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background-color: #f3ece4; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
    
    .table-custom { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
    .table-custom th { background-color: var(--primary-brown); color: #fff; text-align: left; padding: 10px 14px; }
    .table-custom td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
    .badge-pending { background-color: var(--status-pending-bg); color: var(--status-pending-text); }
    .badge-paid { background-color: var(--status-paid-bg); color: var(--status-paid-text); }

    .btn-action { border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; transition: opacity 0.2s; }
    .btn-pay { background-color: var(--primary-green); color: #fff; }
    .btn-unpay { background-color: #736b63; color: #fff; }
    .btn-action:hover { opacity: 0.85; }

    .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; }
    .btn-back { background-color: #736b63; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }
    .btn-download { background-color: var(--primary-green); color: #fff; padding: 10px 18px; border-radius: 8px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; }

    /* Estilos para Impresión / PDF */
    @media print {
      .no-print { display: none !important; }
      body { background-color: #fff; padding: 0; }
      .card { border: none; box-shadow: none; width: 100%; padding: 0; }
      .print-only { display: block !important; }
    }
  </style>
</head>
<body>

  <div class="container">
    
    <!-- DATOS DEL TRABAJADOR -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2><i data-lucide="user-check" size="22"></i> Control de Entregas y Pagos</h2>
        <span style="font-size: 12px; color: var(--text-muted);">Recolector Seleccionado</span>
      </div>
      
      <div class="info-grid">
        <div><strong>Recolector:</strong> <?php echo htmlspecialchars($nombre_recolector); ?></div>
        <div><strong>Cédula:</strong> <?php echo htmlspecialchars($documento_recolector); ?></div>
        <div><strong>Finca:</strong> <?php echo htmlspecialchars($finca_recolector); ?></div>
        <div><strong>Fecha de Consulta:</strong> <?php echo date('Y-m-d'); ?></div>
      </div>
    </div>

    <!-- TABLA 1: GESTIÓN DE ENTREGAS Y CAMBIO DE ESTADO (NO SE IMPRIME) -->
    <div class="card no-print">
      <h3 style="margin-bottom: 10px; color: var(--primary-brown);">1. Registro Histórico de Entregas</h3>
      <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 15px;">
        Cambie el estado a <strong>"Pagado"</strong> para retirar los kilos de la liquidación actual.
      </p>

      <table class="table-custom">
        <thead>
          <tr>
            <th># Entrega</th>
            <th>Fecha / Hora</th>
            <th>Kilos</th>
            <th>Total a Pagar</th>
            <th>Estado Actual</th>
            <th style="text-align: center;">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res_todas->num_rows > 0): ?>
            <?php while ($r = $res_todas->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo $r['id_recoleccion']; ?></td>
                <td><?php echo $r['fecha'] . ' ' . $r['hora']; ?></td>
                <td><strong><?php echo $r['kilos_recolectados']; ?> Kg</strong></td>
                <td>$<?php echo number_format($r['pago_calculado'], 0, ',', '.'); ?></td>
                <td>
                  <?php if ($r['estado_pago'] === 'pagado'): ?>
                    <span class="badge badge-paid">PAGADO</span>
                  <?php else: ?>
                    <span class="badge badge-pending">AÚN NO PAGO</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: center;">
                  <form action="cambiar_estado_pago.php" method="POST" style="display: inline;">
                    <input type="hidden" name="id_recoleccion" value="<?php echo $r['id_recoleccion']; ?>">
                    <input type="hidden" name="id_recolector" value="<?php echo $id_recolector; ?>">
                    <?php if ($r['estado_pago'] === 'pagado'): ?>
                      <input type="hidden" name="nuevo_estado" value="pendiente">
                      <button type="submit" class="btn-action btn-unpay">Marcar como Pendiente</button>
                    <?php else: ?>
                      <input type="hidden" name="nuevo_estado" value="pagado">
                      <button type="submit" class="btn-action btn-pay">Marcar como Pagado</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align: center; padding: 15px;">No hay recolecciones registradas.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- TABLA 2: LIQUIDACIÓN ACTUAL (SOLO PENDIENTES - ESTA ES LA QUE SE IMPRIME / DESCARGA) -->
    <div class="card" id="seccion-reporte">
      <h3 style="margin-bottom: 10px; color: var(--primary-green);">2. Reporte de Liquidación Pendiente</h3>
      <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 15px;">
        Este reporte únicamente incluye los kilos que se encuentran pendientes de pago.
      </p>

      <table class="table-custom">
        <thead>
          <tr>
            <th># Entrega</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Kilos Pendientes</th>
            <th>Total por Pagar</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $sum_kilos = 0;
            $sum_pago = 0;
            if ($res_pendientes->num_rows > 0):
              while ($p = $res_pendientes->fetch_assoc()):
                $sum_kilos += $p['kilos_recolectados'];
                $sum_pago += $p['pago_calculado'];
          ?>
            <tr>
              <td>#<?php echo $p['id_recoleccion']; ?></td>
              <td><?php echo $p['fecha']; ?></td>
              <td><?php echo $p['hora']; ?></td>
              <td><strong><?php echo $p['kilos_recolectados']; ?> Kg</strong></td>
              <td>$<?php echo number_format($p['pago_calculado'], 0, ',', '.'); ?></td>
            </tr>
          <?php 
              endwhile;
          ?>
            <tr style="font-weight: bold; background-color: #f3ece4;">
              <td colspan="3">TOTAL LIQUIDACIÓN PENDIENTE</td>
              <td><?php echo $sum_kilos; ?> Kg</td>
              <td>$<?php echo number_format($sum_pago, 0, ',', '.'); ?></td>
            </tr>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">
                <strong>¡Sin deudas pendientes!</strong> Todas las entregas de este recolector han sido pagadas.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="actions-bar no-print">
        <a href="recolectores_c.php" class="btn-back">Volver a Recolectores</a>
        <button onclick="window.print()" class="btn-download">
          <i data-lucide="printer" size="16"></i> Imprimir / Descargar Reporte
        </button>
      </div>
    </div>

  </div>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>