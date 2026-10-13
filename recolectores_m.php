<?php

session_start();

require_once 'assets/inc/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('America/Bogota');


/* =========================================================
   VALIDAR SESIÓN
   ========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    header("Location: index.php");
    exit();

}

$id_usuario = (int) $_SESSION['id_usuario'];

$mensaje_estado = "";


/* =========================================================
   OBTENER DATOS DEL USUARIO
   ========================================================= */

$stmt_user = $con->prepare("
    SELECT
        id_usuario,
        nombres,
        apellidos,
        foto_perfil,
        rol
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
");

$stmt_user->bind_param(
    "i",
    $id_usuario
);

$stmt_user->execute();

$res_user = $stmt_user->get_result();


if ($row_user = $res_user->fetch_assoc()) {

    $nombre_caficultor =
        $row_user['nombres'] . ' ' .
        $row_user['apellidos'];

    $foto_perfil =
        $row_user['foto_perfil'] ?? null;

    $rol_usuario =
        $row_user['rol'];

} else {

    session_destroy();

    header("Location: index.php");
    exit();

}


/* =========================================================
   VERIFICAR PERMISO GENERAL PARA VER REPORTES
   =========================================================
   
   El mayordomo debe tener por lo menos una finca con:

   ver_reportes = 1

   Si no tiene ninguna, toda la interfaz queda bloqueada.
   ========================================================= */

$tiene_permiso_general = false;


if ($rol_usuario === 'mayordomo') {

    $sql_permiso_general = "
        SELECT id_finca
        FROM permisos_mayordomo
        WHERE id_mayordomo = ?
        AND ver_reportes = 1
        LIMIT 1
    ";

    $stmt_permiso_general =
        $con->prepare($sql_permiso_general);

    $stmt_permiso_general->bind_param(
        "i",
        $id_usuario
    );

    $stmt_permiso_general->execute();

    $res_permiso_general =
        $stmt_permiso_general->get_result();


    if ($res_permiso_general->fetch_assoc()) {

        $tiene_permiso_general = true;

    }

} else {

    /*
     * Si esta página también puede ser utilizada
     * directamente por el caficultor, se permite.
     *
     * El bloqueo por ver_reportes aplica al mayordomo.
     */

    $tiene_permiso_general = true;

}


/* =========================================================
   OBTENER FINCAS DEL CAFICULTOR / USUARIO
   ========================================================= */

$fincas = [];


$stmt_fincas = $con->prepare("
    SELECT
        id_finca,
        nombre
    FROM fincas
    WHERE id_caficultor = ?
");

$stmt_fincas->bind_param(
    "i",
    $id_usuario
);

$stmt_fincas->execute();

$res_fincas =
    $stmt_fincas->get_result();

$fincas =
    $res_fincas->fetch_all(
        MYSQLI_ASSOC
    );


/* =========================================================
   DETERMINAR FINCA ACTIVA
   ========================================================= */

$id_finca = 0;


if (
    isset($_REQUEST['id_finca']) &&
    intval($_REQUEST['id_finca']) > 0
) {

    $id_finca =
        intval($_REQUEST['id_finca']);

} elseif (!empty($fincas)) {

    $id_finca =
        (int) $fincas[0]['id_finca'];

}


/* =========================================================
   FUNCIÓN PARA COMPROBAR PERMISO DE REPORTES EN FINCA
   ========================================================= */

function tienePermisoVerReportes(
    mysqli $con,
    int $id_mayordomo,
    int $id_finca,
    string $rol_usuario
): bool {

    /*
     * El caficultor puede consultar sus propios datos.
     */

    if ($rol_usuario !== 'mayordomo') {

        return true;

    }


    if ($id_mayordomo <= 0 || $id_finca <= 0) {

        return false;

    }


    $sql = "
        SELECT ver_reportes
        FROM permisos_mayordomo
        WHERE id_mayordomo = ?
        AND id_finca = ?
        LIMIT 1
    ";

    $stmt =
        $con->prepare($sql);

    $stmt->bind_param(
        "ii",
        $id_mayordomo,
        $id_finca
    );

    $stmt->execute();

    $res =
        $stmt->get_result();


    if ($permiso = $res->fetch_assoc()) {

        return (
            (int)$permiso['ver_reportes'] === 1
        );

    }


    return false;

}


/* =========================================================
   PROCESAR CAMBIO DE ESTADO DE PAGO
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset(
        $_POST['id_recoleccion'],
        $_POST['nuevo_estado']
    )
) {

    $id_recoleccion =
        intval($_POST['id_recoleccion']);

    $nuevo_estado =
        $_POST['nuevo_estado'] === 'pagado'
        ? 'pagado'
        : 'pendiente';


    /*
     * Obtener finca de la recolección
     */

    $sql_recoleccion =
        "
        SELECT
            id_finca
        FROM recolecciones
        WHERE id_recoleccion = ?
        LIMIT 1
        ";

    $stmt_recoleccion =
        $con->prepare(
            $sql_recoleccion
        );

    $stmt_recoleccion->bind_param(
        "i",
        $id_recoleccion
    );

    $stmt_recoleccion->execute();

    $res_recoleccion =
        $stmt_recoleccion->get_result();


    if (
        !($row_recoleccion =
        $res_recoleccion->fetch_assoc())
    ) {

        die("
            <script>
                alert('La recolección no existe.');
                window.location.href =
                'recolectores_m.php';
            </script>
        ");

    }


    $id_finca_recoleccion =
        (int)
        $row_recoleccion['id_finca'];


    /*
     * COMPROBAR PERMISO
     */

    $puede_ver_reportes =
        tienePermisoVerReportes(
            $con,
            $id_usuario,
            $id_finca_recoleccion,
            $rol_usuario
        );


    if (!$puede_ver_reportes) {

        die("
            <script>
                alert(
                    'No tienes permiso para ver reportes ni modificar información de recolecciones.'
                );
                window.location.href =
                    'recolectores_m.php';
            </script>
        ");

    }


    /*
     * ACTUALIZAR
     */

    $sql_update = "
        UPDATE recolecciones
        SET estado_pago = ?
        WHERE id_recoleccion = ?
        AND id_finca = ?
    ";


    $stmt_u =
        $con->prepare(
            $sql_update
        );


    $stmt_u->bind_param(
        "sii",
        $nuevo_estado,
        $id_recoleccion,
        $id_finca_recoleccion
    );


    $stmt_u->execute();

    $stmt_u->close();


    $recolector_sel =
        isset($_POST['recolector_sel'])
        ? intval($_POST['recolector_sel'])
        : 0;


    if ($recolector_sel > 0) {

        header(
            "Location: recolectores_m.php?id=" .
            $recolector_sel .
            "#tablaPendientes"
        );

    } else {

        header(
            "Location: recolectores_m.php"
        );

    }

    exit();

}


/* =========================================================
   OBTENER RECOLECTOR SELECCIONADO
   ========================================================= */

$id_recolector_sel =
    isset($_GET['id'])
    ? intval($_GET['id'])
    : 0;


/* =========================================================
   VARIABLES
   ========================================================= */

$res_trabajadores = null;
$datos_recolector = null;
$res_pendientes = null;

$puede_ver_recolector = false;


/* =========================================================
   CONSULTA DE LISTA GENERAL
   =========================================================

   Para un mayordomo solo se muestran recolectores de las
   fincas donde tenga ver_reportes = 1.

   Esto evita mostrar reportes de fincas sin permiso.
   ========================================================= */

if ($tiene_permiso_general) {


    if ($rol_usuario === 'mayordomo') {

        $sql_trabajadores = "

            SELECT

                u.id_usuario,

                u.nombres,

                u.apellidos,

                u.documento,

                u.telefono,

                u.estado,

                f.id_finca,

                f.nombre AS nombre_finca,

                f.ubicacion_vereda

            FROM recolectores r

            INNER JOIN usuarios u
                ON r.id_usuario = u.id_usuario

            INNER JOIN fincas f
                ON r.id_finca = f.id_finca

            INNER JOIN permisos_mayordomo pm
                ON pm.id_finca = f.id_finca

            WHERE u.rol = 'recolector'

            AND pm.id_mayordomo = ?

            AND pm.ver_reportes = 1

            ORDER BY u.nombres ASC

        ";


        $stmt_trabajadores =
            $con->prepare(
                $sql_trabajadores
            );


        $stmt_trabajadores->bind_param(
            "i",
            $id_usuario
        );


        $stmt_trabajadores->execute();


        $res_trabajadores =
            $stmt_trabajadores->get_result();


    } else {


        /*
         * Caficultor
         */

        $sql_trabajadores = "

            SELECT

                u.id_usuario,

                u.nombres,

                u.apellidos,

                u.documento,

                u.telefono,

                u.estado,

                f.id_finca,

                f.nombre AS nombre_finca,

                f.ubicacion_vereda

            FROM recolectores r

            INNER JOIN usuarios u
                ON r.id_usuario = u.id_usuario

            INNER JOIN fincas f
                ON r.id_finca = f.id_finca

            WHERE u.rol = 'recolector'

            AND f.id_caficultor = ?

            ORDER BY u.nombres ASC

        ";


        $stmt_trabajadores =
            $con->prepare(
                $sql_trabajadores
            );


        $stmt_trabajadores->bind_param(
            "i",
            $id_usuario
        );


        $stmt_trabajadores->execute();


        $res_trabajadores =
            $stmt_trabajadores->get_result();

    }

}


/* =========================================================
   CONSULTAR RECOLECTOR SELECCIONADO
   ========================================================= */

if (
    $tiene_permiso_general &&
    $id_recolector_sel > 0
) {


    /*
     * Obtener datos del recolector y su finca
     */

    $sql_rec = "

        SELECT

            u.id_usuario,

            u.nombres,

            u.apellidos,

            u.documento,

            f.id_finca,

            f.nombre AS nombre_finca

        FROM recolectores r

        INNER JOIN usuarios u
            ON r.id_usuario = u.id_usuario

        INNER JOIN fincas f
            ON r.id_finca = f.id_finca

        WHERE u.id_usuario = ?

        LIMIT 1

    ";


    $stmt_r =
        $con->prepare(
            $sql_rec
        );


    $stmt_r->bind_param(
        "i",
        $id_recolector_sel
    );


    $stmt_r->execute();


    $datos_recolector =
        $stmt_r
        ->get_result()
        ->fetch_assoc();


    if ($datos_recolector) {


        $id_finca_recolector =
            (int)
            $datos_recolector['id_finca'];


        /*
         * VERIFICAR PERMISO EN ESA FINCA
         */

        $puede_ver_recolector =
            tienePermisoVerReportes(
                $con,
                $id_usuario,
                $id_finca_recolector,
                $rol_usuario
            );


        if (!$puede_ver_recolector) {

            $datos_recolector = null;

        }

    }


    /* =====================================================
       OBTENER PENDIENTES
       ===================================================== */

    if ($puede_ver_recolector) {


        $sql_pendientes = "

            SELECT

                id_recoleccion,

                DATE(fecha) AS fecha,

                TIME(fecha) AS hora,

                kilos_recolectados,

                pago_calculado,

                estado_pago

            FROM recolecciones

            WHERE id_recolector = ?

            AND id_finca = ?

            AND estado_pago = 'pendiente'

            ORDER BY fecha DESC

        ";


        $stmt_p =
            $con->prepare(
                $sql_pendientes
            );


        $stmt_p->bind_param(

            "ii",

            $id_recolector_sel,

            $id_finca_recolector

        );


        $stmt_p->execute();


        $res_pendientes =
            $stmt_p->get_result();

    }

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Reportes - Gestor de Cuentas
</title>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<script
    src="https://unpkg.com/lucide@latest"
></script>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>


<style>

:root {

    --bg-main:#f5f0eb;

    --bg-sidebar:#1b3818;

    --bg-card:#ffffff;

    --bg-card-inner:#eef3ec;

    --primary-brown:#244b20;

    --primary-dark:#173517;

    --sidebar-active:#445c42;

    --text-dark:#1f1f1f;

    --text-muted:#5d5d5d;

    --border-color:#d8cec4;

    --font-family:'Plus Jakarta Sans',sans-serif;

}


/* =========================================================
   GENERAL
   ========================================================= */

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

}


/* =========================================================
   HEADER
   ========================================================= */

.top-header {

    background-color:#244b20;

    color:#fff;

    height:78px;

    padding:0 28px;

    display:flex;

    align-items:center;

    justify-content:space-between;

}


.brand {

    display:flex;

    align-items:center;

    gap:14px;

    width:280px;

}


.brand-logo {

    width:48px;

    height:48px;

    background:#fff;

    color:#244b20;

    border-radius:50%;

    display:flex;

    justify-content:center;

    align-items:center;

}


.brand-text h1 {

    font-size:21px;

    font-weight:700;

}


.header-center {

    display:flex;

    align-items:center;

    flex:1;

    padding-left:20px;

}


.user-welcome h2 {

    font-size:21px;

    font-weight:600;

}


.user-welcome p {

    font-size:16px;

    opacity:.8;

}


.header-actions {

    display:flex;

    align-items:center;

    gap:20px;

}


.user-profile {

    text-decoration:none;

}


.avatar {

    width:50px;

    height:50px;

    background:#fff;

    color:#244b20;

    border-radius:50%;

    overflow:hidden;

    display:flex;

    justify-content:center;

    align-items:center;

}


.avatar img {

    width:100%;

    height:100%;

    object-fit:cover;

    border-radius:50%;

}


/* =========================================================
   LAYOUT
   ========================================================= */

.app-layout {

    display:flex;

    flex:1;

    width:100%;

}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {

    width:290px;

    background:#1b3818;

    padding:28px 18px;

    display:flex;

    flex-direction:column;

    flex-shrink:0;

}


.nav-list {

    list-style:none;

    display:flex;

    flex-direction:column;

    gap:10px;

}


.nav-item a {

    display:flex;

    align-items:center;

    gap:14px;

    padding:14px 18px;

    border-radius:10px;

    color:#fff;

    text-decoration:none;

    font-size:16px;

    font-weight:600;

    transition:.2s;

}


.nav-item.active a {

    background:#445c42;

    color:#fff;

    box-shadow:inset 3px 0 0 #fff;

}


.nav-item:not(.active) a:hover {

    background:rgba(255,255,255,.10);

}


/* =========================================================
   MAIN
   ========================================================= */

.main-content {

    flex:1;

    padding:36px 44px;

    display:flex;

    flex-direction:column;

    gap:28px;

    width:calc(100% - 250px);

}


/* =========================================================
   CARDS
   ========================================================= */

.card {

    background:#fff;

    border-radius:16px;

    border:1px solid var(--border-color);

    padding:30px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.03);

}


/* =========================================================
   AVISO SIN PERMISO
   ========================================================= */

.permission-denied {

    background:#fff3cd;

    border:2px solid #e0b341;

    border-radius:14px;

    padding:22px 25px;

    display:flex;

    align-items:center;

    gap:18px;

    margin-bottom:5px;

    animation:mostrarAviso .3s ease;

}


.permission-icon {

    width:55px;

    height:55px;

    border-radius:50%;

    background:#e0b341;

    color:#fff;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    font-size:26px;

}


.permission-content h3 {

    color:#795548;

    font-size:21px;

    font-weight:800;

    margin-bottom:5px;

}


.permission-content p {

    color:#5d4037;

    font-size:16px;

    font-weight:600;

}


/* =========================================================
   BLOQUEO VISUAL
   ========================================================= */

.interfaz-bloqueada {

    opacity:.55;

    pointer-events:none;

    user-select:none;

}


input:disabled,

button:disabled,

select:disabled,

a.bloqueado {

    cursor:not-allowed !important;

    opacity:.55 !important;

}


@keyframes mostrarAviso {

    from {

        opacity:0;

        transform:translateY(-10px);

    }

    to {

        opacity:1;

        transform:translateY(0);

    }

}


/* =========================================================
   SEARCH
   ========================================================= */

.search-container {

    display:flex;

    gap:10px;

    margin-bottom:20px;

}


.search-box {

    position:relative;

    flex:1;

}


.search-box input {

    width:100%;

    padding:12px 14px 12px 40px;

    border-radius:10px;

    border:1px solid var(--border-color);

    font-size:16px;

    outline:none;

    background:#fff;

}


.search-box i {

    position:absolute;

    left:14px;

    top:50%;

    transform:translateY(-50%);

    color:var(--text-muted);

}


.btn-search {

    background:#244b20;

    color:#fff;

    border:none;

    padding:0 28px;

    border-radius:10px;

    font-weight:600;

    font-size:16px;

    cursor:pointer;

}


/* =========================================================
   HEADER SECTION
   ========================================================= */

.header-section {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:15px;

}


.btn-new {

    background:#244b20;

    color:#fff;

    border:none;

    padding:13px 20px;

    border-radius:10px;

    font-weight:600;

    font-size:16px;

    cursor:pointer;

    display:flex;

    align-items:center;

    gap:10px;

    text-decoration:none;

}


/* =========================================================
   TABLE
   ========================================================= */

.table-custom {

    width:100%;

    border-collapse:collapse;

    font-size:16px;

}


.table-custom th {

    background:#edf1ea;

    color:var(--text-muted);

    font-weight:700;

    text-align:left;

    padding:20px;

    border-bottom:2px solid var(--border-color);

}


.table-custom td {

    padding:17px 16px;

    border-bottom:1px solid var(--border-color);

    vertical-align:middle;

}


.badge-status {

    background:#d4ebd0;

    color:#244b20;

    padding:4px 12px;

    border-radius:16px;

    font-weight:700;

    font-size:14px;

}


/* =========================================================
   BOTONES
   ========================================================= */

.btn-report-action,

.btn-print-action,

.btn-pay-action {

    background:#244b20;

    color:#fff;

    text-decoration:none;

    border:none;

    border-radius:6px;

    font-weight:700;

    cursor:pointer;

}


.btn-report-action {

    padding:16px 20px;

    font-size:16px;

    display:inline-flex;

    align-items:center;

    gap:10px;

}


.btn-pay-action {

    padding:10px 16px;

    font-size:14px;

}


.btn-print-action {

    padding:13px 20px;

    font-size:16px;

    display:inline-flex;

    align-items:center;

    gap:8px;

}


/* =========================================================
   INFO
   ========================================================= */

.info-grid {

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:14px;

    background:#eef3ec;

    padding:14px 18px;

    border-radius:10px;

    margin-bottom:20px;

    font-size:16px;

}


.info-banner {

    background:#fcfaf7;

    border:1px solid var(--border-color);

    border-radius:10px;

    padding:16px 20px;

    font-size:16px;

    color:var(--text-muted);

    display:flex;

    align-items:center;

    gap:8px;

}


/* =========================================================
   FOOTER
   ========================================================= */

.app-footer {

    text-align:center;

    padding:20px;

    font-size:14px;

    color:var(--text-muted);

    display:flex;

    align-items:center;

    justify-content:center;

    gap:10px;

    background:#efe8e0;

    border-top:1px solid var(--border-color);

}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width:992px) {

    .main-content {

        padding:20px;

    }

    .info-grid {

        grid-template-columns:1fr;

    }

}


@media (max-width:750px) {

    .app-layout {

        flex-direction:column;

    }

    .sidebar {

        width:100%;

    }

    .main-content {

        width:100%;

    }

    .header-section {

        flex-direction:column;

        align-items:flex-start;

        gap:15px;

    }

    .search-container {

        flex-direction:column;

    }

}



/* =========================================================
   ALTA VISIBILIDAD PARA PERSONAS MAYORES
   ========================================================= */
html {
    font-size:18px;
    scroll-behavior:smooth;
}

body {
    font-size:18px;
    line-height:1.6;
}

.nav-item a {
    min-height:58px;
    font-size:18px !important;
    font-weight:700;
}

.nav-item a i {
    width:28px;
    min-width:28px;
}

.user-welcome h2 {
    font-size:22px !important;
    font-weight:800;
}

.user-welcome p {
    font-size:16px !important;
}

.card {
    box-shadow:0 4px 14px rgba(0,0,0,.08);
}

.search-box input {
    min-height:56px;
    font-size:18px !important;
    font-weight:500;
}

.btn-search, .btn-new, .btn-report-action, .btn-print-action, .btn-pay-action {
    min-height:52px;
    font-size:17px !important;
    font-weight:800;
    padding:12px 20px !important;
}

.table-custom {
    font-size:17px !important;
}

.table-custom th {
    font-size:16px !important;
    font-weight:800;
    padding:17px 16px !important;
}

.table-custom td {
    font-size:17px !important;
    padding:18px 16px !important;
}

.badge-status {
    font-size:15px !important;
    padding:8px 14px !important;
}

.info-banner, .permission-content p {
    font-size:16px !important;
}

.info-grid {
    font-size:16px !important;
    line-height:1.7;
}

.header-section h2 {
    font-size:24px !important;
}

.header-section p {
    font-size:16px !important;
}

input:focus, button:focus, a:focus {
    outline:4px solid #f2c94c !important;
    outline-offset:3px;
}

@media (max-width:750px) {
    .top-header {
        height:auto;
        min-height:78px;
        padding:14px 18px;
        gap:12px;
    }

    .brand {
        width:auto;
    }

    .brand-text h1 {
        font-size:20px !important;
    }

    .header-center {
        display:none;
    }

    .sidebar {
        padding:16px;
    }

    .main-content {
        padding:22px 16px;
    }

    .table-custom {
        min-width:850px;
    }

    .search-container {
        gap:14px;
    }
}
</style>

</head>


<body>


<!-- =======================================================
     HEADER
     ======================================================== -->

<header class="top-header">


    <div class="brand">

        <div class="brand-logo">

            <i
                data-lucide="coffee"
                size="18"
            ></i>

        </div>


        <div class="brand-text">

            <h1>
                Café control
            </h1>

        </div>

    </div>


    <div class="header-center">

        <div class="user-welcome">

            <h2>

                Bienvenido,
                <?php
                echo htmlspecialchars(
                    $nombre_caficultor,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </h2>

            <p>
                Administra tus fincas y consulta su información
            </p>

        </div>

    </div>


    <div class="header-actions">

        <a
            href="panel_m.php"
            class="user-profile"
            title="Ver perfil"
        >

            <div class="avatar">

                <?php if (!empty($foto_perfil)): ?>

                    <img
                        src="<?php
                            echo htmlspecialchars(
                                $foto_perfil,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>?v=<?php echo time(); ?>"
                        alt="Foto de perfil"
                    >

                <?php else: ?>

                    <i
                        data-lucide="user"
                        size="22"
                    ></i>

                <?php endif; ?>

            </div>

        </a>

    </div>


</header>



<!-- =======================================================
     LAYOUT
     ======================================================== -->

<div class="app-layout">


    <!-- ===================================================
         SIDEBAR
         ==================================================== -->

    <aside class="sidebar">

        <ul class="nav-list">


            <li class="nav-item">

                <a href="panel_m.php">

                    <i
                        data-lucide="layout-dashboard"
                        size="18"
                    ></i>

                    Perfil

                </a>

            </li>


            <li class="nav-item">

                <a href="recolecciones_m.php">

                    <i
                        data-lucide="archive"
                        size="18"
                    ></i>

                    Recolecciones

                </a>

            </li>


            <li class="nav-item active">

                <a href="recolectores_m.php">

                    <i
                        data-lucide="users"
                        size="18"
                    ></i>

                    Trabajadores

                </a>

            </li>


            <li class="nav-item">

                <a href="resumen_m.php">

                    <i
                        data-lucide="bar-chart-2"
                        size="18"
                    ></i>

                    Resumen General

                </a>

            </li>


            <li class="nav-item">

                <a href="masignar_m.php">

                    <i
                        class="fa-solid fa-user-gear"
                    ></i>

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

                    <i
                        data-lucide="log-out"
                        size="18"
                    ></i>

                    Cerrar Sesión

                </a>

            </li>


        </ul>

    </aside>



    <!-- ===================================================
         CONTENIDO
         ==================================================== -->

    <main class="main-content">


        <?php if (!$tiene_permiso_general): ?>


            <!-- =============================================
                 AVISO
                 ============================================== -->

            <div class="permission-denied">


                <div class="permission-icon">

                    <i class="fa-solid fa-lock"></i>

                </div>


                <div class="permission-content">

                    <h3>
                        Sin permiso para ver reportes
                    </h3>


                    <p>

                        El caficultor debe darte el permiso
                        para que puedas consultar los reportes
                        y la información de los recolectores.

                    </p>

                </div>


            </div>


        <?php endif; ?>



        <!-- =================================================
             CONTENIDO QUE SE BLOQUEA
             ================================================== -->

        <div
            id="contenido-reportes"
            class="<?php
                echo !$tiene_permiso_general
                    ? 'interfaz-bloqueada'
                    : '';
            ?>"
        >


            <!-- =============================================
                 BUSCADOR Y LISTADO
                 ============================================== -->

            <div class="card">


                <div class="search-container">


                    <div class="search-box">


                        <i
                            data-lucide="search"
                            size="18"
                        ></i>


                        <input
                            type="text"
                            id="inputBuscar"
                            placeholder="Buscar por número de cédula o nombre..."
                            <?php
                            echo !$tiene_permiso_general
                                ? 'disabled'
                                : '';
                            ?>
                        >


                    </div>


                    <button
                        class="btn-search"
                        id="btn-buscar"
                        type="button"
                        <?php
                        echo !$tiene_permiso_general
                            ? 'disabled'
                            : '';
                        ?>
                    >

                        Buscar

                    </button>


                </div>



                <div class="header-section">


                    <div>

                        <h2
                            style="
                                font-size:18px;
                                font-weight:700;
                            "
                        >

                            <i
                                data-lucide="users"
                                size="20"
                            ></i>

                            Gestionar recolectores

                        </h2>


                        <p
                            style="
                                font-size:12px;
                                color:var(--text-muted);
                            "
                        >

                            Administre los recolectores
                            registrados en el sistema.

                        </p>


                    </div>


                    <a
                        href="agregar_r.php"
                        class="btn-new
                        <?php
                        echo !$tiene_permiso_general
                            ? 'bloqueado'
                            : '';
                        ?>"
                        <?php
                        if (!$tiene_permiso_general) {
                            echo 'style="pointer-events:none;"';
                        }
                        ?>
                    >

                        <i
                            data-lucide="plus"
                            size="16"
                        ></i>

                        Agregar recolector

                    </a>


                </div>



                <table
                    class="table-custom"
                    id="tablaTrabajadores"
                >


                    <thead>

                        <tr>

                            <th>#</th>

                            <th>
                                Nombre completo
                            </th>

                            <th>
                                Documento
                            </th>

                            <th>
                                Teléfono
                            </th>

                            <th>
                                Vereda / Finca
                            </th>

                            <th>
                                Estado
                            </th>

                            <th
                                style="
                                    text-align:right;
                                "
                            >
                                Reportes
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php

                        if (
                            $res_trabajadores &&
                            $res_trabajadores->num_rows > 0
                        ):

                            $i = 1;

                        ?>


                            <?php
                            while (
                                $t =
                                $res_trabajadores
                                ->fetch_assoc()
                            ):
                            ?>


                                <tr>


                                    <td>

                                        <?php
                                        echo $i++;
                                        ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php

                                            echo htmlspecialchars(

                                                $t['nombres'] .
                                                ' ' .
                                                $t['apellidos'],

                                                ENT_QUOTES,
                                                'UTF-8'

                                            );

                                            ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(

                                            $t['documento'],

                                            ENT_QUOTES,
                                            'UTF-8'

                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(

                                            $t['telefono'],

                                            ENT_QUOTES,
                                            'UTF-8'

                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(

                                            (
                                                $t[
                                                    'ubicacion_vereda'
                                                ]
                                                ? $t[
                                                    'ubicacion_vereda'
                                                ] . ' / '
                                                : ''
                                            )
                                            .
                                            $t[
                                                'nombre_finca'
                                            ],

                                            ENT_QUOTES,
                                            'UTF-8'

                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <span
                                            class="badge-status"
                                        >

                                            <?php

                                            echo ucfirst(
                                                $t['estado']
                                                ?? 'Activo'
                                            );

                                            ?>

                                        </span>

                                    </td>


                                    <td
                                        style="
                                            text-align:right;
                                        "
                                    >


                                        <a
                                            href="recolectores_m.php?id=<?php
                                                echo $t[
                                                    'id_usuario'
                                                ];
                                            ?>#tablaPendientes"
                                            class="btn-report-action"
                                        >

                                            <i
                                                data-lucide="file-text"
                                                size="14"
                                            ></i>

                                            Ver Reporte

                                        </a>


                                    </td>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="7"
                                    style="
                                        text-align:center;
                                        padding:20px;
                                    "
                                >

                                    <?php
                                    if (!$tiene_permiso_general):
                                    ?>

                                        No tienes permiso para consultar
                                        los reportes.

                                    <?php
                                    else:
                                    ?>

                                        No hay recolectores registrados.

                                    <?php
                                    endif;
                                    ?>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>


                </table>


            </div>



            <!-- =============================================
                 AVISO
                 ============================================== -->

            <div class="info-banner">


                <i
                    data-lucide="info"
                    size="16"
                ></i>


                <span>

                    <strong>
                        Nota:
                    </strong>

                    Para consultar los reportes de los
                    recolectores debes tener el permiso
                    <strong>
                        “Ver reportes”.
                    </strong>

                </span>


            </div>



            <!-- =============================================
                 REPORTE DEL RECOLECTOR
                 ============================================== -->

            <?php if ($datos_recolector): ?>


                <div
                    class="card"
                    id="tablaPendientes"
                >


                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-bottom:15px;
                        "
                    >


                        <h3
                            style="
                                color:var(--primary-brown);
                                display:flex;
                                align-items:center;
                                gap:8px;
                                font-size:18px;
                            "
                        >

                            <i
                                data-lucide="file-check"
                                size="22"
                            ></i>

                            Entregas Pendientes por Pagar

                        </h3>


                        <a
                            href="reporte_m.php?id=<?php
                                echo $id_recolector_sel;
                            ?>"
                            class="btn-print-action"
                        >

                            <i
                                data-lucide="printer"
                                size="16"
                            ></i>

                            Generar Reporte Imprimible

                        </a>


                    </div>



                    <div class="info-grid">


                        <div>

                            <strong>
                                Recolector:
                            </strong>

                            <?php

                            echo htmlspecialchars(

                                $datos_recolector[
                                    'nombres'
                                ]
                                . ' '
                                .
                                $datos_recolector[
                                    'apellidos'
                                ],

                                ENT_QUOTES,
                                'UTF-8'

                            );

                            ?>

                        </div>


                        <div>

                            <strong>
                                Cédula:
                            </strong>

                            <?php

                            echo htmlspecialchars(

                                $datos_recolector[
                                    'documento'
                                ],

                                ENT_QUOTES,
                                'UTF-8'

                            );

                            ?>

                        </div>


                        <div>

                            <strong>
                                Finca:
                            </strong>

                            <?php

                            echo htmlspecialchars(

                                $datos_recolector[
                                    'nombre_finca'
                                ],

                                ENT_QUOTES,
                                'UTF-8'

                            );

                            ?>

                        </div>


                        <div>

                            <strong>
                                Fecha Consulta:
                            </strong>

                            <?php
                            echo date('Y-m-d');
                            ?>

                        </div>


                    </div>



                    <table class="table-custom">


                        <thead>

                            <tr>

                                <th>
                                    # Entrega
                                </th>

                                <th>
                                    Fecha
                                </th>

                                <th>
                                    Hora
                                </th>

                                <th>
                                    Kilos Pendientes
                                </th>

                                <th>
                                    Subtotal por Pagar
                                </th>

                                <th
                                    style="
                                        text-align:center;
                                    "
                                >
                                    Acción
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php

                            $sum_kilos = 0;

                            $sum_pago = 0;


                            if (
                                $res_pendientes &&
                                $res_pendientes->num_rows > 0
                            ):

                                while (
                                    $p =
                                    $res_pendientes
                                    ->fetch_assoc()
                                ):


                                    $sum_kilos +=
                                        $p[
                                            'kilos_recolectados'
                                        ];


                                    $sum_pago +=
                                        $p[
                                            'pago_calculado'
                                        ];

                            ?>


                                <tr>


                                    <td>

                                        #
                                        <?php
                                        echo $p[
                                            'id_recoleccion'
                                        ];
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo $p['fecha'];
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo $p['hora'];
                                        ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php

                                            echo number_format(

                                                $p[
                                                    'kilos_recolectados'
                                                ],

                                                2,
                                                '.',
                                                ''

                                            );

                                            ?>

                                            Kg

                                        </strong>

                                    </td>


                                    <td>

                                        $

                                        <?php

                                        echo number_format(

                                            $p[
                                                'pago_calculado'
                                            ],

                                            0,
                                            ',',
                                            '.'

                                        );

                                        ?>

                                    </td>


                                    <td
                                        style="
                                            text-align:center;
                                        "
                                    >


                                        <form
                                            action="recolectores_m.php"
                                            method="POST"
                                            style="
                                                display:inline;
                                            "
                                        >


                                            <input
                                                type="hidden"
                                                name="id_recoleccion"
                                                value="<?php
                                                    echo $p[
                                                        'id_recoleccion'
                                                    ];
                                                ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="recolector_sel"
                                                value="<?php
                                                    echo $id_recolector_sel;
                                                ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="nuevo_estado"
                                                value="pagado"
                                            >


                                            <button
                                                type="submit"
                                                class="btn-pay-action"
                                            >

                                                Marcar Pagado

                                            </button>


                                        </form>


                                    </td>


                                </tr>


                            <?php

                                endwhile;

                            ?>


                                <tr
                                    style="
                                        font-weight:bold;
                                        background-color:#edf1ea;
                                    "
                                >

                                    <td colspan="3">

                                        TOTAL PENDIENTE DE PAGO

                                    </td>


                                    <td>

                                        <?php

                                        echo number_format(

                                            $sum_kilos,

                                            2,
                                            '.',
                                            ''

                                        );

                                        ?>

                                        Kg

                                    </td>


                                    <td>

                                        $

                                        <?php

                                        echo number_format(

                                            $sum_pago,

                                            0,
                                            ',',
                                            '.'

                                        );

                                        ?>

                                    </td>


                                    <td></td>


                                </tr>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="6"
                                        style="
                                            text-align:center;
                                            padding:20px;
                                            color:
                                                var(--text-muted);
                                        "
                                    >

                                        <strong>
                                            ¡Sin cuentas pendientes!
                                        </strong>

                                        Todas las entregas registradas
                                        de este recolector han sido pagadas.

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </div>


    </main>


</div>



<!-- =======================================================
     FOOTER
     ======================================================== -->

<footer class="app-footer">


    <i
        data-lucide="coffee"
        size="14"
    ></i>


    <span>

        <strong>
            Café control
        </strong>

        © 2026 Developers:
        Gloria Tatiana Sanchez &
        Tatiana Silva Penagos

    </span>


</footer>



<script>

/* =========================================================
   ICONOS
   ========================================================= */

lucide.createIcons();


/* =========================================================
   PERMISO
   ========================================================= */

const tienePermisoReportes =
    <?php
    echo $tiene_permiso_general
        ? 'true'
        : 'false';
    ?>;


/* =========================================================
   BUSCAR TABLA
   ========================================================= */

function filtrarTabla() {


    if (!tienePermisoReportes) {

        alert(
            'No tienes permiso para ver reportes.'
        );

        return;

    }


    const input =
        document.getElementById(
            "inputBuscar"
        );


    const filter =
        input.value.toLowerCase();


    const tabla =
        document.getElementById(
            "tablaTrabajadores"
        );


    if (!tabla) {
        return;
    }


    const tbody =
        tabla.getElementsByTagName(
            "tbody"
        )[0];


    const filas =
        tbody.getElementsByTagName(
            "tr"
        );


    for (
        let i = 0;
        i < filas.length;
        i++
    ) {


        const textoFila =
            filas[i]
            .textContent
            .toLowerCase();


        filas[i].style.display =
            textoFila.includes(filter)
            ? ""
            : "none";

    }

}


/* =========================================================
   BOTÓN BUSCAR
   ========================================================= */

const botonBuscar =
    document.getElementById(
        'btn-buscar'
    );


if (botonBuscar) {


    botonBuscar.addEventListener(
        'click',
        function() {

            if (!tienePermisoReportes) {

                alert(
                    'No tienes permiso para ver reportes. El caficultor debe habilitar el permiso.'
                );

                return;

            }

            filtrarTabla();

        }
    );

}


/* =========================================================
   BLOQUEAR ENTRADA
   ========================================================= */

if (!tienePermisoReportes) {


    const inputBuscar =
        document.getElementById(
            'inputBuscar'
        );


    if (inputBuscar) {

        inputBuscar.disabled = true;

    }


    if (botonBuscar) {

        botonBuscar.disabled = true;

    }


    /*
     * Deshabilitar formularios
     */

    document
        .querySelectorAll(
            '#contenido-reportes button'
        )
        .forEach(
            function(boton) {

                boton.disabled = true;

            }
        );


    /*
     * Bloquear enlaces de reportes
     */

    document
        .querySelectorAll(
            '#contenido-reportes a'
        )
        .forEach(
            function(enlace) {

                enlace.style.pointerEvents =
                    'none';

                enlace.style.cursor =
                    'not-allowed';

            }
        );


    /*
     * Evitar Enter en búsqueda
     */

    document
        .addEventListener(
            'keydown',
            function(evento) {

                if (
                    evento.key === 'Enter'
                ) {

                    evento.preventDefault();

                }

            }
        );

}

</script>


</body>

</html>