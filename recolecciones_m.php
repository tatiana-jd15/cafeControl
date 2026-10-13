<?php

session_start();

require_once 'assets/inc/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('America/Bogota');


/* =========================================================
   VERIFICAR SESIÓN
   ========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    header("Location: index.php");
    exit();

}


$id_usuario_sesion = (int) $_SESSION['id_usuario'];


/* =========================================================
   OBTENER DATOS DEL MAYORDOMO
   ========================================================= */

$sql_user = "
    SELECT
        u.id_usuario,
        u.nombres,
        u.apellidos,
        u.rol,
        COALESCE(
            NULLIF(pu.foto_perfil, ''),
            u.foto_perfil
        ) AS foto_perfil
    FROM usuarios u
    LEFT JOIN perfiles_usuario pu
        ON pu.id_usuario = u.id_usuario
    WHERE u.id_usuario = ?
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

    $nombre_mayordomo =
        trim(
            $row_user['nombres'] . ' ' .
            $row_user['apellidos']
        );

    $rol_usuario =
        $row_user['rol'];

    // Foto guardada en la base de datos para este usuario.
    $foto_perfil_usuario =
        trim((string) ($row_user['foto_perfil'] ?? ''));

} else {

    session_destroy();

    header("Location: index.php");

    exit();

}


/* =========================================================
   VERIFICAR SI EL MAYORDOMO TIENE PERMISO EN ALGUNA FINCA
   ========================================================= */

$tiene_permiso_general = false;

$sql_permiso_general = "
    SELECT id_finca
    FROM permisos_mayordomo
    WHERE id_mayordomo = ?
    AND registrar_recolecciones = 1
    LIMIT 1
";

$stmt_permiso_general =
    $con->prepare($sql_permiso_general);

$stmt_permiso_general->bind_param(
    "i",
    $id_usuario_sesion
);

$stmt_permiso_general->execute();

$res_permiso_general =
    $stmt_permiso_general->get_result();


if ($res_permiso_general->fetch_assoc()) {

    $tiene_permiso_general = true;

}


/* =========================================================
   API 1
   BUSCAR RECOLECTOR
   ========================================================= */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'buscar_recolector'
) {

    header('Content-Type: application/json; charset=utf-8');

    try {

        /* =============================================
           COMPROBAR SESIÓN
           ============================================= */

        if (!isset($_SESSION['id_usuario'])) {

            echo json_encode([

                'success' => false,

                'permission_denied' => true,

                'message' =>
                    'La sesión ha expirado. Inicia sesión nuevamente.'

            ]);

            exit();

        }


        $cedula =
            trim(
                $_POST['cedula'] ?? ''
            );


        /* =============================================
           VALIDAR CÉDULA
           ============================================= */

        if ($cedula === '') {

            echo json_encode([

                'success' => false,

                'permission_denied' => false,

                'message' =>
                    'Ingresa un número de cédula.'

            ]);

            exit();

        }


        /* =============================================
           BUSCAR RECOLECTOR
           ============================================= */

        $sql = "

            SELECT

                u.id_usuario,

                u.documento,

                u.nombres,

                u.apellidos,

                u.telefono,

                u.rol,

                COALESCE(
                    NULLIF(pu.foto_perfil, ''),
                    u.foto_perfil
                ) AS foto_perfil,

                f.id_finca,

                f.nombre AS nombre_finca,

                f.ubicacion_vereda

            FROM usuarios u

            INNER JOIN recolectores r
                ON u.id_usuario = r.id_usuario

            LEFT JOIN perfiles_usuario pu
                ON pu.id_usuario = u.id_usuario

            INNER JOIN fincas f
                ON r.id_finca = f.id_finca

            WHERE u.documento = ?

            AND u.rol = 'recolector'

            AND u.estado = 'activo'

            LIMIT 1

        ";


        $stmt =
            $con->prepare($sql);


        $stmt->bind_param(
            "s",
            $cedula
        );


        $stmt->execute();


        $res =
            $stmt->get_result();


        /* =============================================
           SI ENCUENTRA RECOLECTOR
           ============================================= */

        if ($recolector = $res->fetch_assoc()) {


            $id_finca_recolector =
                (int) $recolector['id_finca'];


            /* =========================================
               VERIFICAR PERMISO PARA ESA FINCA
               ========================================= */

            $sql_permiso = "

                SELECT
                    registrar_recolecciones

                FROM permisos_mayordomo

                WHERE id_mayordomo = ?

                AND id_finca = ?

                LIMIT 1

            ";


            $stmt_permiso =
                $con->prepare($sql_permiso);


            $stmt_permiso->bind_param(

                "ii",

                $id_usuario_sesion,

                $id_finca_recolector

            );


            $stmt_permiso->execute();


            $res_permiso =
                $stmt_permiso->get_result();


            $tiene_permiso = false;


            if (
                $permiso =
                $res_permiso->fetch_assoc()
            ) {

                $tiene_permiso =

                    (
                        (int)
                        $permiso[
                            'registrar_recolecciones'
                        ] === 1
                    );

            }


            /* =========================================
               SIN PERMISO
               ========================================= */

            if (!$tiene_permiso) {

                echo json_encode([

                    'success' => false,

                    'permission_denied' => true,

                    'id_finca' =>
                        $id_finca_recolector,

                    'message' =>

                        'No tienes permiso para registrar recolecciones en la finca "' .
                        $recolector['nombre_finca'] .
                        '". El caficultor debe darte el permiso "Registrar recolecciones".'

                ]);

                exit();

            }


            /* =========================================
               TIENE PERMISO
               ========================================= */

            echo json_encode([

                'success' => true,

                'permission_denied' => false,

                'data' => $recolector

            ]);

            exit();


        }


        /* =============================================
           RECOLECTOR NO ENCONTRADO
           ============================================= */

        echo json_encode([

            'success' => false,

            'permission_denied' => false,

            'message' =>
                'Recolector no encontrado o inactivo.'

        ]);

        exit();


    } catch (Throwable $e) {

        echo json_encode([

            'success' => false,

            'permission_denied' => false,

            'message' =>
                'Ocurrió un error en la consulta: ' .
                $e->getMessage()

        ]);

        exit();

    }

}


