<?php
session_start();

// Incluir archivo de conexión
require_once 'assets/inc/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del usuario logueado y su foto de perfil (Estructura unificada)
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
    $nombre_usuario_logueado = trim($row_user['nombres'] . ' ' . $row_user['apellidos']);
    $foto_perfil = $row_user['foto_perfil'] ?? '';
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}
$stmt_user->close();

$mensaje = "";
$error = "";

// ==========================================
// 1. OBTENER USUARIO PARA EDITAR (PRE-EDICIÓN)
// ==========================================
$usuarioEdit = null;
if (isset($_GET['accion']) && $_GET['accion'] === 'pre_editar' && isset($_GET['id'])) {
    $id_pre = intval($_GET['id']);
    $stmt_edit = $con->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
    $stmt_edit->bind_param("i", $id_pre);
    $stmt_edit->execute();
    $resultado_edit = $stmt_edit->get_result();
    if ($resultado_edit->num_rows > 0) {
        $usuarioEdit = $resultado_edit->fetch_assoc();
    }
}

// ==========================================
// 2. PROCESAR ACCIONES (POST / GET) CON MYSQLI
// ==========================================

// --- AGREGAR O EDITAR USUARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $id_edit = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : null;
    $documento = trim($_POST['documento']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $telefono = trim($_POST['telefono']);
    $correo = trim($_POST['correo']);
    $usuario = trim($_POST['usuario']);
    $rol = trim($_POST['rol']);
    $estado = trim($_POST['estado']);

    if ($accion === 'agregar') {
        $contrasena = password_hash($_POST['contrasena'], PASSWORD_BCRYPT);
        $stmt = $con->prepare("INSERT INTO usuarios (documento, nombres, apellidos, telefono, correo, usuario, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $documento, $nombres, $apellidos, $telefono, $correo, $usuario, $contrasena, $rol, $estado);
        if ($stmt->execute()) {
            $mensaje = "Usuario agregado exitosamente.";
        } else {
            $error = "Error al agregar usuario: " . $con->error;
        }
    } elseif ($accion === 'editar' && $id_edit) {
        if (!empty($_POST['contrasena'])) {
            $contrasena = password_hash($_POST['contrasena'], PASSWORD_BCRYPT);
            $stmt = $con->prepare("UPDATE usuarios SET documento=?, nombres=?, apellidos=?, telefono=?, correo=?, usuario=?, contrasena=?, rol=?, estado=? WHERE id_usuario=?");
            $stmt->bind_param("sssssssssi", $documento, $nombres, $apellidos, $telefono, $correo, $usuario, $contrasena, $rol, $estado, $id_edit);
        } else {
            $stmt = $con->prepare("UPDATE usuarios SET documento=?, nombres=?, apellidos=?, telefono=?, correo=?, usuario=?, rol=?, estado=? WHERE id_usuario=?");
            $stmt->bind_param("ssssssssi", $documento, $nombres, $apellidos, $telefono, $correo, $usuario, $rol, $estado, $id_edit);
        }
        if ($stmt && $stmt->execute()) {
            $mensaje = "Usuario actualizado correctamente.";
            $usuarioEdit = null; 
        } else {
            $error = "Error al actualizar usuario: " . $con->error;
        }
    }
}

// --- SUSPENDER / CAMBIAR ESTADO ---
if (isset($_GET['accion']) && $_GET['accion'] === 'suspender' && isset($_GET['id'])) {
    $id_target = intval($_GET['id']);
    $estado_actual = $_GET['estado'] ?? 'activo';
    $nuevo_estado = ($estado_actual === 'activo') ? 'inactivo' : 'activo';
    
    $stmt = $con->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $nuevo_estado, $id_target);
    if ($stmt->execute()) {
        $mensaje = "Estado actualizado correctamente.";
    } else {
        $error = "Error al cambiar el estado: " . $con->error;
    }
}

// --- ELIMINAR USUARIO FORZADO (IGNORANDO FOREIGN KEYS) ---
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id'])) {
    $id_target = intval($_GET['id']);
    
    try {
        $con->query("SET FOREIGN_KEY_CHECKS = 0;");
        $stmt = $con->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_target);
        $stmt->execute();
        $con->query("SET FOREIGN_KEY_CHECKS = 1;");

        $mensaje = "El usuario se ha eliminado.";
    } catch (Exception $e) {
        $con->query("SET FOREIGN_KEY_CHECKS = 1;");
        $error = "Error al intentar eliminar el usuario: " . $e->getMessage();
    }
}

