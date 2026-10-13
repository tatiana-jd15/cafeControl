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

$id_usuario_sesion = (int) $_SESSION['id_usuario'];


/* =========================================================
   OBTENER DATOS DEL USUARIO ACTUAL
   ========================================================= */

$sql_user = "
    SELECT
        id_usuario,
        nombres,
        apellidos,
        foto_perfil,
        rol
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
";

$stmt_user = $con->prepare($sql_user);

$stmt_user->bind_param(
    "i",
    $id_usuario_sesion
);

$stmt_user->execute();

$res_user = $stmt_user->get_result();


if ($row_user = $res_user->fetch_assoc()) {

    $nombre_usuario =
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
   VERIFICAR PERMISO GESTIONAR MAYORDOMOS
   =========================================================

   IMPORTANTE:

   - Si es CAFICULTOR: tiene acceso a esta interfaz.
   - Si es MAYORDOMO: se revisa gestionar_mayordomos.
   - Si gestionar_mayordomos = 0: se bloquea todo.
   ========================================================= */

$tiene_permiso_gestionar = true;


/* =========================================================
   SI EL USUARIO ES MAYORDOMO
   ========================================================= */

if ($rol_usuario === 'mayordomo') {

    $tiene_permiso_gestionar = false;


    $sql_permiso = "
        SELECT gestionar_mayordomos
        FROM permisos_mayordomo
        WHERE id_mayordomo = ?
        AND gestionar_mayordomos = 1
        LIMIT 1
    ";


    $stmt_permiso =
        $con->prepare($sql_permiso);


    $stmt_permiso->bind_param(
        "i",
        $id_usuario_sesion
    );


    $stmt_permiso->execute();


    $res_permiso =
        $stmt_permiso->get_result();


    if ($res_permiso->fetch_assoc()) {

        $tiene_permiso_gestionar = true;

    }

}


/* =========================================================
   VARIABLES
   ========================================================= */

$doc_buscado = '';
$nombre_m = '';
$telefono_m = '';
$correo_m = '';
$id_mayordomo_encontrado = '';

$mensaje_alerta = '';
$tipo_alerta = '';


/* =========================================================
   SI NO TIENE PERMISO
   ========================================================= */

if (!$tiene_permiso_gestionar) {

    $mensaje_alerta =
        'No tienes permiso para gestionar mayordomos. El caficultor debe darte el permiso para utilizar esta opción.';

    $tipo_alerta = 'danger';

}


/* =========================================================
   CARGAR FINCAS DEL CAFICULTOR
   ========================================================= */

$fincas_caficultor = [];


if ($tiene_permiso_gestionar) {

    $sql_fincas = "
        SELECT
            id_finca,
            nombre
        FROM fincas
        WHERE id_caficultor = ?
        ORDER BY nombre ASC
    ";


    $stmt_fincas =
        $con->prepare($sql_fincas);


    $stmt_fincas->bind_param(
        "i",
        $id_usuario_sesion
    );


    $stmt_fincas->execute();


    $res_fincas =
        $stmt_fincas->get_result();


    while (
        $finca =
        $res_fincas->fetch_assoc()
    ) {

        $fincas_caficultor[] =
            $finca;

    }

}


/* =========================================================
   RECUPERAR VALORES DEL FORMULARIO
   ========================================================= */

if ($tiene_permiso_gestionar) {

    $doc_buscado =
        trim(
            $_POST['documento'] ?? ''
        );

    $nombre_m =
        trim(
            $_POST['nombre_m'] ?? ''
        );

    $telefono_m =
        trim(
            $_POST['telefono_m'] ?? ''
        );

    $correo_m =
        trim(
            $_POST['correo_m'] ?? ''
        );

    $id_mayordomo_encontrado =
        trim(
            $_POST['id_mayordomo_encontrado']
            ?? ''
        );

}


/* =========================================================
   PROCESAR BÚSQUEDA POR DOCUMENTO
   ========================================================= */

if (
    $tiene_permiso_gestionar &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['buscar_documento'])
) {


    $doc_buscado =
        trim(
            $_POST['documento'] ?? ''
        );


    if ($doc_buscado === '') {

        $mensaje_alerta =
            'Ingresa el número de documento.';

        $tipo_alerta =
            'danger';

    } else {


        $stmt_m =
            $con->prepare(
                "
                SELECT
                    id_usuario,
                    nombres,
                    apellidos,
                    telefono,
                    correo,
                    rol,
                    estado
                FROM usuarios
                WHERE documento = ?
                LIMIT 1
                "
            );


        $stmt_m->bind_param(
            "s",
            $doc_buscado
        );


        $stmt_m->execute();


        $res_m =
            $stmt_m->get_result();


        if (
            $row_m =
            $res_m->fetch_assoc()
        ) {


            /* =========================================
               VERIFICAR QUE SEA UN USUARIO VÁLIDO
               ========================================= */

            if (
                $row_m['estado'] !== 'activo'
            ) {

                $mensaje_alerta =
                    'El usuario encontrado está inactivo.';

                $tipo_alerta =
                    'danger';


                $id_mayordomo_encontrado = '';
                $nombre_m = '';
                $telefono_m = '';
                $correo_m = '';

            } else {


                $id_mayordomo_encontrado =
                    $row_m['id_usuario'];


                $nombre_m =
                    $row_m['nombres'] .
                    ' ' .
                    $row_m['apellidos'];


                $telefono_m =
                    $row_m['telefono'];


                $correo_m =
                    $row_m['correo'];


                $mensaje_alerta =
                    'Usuario encontrado correctamente.';

                $tipo_alerta =
                    'success';

            }


        } else {


            $mensaje_alerta =
                'No se encontró ningún usuario con ese número de documento.';

            $tipo_alerta =
                'danger';


            $id_mayordomo_encontrado = '';
            $nombre_m = '';
            $telefono_m = '';
            $correo_m = '';

        }


        $stmt_m->close();

    }

}