/* =========================================================
   API 2
   GUARDAR RECOLECCIONES
   ========================================================= */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'guardar_recolecciones'
) {

    header('Content-Type: application/json; charset=utf-8');


    try {


        /* =============================================
           COMPROBAR SESIÓN
           ============================================= */

        if (!isset($_SESSION['id_usuario'])) {

            echo json_encode([

                'success' => false,

                'permission_denied' => true,

                'message' =>
                    'La sesión ha expirado. Inicia sesión nuevamente.'

            ]);

            exit();

        }


        /* =============================================
           RECIBIR DATOS
           ============================================= */

        $id_recolector =
            (int)
            ($_POST['id_recolector'] ?? 0);


        $id_finca =
            (int)
            ($_POST['id_finca'] ?? 0);


        $entregas =
            $_POST['entregas'] ?? [];


        /* =============================================
           VALIDAR DATOS
           ============================================= */

        if (
            $id_recolector === 0 ||
            $id_finca === 0 ||
            empty($entregas)
        ) {

            echo json_encode([

                'success' => false,

                'permission_denied' => false,

                'message' =>
                    'Selecciona un recolector válido.'

            ]);

            exit();

        }


        /* =============================================
           VERIFICAR PERMISO NUEVAMENTE
           ============================================= */

        $sql_permiso = "

            SELECT
                registrar_recolecciones

            FROM permisos_mayordomo

            WHERE id_mayordomo = ?

            AND id_finca = ?

            LIMIT 1

        ";


        $stmt_permiso =
            $con->prepare($sql_permiso);


        $stmt_permiso->bind_param(

            "ii",

            $id_usuario_sesion,

            $id_finca

        );


        $stmt_permiso->execute();


        $res_permiso =
            $stmt_permiso->get_result();


        $tiene_permiso =
            false;


        if (
            $permiso =
            $res_permiso->fetch_assoc()
        ) {

            $tiene_permiso =

                (
                    (int)
                    $permiso[
                        'registrar_recolecciones'
                    ] === 1
                );

        }


        /* =============================================
           BLOQUEAR
           ============================================= */

        if (!$tiene_permiso) {

            echo json_encode([

                'success' => false,

                'permission_denied' => true,

                'message' =>
                    'No tienes permiso para registrar recolecciones en esta finca. El caficultor debe darte el permiso "Registrar recolecciones".'

            ]);

            exit();

        }


        /* =============================================
           VERIFICAR QUE EL RECOLECTOR PERTENEZCA
           A LA FINCA
           ============================================= */

        $sql_verificar_recolector = "

            SELECT
                id_usuario

            FROM recolectores

            WHERE id_usuario = ?

            AND id_finca = ?

            LIMIT 1

        ";


        $stmt_verificar =
            $con->prepare(
                $sql_verificar_recolector
            );


        $stmt_verificar->bind_param(

            "ii",

            $id_recolector,

            $id_finca

        );


        $stmt_verificar->execute();


        $res_verificar =
            $stmt_verificar->get_result();


        if (!$res_verificar->fetch_assoc()) {

            echo json_encode([

                'success' => false,

                'permission_denied' => false,

                'message' =>
                    'El recolector no pertenece a la finca seleccionada.'

            ]);

            exit();

        }


        /* =============================================
           CONSULTAR PRECIO VIGENTE
           ============================================= */

        $sql_precio = "

            SELECT
                precio_kilo

            FROM historial_precios

            WHERE id_finca = ?

            ORDER BY fecha_actualizacion DESC

            LIMIT 1

        ";


        $stmt_p =
            $con->prepare($sql_precio);


        $stmt_p->bind_param(
            "i",
            $id_finca
        );


        $stmt_p->execute();


        $res_p =
            $stmt_p->get_result();


        $precio_kilo = 0;


        if ($row_p = $res_p->fetch_assoc()) {

            $precio_kilo =
                (float)
                $row_p['precio_kilo'];

        }


        /* =============================================
           INICIAR TRANSACCIÓN
           ============================================= */

        $con->begin_transaction();


        $sql_ins = "

            INSERT INTO recolecciones

            (
                id_recolector,
                id_finca,
                id_registrado_por,
                numero_entrega,
                fecha,
                kilos_recolectados,
                precio_kilo_aplicado,
                pago_calculado,
                observaciones
            )

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)

        ";


        $stmt_ins =
            $con->prepare($sql_ins);


        $insertados = 0;


        /* =============================================
           RECORRER LAS TRES ENTREGAS
           ============================================= */

        foreach (
            $entregas
            as $item
        ) {


            $kilos =

                (float)
                ($item['kilos'] ?? 0);


            /* -----------------------------------------
               SOLO GUARDAR SI HAY KILOS
               ----------------------------------------- */

            if ($kilos > 0) {


                $num_entrega =

                    (int)
                    ($item['num_entrega'] ?? 1);


                if (
                    $num_entrega < 1 ||
                    $num_entrega > 3
                ) {

                    continue;

                }


                $datetime_input =

                    $item['fecha_hora']
                    ?? date('Y-m-d H:i:s');


                /* -------------------------------------
                   CONVERTIR FECHA
                   ------------------------------------- */

                $timestamp =
                    strtotime($datetime_input);


                if (
                    $timestamp === false
                ) {

                    $fecha_hora_mysql =
                        date(
                            'Y-m-d H:i:s'
                        );

                } else {

                    $fecha_hora_mysql =

                        date(
                            'Y-m-d H:i:s',
                            $timestamp
                        );

                }


                /* -------------------------------------
                   CALCULAR PAGO
                   ------------------------------------- */

                $pago_calculado =

                    $kilos *
                    $precio_kilo;


                /* -------------------------------------
                   OBSERVACIONES
                   ------------------------------------- */

                $obs =

                    trim(
                        $item[
                            'observaciones'
                        ] ?? ''
                    );


                /* -------------------------------------
                   INSERTAR
                   ------------------------------------- */

                $stmt_ins->bind_param(

                    "iiiisddds",

                    $id_recolector,

                    $id_finca,

                    $id_usuario_sesion,

                    $num_entrega,

                    $fecha_hora_mysql,

                    $kilos,

                    $precio_kilo,

                    $pago_calculado,

                    $obs

                );


                $stmt_ins->execute();


                $insertados++;

            }

        }


        /* =============================================
           CONFIRMAR
           ============================================= */

        if ($insertados > 0) {

            $con->commit();


            echo json_encode([

                'success' => true,

                'permission_denied' => false,

                'message' =>

                    "Se registraron " .
                    $insertados .
                    " entrega(s) exitosamente."

            ]);

            exit();

        }


        /* =============================================
           NO HAY ENTREGAS
           ============================================= */

        $con->rollback();


        echo json_encode([

            'success' => false,

            'permission_denied' => false,

            'message' =>
                'Ingresa al menos el peso de una entrega.'

        ]);

        exit();


    } catch (Throwable $e) {


        if (
            $con->errno === 0
        ) {
            // No hacer nada
        }


        try {

            $con->rollback();

        } catch (Throwable $rollbackError) {
            // Evitar otro error
        }


        echo json_encode([

            'success' => false,

            'permission_denied' => false,

            'message' =>
                'Error al guardar: ' .
                $e->getMessage()

        ]);

        exit();

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
    Gestor de Cuentas - Café Recolectado
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


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>


<script
    src="https://unpkg.com/lucide@latest"
></script>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


<style>

:root {

    --bg-main:#f5f0eb;

    --bg-sidebar:#1b3818;

    --bg-card:#ffffff;

    --card-inner-bg:#efe8e0;

    --primary-brown:#244b20;

    --primary-dark:#1b3818;

    --text-dark:#282828;

    --text-muted:#3c3c3c;

    --status-active:#244b20;

    --border-color:#d6cabe;

    --font-family:'Plus Jakarta Sans',sans-serif;

    --btn-green:#244b20;

    --btn-green-hover:#1b3818;

    --active-nav:#445c42;

    --light-green:#d4ebd0;

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

    font-size:16px;

}


/* =========================================================
   HEADER
   ========================================================= */

.top-header {

    background-color:var(--primary-brown);

    color:#fff;

    display:flex;

    align-items:center;

    justify-content:space-between;

    height:70px;

    padding:0 28px;

}


.brand {

    display:flex;

    align-items:center;

    gap:14px;

    width:250px;

}


.brand-logo {

    width:40px;

    height:40px;

    background:#fff;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    color:var(--primary-brown);

}


.brand-text h1 {

    font-size:18px;

    font-weight:800;

}


.header-center {

    display:flex;

    align-items:center;

    gap:20px;

    flex:1;

    padding-left:20px;

}


.user-welcome h2 {

    font-size:16px;

    font-weight:700;

}


.user-welcome p {

    font-size:13px;

    opacity:.9;

}


.header-actions {

    display:flex;

    align-items:center;

    gap:24px;

}


.user-profile {

    text-decoration:none;

}


.avatar {

    width:42px;

    height:42px;

    background:#fff;

    color:var(--primary-brown);

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    overflow:hidden;

}


.header-profile-photo {

    width:100%;

    height:100%;

    object-fit:cover;

    display:block;

    border-radius:50%;

}


/* =========================================================
   LAYOUT
   ========================================================= */

.app-layout {

    display:flex;

    flex:1;

}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {

    width:270px;

    background-color:var(--bg-sidebar);

    padding:24px 18px;

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

    padding:32px 36px;

    overflow-y:auto;

}


.main-card {

    background:#fff;

    border-radius:20px;

    border:1px solid var(--border-color);

    padding:32px;

    box-shadow:0 4px 16px rgba(0,0,0,.03);

}


.panel-header {

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    margin-bottom:30px;

}


.panel-title {

    display:flex;

    align-items:center;

    gap:18px;

}


.title-icon {

    width:56px;

    height:56px;

    background:var(--primary-brown);

    color:#fff;

    border-radius:14px;

    display:flex;

    align-items:center;

    justify-content:center;

}


.title-text h2 {

    font-size:1.6rem;

    font-weight:800;

}


.title-text p {

    font-size:1rem;

    color:var(--text-muted);

    margin-top:2px;

}


.location-badge {

    background:#fff;

    border:1px solid var(--border-color);

    border-radius:12px;

    padding:10px 20px;

    display:flex;

    align-items:center;

    gap:12px;

}


.location-text strong {

    display:block;

    font-size:.95rem;

    font-weight:800;

}


.location-text span {

    font-size:.85rem;

    color:var(--text-muted);

}


/* =========================================================
   AVISO PERMISO
   ========================================================= */

.permission-denied {

    background:#fff3cd;

    border:2px solid #e0b341;

    border-radius:14px;

    padding:20px 24px;

    margin-bottom:24px;

    display:flex;

    align-items:center;

    gap:18px;

    animation:aparecerPermiso .3s ease;

}


.permission-icon {

    width:54px;

    height:54px;

    border-radius:50%;

    background:#e0b341;

    color:#fff;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:22px;

    flex-shrink:0;

}


.permission-content h3 {

    color:#795548;

    font-size:1.1rem;

    font-weight:800;

    margin-bottom:5px;

}


.permission-content p {

    color:#5d4037;

    font-size:.95rem;

    font-weight:600;

}


/* =========================================================
   SECTIONS
   ========================================================= */

.section-block {

    border:1px solid var(--border-color);

    border-radius:16px;

    padding:24px;

    margin-bottom:24px;

    background:#fff;

}


.section-header {

    display:flex;

    align-items:center;

    gap:12px;

    font-size:1.15rem;

    font-weight:800;

    margin-bottom:20px;

    border-bottom:2px solid #f3ece4;

    padding-bottom:10px;

}


/* =========================================================
   SEARCH
   ========================================================= */

.search-row {

    display:flex;

    gap:20px;

    align-items:flex-end;

    margin-bottom:20px;

    flex-wrap:wrap;

}


.search-group {

    flex:1;

    min-width:300px;

}


.search-group label {

    display:block;

    font-size:.98rem;

    font-weight:800;

    margin-bottom:8px;

}


.search-input-wrapper {

    display:flex;

    gap:12px;

}


.search-input-wrapper input {

    flex:1;

    padding:14px 18px;

    border:2px solid var(--border-color);

    border-radius:10px;

    font-size:1.05rem;

    font-weight:600;

    outline:none;

}


.btn-search {

    background:var(--primary-brown);

    color:#fff;

    border:none;

    border-radius:10px;

    padding:14px 24px;

    font-size:1rem;

    font-weight:800;

    cursor:pointer;

    display:flex;

    align-items:center;

    gap:10px;

}


/* =========================================================
   STATUS
   ========================================================= */

.status-alert {

    background:var(--light-green);

    border:2px solid #a8cda3;

    border-radius:12px;

    padding:12px 20px;

    display:none;

    align-items:center;

    gap:14px;

}


.status-alert svg {

    color:var(--status-active);

}


/* =========================================================
   RECOLECTOR
   ========================================================= */

.recollector-card {

    background:var(--card-inner-bg);

    border-radius:14px;

    padding:20px 24px;

    display:none;

    align-items:center;

    gap:24px;

    border:1px solid var(--border-color);

}


.recollector-avatar-box {

    width:75px;

    height:75px;

    border-radius:50%;

    background:#fff;

    border:3px solid var(--primary-brown);

    display:flex;

    align-items:center;

    justify-content:center;

    color:var(--primary-brown);

    flex-shrink:0;

    overflow:hidden;

}


.recollector-avatar-box img {

    width:100%;

    height:100%;

    object-fit:cover;

    border-radius:50%;

    display:none;

}


.recollector-avatar-box img.visible {

    display:block;

}


.recollector-avatar-box i.hidden {

    display:none;

}


.recollector-details {

    display:flex;

    flex-direction:column;

    gap:10px;

    flex:1;

}


.recollector-name {

    font-size:1.3rem;

    font-weight:800;

}


.recollector-grid {

    display:grid;

    grid-template-columns:repeat(2,1fr);

    gap:10px 40px;

    font-size:.98rem;

}


.recollector-grid-item {

    display:flex;

    align-items:center;

    gap:10px;

    font-weight:500;

}


.recollector-grid-item svg {

    color:var(--primary-brown);

    width:20px;

    height:20px;

}


/* =========================================================
   ENTREGAS
   ========================================================= */

.deliveries-grid {

    display:grid;

    grid-template-columns:repeat(3,1fr);

    gap:20px;

    margin-top:15px;

}


.delivery-card {

    background:var(--card-inner-bg);

    border:2px solid var(--border-color);

    border-radius:14px;

    padding:20px;

    display:flex;

    flex-direction:column;

    gap:16px;

}


.delivery-tag {

    background:var(--primary-brown);

    color:#fff;

    font-size:.9rem;

    font-weight:800;

    padding:6px 14px;

    border-radius:8px;

    display:inline-flex;

    align-items:center;

    gap:8px;

    align-self:flex-start;

}


.input-subgroup {

    display:flex;

    flex-direction:column;

    gap:6px;

}


.input-subgroup label {

    font-size:.9rem;

    font-weight:800;

}


.datetime-input,
.weight-input {

    display:flex;

    align-items:center;

    background:#fff;

    border:2px solid var(--border-color);

    border-radius:10px;

    padding:10px 14px;

    gap:10px;

}


.datetime-input input,
.weight-input input,
.obs-input {

    border:none;

    outline:none;

    background:transparent;

    font-size:1rem;

    font-weight:700;

    width:100%;

}


.weight-icon-bg {

    background:var(--primary-brown);

    color:#fff;

    width:32px;

    height:32px;

    border-radius:8px;

    display:flex;

    align-items:center;

    justify-content:center;

}


.unit-label {

    font-size:1rem;

    font-weight:800;

}


.obs-input {

    background:#fff;

    border:2px solid var(--border-color);

    border-radius:10px;

    padding:12px 14px;

}


/* =========================================================
   BOTONES
   ========================================================= */

.actions-bar {

    display:flex;

    justify-content:flex-end;

    gap:16px;

    margin-top:25px;

}


.btn-clean {

    background:#fff;

    border:2px solid var(--border-color);

    border-radius:12px;

    padding:14px 28px;

    font-size:1rem;

    font-weight:800;

    cursor:pointer;

}


.btn-save {

    background:var(--btn-green);

    border:none;

    border-radius:12px;

    padding:14px 34px;

    font-size:1rem;

    font-weight:800;

    color:#fff;

    cursor:pointer;

    display:flex;

    align-items:center;

    gap:10px;

}


.btn-save:hover {

    background:var(--btn-green-hover);

}


/* =========================================================
   INFO
   ========================================================= */

.info-note {

    background:var(--card-inner-bg);

    border:1px solid var(--border-color);

    border-radius:10px;

    padding:14px 20px;

    margin-top:20px;

    display:flex;

    align-items:center;

    gap:12px;

    font-size:.92rem;

    font-weight:600;

}


/* =========================================================
   FOOTER
   ========================================================= */

.app-footer {

    text-align:center;

    padding:20px;

    font-size:13px;

    font-weight:600;

    color:var(--text-muted);

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    border-top:1px solid var(--border-color);

    background:#efe8e0;

}


/* =========================================================
   CAMPOS BLOQUEADOS
   ========================================================= */

input:disabled,
button:disabled {

    cursor:not-allowed !important;

    opacity:.55 !important;

}


input:disabled {

    background:#eeeeee !important;

    color:#777 !important;

}


button:disabled {

    background:#999 !important;

    border-color:#999 !important;

}


/* =========================================================
   ANIMACIÓN
   ========================================================= */

@keyframes aparecerPermiso {

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
   RESPONSIVE
   ========================================================= */

@media(max-width:1100px) {

    .deliveries-grid {

        grid-template-columns:1fr;

    }

    .recollector-grid {

        grid-template-columns:1fr;

    }

}


@media(max-width:850px) {

    .app-layout {

        flex-direction:column;

    }

    .sidebar {

        width:100%;

    }

    .panel-header {

        flex-direction:column;

        gap:18px;

    }

}


@media(max-width:600px) {

    .main-content {

        padding:15px;

    }

    .main-card {

        padding:18px;

    }

    .top-header {

        padding:0 15px;

    }

    .brand {

        width:auto;

    }

    .brand-text {

        display:none;

    }

    .header-center {

        padding-left:10px;

    }

    .user-welcome p {

        display:none;

    }

    .search-input-wrapper {

        flex-direction:column;

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
                echo htmlspecialchars(
                    $nombre_mayordomo,
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

                <?php if ($foto_perfil_usuario !== ''): ?>

                    <img
                        src="<?php echo htmlspecialchars($foto_perfil_usuario, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Foto de perfil de <?php echo htmlspecialchars($nombre_mayordomo, ENT_QUOTES, 'UTF-8'); ?>"
                        class="header-profile-photo"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                    >

                    <i
                        data-lucide="user"
                        size="22"
                        style="display:none;"
                    ></i>

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


            <li class="nav-item active">

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


            <li class="nav-item">

                <a href="masignar_m.php">

                    <i class="fa-solid fa-user-gear"></i>

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


        <div class="main-card">


            <!-- =============================================
                 ENCABEZADO
                 ============================================== -->

            <div class="panel-header">


                <div class="panel-title">


                    <div class="title-icon">

                        <i
                            data-lucide="clipboard-check"
                            size="28"
                        ></i>

                    </div>


                    <div class="title-text">

                        <h2>
                            Registro de entrega de café
                        </h2>

                        <p>
                            Registra las entregas de café realizadas por el recolector en el día.
                        </p>

                    </div>


                </div>


                <div class="location-badge">

                    <i
                        data-lucide="map-pin"
                        size="20"
                    ></i>


                    <div class="location-text">

                        <strong id="label-finca-top">
                            Teruel, Huila
                        </strong>

                        <span>
                            Colombia
                        </span>

                    </div>

                </div>


            </div>



            <!-- =============================================
                 AVISO SIN PERMISO
                 ============================================== -->

            <div
                id="permiso-denegado"
                class="permission-denied"
                style="<?php
                    echo $tiene_permiso_general
                        ? 'display:none;'
                        : 'display:flex;';
                ?>"
            >


                <div class="permission-icon">

                    <i class="fa-solid fa-lock"></i>

                </div>


                <div class="permission-content">


                    <h3>
                        Sin permiso para registrar recolecciones
                    </h3>


                    <p>

                        El caficultor debe darte el permiso

                        para que puedas ingresar las recolecciones de los trabajadores.

                    </p>


                </div>


            </div>



            <!-- =============================================
                 PASO 1
                 ============================================== -->

            <div class="section-block">


                <div class="section-header">

                    <i
                        data-lucide="user"
                        size="22"
                    ></i>

                    <span>
                        1. Identificar recolector
                    </span>

                </div>



                <div class="search-row">


                    <div class="search-group">


                        <label for="cedula">

                            Número de cédula del recolector

                        </label>


                        <div class="search-input-wrapper">


                            <input
                                type="text"
                                id="cedula"
                                placeholder="Ingrese la cédula"
                            >


                            <button
                                type="button"
                                class="btn-search"
                                id="btn-buscar"
                            >

                                <i
                                    data-lucide="search"
                                    size="18"
                                ></i>

                                Buscar

                            </button>


                        </div>


                    </div>



                    <div
                        class="status-alert"
                        id="status-alert"
                    >

                        <i
                            data-lucide="check-circle-2"
                            size="24"
                        ></i>


                        <div>

                            <strong>
                                Recolector encontrado
                            </strong>

                            <span>
                                Información cargada correctamente
                            </span>

                        </div>


                    </div>


                </div>



                <input
                    type="hidden"
                    id="id_recolector_val"
                >


                <input
                    type="hidden"
                    id="id_finca_val"
                >



                <div
                    class="recollector-card"
                    id="recollector-card"
                >


                    <div class="recollector-avatar-box">

                        <img
                            id="rec-foto"
                            src=""
                            alt="Foto de perfil del recolector"
                        >

                        <i
                            id="rec-foto-icon"
                            data-lucide="user-check"
                            size="38"
                        ></i>

                    </div>


                    <div class="recollector-details">


                        <div
                            class="recollector-name"
                            id="rec-nombre"
                        >
                            --
                        </div>


                        <div class="recollector-grid">


                            <div class="recollector-grid-item">

                                <i
                                    data-lucide="id-card"
                                ></i>

                                <span>
                                    Cédula:
                                </span>

                                <strong id="rec-cedula">
                                    --
                                </strong>

                            </div>


                            <div class="recollector-grid-item">

                                <i
                                    data-lucide="navigation"
                                ></i>

                                <span>
                                    Finca / Vereda:
                                </span>

                                <strong id="rec-finca">
                                    --
                                </strong>

                            </div>


                            <div class="recollector-grid-item">

                                <i
                                    data-lucide="phone"
                                ></i>

                                <span>
                                    Teléfono:
                                </span>

                                <strong id="rec-telefono">
                                    --
                                </strong>

                            </div>


                            <div class="recollector-grid-item">

                                <i
                                    data-lucide="tag"
                                ></i>

                                <span>
                                    Rol:
                                </span>

                                <strong id="rec-rol">
                                    Recolector
                                </strong>

                            </div>


                        </div>


                    </div>


                </div>


            </div>



            <!-- =============================================
                 PASO 2
                 ============================================== -->

            <div class="section-block">


                <div class="section-header">

                    <i
                        data-lucide="shopping-bag"
                        size="22"
                    ></i>

                    <span>
                        2. Registrar entregas del día (3 entregas)
                    </span>

                </div>



                <div class="deliveries-grid">


                    <!-- =====================================
                         ENTREGA 1
                         ====================================== -->

                    <div class="delivery-card">


                        <div class="delivery-tag">

                            <i
                                data-lucide="shopping-bag"
                                size="16"
                            ></i>

                            Entrega 1

                        </div>


                        <div class="input-subgroup">


                            <label for="fecha_1">
                                Fecha y hora
                            </label>


                            <div class="datetime-input">

                                <i data-lucide="calendar"></i>

                                <input
                                    type="datetime-local"
                                    id="fecha_1"
                                >

                            </div>


                        </div>


                        <div class="input-subgroup">


                            <label for="kilos_1">
                                Peso entregado (kg)
                            </label>


                            <div class="weight-input">


                                <div class="weight-icon-bg">

                                    <i
                                        data-lucide="scale"
                                        size="16"
                                    ></i>

                                </div>


                                <input
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    id="kilos_1"
                                    placeholder="0.0"
                                >


                                <span class="unit-label">
                                    kg
                                </span>


                            </div>


                        </div>


                        <div class="input-subgroup">


                            <label for="obs_1">
                                Observaciones (opcional)
                            </label>


                            <input
                                type="text"
                                class="obs-input"
                                id="obs_1"
                                placeholder="Ej: Café maduro, buena calidad"
                            >


                        </div>


                    </div>



                    <!-- =====================================
                         ENTREGA 2
                         ====================================== -->

                    <div class="delivery-card">


                        <div class="delivery-tag">

                            <i
                                data-lucide="shopping-bag"
                                size="16"
                            ></i>

                            Entrega 2

                        </div>


                        <div class="input-subgroup">


                            <label for="fecha_2">
                                Fecha y hora
                            </label>


                            <div class="datetime-input">

                                <i data-lucide="calendar"></i>

                                <input
                                    type="datetime-local"
                                    id="fecha_2"
                                >

                            </div>


                        </div>


                        <div class="input-subgroup">


                            <label for="kilos_2">
                                Peso entregado (kg)
                            </label>


                            <div class="weight-input">


                                <div class="weight-icon-bg">

                                    <i
                                        data-lucide="scale"
                                        size="16"
                                    ></i>

                                </div>


                                <input
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    id="kilos_2"
                                    placeholder="0.0"
                                >


                                <span class="unit-label">
                                    kg
                                </span>


                            </div>


                        </div>


                        <div class="input-subgroup">


                            <label for="obs_2">
                                Observaciones (opcional)
                            </label>


                            <input
                                type="text"
                                class="obs-input"
                                id="obs_2"
                                placeholder="Ej: Café maduro"
                            >


                        </div>


                    </div>



                    <!-- =====================================
                         ENTREGA 3
                         ====================================== -->

                    <div class="delivery-card">


                        <div class="delivery-tag">

                            <i
                                data-lucide="shopping-bag"
                                size="16"
                            ></i>

                            Entrega 3

                        </div>


                        <div class="input-subgroup">


                            <label for="fecha_3">
                                Fecha y hora
                            </label>


                            <div class="datetime-input">

                                <i data-lucide="calendar"></i>

                                <input
                                    type="datetime-local"
                                    id="fecha_3"
                                >

                            </div>


                        </div>


                        <div class="input-subgroup">


                            <label for="kilos_3">
                                Peso entregado (kg)
                            </label>


                            <div class="weight-input">


                                <div class="weight-icon-bg">

                                    <i
                                        data-lucide="scale"
                                        size="16"
                                    ></i>

                                </div>


                                <input
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    id="kilos_3"
                                    placeholder="0.0"
                                >


                                <span class="unit-label">
                                    kg
                                </span>


                            </div>


                        </div>


                        <div class="input-subgroup">


                            <label for="obs_3">
                                Observaciones (opcional)
                            </label>


                            <input
                                type="text"
                                class="obs-input"
                                id="obs_3"
                                placeholder="Ej: Buena calidad"
                            >


                        </div>


                    </div>


                </div>


            </div>



            <!-- =============================================
                 BOTONES
                 ============================================== -->

            <div class="actions-bar">


                <button
                    type="button"
                    class="btn-clean"
                    id="btn-limpiar"
                >

                    <i
                        data-lucide="eraser"
                        size="18"
                    ></i>

                    Limpiar

                </button>


                <button
                    type="button"
                    class="btn-save"
                    id="btn-guardar"
                >

                    <i
                        data-lucide="save"
                        size="18"
                    ></i>

                    Guardar entrega

                </button>


            </div>



            <!-- =============================================
                 NOTA
                 ============================================== -->

            <div class="info-note">

                <i data-lucide="info"></i>

                <span>

                    <strong>
                        Nota:
                    </strong>

                    Cada una de las 3 entregas se guardará por separado indicando su hora exacta y su número correspondiente (1, 2 o 3).

                </span>

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
            Café Control
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
   PERMISO GENERAL
   ========================================================= */

const tienePermisoGeneral =
    <?php
    echo $tiene_permiso_general
        ? 'true'
        : 'false';
    ?>;


/* =========================================================
   FUNCIÓN PARA BLOQUEAR TODA LA INTERFAZ
   ========================================================= */

function bloquearInterfaz() {


    const elementos = [

        document.getElementById('cedula'),

        document.getElementById('btn-buscar'),

        document.getElementById('fecha_1'),

        document.getElementById('kilos_1'),

        document.getElementById('obs_1'),

        document.getElementById('fecha_2'),

        document.getElementById('kilos_2'),

        document.getElementById('obs_2'),

        document.getElementById('fecha_3'),

        document.getElementById('kilos_3'),

        document.getElementById('obs_3'),

        document.getElementById('btn-guardar'),

        document.getElementById('btn-limpiar')

    ];


    elementos.forEach(function(elemento) {


        if (elemento) {

            elemento.disabled = true;

        }


    });


}


/* =========================================================
   FUNCIÓN PARA DESBLOQUEAR INTERFAZ
   ========================================================= */

function desbloquearInterfaz() {


    const elementos = [

        document.getElementById('cedula'),

        document.getElementById('btn-buscar'),

        document.getElementById('fecha_1'),

        document.getElementById('kilos_1'),

        document.getElementById('obs_1'),

        document.getElementById('fecha_2'),

        document.getElementById('kilos_2'),

        document.getElementById('obs_2'),

        document.getElementById('fecha_3'),

        document.getElementById('kilos_3'),

        document.getElementById('obs_3'),

        document.getElementById('btn-guardar'),

        document.getElementById('btn-limpiar')

    ];


    elementos.forEach(function(elemento) {


        if (elemento) {

            elemento.disabled = false;

        }


    });


}


/* =========================================================
   INICIALIZAR FECHAS
   ========================================================= */

function inicializarFechas() {


    const ahora =
        new Date();


    const offset =
        ahora.getTimezoneOffset()
        * 60000;


    const fechaHoraLocal =

        new Date(
            ahora.getTime()
            - offset
        )
        .toISOString()
        .slice(0,16);


    document.getElementById('fecha_1').value =
        fechaHoraLocal;


    document.getElementById('fecha_2').value =
        fechaHoraLocal;


    document.getElementById('fecha_3').value =
        fechaHoraLocal;

}


/* =========================================================
   EJECUTAR AL CARGAR
   ========================================================= */

inicializarFechas();


if (!tienePermisoGeneral) {

    bloquearInterfaz();

}


/* =========================================================
   BOTÓN BUSCAR
   ========================================================= */

document
    .getElementById('btn-buscar')
    .addEventListener(
        'click',
        buscarRecolector
    );


/* =========================================================
   ENTER EN CÉDULA
   ========================================================= */

document
    .getElementById('cedula')
    .addEventListener(
        'keypress',
        function(e) {


            if (e.key === 'Enter') {


                e.preventDefault();


                buscarRecolector();


            }


        }
    );


/* =========================================================
   BUSCAR RECOLECTOR
   ========================================================= */

function buscarRecolector() {


    /* -----------------------------------------------
       SI NO HAY PERMISO GENERAL
       ----------------------------------------------- */

    if (!tienePermisoGeneral) {


        Swal.fire({

            icon:'warning',

            title:'Sin permiso',

            text:
                'El caficultor debe darte el permiso "Registrar recolecciones" antes de ingresar datos.',

            confirmButtonText:'Entendido'

        });


        bloquearInterfaz();


        return;

    }


    const cedula =

        document
        .getElementById('cedula')
        .value
        .trim();


    if (!cedula) {


        Swal.fire({

            icon:'warning',

            title:'Atención',

            text:
                'Por favor ingresa la cédula del recolector.',

            confirmButtonText:'Entendido'

        });


        return;

    }


    const formData =
        new FormData();


    formData.append(
        'cedula',
        cedula
    );


    fetch(
        'recolecciones_m.php?action=buscar_recolector',
        {

            method:'POST',

            body:formData

        }
    )


    .then(function(res) {

        return res.json();

    })


    .then(function(data) {


        /* =========================================
           SIN PERMISO
           ========================================= */

        if (data.permission_denied) {


            document
                .getElementById(
                    'permiso-denegado'
                )
                .style.display =
                    'flex';


            ocultarDetalles();


            bloquearInterfaz();


            Swal.fire({

                icon:'warning',

                title:'Sin permiso',

                text:
                    data.message,

                confirmButtonText:
                    'Entendido'

            });


            return;

        }


        /* =========================================
           CORRECTO
           ========================================= */

        if (data.success) {


            desbloquearInterfaz();


            document
                .getElementById(
                    'permiso-denegado'
                )
                .style.display =
                    'none';


            const rec =
                data.data;


            /* =========================================
               MOSTRAR FOTO DE PERFIL
               ========================================= */

            const foto =
                document.getElementById('rec-foto');

            const fotoIcon =
                document.getElementById('rec-foto-icon');


            if (
                rec.foto_perfil &&
                String(rec.foto_perfil).trim() !== ''
            ) {

                foto.src =
                    rec.foto_perfil;

                foto.classList.add('visible');

                fotoIcon.classList.add('hidden');

            } else {

                foto.src = '';

                foto.classList.remove('visible');

                fotoIcon.classList.remove('hidden');

            }


            /* Si la ruta de la foto no existe, volver al icono */

            foto.onerror = function() {

                foto.src = '';

                foto.classList.remove('visible');

                fotoIcon.classList.remove('hidden');

            };


            document
                .getElementById(
                    'id_recolector_val'
                )
                .value =
                    rec.id_usuario;


            document
                .getElementById(
                    'id_finca_val'
                )
                .value =
                    rec.id_finca;


            document
                .getElementById(
                    'rec-nombre'
                )
                .textContent =

                    rec.nombres +
                    ' ' +
                    rec.apellidos;


            document
                .getElementById(
                    'rec-cedula'
                )
                .textContent =
                    rec.documento;


            document
                .getElementById(
                    'rec-finca'
                )
                .textContent =

                    rec.nombre_finca +
                    ' / ' +
                    rec.ubicacion_vereda;


            document
                .getElementById(
                    'rec-telefono'
                )
                .textContent =

                    rec.telefono ||
                    'Sin teléfono';


            document
                .getElementById(
                    'rec-rol'
                )
                .textContent =
                    rec.rol;


            document
                .getElementById(
                    'label-finca-top'
                )
                .textContent =
                    rec.nombre_finca;


            document
                .getElementById(
                    'status-alert'
                )
                .style.display =
                    'flex';


            document
                .getElementById(
                    'recollector-card'
                )
                .style.display =
                    'flex';


            lucide.createIcons();


        } else {


            Swal.fire({

                icon:'error',

                title:'No encontrado',

                text:
                    data.message,

                confirmButtonText:
                    'Entendido'

            });


            ocultarDetalles();


        }


    })


    .catch(function(error) {


        console.error(error);


        Swal.fire({

            icon:'error',

            title:'Error',

            text:
                'Ocurrió un error al consultar el servidor.',

            confirmButtonText:
                'Entendido'

        });


    });

}


/* =========================================================
   OCULTAR DETALLES
   ========================================================= */

function ocultarDetalles() {


    document
        .getElementById(
            'id_recolector_val'
        )
        .value =
            '';


    document
        .getElementById(
            'id_finca_val'
        )
        .value =
            '';


    document
        .getElementById(
            'status-alert'
        )
        .style.display =
            'none';


    document
        .getElementById(
            'recollector-card'
        )
        .style.display =
            'none';


    /* Restablecer foto del recolector */

    const foto =
        document.getElementById('rec-foto');

    const fotoIcon =
        document.getElementById('rec-foto-icon');


    if (foto) {

        foto.src = '';

        foto.classList.remove('visible');

    }


    if (fotoIcon) {

        fotoIcon.classList.remove('hidden');

    }


}


/* =========================================================
   BOTÓN GUARDAR
   ========================================================= */

document
    .getElementById('btn-guardar')
    .addEventListener(
        'click',
        guardarRecolecciones
    );


/* =========================================================
   GUARDAR
   ========================================================= */

function guardarRecolecciones() {


    /* -----------------------------------------------
       COMPROBAR PERMISO
       ----------------------------------------------- */

    if (!tienePermisoGeneral) {


        Swal.fire({

            icon:'warning',

            title:'Sin permiso',

            text:
                'No tienes permiso para registrar recolecciones.',

            confirmButtonText:
                'Entendido'

        });


        bloquearInterfaz();


        return;

    }


    const idRecolector =

        document
        .getElementById(
            'id_recolector_val'
        )
        .value;


    const idFinca =

        document
        .getElementById(
            'id_finca_val'
        )
        .value;


    /* -----------------------------------------------
       VALIDAR RECOLECTOR
       ----------------------------------------------- */

    if (!idRecolector || !idFinca) {


        Swal.fire({

            icon:'warning',

            title:'Atención',

            text:
                'Primero debes buscar un recolector autorizado.',

            confirmButtonText:
                'Entendido'

        });


        return;

    }


    /* -----------------------------------------------
       OBTENER ENTREGAS
       ----------------------------------------------- */

    const entregas = [


        {

            num_entrega:1,

            fecha_hora:

                document
                .getElementById('fecha_1')
                .value,

            kilos:

                document
                .getElementById('kilos_1')
                .value,

            observaciones:

                document
                .getElementById('obs_1')
                .value

        },


        {

            num_entrega:2,

            fecha_hora:

                document
                .getElementById('fecha_2')
                .value,

            kilos:

                document
                .getElementById('kilos_2')
                .value,

            observaciones:

                document
                .getElementById('obs_2')
                .value

        },


        {

            num_entrega:3,

            fecha_hora:

                document
                .getElementById('fecha_3')
                .value,

            kilos:

                document
                .getElementById('kilos_3')
                .value,

            observaciones:

                document
                .getElementById('obs_3')
                .value

        }


    ];


    let hayKilos = false;


    entregas.forEach(function(entrega) {


        if (
            parseFloat(entrega.kilos) > 0
        ) {

            hayKilos = true;

        }


    });


    if (!hayKilos) {


        Swal.fire({

            icon:'warning',

            title:'Atención',

            text:
                'Ingresa al menos el peso de una entrega.',

            confirmButtonText:
                'Entendido'

        });


        return;

    }


    /* -----------------------------------------------
       FORM DATA
       ----------------------------------------------- */

    const formData =
        new FormData();


    formData.append(
        'id_recolector',
        idRecolector
    );


    formData.append(
        'id_finca',
        idFinca
    );


    entregas.forEach(
        function(e, idx) {


            formData.append(

                `entregas[${idx}][num_entrega]`,

                e.num_entrega

            );


            formData.append(

                `entregas[${idx}][fecha_hora]`,

                e.fecha_hora

            );


            formData.append(

                `entregas[${idx}][kilos]`,

                e.kilos

            );


            formData.append(

                `entregas[${idx}][observaciones]`,

                e.observaciones

            );


        }
    );


    /* -----------------------------------------------
       ENVIAR
       ----------------------------------------------- */

    fetch(

        'recolecciones_m.php?action=guardar_recolecciones',

        {

            method:'POST',

            body:formData

        }

    )


    .then(function(res) {

        return res.json();

    })


    .then(function(data) {


        /* =========================================
           SIN PERMISO
           ========================================= */

        if (data.permission_denied) {


            document
                .getElementById(
                    'permiso-denegado'
                )
                .style.display =
                    'flex';


            bloquearInterfaz();


            Swal.fire({

                icon:'warning',

                title:'Sin permiso',

                text:
                    data.message,

                confirmButtonText:
                    'Entendido'

            });


            return;

        }


        /* =========================================
           CORRECTO
           ========================================= */

        if (data.success) {


            Swal.fire({

                icon:'success',

                title:'¡Éxito!',

                text:
                    data.message,

                confirmButtonText:
                    'Aceptar'

            })


            .then(function() {


                limpiarCampos();


                /*
                 * Volver a bloquear si ya no
                 * existe permiso.
                 */


                if (!tienePermisoGeneral) {

                    bloquearInterfaz();

                }


            });


        } else {


            Swal.fire({

                icon:'error',

                title:'Error',

                text:
                    data.message,

                confirmButtonText:
                    'Entendido'

            });


        }


    })


    .catch(function(error) {


        console.error(error);


        Swal.fire({

            icon:'error',

            title:'Error',

            text:
                'No se pudieron guardar las entregas.',

            confirmButtonText:
                'Entendido'

        });


    });

}


/* =========================================================
   BOTÓN LIMPIAR
   ========================================================= */

document
    .getElementById('btn-limpiar')
    .addEventListener(
        'click',
        limpiarCampos
    );


/* =========================================================
   LIMPIAR CAMPOS
   ========================================================= */

function limpiarCampos() {


    document
        .getElementById('cedula')
        .value =
            '';


    document
        .getElementById('kilos_1')
        .value =
            '';


    document
        .getElementById('kilos_2')
        .value =
            '';


    document
        .getElementById('kilos_3')
        .value =
            '';


    document
        .getElementById('obs_1')
        .value =
            '';


    document
        .getElementById('obs_2')
        .value =
            '';


    document
        .getElementById('obs_3')
        .value =
            '';


    ocultarDetalles();


    document
        .getElementById(
            'permiso-denegado'
        )
        .style.display =

            tienePermisoGeneral
                ? 'none'
                : 'flex';


    inicializarFechas();


    /*
     * Si tiene permiso general,
     * dejamos la interfaz habilitada.
     *
     * Si no tiene permiso,
     * queda completamente bloqueada.
     */

    if (tienePermisoGeneral) {

        desbloquearInterfaz();

    } else {

        bloquearInterfaz();

    }

}


/* =========================================================
   DESACTIVAR ENTER SI NO TIENE PERMISO
   ========================================================= */

document
    .getElementById('cedula')
    .addEventListener(
        'keydown',
        function(e) {


            if (
                !tienePermisoGeneral &&
                e.key === 'Enter'
            ) {

                e.preventDefault();

            }


        }
    );


</script>


</body>

</html>