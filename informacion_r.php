<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/assets/inc/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$id_usuario = (int) $_SESSION['id_usuario'];

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
/*
 * Perfil persistente del recolector.
 * Se guarda en una tabla separada para no modificar la estructura existente
 * de la tabla usuarios (la base mostrada no tiene un campo de fotografía).
 */
$con->query("CREATE TABLE IF NOT EXISTS perfiles_usuario (
    id_usuario INT NOT NULL PRIMARY KEY,
    foto_perfil VARCHAR(255) NULL,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* Migración compatible: si una versión anterior usaba perfiles_recolectores,
   copia sus fotos a la tabla única perfiles_usuario sin reemplazar las actuales. */
try {
    $existe_antigua = false;
    $stmt_tabla = $con->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'perfiles_recolectores'"
    );
    if ($stmt_tabla) {
        $stmt_tabla->execute();
        $r_tabla = $stmt_tabla->get_result();
        $fila_tabla = $r_tabla->fetch_assoc();
        $existe_antigua = ((int)($fila_tabla['total'] ?? 0) > 0);
        $stmt_tabla->close();
    }

    if ($existe_antigua) {
        $con->query(
            "INSERT INTO perfiles_usuario (id_usuario, foto_perfil)
             SELECT id_usuario, foto_perfil
             FROM perfiles_recolectores
             WHERE foto_perfil IS NOT NULL AND foto_perfil <> ''
             ON DUPLICATE KEY UPDATE
                 foto_perfil = CASE
                     WHEN perfiles_usuario.foto_perfil IS NULL OR perfiles_usuario.foto_perfil = ''
                     THEN VALUES(foto_perfil)
                     ELSE perfiles_usuario.foto_perfil
                 END"
        );
    }
} catch (Throwable $e) {
    /* La migración es opcional; nunca debe impedir que cargue el panel. */
}

$perfil_mensaje = '';
$perfil_tipo = '';

$directorio_perfiles = __DIR__ . '/uploads/perfiles';
if (!is_dir($directorio_perfiles)) {
    @mkdir($directorio_perfiles, 0755, true);
}