/* =========================================================
   PROCESAR ASIGNACIÓN Y PERMISOS
   ========================================================= */

if (
    $tiene_permiso_gestionar &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['guardar_mayordomo'])
) {


    $id_mayordomo =
        intval(
            $_POST['id_mayordomo_encontrado']
            ?? 0
        );


    $id_finca =
        intval(
            $_POST['id_finca']
            ?? 0
        );


    /* =====================================================
       VALIDAR MAYORDOMO
       ===================================================== */

    if ($id_mayordomo <= 0) {


        $mensaje_alerta =
            'Primero debe buscar y seleccionar un mayordomo válido.';

        $tipo_alerta =
            'danger';


    } elseif ($id_finca <= 0) {


        $mensaje_alerta =
            'Debe seleccionar una finca para asignar al mayordomo.';

        $tipo_alerta =
            'danger';


    } else {


        /* =================================================
           COMPROBAR QUE LA FINCA PERTENEZCA AL CAFICULTOR
           ================================================= */

        $sql_finca_validar = "
            SELECT id_finca
            FROM fincas
            WHERE id_finca = ?
            AND id_caficultor = ?
            LIMIT 1
        ";


        $stmt_finca_validar =
            $con->prepare(
                $sql_finca_validar
            );


        $stmt_finca_validar->bind_param(
            "ii",
            $id_finca,
            $id_usuario_sesion
        );


        $stmt_finca_validar->execute();


        $res_finca_validar =
            $stmt_finca_validar->get_result();


        if (
            !$res_finca_validar->fetch_assoc()
        ) {


            $mensaje_alerta =
                'La finca seleccionada no pertenece al usuario actual.';

            $tipo_alerta =
                'danger';


        } else {


            /* =============================================
               PERMISOS
               ============================================= */

            $registrar_recolecciones =
                isset(
                    $_POST['registrar_recolecciones']
                )
                ? 1
                : 0;


            $editar_recolecciones =
                isset(
                    $_POST['editar_recolecciones']
                )
                ? 1
                : 0;


            $eliminar_recolectores =
                isset(
                    $_POST['eliminar_recolectores']
                )
                ? 1
                : 0;


            $ver_reportes =
                isset(
                    $_POST['ver_reportes']
                )
                ? 1
                : 0;


            $gestionar_mayordomos =
                isset(
                    $_POST['gestionar_mayordomos']
                )
                ? 1
                : 0;


            try {


                $con->begin_transaction();


                /* =========================================
                   GUARDAR PERMISOS
                   ========================================= */

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


                $stmt_save =
                    $con->prepare(
                        $sql_save
                    );


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

                $stmt_save->close();


                /* =========================================
                   ACTUALIZAR ROL
                   ========================================= */

                $sql_rol = "

                    UPDATE usuarios

                    SET rol = 'mayordomo'

                    WHERE id_usuario = ?

                ";


                $stmt_rol =
                    $con->prepare(
                        $sql_rol
                    );


                $stmt_rol->bind_param(
                    "i",
                    $id_mayordomo
                );


                $stmt_rol->execute();

                $stmt_rol->close();


                /* =========================================
                   CONFIRMAR
                   ========================================= */

                $con->commit();


                $mensaje_alerta =
                    '¡Mayordomo y permisos asignados con éxito!';

                $tipo_alerta =
                    'success';


                /* =========================================
                   LIMPIAR
                   ========================================= */

                $doc_buscado = '';
                $nombre_m = '';
                $telefono_m = '';
                $correo_m = '';
                $id_mayordomo_encontrado = '';


            } catch (Throwable $e) {


                $con->rollback();


                $mensaje_alerta =
                    'Error al guardar en la base de datos: ' .
                    $e->getMessage();

                $tipo_alerta =
                    'danger';

            }

        }

        $stmt_finca_validar->close();

    }

}