$resultado_usuarios = $con->query("SELECT * FROM usuarios ORDER BY id_usuario DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Café control</title>
    <!-- Bootstrap CSS, FontAwesome & Google Fonts (Inter) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bar-bg: #321c0d;
            --sidebar-bg: #e8ded1;
            --sidebar-active-bg: #3b1d0e;
            --sidebar-active-text: #ffffff;
            --sidebar-text: #2c180b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 0.95rem;
            line-height: 1.5;
            background-color: #f6f3ee;
            margin: 0;
            padding: 0;
            color: #2c180b;
            -webkit-font-smoothing: antialiased;
        }

        .top-navbar {
            background-color: var(--bar-bg);
            color: #ffffff;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-circle-nav {
            width: 42px;
            height: 42px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid #8b5a2b;
        }

        .brand-name {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #ffffff;
            margin: 0;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 1.3rem;
            cursor: pointer;
            margin-left: 15px;
        }

        .welcome-text {
            margin-left: 15px;
        }

        .welcome-text h5 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .welcome-text p {
            margin: 0;
            font-size: 0.82rem;
            color: #d8c8b8;
        }

        .user-nav-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-icon {
            position: relative;
            color: #ffffff;
            font-size: 1.25rem;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 9px;
            height: 9px;
            background-color: #28a745;
            border-radius: 50%;
        }

        .profile-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
        }

        .user-avatar-placeholder {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid #ffffff;
        }

        .layout-wrapper {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            padding: 20px 12px;
            flex-shrink: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover {
            background-color: rgba(59, 29, 14, 0.08);
        }

        .sidebar-item.active a {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }

        .sidebar-item i {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .page-title {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 1.25rem;
            color: #1f1208;
        }

        .card-header-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .form-label {
            font-size: 0.88rem;
            font-weight: 600;
            color: #3b2010;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            font-size: 0.92rem;
            font-weight: 400;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #d5cbc1;
        }

        .form-control:focus, .form-select:focus {
            border-color: #8b5a2b;
            box-shadow: 0 0 0 0.25rem rgba(139, 90, 43, 0.15);
        }

        .btn-custom-action {
            font-size: 0.92rem;
            font-weight: 600;
            padding: 9px 20px;
            border-radius: 8px;
            letter-spacing: 0.01em;
        }

        .table {
            font-size: 0.92rem;
            border-radius: 10px;
        }

        .table th {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 14px;
            color: #524338;
        }

        .table td {
            padding: 12px 14px;
            vertical-align: middle;
            color: #2c180b;
        }

        .badge {
            font-size: 0.78rem;
            padding: 5px 10px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .dropdown-item {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 14px;
        }
    
        .app-footer {
            text-align: center;
            padding: 24px;
            font-size: 15px;
            font-weight: 600;
            color: #524338;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #f6f3ee;
            border-top: 1px solid #d5cbc1;
        }
    </style>
</head>
<body>

    <!-- NAV SUPERIOR -->
    <header class="top-navbar">
        <div class="d-flex align-items-center">
            <div class="brand-section">
               <div class="logo-circle-nav">
                   <img src="images/logo.jpeg" alt="Logo Café Control" style="width: 100%; height: 100%; object-fit: cover;">
               </div>
               <h1 class="brand-name">Café control</h1>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
            <div class="welcome-text d-none d-md-block">
                <h5>Bienvenido, <?= htmlspecialchars(strtoupper($nombre_usuario_logueado)) ?></h5>
                <p>Administra tus fincas y consulta su información</p>
            </div>
        </div>

        <div class="user-nav-actions">
            <div class="notification-icon">
                <i class="fa-regular fa-bell"></i>
                <span class="notification-badge"></span>
            </div>
            <div class="dropdown">
                <div class="profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (!empty($foto_perfil) && file_exists($foto_perfil)): ?>
                        <img src="<?= htmlspecialchars($foto_perfil) ?>" alt="Foto" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder">
                            <?= strtoupper(substr($nombre_usuario_logueado, 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <i class="fa-solid fa-chevron-down text-white small"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="panel_caficultor.php"><i class="fa-regular fa-user me-2"></i> Mi Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item fw-semibold" href="cerrar.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="layout-wrapper">
        <!-- MENÚ LATERAL -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="panel_caficultor.php"><i class="fa-solid fa-house"></i> Perfil</a>
                </li>
                <li class="sidebar-item">
                    <a href="fincas.php"><i class="fa-solid fa-location-dot"></i> Fincas</a>
                </li>
                <li class="sidebar-item">
                    <a href="recolecciones_c.php"><i class="fa-solid fa-box-archive"></i> Recolecciones</a>
                </li>
                <li class="sidebar-item">
                    <a href="recolectores_c.php"><i class="fa-solid fa-user-group"></i> Trabajadores</a>
                </li>
                <li class="sidebar-item">
                    <a href="resumen_c.php"><i class="fa-solid fa-chart-simple"></i> Resumen General</a>
                </li>
                <li class="sidebar-item">
                    <a href="precio.php"><i class="fa-solid fa-dollar-sign"></i> Precio del Kilo</a>
                </li>
                <li class="sidebar-item">
                    <a href="asignar_m.php"><i class="fa-solid fa-user-gear" style="font-size: 18px;"></i> Mayordomo</a>
                </li>
                <li class="sidebar-item active">
                    <a href="gestionar_usuarios.php">
                        <i class="fa-solid fa-users-gear"></i>
                        Gestionar Usuarios
                    </a>
                </li>
                <li class="sidebar-item mt-3">
                    <a href="cerrar.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
                </li>
            </ul>
        </aside>

        <!-- CONTENIDO DE GESTIÓN DE USUARIOS -->
        <main class="main-content">
            <h2 class="page-title">Gestión General de Usuarios</h2>

            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 fs-6" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 fs-6" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Formulario Agregar / Editar -->
            <div class="card shadow-sm border-0 mb-4 rounded-3">
                <div class="card-header bg-success text-white card-header-title py-3">
                    <?= $usuarioEdit ? 'Editar Usuario' : 'Agregar Nuevo Usuario' ?>
                </div>
                <div class="card-body p-4">
                    <form action="gestionar_usuarios.php" method="POST">
                        <input type="hidden" name="accion" value="<?= $usuarioEdit ? 'editar' : 'agregar' ?>">
                        <?php if ($usuarioEdit): ?>
                            <input type="hidden" name="id_usuario" value="<?= $usuarioEdit['id_usuario'] ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Documento</label>
                                <input type="text" class="form-control" name="documento" value="<?= htmlspecialchars($usuarioEdit['documento'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Nombres</label>
                                <input type="text" class="form-control" name="nombres" value="<?= htmlspecialchars($usuarioEdit['nombres'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Apellidos</label>
                                <input type="text" class="form-control" name="apellidos" value="<?= htmlspecialchars($usuarioEdit['apellidos'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" value="<?= htmlspecialchars($usuarioEdit['telefono'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Correo electrónico</label>
                                <input type="email" class="form-control" name="correo" value="<?= htmlspecialchars($usuarioEdit['correo'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nombre de usuario</label>
                                <input type="text" class="form-control" name="usuario" value="<?= htmlspecialchars($usuarioEdit['usuario'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contraseña <span class="fw-normal text-muted"><?= $usuarioEdit ? '(Opcional)' : '' ?></span></label>
                                <input type="password" class="form-control" name="contrasena" <?= $usuarioEdit ? '' : 'required' ?>>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rol Asignado</label>
                                <select class="form-select" name="rol" required>
                                    <option value="recolector" <?= (isset($usuarioEdit) && $usuarioEdit['rol'] === 'recolector') ? 'selected' : '' ?>>Recolector</option>
                                    <option value="mayordomo" <?= (isset($usuarioEdit) && $usuarioEdit['rol'] === 'mayordomo') ? 'selected' : '' ?>>Mayordomo</option>
                                    <option value="admin" <?= (isset($usuarioEdit) && $usuarioEdit['rol'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado" required>
                                    <option value="activo" <?= (isset($usuarioEdit) && $usuarioEdit['estado'] === 'activo') ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactivo" <?= (isset($usuarioEdit) && $usuarioEdit['estado'] === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn btn-success btn-custom-action me-2"><?= $usuarioEdit ? 'Actualizar Usuario' : 'Guardar Cambios' ?></button>
                            <?php if ($usuarioEdit): ?>
                                <a href="gestionar_usuarios.php" class="btn btn-secondary btn-custom-action">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Todos los Usuarios Registrados -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-dark text-white card-header-title py-3">
                    Lista General de Usuarios Registrados
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento</th>
                                    <th>Nombre Completo</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_usuarios && $resultado_usuarios->num_rows > 0): ?>
                                    <?php while ($row = $resultado_usuarios->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-medium text-secondary"><?= htmlspecialchars($row['documento']) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?></td>
                                            <td><?= htmlspecialchars($row['telefono']) ?></td>
                                            <td><?= htmlspecialchars($row['correo']) ?></td>
                                            <td><code class="text-dark bg-light px-2 py-1 rounded"><?= htmlspecialchars($row['usuario']) ?></code></td>
                                            <td>
                                                <span class="badge bg-info text-dark text-capitalize">
                                                    <?= htmlspecialchars($row['rol']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?= $row['estado'] === 'activo' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= ucfirst($row['estado']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="gestionar_usuarios.php?accion=pre_editar&id=<?= $row['id_usuario'] ?>" class="btn btn-sm btn-outline-warning me-1" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                                <a href="gestionar_usuarios.php?accion=suspender&id=<?= $row['id_usuario'] ?>&estado=<?= $row['estado'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Cambiar Estado"><i class="fa-solid fa-ban"></i></a>
                                                <a href="gestionar_usuarios.php?accion=eliminar&id=<?= $row['id_usuario'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')" title="Eliminar"><i class="fa-solid fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted fs-6">No se encontraron usuarios registrados en la base de datos.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <footer class="app-footer">
        <i data-lucide="coffee" size="18"></i>
        <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos</span>
    </footer>

    <!-- Script de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>