/*
 * Guardar cambios del perfil. El nombre/apellido se mantienen en usuarios
 * y la fotografía se almacena de forma permanente en uploads/perfiles.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_perfil'])) {
    $nuevo_nombre = trim((string) ($_POST['perfil_nombres'] ?? ''));
    $nuevo_apellido = trim((string) ($_POST['perfil_apellidos'] ?? ''));

    if ($nuevo_nombre === '' || $nuevo_apellido === '') {
        $perfil_mensaje = 'El nombre y el apellido son obligatorios.';
        $perfil_tipo = 'error';
    } else {
        $ruta_foto_nueva = null;
        $error_foto = '';

        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
                $error_foto = 'No se pudo cargar la imagen seleccionada.';
            } elseif ((int) $_FILES['foto_perfil']['size'] > 2 * 1024 * 1024) {
                $error_foto = 'La imagen no puede superar 2 MB.';
            } else {
                $info_imagen = @getimagesize($_FILES['foto_perfil']['tmp_name']);
                $mime = $info_imagen['mime'] ?? '';
                $mime_ext = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (!$info_imagen || !isset($mime_ext[$mime])) {
                    $error_foto = 'Solo se permiten imágenes JPG, PNG o WEBP.';
                } elseif (!is_dir($directorio_perfiles) || !is_writable($directorio_perfiles)) {
                    $error_foto = 'La carpeta uploads/perfiles no tiene permisos de escritura.';
                } else {
                    try {
                        $nombre_archivo = 'perfil_' . $id_usuario . '_' . bin2hex(random_bytes(8)) . '.' . $mime_ext[$mime];
                    } catch (Throwable $e) {
                        $nombre_archivo = 'perfil_' . $id_usuario . '_' . uniqid('', true) . '.' . $mime_ext[$mime];
                    }

                    $destino = $directorio_perfiles . '/' . $nombre_archivo;
                    if (!move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destino)) {
                        $error_foto = 'No se pudo guardar la imagen en el servidor.';
                    } else {
                        $ruta_foto_nueva = 'uploads/perfiles/' . $nombre_archivo;
                    }
                }
            }
        }

        if ($error_foto === '') {
            $foto_anterior = null;
            $stmt_foto = $con->prepare("SELECT foto_perfil FROM perfiles_usuario WHERE id_usuario = ? LIMIT 1");
            if ($stmt_foto) {
                $stmt_foto->bind_param('i', $id_usuario);
                $stmt_foto->execute();
                $res_foto = $stmt_foto->get_result();
                if ($fila_foto = $res_foto->fetch_assoc()) {
                    $foto_anterior = $fila_foto['foto_perfil'];
                }
                $stmt_foto->close();
            }

            $con->begin_transaction();
            try {
                $stmt_usuario = $con->prepare("UPDATE usuarios SET nombres = ?, apellidos = ? WHERE id_usuario = ?");
                if (!$stmt_usuario) {
                    throw new RuntimeException('No fue posible preparar la actualización del perfil.');
                }
                $stmt_usuario->bind_param('ssi', $nuevo_nombre, $nuevo_apellido, $id_usuario);
                if (!$stmt_usuario->execute()) {
                    throw new RuntimeException('No fue posible actualizar los datos del perfil.');
                }
                $stmt_usuario->close();

                if ($ruta_foto_nueva !== null) {
                    $stmt_perfil = $con->prepare(
                        "INSERT INTO perfiles_usuario (id_usuario, foto_perfil)
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE foto_perfil = VALUES(foto_perfil)"
                    );
                    if (!$stmt_perfil) {
                        throw new RuntimeException('No fue posible guardar la fotografía del perfil.');
                    }
                    $stmt_perfil->bind_param('is', $id_usuario, $ruta_foto_nueva);
                    if (!$stmt_perfil->execute()) {
                        throw new RuntimeException('No fue posible guardar la fotografía del perfil.');
                    }
                    $stmt_perfil->close();
                } else {
                    $stmt_perfil = $con->prepare(
                        "INSERT INTO perfiles_usuario (id_usuario, foto_perfil)
                         VALUES (?, NULL)
                         ON DUPLICATE KEY UPDATE id_usuario = VALUES(id_usuario)"
                    );
                    if ($stmt_perfil) {
                        $stmt_perfil->bind_param('i', $id_usuario);
                        $stmt_perfil->execute();
                        $stmt_perfil->close();
                    }
                }

                $con->commit();

                if ($ruta_foto_nueva !== null && $foto_anterior) {
                    $archivo_anterior = __DIR__ . '/' . ltrim((string) $foto_anterior, '/');
                    if (is_file($archivo_anterior) && strpos(realpath($archivo_anterior) ?: '', realpath($directorio_perfiles) ?: '__no__') === 0) {
                        @unlink($archivo_anterior);
                    }
                }

                header('Location: informacion_r.php?perfil=guardado');
                exit;
            } catch (Throwable $e) {
                $con->rollback();
                if ($ruta_foto_nueva !== null) {
                    @unlink(__DIR__ . '/' . ltrim($ruta_foto_nueva, '/'));
                }
                $perfil_mensaje = 'No se pudo guardar el perfil. Inténtalo nuevamente.';
                $perfil_tipo = 'error';
            }
        } else {
            $perfil_mensaje = $error_foto;
            $perfil_tipo = 'error';
        }
    }
}

/* Leer fotografía actual guardada de forma permanente. */
$foto_perfil = '';
$stmt_perfil_actual = $con->prepare("SELECT foto_perfil FROM perfiles_usuario WHERE id_usuario = ? LIMIT 1");
if ($stmt_perfil_actual) {
    $stmt_perfil_actual->bind_param('i', $id_usuario);
    $stmt_perfil_actual->execute();
    $res_perfil_actual = $stmt_perfil_actual->get_result();
    if ($fila_perfil_actual = $res_perfil_actual->fetch_assoc()) {
        $foto_perfil = trim((string) ($fila_perfil_actual['foto_perfil'] ?? ''));
    }
    $stmt_perfil_actual->close();
}

$roles = [
    'caficultor' => 'Caficultor',
    'mayordomo' => 'Mayordomo',
    'recolector' => 'Recolector'
];