/* =========================================================
   SI INTENTAN ENVIAR MANUALMENTE SIN PERMISO
   ========================================================= */

if (
    !$tiene_permiso_gestionar &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
     * No se procesa absolutamente ningún dato.
     */

    $doc_buscado = '';
    $nombre_m = '';
    $telefono_m = '';
    $correo_m = '';
    $id_mayordomo_encontrado = '';

}


/* =========================================================
   FUNCIÓN DE ESCAPE HTML
   ========================================================= */

function e($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
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
    Delegar Mayordomo - Café Control
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
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
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

    --primary-green:#244b20;

    --primary-green-dark:#173517;

    --active-nav:#445c42;

    --text-dark:#1f1f1f;

    --text-muted:#5d5d5d;

    --border-color:#d8cec4;

    --success-bg:#d8ead2;

    --success-border:#8fb485;

    --success-text:#244b20;

    --danger-bg:#fff3cd;

    --danger-border:#e0b341;

    --danger-text:#795548;

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

    font-size:18px;

    line-height:1.6;

}


/* =========================================================
   HEADER
   ========================================================= */

.top-header {

    background-color:var(--primary-green);

    color:#fff;

    display:flex;

    align-items:center;

    justify-content:space-between;

    height:76px;

    padding:0 30px;

}


.brand {

    display:flex;

    align-items:center;

    gap:14px;

    width:270px;

}


.brand-logo {

    width:44px;

    height:44px;

    background:#fff;

    color:var(--primary-green);

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

}


.brand-text h1 {

    font-size:20px;

    font-weight:800;

}


.header-center {

    display:flex;

    align-items:center;

    gap:18px;

    flex:1;

}


.user-welcome h2 {

    font-size:17px;

    font-weight:800;

}


.user-welcome p {

    font-size:13px;

    opacity:.9;

    font-weight:500;

}


.header-actions {

    display:flex;

    align-items:center;

    gap:20px;

}


.avatar {

    width:46px;

    height:46px;

    min-width:46px;

    min-height:46px;

    background:#fff;

    color:var(--primary-green);

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    overflow:hidden;

    border:2px solid rgba(255,255,255,.95);

}


.avatar img {

    width:100%;

    height:100%;

    display:block;

    object-fit:cover;

    border-radius:50%;

}


.user-profile {

    display:flex;

    align-items:center;

    justify-content:center;

    width:46px;

    height:46px;

    text-decoration:none;

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

    width:260px;

    background-color:var(--bg-sidebar);

    padding:24px 16px;

    flex-shrink:0;

}


.nav-list {

    list-style:none;

    display:flex;

    flex-direction:column;

    gap:8px;

}


.nav-item a {

    display:flex;

    align-items:center;

    gap:14px;

    padding:12px 18px;

    border-radius:10px;

    color:#fff;

    text-decoration:none;

    font-size:15px;

    font-weight:700;

    transition:.2s;

}


.nav-item.active a {

    background-color:var(--active-nav);

    color:#fff;

    box-shadow:inset 3px 0 0 #fff;

}


.nav-item:not(.active) a:hover {

    background-color:rgba(255,255,255,.10);

}


/* =========================================================
   MAIN
   ========================================================= */

