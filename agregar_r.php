<?php
// Activar reporte de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'assets/inc/conexion.php'; 

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_caficultor = $_SESSION['id_usuario'];

// Obtener datos del caficultor logueado
$sql_user = "SELECT nombres, apellidos FROM usuarios WHERE id_usuario = ?";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id_caficultor);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

if ($row_user = $res_user->fetch_assoc()) {
    $nombre_caficultor = $row_user['nombres'] . ' ' . $row_user['apellidos'];
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}

$mensaje = "";
$tipo_alerta = "";

// Obtener fincas del caficultor
$sql_fincas = "SELECT id_finca, nombre, ubicacion_vereda FROM fincas WHERE id_caficultor = ? AND estado = 'activa'";
$stmt_fincas = $con->prepare($sql_fincas);
$stmt_fincas->bind_param("i", $id_caficultor);
$stmt_fincas->execute();
$res_fincas = $stmt_fincas->get_result();

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento  = isset($_POST['documento']) ? trim($_POST['documento']) : '';
    $nombres    = isset($_POST['nombres']) ? trim($_POST['nombres']) : '';
    $apellidos  = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
    $telefono   = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $correo     = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $usuario    = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $contrasena = isset($_POST['contrasena']) ? trim($_POST['contrasena']) : '';
    $id_finca   = isset($_POST['id_finca']) ? intval($_POST['id_finca']) : 0;

    if (empty($documento) || empty($id_finca)) {
        $mensaje = "El número de documento y la finca son obligatorios.";
        $tipo_alerta = "danger";
    } else {
        // 1. Verificar si el usuario existe por documento o por usuario
        $sql_check = "SELECT id_usuario FROM usuarios WHERE documento = ? OR (usuario = ? AND usuario != '')";
        $stmt_check = $con->prepare($sql_check);
        $stmt_check->bind_param("ss", $documento, $usuario);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        $con->begin_transaction();

        try {
            if ($row_existente = $res_check->fetch_assoc()) {
                // --- CASO 1: EL USUARIO YA EXISTE ---
                $id_usuario_existente = $row_existente['id_usuario'];

                // Verificar si ya está asignado a esa finca
                $sql_check_rec = "SELECT id_usuario FROM recolectores WHERE id_usuario = ? AND id_finca = ?";
                $stmt_check_rec = $con->prepare($sql_check_rec);
                $stmt_check_rec->bind_param("ii", $id_usuario_existente, $id_finca);
                $stmt_check_rec->execute();
                $res_check_rec = $stmt_check_rec->get_result();

                if ($res_check_rec->num_rows > 0) {
                    $mensaje = "Este recolector ya está asignado a la finca seleccionada.";
                    $tipo_alerta = "danger";
                    $con->rollback();
                } else {
                    // Vincular el usuario existente a la finca
                    $sql_ins_rec = "INSERT INTO recolectores (id_usuario, id_finca) VALUES (?, ?)";
                    $stmt_rec = $con->prepare($sql_ins_rec);
                    $stmt_rec->bind_param("ii", $id_usuario_existente, $id_finca);
                    $stmt_rec->execute();

                    $con->commit();
                    header("Location: recolectores_c.php?status=linked");
                    exit();
                }

            } else {
                // --- CASO 2: EL USUARIO NO EXISTE (CREAR NUEVA CUENTA) ---
                if (empty($nombres) || empty($apellidos) || empty($usuario) || empty($contrasena)) {
                    $mensaje = "Para registrar un nuevo recolector debe llenar todos los campos obligatorios (*).";
                    $tipo_alerta = "danger";
                    $con->rollback();
                } else {
                    $pass_hashed = password_hash($contrasena, PASSWORD_DEFAULT);
                    $rol = 'recolector';
                    $estado = 'activo';

                    // Crear el nuevo usuario
                    $sql_ins_user = "INSERT INTO usuarios (documento, nombres, apellidos, telefono, correo, usuario, contrasena, rol, estado) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_ins = $con->prepare($sql_ins_user);
                    $stmt_ins->bind_param("sssssssss", $documento, $nombres, $apellidos, $telefono, $correo, $usuario, $pass_hashed, $rol, $estado);
                    $stmt_ins->execute();

                    $nuevo_id_usuario = $con->insert_id;

                    // Vincular el nuevo usuario a la finca
                    $sql_ins_rec = "INSERT INTO recolectores (id_usuario, id_finca) VALUES (?, ?)";
                    $stmt_rec = $con->prepare($sql_ins_rec);
                    $stmt_rec->bind_param("ii", $nuevo_id_usuario, $id_finca);
                    $stmt_rec->execute();

                    $con->commit();
                    header("Location: recolectores_c.php?status=created");
                    exit();
                }
            }
        } catch (Exception $e) {
            $con->rollback();
            $mensaje = "Error al procesar la solicitud: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Recolector - Gestor de Cuentas</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    :root {
      --bg-main: #f4f0eb;
      --bg-sidebar: #173d19;
      --bg-card: #ffffff;
      --primary-brown: #214f22;
      --primary-dark: #173d19;
      --primary-green: #214f22;
      --primary-green-hover: #173d19;
      --text-dark: #173d19;
      --text-muted: #5b6258;
      --border-color: #d8d0c6;
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

    /* Header Superior */
    .top-header { 
      background-color: #173d19; 
      color: #fff; 
      display: flex; 
      align-items: center; 
      justify-content: space-between; 
      height: 98px; 
      padding: 0 28px; 
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    
    .brand { display: flex; align-items: center; gap: 16px; width: 280px; }
    .brand-logo { 
      width: 48px; height: 48px; 
      background-color: #fff; 
      border-radius: 50%; 
      display: flex; align-items: center; justify-content: center; 
      color: var(--primary-green); 
    }
    .brand-text h1 { font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }

    .header-center { display: flex; align-items: center; gap: 20px; flex: 1; padding-left: 24px; }
    .menu-toggle { background: none; border: none; color: #fff; cursor: pointer; display: flex; align-items: center; }
    .user-welcome h2 { font-size: 20px; font-weight: 800; }
    .user-welcome p { font-size: 15px; opacity: 0.9; font-weight: 500; }

    .header-actions { display: flex; align-items: center; gap: 24px; }
    .icon-btn { background: none; border: none; color: #fff; cursor: pointer; position: relative; display: flex; align-items: center; }
    .user-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; }
    .avatar { 
      width: 48px; height: 48px; 
      background-color: #fff; 
      color: var(--primary-green); 
      border-radius: 50%; 
      display: flex; align-items: center; justify-content: center; 
      font-weight: 800; 
    }

    /* Layout Structure */
    .app-layout { display: flex; flex: 1; min-height: calc(100vh - 98px - 66px); }

    /* Sidebar */
    .sidebar { width: 285px; background-color: #173d19; padding: 26px 18px 34px; flex-shrink: 0; min-height: calc(100vh - 98px); }
    .nav-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .nav-item a { 
      display: flex; align-items: center; gap: 16px; 
      padding: 15px 18px; border-radius: 10px; 
      color: #fff; text-decoration: none; 
      font-size: 16px; font-weight: 700; 
      transition: all 0.2s ease-in-out; 
      min-height: 50px;
    }
    .nav-item.active a {
      background-color: #49664a;
      color: #fff;
      border-left: 4px solid #fff;
      padding-left: 14px;
      box-shadow: 0 4px 12px rgba(33, 79, 34, 0.22);
    }
    .nav-item:not(.active) a:hover { background-color: rgba(255, 255, 255, 0.08); color: #fff; }

    /* Main Content */
    .main-content { flex: 1; padding: 34px 42px 42px; display: flex; flex-direction: column; gap: 28px; max-width: none; min-width: 0; }
    .section-card { 
      background-color: var(--bg-card); 
      border-radius: 18px; 
      border: 1px solid var(--border-color); 
      padding: 40px; 
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06); 
    }
    
    .section-title { display: flex; align-items: center; gap: 18px; margin-bottom: 32px; }
    .title-icon { 
      width: 56px; height: 56px; 
      background-color: var(--primary-green); 
      color: #fff; 
      border-radius: 16px; 
      display: flex; align-items: center; justify-content: center; 
    }
    .title-text h2 { font-size: 26px; color: var(--primary-dark); font-weight: 800; }
    .title-text p { font-size: 17px; color: var(--text-muted); font-weight: 600; margin-top: 4px; }

    /* Formulario Grid con tipografía de alto contraste */
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
    .form-group { display: flex; flex-direction: column; gap: 10px; }
    .form-group.full-width { grid-column: span 2; }
    
    .form-group label { 
      font-size: 17px; 
      font-weight: 700; 
      color: var(--primary-dark); 
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .form-group label span { color: #d32f2f; font-weight: 800; font-size: 18px; }
    
    .form-control { 
      width: 100%; 
      padding: 16px 20px; 
      border-radius: 14px; 
      border: 2px solid var(--border-color); 
      background-color: #fff; 
      font-size: 18px; 
      font-weight: 600; 
      color: var(--text-dark); 
      outline: none; 
      transition: border-color 0.2s, box-shadow 0.2s; 
    }
    .form-control:focus { 
      border-color: var(--primary-green); 
      box-shadow: 0 0 0 4px rgba(33, 79, 34, 0.12); 
    }
    .form-control::placeholder { color: #9c8e80; font-weight: 500; }

    /* Alerta clara y legible */
    .alert { 
      padding: 18px 24px; 
      border-radius: 14px; 
      font-size: 17px; 
      font-weight: 700; 
      margin-bottom: 28px; 
      display: flex; 
      align-items: center; 
      gap: 12px; 
    }
    .alert-danger { background-color: #fde8e8; color: #9b1c1c; border: 2px solid #f8b4b4; }

    /* Acciones del formulario */
    .form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 36px; }
    .btn { 
      padding: 16px 32px; 
      border-radius: 14px; 
      font-size: 17px; 
      font-weight: 800; 
      cursor: pointer; 
      border: none; 
      text-decoration: none; 
      display: inline-flex; 
      align-items: center; 
      gap: 10px; 
      transition: all 0.2s ease-in-out;
    }
    .btn-secondary { background-color: #e4e1dc; color: var(--text-dark); }
    .btn-secondary:hover { background-color: #d5d2cc; }
    
    .btn-primary { 
      background-color: var(--primary-green); 
      color: #fff; 
      box-shadow: 0 4px 12px rgba(7, 94, 54, 0.25); 
    }
    .btn-primary:hover { 
      background-color: var(--primary-green-hover); 
      transform: translateY(-2px); 
    }

    /* Footer */
    .app-footer { 
      background-color: #eee8df;
      border-top: 1px solid #d8d0c6;
      text-align: center; 
      padding: 18px 24px; 
      font-size: 14px; 
      color: var(--text-muted); 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      gap: 8px; 
      font-weight: 600;
    }

    @media (max-width: 900px) {
      .sidebar { width: 285px; }
      .header-center { display: none; }
      .main-content { padding: 24px; }
    }

    @media (max-width: 650px) {
      .sidebar { width: 100%; min-height: auto; }
      .app-layout { flex-direction: column; }
      .header-actions { gap: 12px; }
      .brand { width: auto; }
      .main-content { padding: 20px; }
      .form-grid { grid-template-columns: 1fr; }
      .form-group.full-width { grid-column: span 1; }
    }
  </style>
</head>
<body>

  <!-- Header Superior -->
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
          <a href="panel_m.php"><i data-lucide="home" size="24"></i> Perfil</a>
        </li>
        <li class="nav-item">
          <a href="recolecciones_m.php"><i data-lucide="archive" size="24"></i> Recolecciones</a>
        </li>
        <li class="nav-item active">
          <a href="recolectores_m.php"><i data-lucide="users" size="24"></i> Recolectores</a>
        </li>
        <li class="nav-item">
          <a href="resumen_m.php"><i data-lucide="bar-chart-2" size="24"></i> Resumen General</a>
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
            <i data-lucide="user-plus" size="30"></i>
          </div>
          <div class="title-text">
            <h2>Registrar Nuevo Recolector</h2>
            <p>Si la cédula ya existe en el sistema, se vinculará automáticamente a la finca seleccionada.</p>
          </div>
        </div>

        <?php if (!empty($mensaje)): ?>
          <div class="alert alert-<?php echo $tipo_alerta; ?>">
            <i data-lucide="alert-circle" size="24"></i>
            <span><?php echo $mensaje; ?></span>
          </div>
        <?php endif; ?>

        <form action="" method="POST">
          <div class="form-grid">
            
            <div class="form-group">
              <label for="documento">Nro. Documento / Cédula <span>*</span></label>
              <input type="text" id="documento" name="documento" class="form-control" placeholder="Ej: 1075263894" value="<?php echo isset($_POST['documento']) ? htmlspecialchars($_POST['documento']) : ''; ?>" required>
            </div>

            <div class="form-group">
              <label for="id_finca">Finca Asignada <span>*</span></label>
              <select id="id_finca" name="id_finca" class="form-control" required>
                <option value="">-- Seleccionar Finca --</option>
                <?php while ($finca = $res_fincas->fetch_assoc()): ?>
                  <option value="<?php echo $finca['id_finca']; ?>">
                    <?php echo htmlspecialchars($finca['nombre'] . ' (' . $finca['ubicacion_vereda'] . ')'); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="nombres">Nombres</label>
              <input type="text" id="nombres" name="nombres" class="form-control" placeholder="Ej: Juan Carlos" value="<?php echo isset($_POST['nombres']) ? htmlspecialchars($_POST['nombres']) : ''; ?>">
            </div>

            <div class="form-group">
              <label for="apellidos">Apellidos</label>
              <input type="text" id="apellidos" name="apellidos" class="form-control" placeholder="Ej: Ramírez Gómez" value="<?php echo isset($_POST['apellidos']) ? htmlspecialchars($_POST['apellidos']) : ''; ?>">
            </div>

            <div class="form-group">
              <label for="telefono">Teléfono de Contacto</label>
              <input type="text" id="telefono" name="telefono" class="form-control" placeholder="Ej: 312 456 7890" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
            </div>

            <div class="form-group">
              <label for="correo">Correo Electrónico</label>
              <input type="email" id="correo" name="correo" class="form-control" placeholder="correo@ejemplo.com" value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
            </div>

            <div class="form-group">
              <label for="usuario">Nombre de Usuario (Acceso)</label>
              <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Ej: jramirez" value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
            </div>

            <div class="form-group">
              <label for="contrasena">Contraseña</label>
              <input type="password" id="contrasena" name="contrasena" class="form-control" placeholder="••••••••">
            </div>

          </div>

          <div class="form-actions">
            <a href="recolectores_m.php" class="btn btn-secondary">
              <i data-lucide="x" size="22"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
              <i data-lucide="save" size="22"></i> Guardar Recolector
            </button>
          </div>
        </form>

      </section>

    </main>
  </div>

  <footer class="app-footer">
    <i data-lucide="coffee" size="18"></i>
    <span><strong>Gestor de Cuentas de Café</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
  </footer>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>