$nombre = 'Usuario';
$apellido = '';
$rol = 'recolector';
$estado = 'activo';
$foto = '';

$stmt = $con->prepare(
    "SELECT u.nombres, u.apellidos, u.rol, u.estado,
            COALESCE(p.foto_perfil, '') AS foto_perfil
     FROM usuarios u
     LEFT JOIN perfiles_usuario p ON p.id_usuario = u.id_usuario
     WHERE u.id_usuario = ?
     LIMIT 1"
);

if ($stmt) {
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($fila = $res->fetch_assoc()) {
        $nombre = trim((string)($fila['nombres'] ?? 'Usuario'));
        $apellido = trim((string)($fila['apellidos'] ?? ''));
        $rol = trim((string)($fila['rol'] ?? 'recolector'));
        $estado = trim((string)($fila['estado'] ?? 'activo'));
        $foto = trim((string)($fila['foto_perfil'] ?? ''));
    }
    $stmt->close();
}

$rol_mostrado = $roles[$rol] ?? ucfirst($rol);
$activo = ($estado === 'activo');
$estado_mostrado = $activo ? 'Activo' : 'Inactivo';
$nombre_completo = trim($nombre . ' ' . $apellido);
$foto_url = $foto !== '' ? $foto : '';
$ahora = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
$foto_version = '';
if ($foto_url !== '') {
    $ruta_foto = __DIR__ . '/' . ltrim($foto_url, '/');
    if (is_file($ruta_foto)) {
        $foto_version = (string) filemtime($ruta_foto);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Información - Café Control</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box}
:root{
  --sidebar:#17100c; --sidebar-card:#2b201a; --cream:#f9f5ed;
  --border:#cfc4bc; --text:#17120f; --muted:#4d433d;
  --green:#2a693c; --orange:#ffb000; --white:#fff;
}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f7f3ef;color:var(--text);font-size:18px;line-height:1.55;min-height:100vh;display:flex}
.sidebar{width:250px;min-height:100vh;background:var(--sidebar);color:#fff;padding:20px 12px;display:flex;flex-direction:column;justify-content:space-between;flex-shrink:0}
.logo-area{display:flex;align-items:center;gap:12px;padding:0 5px 20px;border-bottom:2px solid #382820}
.logo{width:50px;height:50px;border-radius:50%;background:#fff;color:#4a2b1c;display:flex;align-items:center;justify-content:center;font-size:23px}
.logo h1{font-size:18px;margin:0;font-weight:800}
.user-box{display:flex;align-items:center;gap:12px;margin-top:14px;padding:14px 12px;border-radius:12px;background:var(--sidebar-card);border:0;color:#fff;width:100%;text-align:left;cursor:pointer}
.user-photo{width:50px;height:50px;min-width:50px;border-radius:50%;object-fit:cover;background:#d9d9d9;color:#222;display:flex;align-items:center;justify-content:center;font-size:22px}
.user-info h6{margin:0;color:#fff;font-size:16px;line-height:20px;font-weight:800}
.user-info small{display:block;color:#eee;font-size:14px}
.active-t{color:#32df68!important;font-weight:800}
.inactive-t{color:#ff7777!important;font-weight:800}
.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px;background:currentColor}
.menu{margin-top:18px}
.menu a{display:flex;align-items:center;gap:15px;width:100%;min-height:50px;margin-bottom:7px;padding:12px 14px;border-radius:12px;text-decoration:none;color:#fff;font-size:17px;font-weight:700}
.menu a:hover{background:#30231c}.menu a.active{background:#fff1e6;color:#1c110b}.menu i{width:23px;text-align:center;font-size:18px}

.main{margin-left:0;flex:1;min-height:100vh;padding:0 25px 25px;display:flex;flex-direction:column}
.topbar{min-height:55px;display:flex;align-items:center;justify-content:flex-end}
.date-time{display:flex;align-items:center;justify-content:flex-end;gap:22px;font-size:18px;font-weight:800;white-space:nowrap;margin-left:auto}
.date-time span{display:inline-flex;align-items:center;gap:8px}
.content{flex:1 0 auto;background:#fffdfb;border:2px solid #e5ddd6;border-radius:14px;padding:28px}
h1{font-size:34px;margin:0 0 8px;font-weight:800}.intro{font-size:17px;color:var(--muted);margin:0 0 25px}
.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.info-card{background:var(--cream);border:2px solid #b8b0a9;border-radius:16px;padding:22px}
.info-card h2{font-size:21px;margin:0 0 9px}.info-card p{margin:0;color:#2d2824;font-size:17px;line-height:1.55}
.info-card i{font-size:24px;margin-bottom:12px}.full{grid-column:1/-1}
.app-footer{width:100%;margin-top:20px;padding:18px 24px;border-top:1px solid #d8cfc7;background:#f4eee8;color:#4a403a}
.footer-inner{min-height:28px;display:flex;align-items:center;justify-content:center;gap:9px;text-align:center;font-size:15px;line-height:1.4;font-weight:600}
@media(max-width:800px){.sidebar{position:fixed;left:0;top:0;z-index:20;transform:translateX(-100%);transition:.2s}.main{padding:0 12px 15px}.info-grid{grid-template-columns:1fr}}

/* ===== MENÚ LATERAL Y PIE UNIFICADOS ===== */
.sidebar,
aside.sidebar {
    width: 250px !important;
    min-width: 250px !important;
    background-color: #1c110b !important;
    color: #fff !important;
    padding: 20px 12px !important;
    min-height: 100vh;
    box-sizing: border-box;
}
.sidebar .logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 5px 20px;
    border-bottom: 2px solid #382820;
}
.sidebar .logo,
.sidebar .brand-icon {
    width: 50px;
    height: 50px;
    min-width: 50px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4a2b1c;
    font-size: 23px;
}
.sidebar .logo-text h5,
.sidebar .logo-text h1,
.sidebar .brand-text h1 {
    margin: 0;
    color: #fff;
    font-size: 18px;
    line-height: 1.15;
    font-weight: 800;
}
.sidebar .user-box {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    margin-top: 14px;
    padding: 14px 12px;
    border-radius: 12px;
    background: #2b201a;
    color: #fff;
}
.sidebar .user-box.profile-trigger,
.sidebar button.user-box {
    border: 0;
    text-align: left;
    cursor: pointer;
}
.sidebar .user-info h6,
.sidebar .user-info h3 {
    margin: 0;
    color: #fff;
    font-size: 16px;
    line-height: 20px;
    font-weight: 800;
}
.sidebar .user-info small,
.sidebar .user-details p {
    display: block;
    color: #eee;
    font-size: 14px;
    line-height: 20px;
}
.sidebar .menu,
.sidebar .nav-menu {
    margin-top: 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sidebar .menu a,
.sidebar .nav-item a {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
    min-height: 50px;
    margin: 0;
    padding: 12px 14px;
    border-radius: 12px;
    text-decoration: none;
    color: #fff;
    font-size: 17px;
    line-height: 1.2;
    font-weight: 700;
    transition: .2s;
    border: 0;
}
.sidebar .menu a:hover,
.sidebar .nav-item a:hover {
    background: #30231c;
    color: #fff;
}
.sidebar .menu a.active,
.sidebar .nav-item.active a {
    background: #fff1e6;
    color: #1c110b;
}
.sidebar .menu i,
.sidebar .nav-item a i {
    width: 23px;
    min-width: 23px;
    text-align: center;
    font-size: 18px;
}
.sidebar .activo {
    color: #32df68 !important;
    font-weight: 800;
}
.sidebar .inactivo {
    color: #ff7777 !important;
    font-weight: 800;
}

/* SEPARACIÓN DEL BOTÓN DE CERRAR SESIÓN */
.sidebar .logout,
.sidebar .btn-logout {
    width: 100%;
    margin-top: 25px; /* Crea el espacio entre Información y Cerrar sesión */
}

.sidebar .logout button,
.sidebar .btn-logout {
    width: 100%;
    min-height: 48px;
    padding: 10px 12px;
    border: 2px solid #49372d;
    border-radius: 12px;
    background: #2b201a;
    color: #fff;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
}
.sidebar .logout button:hover,
.sidebar .btn-logout:hover {
    background: #412f26;
}

/* Perfil: misma caja y tamaños */
.sidebar .profile-trigger {
    width: 50px !important;
    min-width: 50px !important;
    max-width: 50px !important;
    padding: 0 !important;
    margin: 0 !important;
    background: transparent !important;
}
.sidebar .profile-trigger .user-avatar,
.sidebar .profile-trigger .user-avatar-placeholder {
    width: 50px !important;
    height: 50px !important;
}

/* Pie de página */
.app-footer,
footer.app-footer {
    width: 100%;
    margin-top: auto !important;
    padding: 18px 24px !important;
    border-top: 1px solid #d8cfc7 !important;
    background: #f4eee8 !important;
    color: #4a403a !important;
    box-sizing: border-box;
}
.app-footer .footer-inner {
    min-height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    text-align: center;
    font-size: 15px !important;
    line-height: 1.4;
    font-weight: 600;
}
.app-footer .footer-inner i {
    font-size: 18px;
}
@media (max-width: 768px) {
    .sidebar,
    aside.sidebar {
        width: 250px !important;
        min-width: 250px !important;
    }
}

/* MODAL EDITAR PERFIL */
.profile-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.55);
}
.profile-modal.open { display: flex; }
.profile-modal-card {
    width: min(480px, 100%);
    background: #fff;
    border-radius: 18px;
    border: 2px solid #b8b8b8;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    padding: 24px;
    position: relative;
}
.profile-modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.profile-modal-head h3 { font-size: 1.35rem; font-weight: 800; }
.profile-close {
    border: 0;
    background: #f1f1f1;
    border-radius: 50%;
    width: 42px;
    height: 42px;
    cursor: pointer;
    font-size: 1.1rem;
}
.profile-photo-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 18px;
}
.profile-photo-preview {
    width: 118px;
    height: 118px;
    border-radius: 50%;
    object-fit: cover;
    background: #ededed;
    border: 4px solid #fff;
    box-shadow: 0 0 0 2px #d0d0d0;
}
.profile-photo-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 44px;
    border: 2px solid #b0b0b0;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 800;
    background: #f9f5ed;
    margin-bottom: 16px;
}
.profile-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
.profile-field label { font-weight: 800; font-size: .95rem; }
.profile-field input {
    width: 100%;
    padding: 11px 12px;
    border-radius: 10px;
    border: 2px solid #c0c0c0;
    font-size: 1rem;
    outline: none;
}
.profile-field input:focus { border-color: #2a693c; box-shadow: 0 0 0 3px rgba(42,105,60,.12); }
.profile-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}
.profile-btn {
    min-height: 44px;
    padding: 10px 18px;
    border-radius: 10px;
    border: 2px solid transparent;
    cursor: pointer;
    font-weight: 800;
}
.profile-btn.cancel { background: #eee; border-color: #c6c6c6; }
.profile-btn.save { background: #2a693c; color: #fff; }
.profile-message {
    padding: 10px 12px;
    border-radius: 10px;
    font-weight: 700;
    margin-bottom: 14px;
    background: #fde8e8;
    color: #9b1c1c;
    border: 1px solid #f2b2b2;
}
.sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

@media (max-width: 600px) {
    .app-footer { padding: 14px 12px; }
    .footer-inner { font-size: 13px; }
}
</style>
</head>
<body>
<aside class="sidebar">
<div>
  <div class="logo-area"><div class="logo"><i class="fa-solid fa-mug-hot"></i></div><div class="logo-text"><h1>Café Control</h1></div></div>
  <button class="user-box profile-trigger" id="profileTrigger" type="button" aria-label="Editar perfil">
    <?php if($foto_url!==''): ?>
      <img class="user-photo" src="<?php echo e($foto_url); ?>" alt="Foto de <?php echo e($nombre_completo); ?>">
    <?php else: ?>
      <div class="user-photo"><i class="fa-solid fa-user"></i></div>
    <?php endif; ?>
    <div class="user-info">
      <h6><?php echo e($nombre); ?><br><?php echo e($apellido); ?></h6>
      <small><?php echo e($rol_mostrado); ?></small>
      <small class="<?php echo $activo?'active-t':'inactive-t'; ?>"><span class="status-dot"></span><?php echo e($estado_mostrado); ?></small>
    </div>
  </button>
  <nav class="menu">
    <a href="panel_r.php"><i class="fa-solid fa-house"></i><span>Inicio</span></a>
    <a href="entregas_r.php"><i class="fa-solid fa-basket-shopping"></i><span>Mis Entregas</span></a>
    <a href="informacion_r.php" class="active"><i class="fa-solid fa-circle-info"></i><span>Información</span></a>
  </nav>

  <!-- CERRAR SESION -->
  <div class="logout">
     <a href="cerrar.php" style="text-decoration: none;">
       <button type="button">
          <i class="fa-solid fa-power-off"></i>
          &nbsp; Cerrar sesión
      </button>
     </a>
  </div>
</div> 
</aside>

<main class="main">
<header class="topbar">
  <div class="date-time">
    <span><i class="fa-regular fa-calendar"></i><span id="fechaActual"><?php echo e($ahora->format('d/m/Y')); ?></span></span>
    <span>|</span>
    <span><i class="fa-regular fa-clock"></i><span id="horaActual"><?php echo e($ahora->format('g:i a')); ?></span></span>
  </div>
</header>

<section class="content">
  <h1>Información de Café Control</h1>
  <p class="intro">Una aplicación creada para facilitar la gestión de la recolección de café, organizar la información de los recolectores y mantener claros los cálculos de pago.</p>

  <div class="info-grid">
    <article class="info-card">
      <i class="fa-solid fa-seedling"></i>
      <h2>¿Para qué sirve?</h2>
      <p>Café Control permite que los caficultores y responsables de una finca registren los kilos de café recolectados por cada recolector. Así se lleva un control ordenado de las entregas y del trabajo realizado.</p>
    </article>

    <article class="info-card">
      <i class="fa-solid fa-coins"></i>
      <h2>Control de pagos</h2>
      <p>El sistema utiliza el precio por kilogramo definido por el caficultor para calcular cuánto corresponde pagar según los kilos registrados. Esto facilita consultar el total de cada semana y mantener la información actualizada.</p>
    </article>

    <article class="info-card">
      <i class="fa-solid fa-calendar-week"></i>
      <h2>Seguimiento semanal</h2>
      <p>Las entregas se organizan por semanas para que cada usuario pueda consultar su actividad, revisar los kilos acumulados y distinguir claramente un periodo de trabajo del siguiente.</p>
    </article>

    <article class="info-card">
      <i class="fa-solid fa-users"></i>
      <h2>Trabajo coordinado</h2>
      <p>Los datos registrados por el caficultor o el mayordomo quedan asociados al recolector correspondiente. De esta manera, cada persona puede consultar únicamente la información vinculada con su cuenta y sus entregas.</p>
    </article>

    <article class="info-card full">
      <i class="fa-solid fa-shield-halved"></i>
      <h2>Información de la cuenta</h2>
      <p>El perfil, nombre, fotografía y estado de la cuenta se mantienen sincronizados con la base de datos. Cuando un responsable activa o inactiva una cuenta, el cambio puede verse en las diferentes interfaces del usuario al actualizar la sesión.</p>
    </article>
  </div>
</section>

<footer class="app-footer">
    <div class="footer-inner">
        <i class="fa-solid fa-mug-hot"></i>
        <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez &amp; Tatiana Silva Penagos</span>
    </div>
</footer>
</main>

<!-- MODAL EDITAR PERFIL -->
<div class="profile-modal" id="perfilModal" role="dialog" aria-modal="true"
     aria-labelledby="tituloPerfil" aria-hidden="true">
    <div class="profile-modal-card">
        <div class="profile-modal-head">
            <div>
                <h3 id="tituloPerfil">Editar perfil</h3>
                <div style="color:#6b625c;font-size:.95rem;margin-top:3px;">
                    Actualiza tu nombre o tu fotografía.
                </div>
            </div>
            <button type="button" class="profile-close" id="cerrarPerfil" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <?php if ($perfil_mensaje !== ''): ?>
            <div class="profile-message"><?php echo e($perfil_mensaje); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" id="formPerfil">
            <div class="profile-photo-wrap">
                <?php if ($foto_perfil !== ''): ?>
                    <img
                        class="profile-photo-preview"
                        id="vistaPerfil"
                        src="<?php echo e($foto_perfil); ?>?v=<?php echo e($foto_version); ?>"
                        alt="Vista previa de la foto de perfil">
                <?php else: ?>
                    <div class="profile-photo-preview" id="vistaPerfil"
                         style="display:flex;align-items:center;justify-content:center;color:#17100c;font-size:2rem;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>

            <label class="profile-photo-label" for="fotoPerfilInput">
                <i class="fa-solid fa-camera"></i> Cambiar fotografía
            </label>
            <input class="sr-only" type="file" id="fotoPerfilInput"
                   name="foto_perfil" accept="image/jpeg,image/png,image/webp">

            <div class="profile-field">
                <label for="perfilNombres">Nombres</label>
                <input type="text" id="perfilNombres" name="perfil_nombres"
                       value="<?php echo e($nombre); ?>" maxlength="100" required>
            </div>

            <div class="profile-field">
                <label for="perfilApellidos">Apellidos</label>
                <input type="text" id="perfilApellidos" name="perfil_apellidos"
                       value="<?php echo e($apellido); ?>" maxlength="100" required>
            </div>

            <div class="profile-actions">
                <button type="button" class="profile-btn cancel" id="cancelarPerfil">
                    Cancelar
                </button>
                <button type="submit" class="profile-btn save" name="guardar_perfil" value="1">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('perfilModal');
    const abrir = document.getElementById('profileTrigger');
    const cerrar = document.getElementById('cerrarPerfil');
    const cancelar = document.getElementById('cancelarPerfil');
    const inputFoto = document.getElementById('fotoPerfilInput');
    const vista = document.getElementById('vistaPerfil');

    function abrirModal() {
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const input = document.getElementById('perfilNombres');
        if (input) input.focus();
    }

    function cerrarModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    if (abrir) abrir.addEventListener('click', abrirModal);
    if (cerrar) cerrar.addEventListener('click', cerrarModal);
    if (cancelar) cancelar.addEventListener('click', cerrarModal);

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) cerrarModal();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') cerrarModal();
    });

    if (inputFoto) {
        inputFoto.addEventListener('change', function () {
            const archivo = this.files && this.files[0];
            if (!archivo || !vista) return;

            if (archivo.size > 2 * 1024 * 1024) {
                alert('La imagen no puede superar 2 MB.');
                this.value = '';
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
                alert('Solo se permiten imágenes JPG, PNG o WEBP.');
                this.value = '';
                return;
            }

            const url = URL.createObjectURL(archivo);

            if (vista.tagName === 'IMG') {
                vista.src = url;
            } else {
                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Vista previa de la nueva foto';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                vista.replaceWith(img);
                img.id = 'vistaPerfil';
            }
        });
    }

    <?php if ($perfil_mensaje !== ''): ?>
    abrirModal();
    <?php endif; ?>

    // Reloj unificado para toda la cuenta: Colombia.
    const fecha = document.getElementById('fechaActual');
    const hora = document.getElementById('horaActual');

    function actualizarReloj() {
        const ahora = new Date();
        const parts = new Intl.DateTimeFormat('es-CO', {
            timeZone: 'America/Bogota',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).formatToParts(ahora);

        const get = (tipo) => {
            const p = parts.find(x => x.type === tipo);
            return p ? p.value : '';
        };

        if (fecha) fecha.textContent = get('day') + '/' + get('month') + '/' + get('year');

        if (hora) {
            hora.textContent = new Intl.DateTimeFormat('es-CO', {
                timeZone: 'America/Bogota',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).format(ahora).toLowerCase()
              .replace('a. m.', 'am')
              .replace('p. m.', 'pm');
        }
    }

    actualizarReloj();
    setInterval(actualizarReloj, 1000);
})();
</script>

</body>
</html>