.main-content {

    flex:1;

    padding:32px 40px;

    display:flex;

    flex-direction:column;

    gap:24px;

    min-width:0;

}


/* =========================================================
   CARD
   ========================================================= */

.panel-card {

    background:#fff;

    border-radius:18px;

    border:1px solid var(--border-color);

    padding:32px;

    box-shadow:
        0 5px 16px rgba(0,0,0,.05);

    width:100%;

}


.panel-header {

    display:flex;

    align-items:center;

    gap:16px;

    margin-bottom:28px;

}


.panel-icon-circle {

    width:54px;

    height:54px;

    background:var(--primary-green);

    color:#fff;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

}


.panel-header-text h2 {

    font-size:24px;

    color:var(--primary-green);

    font-weight:800;

}


.panel-header-text p {

    font-size:15px;

    color:var(--text-muted);

    font-weight:600;

}


/* =========================================================
   ALERTA DE PERMISO
   ========================================================= */

.permission-denied {

    background:var(--danger-bg);

    border:2px solid var(--danger-border);

    border-radius:14px;

    padding:20px 24px;

    margin-bottom:24px;

    display:flex;

    align-items:center;

    gap:18px;

    animation:aparecerAviso .3s ease;

}


.permission-icon {

    width:56px;

    height:56px;

    border-radius:50%;

    background:var(--danger-border);

    color:#fff;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    font-size:22px;

}


.permission-content h3 {

    color:var(--danger-text);

    font-size:19px;

    font-weight:800;

    margin-bottom:4px;

}


.permission-content p {

    color:#5d4037;

    font-size:14px;

    font-weight:600;

}


/* =========================================================
   BLOQUEO
   ========================================================= */

.interfaz-bloqueada {

    opacity:.55;

    pointer-events:none;

    user-select:none;

}


.interfaz-bloqueada input,
.interfaz-bloqueada select,
.interfaz-bloqueada button {

    cursor:not-allowed !important;

}


input:disabled,
select:disabled,
button:disabled {

    cursor:not-allowed !important;

    opacity:.55;

}


@keyframes aparecerAviso {

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
   ALERTAS NORMALES
   ========================================================= */

.alert-message {

    padding:14px 18px;

    border-radius:12px;

    font-size:16px;

    margin-bottom:24px;

    font-weight:800;

}


.alert-danger {

    background:var(--danger-bg);

    color:var(--danger-text);

    border:2px solid var(--danger-border);

}


.alert-success {

    background:var(--success-bg);

    color:var(--success-text);

    border:2px solid var(--success-border);

}


/* =========================================================
   GRID
   ========================================================= */

.delegation-grid {

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:24px;

    align-items:stretch;

}


.sub-card {

    background:var(--bg-card-inner);

    border:1px solid var(--border-color);

    border-radius:16px;

    padding:24px;

    display:flex;

    flex-direction:column;

    gap:18px;

}


.sub-card-title {

    font-size:19px;

    font-weight:800;

    color:var(--primary-green);

    border-bottom:2px solid var(--border-color);

    padding-bottom:9px;

}


/* =========================================================
   FORMULARIOS
   ========================================================= */

.form-group {

    display:flex;

    flex-direction:column;

    gap:7px;

    margin-bottom:15px;

}


.form-group label {

    font-size:15px;

    font-weight:800;

}


.input-search-group {

    display:flex;

    gap:10px;

}


.custom-input,
.custom-select {

    width:100%;

    padding:13px 16px;

    border-radius:10px;

    border:2px solid var(--border-color);

    background:#fff;

    font-size:16px;

    font-weight:700;

    outline:none;

    color:var(--text-dark);

}


.custom-input:focus,
.custom-select:focus {

    border-color:var(--primary-green);

}


.custom-input:read-only {

    background:#e7eee4;

    cursor:not-allowed;

}


.btn-search {

    background:var(--primary-green);

    color:#fff;

    border:none;

    padding:0 22px;

    border-radius:10px;

    font-weight:800;

    font-size:15px;

    cursor:pointer;

    white-space:nowrap;

}


/* =========================================================
   PERMISOS
   ========================================================= */

.permisos-container {

    justify-content:space-between;

}


.permiso-list {

    display:flex;

    flex-direction:column;

    gap:10px;

    margin-top:14px;

}


.permiso-item {

    display:flex;

    align-items:flex-start;

    gap:12px;

    cursor:pointer;

    padding:9px 10px;

    border-radius:10px;

}


.permiso-checkbox {

    width:22px;

    height:22px;

    accent-color:var(--primary-green);

    margin-top:2px;

    cursor:pointer;

    flex-shrink:0;

}


.permiso-text h4 {

    font-size:16px;

    font-weight:800;

    color:var(--text-dark);

    line-height:1.2;

}


.permiso-text p {

    font-size:14px;

    color:var(--text-muted);

    font-weight:600;

    margin-top:3px;

}


/* =========================================================
   GUARDAR
   ========================================================= */

.btn-save-container {

    display:flex;

    justify-content:flex-end;

    margin-top:16px;

}


.btn-save {

    background:var(--primary-green);

    color:#fff;

    border:none;

    padding:13px 24px;

    border-radius:10px;

    font-weight:800;

    font-size:15px;

    cursor:pointer;

    display:inline-flex;

    align-items:center;

    gap:10px;

}


/* =========================================================
   FOOTER
   ========================================================= */

.app-footer {

    text-align:center;

    padding:18px;

    font-size:13px;

    font-weight:600;

    color:var(--text-muted);

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    background:#efe8e0;

    border-top:1px solid var(--border-color);

    margin-top:auto;

}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width:1050px) {

    .delegation-grid {

        grid-template-columns:1fr;

    }

}


