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


/* =========================================================
   CARGAR FINCAS DEL CAFICULTOR
   ========================================================= */

$fincas_caficultor = [];

$sql_fincas = "SELECT id_finca, nombre
               FROM fincas
               WHERE id_caficultor = ?
               ORDER BY nombre ASC";

$stmt_fincas = $con->prepare($sql_fincas);
$stmt_fincas->bind_param("i", $id_usuario);
$stmt_fincas->execute();

$res_fincas = $stmt_fincas->get_result();

while ($finca = $res_fincas->fetch_assoc()) {
    $fincas_caficultor[] = $finca;
}


/* =========================================================
   VARIABLES DEL FORMULARIO
   ========================================================= */

$doc_buscado = $_POST['documento'] ?? '';
$nombre_m = $_POST['nombre_m'] ?? '';
$telefono_m = $_POST['telefono_m'] ?? '';
$correo_m = $_POST['correo_m'] ?? '';
$id_mayordomo_encontrado = $_POST['id_mayordomo_encontrado'] ?? '';

$mensaje_alerta = '';
$tipo_alerta = '';


/* =========================================================
   BUSCAR MAYORDOMO POR DOCUMENTO
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['buscar_documento'])
) {

    $doc_buscado = trim($_POST['documento'] ?? '');

    if ($doc_buscado === '') {

        $mensaje_alerta = "Ingrese un número de documento.";
        $tipo_alerta = "danger";

    } else {

        $sql_m = "SELECT 
                    id_usuario,
                    nombres,
                    apellidos,
                    telefono,
                    correo,
                    rol,
                    estado
                  FROM usuarios
                  WHERE documento = ?
                  LIMIT 1";

        $stmt_m = $con->prepare($sql_m);
        $stmt_m->bind_param("s", $doc_buscado);
        $stmt_m->execute();

        $res_m = $stmt_m->get_result();

        if ($row_m = $res_m->fetch_assoc()) {

            $id_mayordomo_encontrado = $row_m['id_usuario'];

            $nombre_m =
                $row_m['nombres'] . ' ' . $row_m['apellidos'];

            $telefono_m = $row_m['telefono'];
            $correo_m = $row_m['correo'];

        } else {

            $mensaje_alerta =
                "No se encontró ningún usuario con ese número de documento.";

            $tipo_alerta = "danger";

            $id_mayordomo_encontrado = '';
            $nombre_m = '';
            $telefono_m = '';
            $correo_m = '';
        }
    }
}


/* =========================================================
   GUARDAR MAYORDOMO Y PERMISOS
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['guardar_mayordomo'])
) {

    $id_mayordomo =
        (int) ($_POST['id_mayordomo_encontrado'] ?? 0);

    $id_finca =
        (int) ($_POST['id_finca'] ?? 0);


    if ($id_mayordomo <= 0) {

        $mensaje_alerta =
            "Primero debe buscar un mayordomo válido.";

        $tipo_alerta = "danger";

    } elseif ($id_finca <= 0) {

        $mensaje_alerta =
            "Debe seleccionar una finca.";

        $tipo_alerta = "danger";

    } else {

        /* ================================================
           VERIFICAR QUE LA FINCA PERTENEZCA AL CAFICULTOR
           ================================================ */

        $sql_verificar_finca =
            "SELECT id_finca
             FROM fincas
             WHERE id_finca = ?
             AND id_caficultor = ?
             LIMIT 1";

        $stmt_verificar_finca =
            $con->prepare($sql_verificar_finca);

        $stmt_verificar_finca->bind_param(
            "ii",
            $id_finca,
            $id_usuario
        );

        $stmt_verificar_finca->execute();

        $res_verificar_finca =
            $stmt_verificar_finca->get_result();


        if (!$res_verificar_finca->fetch_assoc()) {

            $mensaje_alerta =
                "La finca seleccionada no pertenece a este caficultor.";

            $tipo_alerta = "danger";

        } else {

            /* ============================================
               OBTENER PERMISOS
               ============================================ */

            $registrar_recolecciones =
                isset($_POST['registrar_recolecciones']) ? 1 : 0;

            $editar_recolecciones =
                isset($_POST['editar_recolecciones']) ? 1 : 0;

            $eliminar_recolectores =
                isset($_POST['eliminar_recolectores']) ? 1 : 0;

            $ver_reportes =
                isset($_POST['ver_reportes']) ? 1 : 0;

            $gestionar_mayordomos =
                isset($_POST['gestionar_mayordomos']) ? 1 : 0;


            try {

                /* ========================================
                   GUARDAR / ACTUALIZAR PERMISOS
                   ======================================== */

                $sql_save = "
                    INSERT INTO permisos_mayordomo
                    (
                        id_mayordomo,
                        id_finca,
                        registrar_recolecciones,
                        editar_recolecciones,
                        eliminar_recolectores,
                        ver_reportes,
                        gestionar_mayordomos
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)

                    ON DUPLICATE KEY UPDATE

                        registrar_recolecciones =
                            VALUES(registrar_recolecciones),

                        editar_recolecciones =
                            VALUES(editar_recolecciones),

                        eliminar_recolectores =
                            VALUES(eliminar_recolectores),

                        ver_reportes =
                            VALUES(ver_reportes),

                        gestionar_mayordomos =
                            VALUES(gestionar_mayordomos)
                ";

                $stmt_save = $con->prepare($sql_save);

                $stmt_save->bind_param(
                    "iiiiiii",
                    $id_mayordomo,
                    $id_finca,
                    $registrar_recolecciones,
                    $editar_recolecciones,
                    $eliminar_recolectores,
                    $ver_reportes,
                    $gestionar_mayordomos
                );

                $stmt_save->execute();


                /* ========================================
                   CAMBIAR ROL A MAYORDOMO
                   ======================================== */

                $sql_rol = "
                    UPDATE usuarios
                    SET rol = 'mayordomo'
                    WHERE id_usuario = ?
                ";

                $stmt_rol = $con->prepare($sql_rol);
                $stmt_rol->bind_param("i", $id_mayordomo);
                $stmt_rol->execute();


                /* ========================================
                   MENSAJE DE ÉXITO
                   ======================================== */

                $mensaje_alerta =
                    "¡Mayordomo y permisos asignados con éxito!";

                $tipo_alerta = "success";


                /* ========================================
                   LIMPIAR FORMULARIO
                   ======================================== */

                $doc_buscado = '';
                $nombre_m = '';
                $telefono_m = '';
                $correo_m = '';
                $id_mayordomo_encontrado = '';

            } catch (Exception $e) {

                $mensaje_alerta =
                    "Error al guardar en la base de datos: "
                    . $e->getMessage();

                $tipo_alerta = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Delegar Mayordomo - Café Control</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
rel="stylesheet">

<script src="https://unpkg.com/lucide@latest"></script>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<style>

:root {

    --bg-main:#f4efe9;
    --bg-sidebar:#e8ded1;
    --bg-card:#ffffff;
    --bg-card-inner:#faf7f4;

    --primary-brown:#3d210d;
    --primary-dark:#241206;

    --primary-green:#075e36;
    --primary-green-hover:#054829;

    --text-dark:#1a1410;
    --text-muted:#5c5249;

    --border-color:#c7b9ab;

    --font-family:'Plus Jakarta Sans',sans-serif;
}

* {
    box-sizing:border-box;
    margin:0;
    padding:0;
    font-family:var(--font-family);
}

body {

    background-color:var(--bg-main);
    color:var(--text-dark);

    display:flex;
    flex-direction:column;

    min-height:100vh;

    font-size:18px;
    line-height:1.6;
}

img {
    max-width: 100%;
    height: auto;
    display: block;
}

.top-header {

    background-color:var(--primary-brown);
    color:#fff;

    display:flex;
    align-items:center;
    justify-content:space-between;

    height:84px;
    padding:0 36px;

    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

.brand {

    display:flex;
    align-items:center;
    gap:16px;

    width:280px;
}

.brand-logo {

    width:48px;
    height:48px;

    background:#fff;

    border-radius:50%;

    display:flex;
    align-items:center;
    justify-content:center;

    overflow:hidden;
    flex-shrink:0;
}

.brand-logo img,
.brand-logo .app-logo {
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
}

.brand-text h1 {

    font-size:22px;
    font-weight:800;
}

.header-center {

    display:flex;
    align-items:center;
    gap:20px;

    flex:1;
    padding-left:24px;
}

.menu-toggle {

    background:none;
    border:none;

    color:#fff;

    cursor:pointer;
}

.user-welcome h2 {

    font-size:20px;
    font-weight:800;
}

.user-welcome p {

    font-size:15px;
    opacity:.9;
}

.header-actions {

    display:flex;
    align-items:center;
    gap:24px;
}

.icon-btn {

    background:none;
    border:none;

    color:#fff;

    cursor:pointer;

    position:relative;
}

.badge-dot {

    position:absolute;

    top:-2px;
    right:-2px;

    width:12px;
    height:12px;

    background:#4caf50;

    border:2px solid var(--primary-brown);

    border-radius:50%;
}

a.user-profile,
a.user-profile:visited,
a.user-profile:hover,
a.user-profile:active {
    display:flex;
    align-items:center;
    gap:12px;
    cursor: pointer;
    color: inherit;
    text-decoration: none;
}

.avatar {

    width:48px;
    height:48px;

    background:#fff;
    color:var(--primary-brown);

    border-radius:50%;

    display:flex;
    align-items:center;
    justify-content:center;

    overflow:hidden;
    flex-shrink:0;
    border: 1px solid var(--border-color);
}

.avatar img,
.avatar .header-avatar {
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
    display: block;
}

.avatar svg {
    stroke: var(--primary-brown) !important;
    color: var(--primary-brown) !important;
}

.app-layout {

    display:flex;
    flex:1;
}

.sidebar {

    width:290px;

    background:var(--bg-sidebar);

    padding:32px 20px;

    flex-shrink:0;
}

.nav-list {

    list-style:none;

    display:flex;
    flex-direction:column;

    gap:12px;
}

.nav-item a {

    display:flex;
    align-items:center;

    gap:16px;

    padding:16px 22px;

    border-radius:14px;

    color:var(--text-dark);

    text-decoration:none;

    font-size:17px;

    font-weight:700;
}

.nav-item.active a {

    background:var(--primary-brown);

    color:#fff;

    box-shadow:0 4px 12px rgba(61,33,13,.2);
}

.nav-item:not(.active) a:hover {

    background:rgba(0,0,0,.08);
}

.main-content {

    flex:1;

    padding:40px 48px;

    display:flex;
    flex-direction:column;

    gap:32px;
}

.panel-card {

    background:#fff;

    border-radius:20px;

    border:1px solid var(--border-color);

    padding:40px;

    box-shadow:0 6px 18px rgba(0,0,0,.06);

    width:100%;
}

.panel-header {

    display:flex;

    align-items:center;

    gap:16px;

    margin-bottom:32px;
}

.panel-icon-circle {

    width:56px;
    height:56px;

    background:var(--primary-green);

    color:#fff;

    border-radius:50%;

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:24px;
}

.panel-header-text h2 {

    font-size:26px;

    color:var(--primary-dark);

    font-weight:800;
}

.panel-header-text p {

    font-size:17px;

    color:var(--text-muted);

    font-weight:600;
}

.delegation-grid {

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:32px;
}

.sub-card {

    background:var(--bg-card-inner);

    border:2px solid var(--border-color);

    border-radius:16px;

    padding:28px;

    display:flex;

    flex-direction:column;

    gap:20px;
}

.sub-card-title {

    font-size:20px;

    font-weight:800;

    color:var(--primary-dark);

    border-bottom:2px solid var(--border-color);

    padding-bottom:8px;
}

.form-group {

    display:flex;

    flex-direction:column;

    gap:8px;

}

.form-group label {

    font-size:17px;

    font-weight:800;
}

.input-search-group {

    display:flex;

    gap:12px;
}

.custom-input,
.custom-select {

    width:100%;

    padding:16px 20px;

    border-radius:14px;

    border:2px solid var(--border-color);

    background:#fff;

    font-size:18px;

    font-weight:700;

    outline:none;
}

.custom-input:focus,
.custom-select:focus {

    border-color:var(--primary-brown);
}

.custom-input:read-only {

    background:#ebe3d9;

    cursor:not-allowed;
}

.btn-search {

    background:var(--primary-green);

    color:#fff;

    border:none;

    padding:0 24px;

    border-radius:14px;

    font-weight:800;

    font-size:18px;

    cursor:pointer;
}

.btn-search:hover {

    background:var(--primary-green-hover);
}

.permisos-container {

    display:flex;

    flex-direction:column;

    justify-content:space-between;
}

.permiso-item {

    display:flex;

    align-items:flex-start;

    gap:14px;

    cursor:pointer;

    padding:10px 12px;

    border-radius:10px;
}

.permiso-item:hover {

    background:rgba(0,0,0,.03);
}

.permiso-checkbox {

    width:24px;
    height:24px;

    accent-color:var(--primary-green);

    margin-top:2px;

    cursor:pointer;
}

.permiso-text h4 {

    font-size:18px;

    font-weight:800;

    line-height:1.2;
}

.permiso-text p {

    font-size:15px;

    color:var(--text-muted);

    font-weight:600;

    margin-top:4px;
}

.btn-save-container {

    display:flex;

    justify-content:flex-end;

    margin-top:16px;
}

.btn-save {

    background:var(--primary-green);

    color:#fff;

    border:none;

    padding:16px 32px;

    border-radius:14px;

    font-weight:800;

    font-size:18px;

    cursor:pointer;

    display:inline-flex;

    align-items:center;

    gap:12px;
}

.btn-save:hover {

    background:var(--primary-green-hover);
}

.alert-message {

    padding:16px 20px;

    border-radius:12px;

    font-size:18px;

    margin-bottom:24px;

    font-weight:800;
}

.alert-danger {

    background:#ffcdd2;

    color:#b71c1c;

    border:2px solid #ef5350;
}

.alert-success {

    background:#c8e6c9;

    color:#1b5e20;

    border:2px solid #81c784;
}

.app-footer {

    text-align:center;

    padding:24px;

    font-size:15px;

    font-weight:600;

    color:var(--text-muted);

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    background:var(--bg-main);

    border-top:1px solid var(--border-color);
}

/* Fuerza a los íconos de la cabecera a ser blancos (excepto la foto de perfil) */
.header-actions .icon-btn i,
.header-actions .icon-btn svg {
    color: #fff !important;
    stroke: #fff !important;
}

@media(max-width:992px){

    .delegation-grid{
        grid-template-columns:1fr;
    }

    .app-layout{
        flex-direction:column;
    }

    .sidebar{
        width:100%;
    }

    .main-content{
        padding:24px;
    }
}

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
<a href="panel_caficultor.php">
<i data-lucide="home" size="24"></i>
Perfil
</a>
</li>

<li class="nav-item">
<a href="fincas.php">
<i data-lucide="map-pin" size="24"></i>
Fincas
</a>
</li>

<li class="nav-item">
<a href="recolecciones_c.php">
<i data-lucide="archive" size="24"></i>
Recolecciones
</a>
</li>

<li class="nav-item">
<a href="recolectores_c.php">
<i data-lucide="users" size="24"></i>
Trabajadores
</a>
</li>

<li class="nav-item">
<a href="resumen_c.php">
<i data-lucide="bar-chart-2" size="24"></i>
Resumen General
</a>
</li>

<li class="nav-item">
<a href="precio.php">
<i data-lucide="dollar-sign" size="24"></i>
Precio del Kilo
</a>
</li>

<li class="nav-item active">
<a href="asignar_m.php">
<i class="fa-solid fa-user-gear"></i>
Mayordomo
</a>
</li>
               <li class="nav-item">
                    <a href="gestionar_usuarios.php">
                        <i class="fa-solid fa-users-gear" style="font-size:18px;"></i>
                        Gestionar Usuarios
                    </a>
               </li>

<li class="nav-item">
<a href="cerrar.php">
<i data-lucide="log-out" size="24"></i>
Cerrar Sesión
</a>
</li>

</ul>

</aside>


<main class="main-content">

<div class="panel-card">

<div class="panel-header">

<div class="panel-icon-circle">
<i class="fa-solid fa-user-large"></i>
</div>

<div class="panel-header-text">

<h2>Delegar mayordomo</h2>

<p>
Asigne una persona como mayordomo y configure sus permisos en el sistema.
</p>

</div>

</div>


<?php if (!empty($mensaje_alerta)): ?>

<div class="alert-message alert-<?php echo $tipo_alerta; ?>">

<?php echo htmlspecialchars($mensaje_alerta); ?>

</div>

<?php endif; ?>


<div class="delegation-grid">


<!-- =====================================================
     INFORMACIÓN DEL MAYORDOMO
     ===================================================== -->

<div class="sub-card">

<h3 class="sub-card-title">
Información del mayordomo
</h3>


<form method="POST" action="asignar_m.php">

<div class="form-group">

<label for="doc_input">
Número de documento
</label>

<div class="input-search-group">

<input
type="text"
id="doc_input"
name="documento"
class="custom-input"
placeholder="Ej: 1085925030"
value="<?php echo htmlspecialchars($doc_buscado); ?>"
required
>

<button
type="submit"
name="buscar_documento"
class="btn-search"
>

Buscar

</button>

</div>

</div>

</form>


<form
method="POST"
action="asignar_m.php"
id="form_guardar_m"
>


<input
type="hidden"
name="id_mayordomo_encontrado"
value="<?php echo htmlspecialchars($id_mayordomo_encontrado); ?>"
>

<input
type="hidden"
name="documento"
value="<?php echo htmlspecialchars($doc_buscado); ?>"
>

<input
type="hidden"
name="nombre_m"
value="<?php echo htmlspecialchars($nombre_m); ?>"
>

<input
type="hidden"
name="telefono_m"
value="<?php echo htmlspecialchars($telefono_m); ?>"
>

<input
type="hidden"
name="correo_m"
value="<?php echo htmlspecialchars($correo_m); ?>"
>


<div class="form-group">

<label>Nombre completo</label>

<input
type="text"
class="custom-input"
readonly
value="<?php echo htmlspecialchars($nombre_m); ?>"
>

</div>


<div class="form-group">

<label>Teléfono</label>

<input
type="text"
class="custom-input"
readonly
value="<?php echo htmlspecialchars($telefono_m); ?>"
>

</div>


<div class="form-group">

<label>Correo electrónico</label>

<input
type="email"
class="custom-input"
readonly
value="<?php echo htmlspecialchars($correo_m); ?>"
>

</div>


<div class="form-group">

<label for="finca_select">
Finca a asignar *
</label>

<select
name="id_finca"
id="finca_select"
class="custom-select"
required
>

<option value="">
-- Seleccione una finca --
</option>

<?php foreach ($fincas_caficultor as $finca): ?>

<option value="<?php echo $finca['id_finca']; ?>">

<?php echo htmlspecialchars($finca['nombre']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>


<!-- =====================================================
     PERMISOS
     ===================================================== -->

<div class="sub-card permisos-container">

<div>

<h3 class="sub-card-title">
Permisos asignados
</h3>


<div
style="
display:flex;
flex-direction:column;
gap:16px;
margin-top:16px;
"
>


<label class="permiso-item">

<input
type="checkbox"
name="registrar_recolecciones"
class="permiso-checkbox"
checked
>

<div class="permiso-text">

<h4>
Registrar recolecciones
</h4>
 
<p>
Puede registrar las entregas de café de los recolectores.
</p>

</div>

</label>


<label class="permiso-item">

<input
type="checkbox"
name="editar_recolecciones"
class="permiso-checkbox"
checked
>

<div class="permiso-text">

<h4>
Editar recolecciones
</h4>

<p>
Puede editar las recolecciones registradas.
</p>

</div>

</label>


<label class="permiso-item">

<input
type="checkbox"
name="eliminar_recolectores"
class="permiso-checkbox"
checked
>

<div class="permiso-text">

<h4>
Eliminar recolectores
</h4>

<p>
Puede eliminar recolectores de la base de datos.
</p>

</div>

</label>


<label class="permiso-item">

<input
type="checkbox"
name="ver_reportes"
class="permiso-checkbox"
checked
>

<div class="permiso-text">

<h4>
Ver reportes
</h4>

<p>
Puede visualizar reportes y resúmenes.
</p>

</div>

</label>


<label class="permiso-item">

<input
type="checkbox"
name="gestionar_mayordomos"
class="permiso-checkbox"
checked
>

<div class="permiso-text">

<h4>
Gestionar mayordomos
</h4>

<p>
Puede asignar o modificar mayordomos.
</p>

</div>

</label>

</div>

</div>


<div class="btn-save-container">

<button
type="submit"
form="form_guardar_m"
name="guardar_mayordomo"
class="btn-save"
>

<i class="fa-solid fa-user-plus"></i>

Guardar mayordomo

</button>

</div>

</div>

</form>

</div>

</main>

</div>


<footer class="app-footer">

<i data-lucide="coffee" size="18"></i>

<span>

<strong>Café Control</strong>

© 2026 Developers:
Gloria Tatiana Sanchez &
Tatiana Silva Penagos

</span>

</footer>


<script>

lucide.createIcons();

</script>

</body>

</html>