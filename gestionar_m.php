<?php
session_start();

require_once 'assets/inc/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = (int) $_SESSION['id_usuario'];

$mensaje = "";
$error = "";
$permiso_gestionar_mayordomos = 0;

/*
|--------------------------------------------------------------------------
| 1. DATOS DEL USUARIO QUE INICIÓ SESIÓN
|--------------------------------------------------------------------------
| La foto se consulta desde perfiles_usuario.
| En esta interfaz solamente se muestra; no se permite editarla.
*/
$sql_user = "
    SELECT
        u.id_usuario,
        u.nombres,
        u.apellidos,
        u.usuario,
        u.rol,
        u.estado,
        COALESCE(
            NULLIF(TRIM(pu.foto_perfil), ''),
            NULLIF(TRIM(pr.foto_perfil), ''),
            NULLIF(TRIM(u.foto_perfil), '')
        ) AS foto_perfil
    FROM usuarios u
    LEFT JOIN perfiles_usuario pu
        ON pu.id_usuario = u.id_usuario
    LEFT JOIN perfiles_recolectores pr
        ON pr.id_usuario = u.id_usuario
    WHERE u.id_usuario = ?
    LIMIT 1
";

$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id_usuario);
$stmt_user->execute();
$datos_sesion = $stmt_user->get_result()->fetch_assoc();

if (!$datos_sesion) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$nombre_usuario_logueado = trim(
    ($datos_sesion['nombres'] ?? '') . ' ' .
    ($datos_sesion['apellidos'] ?? '')
);

/*
|--------------------------------------------------------------------------
| 2. OBTENER PERMISO PARA GESTIONAR MAYORDOMOS
|--------------------------------------------------------------------------
| Si el usuario tiene al menos una asignación con gestionar_mayordomos = 1,
| puede utilizar esta interfaz.
|
| Si está en 0, la página se puede consultar, pero NO se puede:
| - agregar
| - editar
| - cambiar estado
| - eliminar
*/
$sql_permiso = "
    SELECT COALESCE(MAX(gestionar_mayordomos), 0) AS permiso
    FROM permisos_mayordomo
    WHERE id_mayordomo = ?
";

$stmt_permiso = $con->prepare($sql_permiso);
$stmt_permiso->bind_param("i", $id_usuario);
$stmt_permiso->execute();
$res_permiso = $stmt_permiso->get_result()->fetch_assoc();

$permiso_gestionar_mayordomos = (int)($res_permiso['permiso'] ?? 0);
$puede_gestionar = ($permiso_gestionar_mayordomos === 1);

/*
|--------------------------------------------------------------------------
| 3. FOTO DE PERFIL
|--------------------------------------------------------------------------
*/
$foto_perfil_db = trim((string)($datos_sesion['foto_perfil'] ?? ''));
$foto_perfil_url = '';