@media (max-width:850px) {

    .top-header {

        height:auto;

        min-height:76px;

        padding:12px 18px;

        flex-wrap:wrap;

    }


    .brand {

        width:auto;

    }


    .header-center {

        padding-left:0;

    }


    .sidebar {

        width:100%;

    }


    .app-layout {

        flex-direction:column;

    }


    .main-content {

        padding:20px;

    }

}


@media (max-width:600px) {

    .input-search-group {

        flex-direction:column;

    }


    .btn-search {

        min-height:48px;

    }


    .btn-save-container {

        justify-content:stretch;

    }


    .btn-save {

        width:100%;

        justify-content:center;

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
                size="22"
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
                echo e($nombre_usuario);
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
                            echo e($foto_perfil);
                        ?>?v=<?php
                            echo time();
                        ?>"
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
                        data-lucide="home"
                        size="20"
                    ></i>

                    Perfil

                </a>

            </li>


            <li class="nav-item">

                <a href="recolecciones_m.php">

                    <i
                        data-lucide="archive"
                        size="20"
                    ></i>

                    Recolecciones

                </a>

            </li>


            <li class="nav-item">

                <a href="recolectores_m.php">

                    <i
                        data-lucide="users"
                        size="20"
                    ></i>

                    Trabajadores

                </a>

            </li>


            <li class="nav-item">

                <a href="resumen_m.php">

                    <i
                        data-lucide="bar-chart-2"
                        size="20"
                    ></i>

                    Resumen General

                </a>

            </li>


            <li class="nav-item active">

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
                        size="20"
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


        <!-- ================================================
             AVISO DE SIN PERMISO
             ================================================ -->

        <?php if (!$tiene_permiso_gestionar): ?>


            <div class="permission-denied">


                <div class="permission-icon">

                    <i class="fa-solid fa-lock"></i>

                </div>


                <div class="permission-content">


                    <h3>
                        Sin permiso para gestionar mayordomos
                    </h3>


                    <p>

                        El caficultor debe darte el permiso
                       
                        para que puedas asignar, modificar o gestionar mayordomos.

                    </p>


                </div>


            </div>


        <?php endif; ?>



        <!-- ================================================
             PANEL
             ================================================ -->

        <div
            class="<?php
                echo !$tiene_permiso_gestionar
                    ? 'interfaz-bloqueada'
                    : '';
            ?>"
        >


            <div class="panel-card">


                <div class="panel-header">


                    <div class="panel-icon-circle">

                        <i
                            class="fa-solid fa-user-large"
                        ></i>

                    </div>


                    <div class="panel-header-text">

                        <h2>
                            Delegar mayordomo
                        </h2>


                        <p>

                            Asigne una persona como
                            mayordomo y configure sus
                            permisos en el sistema.

                        </p>

                    </div>


                </div>



                <!-- =========================================
                     ALERTAS
                     ========================================== -->

                <?php if (!empty($mensaje_alerta)): ?>


                    <div
                        class="alert-message alert-<?php
                            echo e($tipo_alerta);
                        ?>"
                    >

                        <?php
                        echo e($mensaje_alerta);
                        ?>

                    </div>


                <?php endif; ?>



                <!-- =========================================
                     GRID
                     ========================================== -->

                <div class="delegation-grid">


                    <!-- =====================================
                         COLUMNA IZQUIERDA
                         ====================================== -->

                    <div class="sub-card">


                        <h3 class="sub-card-title">
                            Información del mayordomo
                        </h3>



                        <!-- FORMULARIO BUSCAR -->

                        <form
                            method="POST"
                            action="masignar_m.php"
                            id="form_buscar"
                        >


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
                                        value="<?php
                                            echo e(
                                                $doc_buscado
                                            );
                                        ?>"
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



                        <!-- FORMULARIO GUARDAR -->

                        <form
                            method="POST"
                            action="masignar_m.php"
                            id="form_guardar_m"
                        >


                            <input
                                type="hidden"
                                name="id_mayordomo_encontrado"
                                value="<?php
                                    echo e(
                                        $id_mayordomo_encontrado
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="documento"
                                value="<?php
                                    echo e(
                                        $doc_buscado
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="nombre_m"
                                value="<?php
                                    echo e(
                                        $nombre_m
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="telefono_m"
                                value="<?php
                                    echo e(
                                        $telefono_m
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="correo_m"
                                value="<?php
                                    echo e(
                                        $correo_m
                                    );
                                ?>"
                            >



                            <div class="form-group">


                                <label for="nombre_completo">

                                    Nombre completo

                                </label>


                                <input
                                    type="text"
                                    id="nombre_completo"
                                    class="custom-input"
                                    readonly
                                    placeholder="Nombre completo"
                                    value="<?php
                                        echo e(
                                            $nombre_m
                                        );
                                    ?>"
                                >


                            </div>



                            <div class="form-group">


                                <label for="telefono">

                                    Teléfono

                                </label>


                                <input
                                    type="text"
                                    id="telefono"
                                    class="custom-input"
                                    readonly
                                    placeholder="Teléfono de contacto"
                                    value="<?php
                                        echo e(
                                            $telefono_m
                                        );
                                    ?>"
                                >


                            </div>



                            <div class="form-group">


                                <label for="correo">

                                    Correo electrónico

                                </label>


                                <input
                                    type="email"
                                    id="correo"
                                    class="custom-input"
                                    readonly
                                    placeholder="correo@finca.com"
                                    value="<?php
                                        echo e(
                                            $correo_m
                                        );
                                    ?>"
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


                                    <?php foreach (
                                        $fincas_caficultor
                                        as $finca
                                    ): ?>


                                        <option
                                            value="<?php
                                                echo (int)
                                                    $finca[
                                                        'id_finca'
                                                    ];
                                            ?>"
                                        >

                                            <?php

                                            echo e(
                                                $finca[
                                                    'nombre'
                                                ]
                                            );

                                            ?>

                                        </option>


                                    <?php endforeach; ?>


                                </select>


                            </div>


                        </form>


                    </div>



                    <!-- =====================================
                         COLUMNA DERECHA
                         ====================================== -->

                    <div class="sub-card permisos-container">


                        <div>


                            <h3 class="sub-card-title">

                                Permisos asignados

                            </h3>



                            <div class="permiso-list">


                                <!-- REGISTRAR -->

                                <label class="permiso-item">


                                    <input
                                        type="checkbox"
                                        name="registrar_recolecciones"
                                        class="permiso-checkbox"
                                        form="form_guardar_m"
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



                                <!-- EDITAR -->

                                <label class="permiso-item">


                                    <input
                                        type="checkbox"
                                        name="editar_recolecciones"
                                        class="permiso-checkbox"
                                        form="form_guardar_m"
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



                                <!-- ELIMINAR -->

                                <label class="permiso-item">


                                    <input
                                        type="checkbox"
                                        name="eliminar_recolectores"
                                        class="permiso-checkbox"
                                        form="form_guardar_m"
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



                                <!-- REPORTES -->

                                <label class="permiso-item">


                                    <input
                                        type="checkbox"
                                        name="ver_reportes"
                                        class="permiso-checkbox"
                                        form="form_guardar_m"
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



                                <!-- GESTIONAR MAYORDOMOS -->

                                <label class="permiso-item">


                                    <input
                                        type="checkbox"
                                        name="gestionar_mayordomos"
                                        class="permiso-checkbox"
                                        form="form_guardar_m"
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



                        <!-- ===================================
                             BOTÓN GUARDAR
                             ==================================== -->

                        <div class="btn-save-container">


                            <button
                                type="submit"
                                form="form_guardar_m"
                                name="guardar_mayordomo"
                                class="btn-save"
                            >

                                <i
                                    class="fa-solid fa-user-plus"
                                ></i>

                                Guardar mayordomo

                            </button>


                        </div>


                    </div>


                </div>


            </div>


        </div>


    </main>


</div>



<!-- =======================================================
     FOOTER
     ======================================================== -->

<footer class="app-footer">


    <i
        data-lucide="coffee"
        size="16"
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
   PERMISO DESDE PHP
   ========================================================= */

const tienePermisoGestionar =
    <?php
    echo $tiene_permiso_gestionar
        ? 'true'
        : 'false';
    ?>;


/* =========================================================
   BLOQUEAR TODA LA INTERFAZ
   ========================================================= */

function bloquearInterfaz() {


    const elementos = [

        document.getElementById(
            'doc_input'
        ),

        document.querySelector(
            '[name="buscar_documento"]'
        ),

        document.getElementById(
            'nombre_completo'
        ),

        document.getElementById(
            'telefono'
        ),

        document.getElementById(
            'correo'
        ),

        document.getElementById(
            'finca_select'
        ),

        document.querySelector(
            '[name="registrar_recolecciones"]'
        ),

        document.querySelector(
            '[name="editar_recolecciones"]'
        ),

        document.querySelector(
            '[name="eliminar_recolectores"]'
        ),

        document.querySelector(
            '[name="ver_reportes"]'
        ),

        document.querySelector(
            '[name="gestionar_mayordomos"]'
        ),

        document.querySelector(
            '[name="guardar_mayordomo"]'
        )

    ];


    elementos.forEach(
        function(elemento) {


            if (elemento) {

                elemento.disabled = true;

            }


        }
    );


}


/* =========================================================
   SI NO HAY PERMISO, BLOQUEAR AL CARGAR
   ========================================================= */

if (!tienePermisoGestionar) {

    bloquearInterfaz();

}


/* =========================================================
   PREVENIR ENVÍOS SIN PERMISO
   ========================================================= */

document.addEventListener(
    'submit',
    function(evento) {


        if (!tienePermisoGestionar) {


            evento.preventDefault();


            alert(
                'No tienes permiso para gestionar mayordomos. El caficultor debe habilitar el permiso "Gestionar mayordomos".'
            );


            bloquearInterfaz();


        }


    }
);


/* =========================================================
   PREVENIR CAMBIOS DE CHECKBOX SIN PERMISO
   ========================================================= */

document
    .querySelectorAll('.permiso-checkbox')
    .forEach(
        function(checkbox) {


            checkbox.addEventListener(
                'change',
                function(evento) {


                    if (!tienePermisoGestionar) {


                        evento.preventDefault();


                        this.checked =
                            !this.checked;


                        alert(
                            'No tienes permiso para modificar los permisos de mayordomos.'
                        );


                        bloquearInterfaz();


                    }


                }
            );


        }
    );


/* =========================================================
   PREVENIR ESCRITURA DIRECTA
   ========================================================= */

const campoDocumento =
    document.getElementById(
        'doc_input'
    );


if (campoDocumento) {


    campoDocumento.addEventListener(
        'input',
        function() {


            if (!tienePermisoGestionar) {


                this.value = '';


                alert(
                    'No tienes permiso para ingresar datos en esta interfaz.'
                );


                bloquearInterfaz();


            }


        }
    );

}


/* =========================================================
   PREVENIR CAMBIO DE FINCA
   ========================================================= */

const fincaSelect =
    document.getElementById(
        'finca_select'
    );


if (fincaSelect) {


    fincaSelect.addEventListener(
        'change',
        function() {


            if (!tienePermisoGestionar) {


                this.value = '';


                alert(
                    'No tienes permiso para seleccionar una finca.'
                );


                bloquearInterfaz();


            }


        }
    );

}

</script>


</body>

</html>