if ($foto_perfil_db !== '') {
    $foto_perfil_db = str_replace('\\', '/', $foto_perfil_db);

    if (preg_match('#^https?://#i', $foto_perfil_db)) {
        $foto_perfil_url = $foto_perfil_db;
    } else {
        $foto_perfil_url = ltrim($foto_perfil_db, '/');

        if (
            strpos($foto_perfil_url, '/') === false &&
            !file_exists(__DIR__ . '/' . $foto_perfil_url)
        ) {
            $carpetas_foto = [
                'images/',
                'uploads/',
                'uploads/perfiles/',
                'uploads/perfil/',
                'assets/images/',
                'assets/img/'
            ];

            foreach ($carpetas_foto as $carpeta) {
                if (file_exists(__DIR__ . '/' . $carpeta . $foto_perfil_url)) {
                    $foto_perfil_url = $carpeta . $foto_perfil_url;
                    break;
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| 4. FUNCIÓN PARA MOSTRAR MENSAJE DE PERMISO
|--------------------------------------------------------------------------
*/
function bloquear_accion(): void
{
    global $error;
    $error = "Debes tener permiso para esta acción.";
}

/*
|--------------------------------------------------------------------------
| 5. AGREGAR / EDITAR USUARIO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    $accion = trim($_POST['accion']);
    $id_edit = isset($_POST['id_usuario']) ? (int) $_POST['id_usuario'] : 0;

    /*
     * Cualquier modificación requiere permiso.
     */
    if (!$puede_gestionar) {
        bloquear_accion();
    } else {

        $documento = trim($_POST['documento'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        if ($accion === 'agregar') {

            $contrasena_plana = $_POST['contrasena'] ?? '';

            if ($contrasena_plana === '') {
                $error = "La contraseña es obligatoria para agregar un usuario.";
            } else {

                $contrasena = password_hash($contrasena_plana, PASSWORD_BCRYPT);

                $stmt = $con->prepare("
                    INSERT INTO usuarios
                    (
                        documento,
                        nombres,
                        apellidos,
                        telefono,
                        correo,
                        usuario,
                        contrasena,
                        rol,
                        estado
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssssssss",
                    $documento,
                    $nombres,
                    $apellidos,
                    $telefono,
                    $correo,
                    $usuario,
                    $contrasena,
                    $rol,
                    $estado
                );

                if ($stmt->execute()) {
                    $mensaje = "Usuario agregado exitosamente.";
                } else {
                    $error = "Error al agregar usuario: " . $con->error;
                }
            }

        } elseif ($accion === 'editar' && $id_edit > 0) {

            if (!empty($_POST['contrasena'])) {

                $contrasena = password_hash(
                    $_POST['contrasena'],
                    PASSWORD_BCRYPT
                );

                $stmt = $con->prepare("
                    UPDATE usuarios
                    SET
                        documento = ?,
                        nombres = ?,
                        apellidos = ?,
                        telefono = ?,
                        correo = ?,
                        usuario = ?,
                        contrasena = ?,
                        rol = ?,
                        estado = ?
                    WHERE id_usuario = ?
                ");

                $stmt->bind_param(
                    "sssssssssi",
                    $documento,
                    $nombres,
                    $apellidos,
                    $telefono,
                    $correo,
                    $usuario,
                    $contrasena,
                    $rol,
                    $estado,
                    $id_edit
                );

            } else {

                $stmt = $con->prepare("
                    UPDATE usuarios
                    SET
                        documento = ?,
                        nombres = ?,
                        apellidos = ?,
                        telefono = ?,
                        correo = ?,
                        usuario = ?,
                        rol = ?,
                        estado = ?
                    WHERE id_usuario = ?
                ");

                $stmt->bind_param(
                    "ssssssssi",
                    $documento,
                    $nombres,
                    $apellidos,
                    $telefono,
                    $correo,
                    $usuario,
                    $rol,
                    $estado,
                    $id_edit
                );
            }

            if ($stmt->execute()) {
                $mensaje = "Usuario actualizado correctamente.";
            } else {
                $error = "Error al actualizar usuario: " . $con->error;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| 6. CAMBIAR ESTADO
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['accion']) &&
    $_GET['accion'] === 'suspender' &&
    isset($_GET['id'], $_GET['estado'])
) {

    if (!$puede_gestionar) {
        bloquear_accion();
    } else {

        $id_target = (int) $_GET['id'];
        $nuevo_estado = $_GET['estado'] === 'activo'
            ? 'inactivo'
            : 'activo';

        $stmt = $con->prepare("
            UPDATE usuarios
            SET estado = ?
            WHERE id_usuario = ?
        ");

        $stmt->bind_param(
            "si",
            $nuevo_estado,
            $id_target
        );

        if ($stmt->execute()) {
            $mensaje = "El estado del usuario ha sido actualizado.";
        } else {
            $error = "Error al cambiar el estado: " . $con->error;
        }
    }
}

/*
|--------------------------------------------------------------------------
| 7. ELIMINAR USUARIO
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['accion']) &&
    $_GET['accion'] === 'eliminar' &&
    isset($_GET['id'])
) {

    if (!$puede_gestionar) {
        bloquear_accion();
    } else {

        $id_target = (int) $_GET['id'];

        /*
         * Evita que el usuario intente eliminarse a sí mismo.
         */
        if ($id_target === $id_usuario) {
            $error = "No puedes eliminar el usuario con el que tienes iniciada la sesión.";
        } else {

            $stmt = $con->prepare("
                DELETE FROM usuarios
                WHERE id_usuario = ?
            ");

            $stmt->bind_param("i", $id_target);

            if ($stmt->execute()) {
                $mensaje = "Usuario eliminado correctamente.";
            } else {
                $error = "No se puede eliminar el usuario porque tiene registros asociados.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| 8. CARGAR USUARIO PARA EDITAR
|--------------------------------------------------------------------------
*/
$usuarioEdit = null;

if (
    isset($_GET['accion']) &&
    $_GET['accion'] === 'pre_editar' &&
    isset($_GET['id'])
) {

    if (!$puede_gestionar) {
        bloquear_accion();
    } else {

        $id_editar = (int) $_GET['id'];

        $stmt = $con->prepare("
            SELECT *
            FROM usuarios
            WHERE id_usuario = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $id_editar);
        $stmt->execute();

        $usuarioEdit = $stmt->get_result()->fetch_assoc();

        if (!$usuarioEdit) {
            $error = "El usuario seleccionado no existe.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| 9. OBTENER TODOS LOS USUARIOS
|--------------------------------------------------------------------------
*/
$resultado_usuarios = $con->query("
    SELECT *
    FROM usuarios
    ORDER BY id_usuario DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Café Control</title>

    <!-- Bootstrap, FontAwesome y Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --verde-oscuro: #1f5220;
            --verde-principal: #225b24;
            --verde-sidebar: #193f1a;
            --verde-activo: #496849;
            --verde-claro: #e8f0e7;
            --fondo: #f4f0eb;
            --blanco: #ffffff;
            --texto: #171717;
            --texto-secundario: #6f665e;
            --borde: #ded7d0;
            --cafe: #8c5a2b;
            --rojo: #c53b3b;
            --sombra: 0 12px 30px rgba(40, 30, 20, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: var(--fondo);
            color: var(--texto);
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a {
            text-decoration: none;
        }

        /* =========================
           BARRA SUPERIOR
        ========================== */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 87px;
            background: var(--verde-oscuro);
            color: var(--blanco);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px 0 32px;
            z-index: 1100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 290px;
        }

        .logo-circle-nav {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
            border: 2px solid rgba(255,255,255,.85);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .logo-circle-nav img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .brand-name {
            margin: 0;
            color: #ffffff;
            font-size: 1.38rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .menu-toggle {
            display: none;
            background: transparent;
            border: 0;
            color: #ffffff;
            font-size: 1.35rem;
            margin-left: 15px;
        }

        .welcome-text {
            margin-left: 26px;
        }

        .welcome-text h5 {
            margin: 0;
            color: #ffffff;
            font-size: 1.15rem;
            line-height: 1.2;
            font-weight: 800;
        }

        .welcome-text p {
            margin: 4px 0 0;
            color: rgba(255,255,255,.78);
            font-size: .86rem;
            font-weight: 500;
        }

        .user-nav-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .notification-icon {
            position: relative;
            color: #fff;
            font-size: 1.18rem;
        }

        .notification-badge {
            position: absolute;
            top: -1px;
            right: -2px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f5d36f;
            border: 1px solid var(--verde-oscuro);
        }

        .profile-dropdown {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .user-avatar,
        .user-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            background: #d5d0ca;
            color: var(--verde-oscuro);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 800;
        }

        /* =========================
           ESTRUCTURA
        ========================== */
        .layout-wrapper {
            display: flex;
            min-height: calc(100vh - 87px);
            margin-top: 87px;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 87px;
            bottom: 0;
            width: 286px;
            background: var(--verde-sidebar);
            padding: 30px 21px 24px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar-item {
            margin-bottom: 9px;
        }

        .sidebar-item a {
            min-height: 53px;
            display: flex;
            align-items: center;
            gap: 17px;
            padding: 0 20px;
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            transition: .2s ease;
        }

        .sidebar-item a i {
            width: 25px;
            text-align: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .sidebar-item a:hover {
            background: rgba(255,255,255,.08);
        }

        .sidebar-item.active a {
            background: var(--verde-activo);
            box-shadow: inset 4px 0 0 #ffffff;
        }

        .main-content {
            width: calc(100% - 286px);
            margin-left: 286px;
            padding: 34px 38px 90px;
        }

        .page-title {
            margin: 0 0 23px;
            color: var(--verde-sidebar);
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -.025em;
        }

        /* =========================
           ALERTAS
        ========================== */
        .alert {
            border: 0;
            border-radius: 13px !important;
            box-shadow: 0 7px 18px rgba(30, 30, 30, .06);
            font-weight: 600;
        }

        .alert-success {
            background: #e7f3e5;
            color: #225b24;
        }

        .alert-danger {
            background: #f7e2e1;
            color: #8d2422;
        }

        /* =========================
           TARJETAS
        ========================== */
        .card {
            border: 1px solid var(--borde) !important;
            border-radius: 16px !important;
            overflow: hidden;
            box-shadow: var(--sombra) !important;
            background: #ffffff;
        }

        .card-header {
            border-bottom: 0 !important;
            padding: 17px 22px !important;
            font-weight: 800 !important;
            font-size: 1rem !important;
        }

        .card-header.bg-success {
            background: var(--verde-principal) !important;
        }

        .card-header.bg-dark {
            background: var(--verde-sidebar) !important;
        }

        .card-body {
            padding: 24px !important;
        }

        /* =========================
           FORMULARIO
        ========================== */
        .form-label {
            display: block;
            margin-bottom: 7px;
            color: #2f241d;
            font-size: .84rem;
            font-weight: 800;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border: 1px solid #d5cec7;
            border-radius: 9px;
            padding: 10px 13px;
            font-size: .92rem;
            font-weight: 500;
            color: #26211d;
            background: #ffffff;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #6d956e;
            box-shadow: 0 0 0 .22rem rgba(70, 113, 72, .14);
        }

        .btn-custom-action {
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 9px;
            font-size: .9rem;
            font-weight: 800;
        }

        .btn-success {
            background: var(--verde-principal) !important;
            border-color: var(--verde-principal) !important;
        }

        .btn-success:hover {
            background: #19481c !important;
            border-color: #19481c !important;
        }

        /* =========================
           TABLA
        ========================== */
        .table-responsive {
            border-radius: 0 0 16px 16px;
        }

        .table {
            margin-bottom: 0;
            font-size: .9rem;
        }

        .table thead th {
            background: #f2eee9 !important;
            color: #4b423a;
            border-bottom: 1px solid var(--borde);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .045em;
            padding: 14px 13px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 13px;
            vertical-align: middle;
            border-color: #eee9e4;
            color: #312a25;
        }

        .table-hover tbody tr:hover {
            background-color: #faf8f5;
        }

        .badge {
            border-radius: 999px !important;
            padding: 7px 11px;
            font-size: .72rem;
            font-weight: 800;
        }

        .badge.bg-info {
            background: #e1ece1 !important;
            color: #29592b !important;
        }

        .badge.bg-success {
            background: #dcefdc !important;
            color: #245e27 !important;
        }

        .badge.bg-danger {
            background: #f5dfdf !important;
            color: #9b2828 !important;
        }

        .table code {
            color: #4b3b30 !important;
            background: #f3efe9 !important;
        }

        .btn-outline-warning,
        .btn-outline-secondary,
        .btn-outline-danger {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-outline-warning {
            color: #8a6021;
            border-color: #d7b36e;
        }

        .btn-outline-warning:hover {
            color: #ffffff;
            background: #9c722b;
            border-color: #9c722b;
        }

        .btn-outline-secondary {
            color: #5e655e;
            border-color: #c7ccc7;
        }

        .btn-outline-secondary:hover {
            color: #ffffff;
            background: #687268;
            border-color: #687268;
        }

        .btn-outline-danger {
            color: #b63d3d;
            border-color: #df9d9d;
        }

        .btn-outline-danger:hover {
            color: #ffffff;
            background: #b63d3d;
            border-color: #b63d3d;
        }

        /* =========================
           PIE DE PAGINA
        ========================== */
        .app-footer {
            margin-left: 286px;
            padding: 20px 28px;
            border-top: 1px solid var(--borde);
            background: #f4f0eb;
            color: #6c6259;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            font-size: .84rem;
            font-weight: 600;
            text-align: center;
        }

        .app-footer i {
            color: var(--verde-principal);
        }

        /* =========================
           PERMISO DE GESTIÓN
        ========================== */
        .permission-alert {
            display: flex;
            align-items: center;
            gap: 22px;
            padding: 20px 30px;
            border: 2px solid #e7b529;
            border-radius: 16px;
            background: #fff4cf;
            color: #6f4b24;
        }

        .permission-icon {
            width: 70px;
            height: 70px;
            min-width: 70px;
            border-radius: 50%;
            background: #e4b23b;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
        }

        .permission-alert h4 {
            margin: 0 0 5px;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .permission-alert p {
            margin: 0;
            font-size: .92rem;
            font-weight: 600;
        }

        .card-disabled {
            opacity: .62;
        }

        .card-disabled .form-control,
        .card-disabled .form-select {
            cursor: not-allowed;
            background: #f0f0ec;
        }

        .card-disabled .card-header {
            filter: grayscale(.2);
        }

        /* =========================
           PERFIL DEL USUARIO
        ========================== */
        .perfil-menu {
            min-width: 310px;
            padding: 0;
            border-radius: 15px !important;
            overflow: hidden;
        }

        .perfil-contenedor {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px;
            background: #ffffff;
        }

        .perfil-foto-grande {
            width: 72px;
            height: 72px;
            min-width: 72px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #225b24;
            background: #e8f0e7;
        }

        .perfil-foto-grande img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .perfil-inicial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dcefdc;
            color: #225b24;
            font-size: 1.7rem;
            font-weight: 800;
        }

        .perfil-datos {
            min-width: 0;
        }

        .perfil-datos h6 {
            margin: 0 0 7px;
            color: #193f1a;
            font-size: 1rem;
            font-weight: 800;
        }

        .perfil-datos p {
            margin: 3px 0;
            color: #6f665e;
            font-size: .78rem;
            font-weight: 600;
        }

        .perfil-datos p i {
            color: #225b24;
            width: 15px;
        }

        .estado-activo {
            color: #23843a;
            font-weight: 800;
        }

        .estado-inactivo {
            color: #c53b3b;
            font-weight: 800;
        }

        .estado-activo::before {
            content: "";
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 5px;
            border-radius: 50%;
            background: #23843a;
        }

        .estado-inactivo::before {
            content: "";
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 5px;
            border-radius: 50%;
            background: #c53b3b;
        }

        /* =========================
           RESPONSIVE
        ========================== */
        @media (max-width: 1200px) {
            .sidebar {
                width: 255px;
            }

            .main-content,
            .app-footer {
                margin-left: 255px;
                width: calc(100% - 255px);
            }

            .brand-section {
                min-width: auto;
            }

            .welcome-text {
                margin-left: 18px;
            }
        }

        @media (max-width: 992px) {
            .top-navbar {
                padding: 0 18px;
            }

            .menu-toggle {
                display: inline-block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
                width: 275px;
                box-shadow: 12px 0 28px rgba(0,0,0,.12);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .main-content,
            .app-footer {
                margin-left: 0;
                width: 100%;
            }

            .main-content {
                padding: 28px 20px 70px;
            }

            .brand-section {
                gap: 11px;
            }

            .brand-name {
                font-size: 1.2rem;
            }

            .welcome-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .top-navbar {
                height: 74px;
            }

            .layout-wrapper {
                margin-top: 74px;
                min-height: calc(100vh - 74px);
            }

            .sidebar {
                top: 74px;
            }

            .main-content {
                padding: 22px 14px 50px;
            }

            .page-title {
                font-size: 1.4rem;
                margin-bottom: 18px;
            }

            .card-body {
                padding: 18px !important;
            }

            .user-avatar,
            .user-avatar-placeholder,
            .logo-circle-nav {
                width: 44px;
                height: 44px;
            }

            .notification-icon {
                display: none;
            }

            .app-footer {
                margin-left: 0;
                padding: 17px 12px;
                font-size: .75rem;
            }
        }

        @media (max-width: 576px) {
            .top-navbar {
                padding: 0 12px;
            }

            .brand-name {
                font-size: 1.05rem;
            }

            .main-content {
                padding: 18px 10px 45px;
            }

            .card-header {
                padding: 15px 16px !important;
            }

            .card-body {
                padding: 16px !important;
            }

            .btn-custom-action {
                width: 100%;
                margin: 0 0 8px !important;
            }

            .table {
                font-size: .82rem;
            }
        }
    </style>
</head>
<body>

    <!-- NAV SUPERIOR -->
    <header class="top-navbar">
        <div class="d-flex align-items-center flex-grow-1">
            <div class="brand-section">
                <div class="logo-circle-nav">
                    <img src="images/logo.jpeg" alt="Logo Café Control">
                </div>
                <h1 class="brand-name">Café control</h1>
            </div>

            <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="welcome-text d-none d-md-block">
                <h5>Bienvenido, <?= htmlspecialchars(strtoupper($nombre_usuario_logueado)) ?></h5>
                <p>Administra tus fincas y consulta su información</p>
            </div>
        </div>

        <div class="user-nav-actions">
           

            <div class="dropdown">
                <div class="profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (!empty($foto_perfil_url)): ?>
                        <img
                            src="<?= htmlspecialchars($foto_perfil_url, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Foto de perfil"
                            class="user-avatar"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                        >
                        <div class="user-avatar-placeholder" style="display:none;">
                            <?= htmlspecialchars(strtoupper(substr($nombre_usuario_logueado, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php else: ?>
                        <div class="user-avatar-placeholder">
                            <?= htmlspecialchars(strtoupper(substr($nombre_usuario_logueado, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                   
                </div>

               
            </div>
        </div>
    </header>

    <div class="layout-wrapper">
        <!-- MENÚ LATERAL -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="panel_m.php">
                        <i class="fa-solid fa-house"></i>
                        <span>Perfil</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="recolecciones_m.php">
                        <i class="fa-solid fa-box-archive"></i>
                        <span>Recolecciones</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="recolectores_m.php">
                        <i class="fa-solid fa-user-group"></i>
                        <span>Trabajadores</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="resumen_m.php">
                        <i class="fa-solid fa-chart-simple"></i>
                        <span>Resumen General</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="masignar_m.php">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>Mayordomo</span>
                    </a>
                </li>

                <li class="sidebar-item active">
                    <a href="gestionar_m.php">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Gestionar Usuarios</span>
                    </a>
                </li>

                <li class="sidebar-item mt-4">
                    <a href="cerrar.php">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- CONTENIDO -->
        <main class="main-content">
            <h2 class="page-title">Gestión General de Usuarios</h2>
            <?php if (!$puede_gestionar): ?>
                <div class="permission-alert mb-4">
                    <div class="permission-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div>
                        <h4>Sin permiso para gestionar usuarios</h4>
                        <p>
                            El caficultor debe darte el permiso para que puedas
                            agregar, modificar o gestionar usuarios.
                        </p>
                    </div>
                </div>
            <?php endif; ?>


            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show fs-6" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fs-6" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <!-- FORMULARIO AGREGAR / EDITAR -->
            <div class="card mb-4 <?= !$puede_gestionar ? 'card-disabled' : '' ?>">
                <div class="card-header bg-success text-white">
                    <i class="fa-solid fa-user-pen me-2"></i>
                    <?= !$puede_gestionar ? 'Gestión de Usuarios (solo consulta)' : ($usuarioEdit ? 'Editar Usuario' : 'Agregar Nuevo Usuario') ?>
                </div>

                <div class="card-body">
                    <form action="gestionar_usuarios.php" method="POST">
                        <input type="hidden" name="accion" value="<?= $usuarioEdit ? 'editar' : 'agregar' ?>">

                        <?php if ($usuarioEdit): ?>
                            <input type="hidden" name="id_usuario" value="<?= (int) $usuarioEdit['id_usuario'] ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Documento</label>
                                <input type="text" class="form-control" name="documento" <?= !$puede_gestionar ? 'disabled' : '' ?> value="<?= htmlspecialchars($usuarioEdit['documento'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Nombres</label>
                                <input type="text" class="form-control" name="nombres" <?= !$puede_gestionar ? 'disabled' : '' ?> value="<?= htmlspecialchars($usuarioEdit['nombres'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Apellidos</label>
                                <input type="text" class="form-control" name="apellidos" <?= !$puede_gestionar ? 'disabled' : '' ?> value="<?= htmlspecialchars($usuarioEdit['apellidos'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" <?= !$puede_gestionar ? 'disabled' : '' ?> value="<?= htmlspecialchars($usuarioEdit['telefono'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Correo electrónico</label>
                                <input type="email" class="form-control" name="correo" <?= !$puede_gestionar ? 'disabled' : '' ?> value="<?= htmlspecialchars($usuarioEdit['correo'] ?? '') ?>">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nombre de usuario</label>
                                <input type="text" class="form-control" name="usuario" <?= !$puede_gestionar ? 'disabled' : '' ?> value="<?= htmlspecialchars($usuarioEdit['usuario'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    Contraseña
                                    <span class="fw-normal text-muted"><?= $usuarioEdit ? '(Opcional)' : '' ?></span>
                                </label>
                                <input type="password" class="form-control" name="contrasena" <?= !$puede_gestionar ? 'disabled' : '' ?> <?= $usuarioEdit ? '' : 'required' ?>>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rol asignado</label>
                                <select class="form-select" name="rol" <?= !$puede_gestionar ? 'disabled' : '' ?> required>
                                    <option value="recolector" <?= (isset($usuarioEdit) && $usuarioEdit['rol'] === 'recolector') ? 'selected' : '' ?>>Recolector</option>
                                    <option value="mayordomo" <?= (isset($usuarioEdit) && $usuarioEdit['rol'] === 'mayordomo') ? 'selected' : '' ?>>Mayordomo</option>
                                    <option value="admin" <?= (isset($usuarioEdit) && $usuarioEdit['rol'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado" <?= !$puede_gestionar ? 'disabled' : '' ?> required>
                                    <option value="activo" <?= (isset($usuarioEdit) && $usuarioEdit['estado'] === 'activo') ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactivo" <?= (isset($usuarioEdit) && $usuarioEdit['estado'] === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-2">
                            <?php if ($puede_gestionar): ?>
                                <button type="submit" class="btn btn-success btn-custom-action me-2">
                                    <i class="fa-solid <?= $usuarioEdit ? 'fa-rotate' : 'fa-floppy-disk' ?> me-2"></i>
                                    <?= $usuarioEdit ? 'Actualizar Usuario' : 'Guardar Cambios' ?>
                                </button>
                            <?php else: ?>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-custom-action me-2"
                                    onclick="mostrarSinPermiso();"
                                >
                                    <i class="fa-solid fa-lock me-2"></i>
                                    Sin permiso
                                </button>
                            <?php endif; ?>

                            <?php if ($usuarioEdit): ?>
                                <a href="gestionar_usuarios.php" class="btn btn-secondary btn-custom-action">
                                    <i class="fa-solid fa-xmark me-2"></i>Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABLA -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <i class="fa-solid fa-users me-2"></i>
                    Lista General de Usuarios Registrados
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Nombre completo</th>
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
                                            <td class="fw-semibold text-secondary">
                                                <?= htmlspecialchars($row['documento']) ?>
                                            </td>

                                            <td class="fw-bold">
                                                <?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?>
                                            </td>

                                            <td><?= htmlspecialchars($row['telefono']) ?></td>
                                            <td><?= htmlspecialchars($row['correo']) ?></td>

                                            <td>
                                                <code class="text-dark bg-light px-2 py-1 rounded">
                                                    <?= htmlspecialchars($row['usuario']) ?>
                                                </code>
                                            </td>

                                            <td>
                                                <span class="badge bg-info text-capitalize">
                                                    <?= htmlspecialchars($row['rol']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge rounded-pill <?= $row['estado'] === 'activo' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= htmlspecialchars(ucfirst($row['estado'])) ?>
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <?php if ($puede_gestionar): ?>

                                                    <a
                                                        href="gestionar_usuarios.php?accion=pre_editar&id=<?= (int) $row['id_usuario'] ?>"
                                                        class="btn btn-sm btn-outline-warning me-1"
                                                        title="Editar"
                                                    >
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>

                                                    <a
                                                        href="gestionar_usuarios.php?accion=suspender&id=<?= (int) $row['id_usuario'] ?>&estado=<?= urlencode($row['estado']) ?>"
                                                        class="btn btn-sm btn-outline-secondary me-1"
                                                        title="Cambiar estado"
                                                    >
                                                        <i class="fa-solid fa-ban"></i>
                                                    </a>

                                                    <a
                                                        href="gestionar_usuarios.php?accion=eliminar&id=<?= (int) $row['id_usuario'] ?>"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('¿Seguro que deseas eliminar este usuario?')"
                                                        title="Eliminar"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>

                                                <?php else: ?>

                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Sin permiso"
                                                        onclick="mostrarSinPermiso();"
                                                    >
                                                        <i class="fa-solid fa-lock"></i>
                                                    </button>

                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            No se encontraron usuarios registrados en la base de datos.
                                        </td>
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
        <i class="fa-solid fa-mug-hot"></i>
        <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez &amp; Tatiana Silva Penagos</span>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const menuToggle = document.getElementById('menuToggle');

        if (menuToggle) {
            menuToggle.addEventListener('click', function () {
                document.body.classList.toggle('sidebar-open');
            });
        }

        document.querySelectorAll('.sidebar a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    document.body.classList.remove('sidebar-open');
                }
            });
        });

        function mostrarSinPermiso() {
            alert('Debes tener permiso para esta acción.');
        }
    </script>
</body>
</html>
