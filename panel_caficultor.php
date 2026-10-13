<?php
session_start();

require_once 'assets/inc/conexion.php';

/* =========================================================
   VERIFICAR SESIÓN
   ========================================================= */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = (int) $_SESSION['id_usuario'];

$mensaje = "";
$tipo_mensaje = "";


/* =========================================================
   SUBIR / CAMBIAR FOTO DE PERFIL
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_foto'])) {

    /* Verificar que se haya enviado una imagen */
    if (!isset($_FILES['foto_perfil']) || $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {

        $mensaje = "No se pudo seleccionar la imagen.";
        $tipo_mensaje = "error";

    } else {

        $archivo = $_FILES['foto_perfil'];

        /* Tamaño máximo: 5 MB */
        if ($archivo['size'] > 5 * 1024 * 1024) {

            $mensaje = "La imagen no puede superar los 5 MB.";
            $tipo_mensaje = "error";

        } else {

            /* Comprobar que sea realmente una imagen */
            $imagen = getimagesize($archivo['tmp_name']);

            if ($imagen === false) {

                $mensaje = "El archivo seleccionado no es una imagen válida.";
                $tipo_mensaje = "error";

            } else {

                $mime = $imagen['mime'];

                /* Tipos permitidos */
                $tipos_permitidos = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($tipos_permitidos[$mime])) {

                    $mensaje = "Solo se permiten imágenes JPG, PNG o WEBP.";
                    $tipo_mensaje = "error";

                } else {

                    /* =====================================================
                       CREAR CARPETA PARA LAS FOTOS
                       ===================================================== */
                    $carpeta = __DIR__ . '/uploads/perfiles/';

                    if (!is_dir($carpeta)) {
                        mkdir($carpeta, 0777, true);
                    }

                    /* Extensión correcta */
                    $extension = $tipos_permitidos[$mime];

                    /* =====================================================
                       CREAR NOMBRE ÚNICO
                       ===================================================== */
                    $nombre_archivo =
                        'perfil_' .
                        $id_usuario . '_' .
                        time() . '_' .
                        uniqid() . '.' .
                        $extension;

                    /* Ruta física */
                    $ruta_fisica = $carpeta . $nombre_archivo;

                    /* Ruta que se guardará en la base de datos */
                    $ruta_bd = 'uploads/perfiles/' . $nombre_archivo;


                    /* =====================================================
                       BUSCAR FOTO ANTERIOR
                       ===================================================== */
                    $sql_anterior = "
                        SELECT foto_perfil
                        FROM perfiles_usuario
                        WHERE id_usuario = ?
                    ";

                    $stmt_anterior = $con->prepare($sql_anterior);
                    $stmt_anterior->bind_param("i", $id_usuario);
                    $stmt_anterior->execute();

                    $resultado_anterior = $stmt_anterior->get_result();

                    $foto_anterior = "";

                    if ($fila_anterior = $resultado_anterior->fetch_assoc()) {
                        $foto_anterior = $fila_anterior['foto_perfil'];
                    }

                    $stmt_anterior->close();


                    /* =====================================================
                       GUARDAR LA IMAGEN FÍSICAMENTE
                       ===================================================== */
                    if (move_uploaded_file($archivo['tmp_name'], $ruta_fisica)) {


                        /* =====================================================
                           GUARDAR LA RUTA EN LA BASE DE DATOS
                           
                           Tabla: perfiles_usuario
                           Campos:
                           - id_usuario
                           - foto_perfil
                           - fecha_actualizacion
                           ===================================================== */
                        $sql_guardar = "
                            INSERT INTO perfiles_usuario
                                (id_usuario, foto_perfil)
                            VALUES
                                (?, ?)
                            ON DUPLICATE KEY UPDATE
                                foto_perfil = VALUES(foto_perfil),
                                fecha_actualizacion = CURRENT_TIMESTAMP
                        ";

                        $stmt_guardar = $con->prepare($sql_guardar);
                        $stmt_guardar->bind_param(
                            "is",
                            $id_usuario,
                            $ruta_bd
                        );


                        if ($stmt_guardar->execute()) {

                            /* =================================================
                               ELIMINAR FOTO ANTERIOR
                               ================================================= */
                            if (
                                !empty($foto_anterior) &&
                                strpos($foto_anterior, 'uploads/perfiles/') === 0
                            ) {

                                $ruta_anterior =
                                    __DIR__ . '/' . $foto_anterior;

                                if (is_file($ruta_anterior)) {
                                    unlink($ruta_anterior);
                                }
                            }

                            $mensaje =
                                "La foto se guardó correctamente en la base de datos.";

                            $tipo_mensaje = "exito";

                        } else {

                            /* Si falla la BD, eliminar archivo nuevo */
                            if (is_file($ruta_fisica)) {
                                unlink($ruta_fisica);
                            }

                            $mensaje =
                                "La imagen se subió, pero no se pudo guardar en la base de datos.";

                            $tipo_mensaje = "error";
                        }

                        $stmt_guardar->close();

                    } else {

                        $mensaje =
                            "No se pudo guardar la imagen en el servidor.";

                        $tipo_mensaje = "error";
                    }
                }
            }
        }
    }
}


/* =========================================================
   ELIMINAR FOTO DE PERFIL
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_foto'])) {

    $sql_foto = "
        SELECT foto_perfil
        FROM perfiles_usuario
        WHERE id_usuario = ?
    ";

    $stmt_foto = $con->prepare($sql_foto);
    $stmt_foto->bind_param("i", $id_usuario);
    $stmt_foto->execute();

    $resultado_foto = $stmt_foto->get_result();
    $foto_anterior = "";

    if ($fila_foto = $resultado_foto->fetch_assoc()) {
        $foto_anterior = $fila_foto['foto_perfil'] ?? "";
    }

    $stmt_foto->close();

    $sql_eliminar = "
        UPDATE perfiles_usuario
        SET foto_perfil = NULL,
            fecha_actualizacion = CURRENT_TIMESTAMP
        WHERE id_usuario = ?
    ";

    $stmt_eliminar = $con->prepare($sql_eliminar);
    $stmt_eliminar->bind_param("i", $id_usuario);

    if ($stmt_eliminar->execute()) {

        if (
            !empty($foto_anterior) &&
            strpos($foto_anterior, 'uploads/perfiles/') === 0
        ) {
            $ruta_anterior = __DIR__ . '/' . $foto_anterior;

            if (is_file($ruta_anterior)) {
                unlink($ruta_anterior);
            }
        }

        $mensaje = "La foto de perfil fue eliminada correctamente.";
        $tipo_mensaje = "exito";

    } else {
        $mensaje = "No se pudo eliminar la foto de perfil.";
        $tipo_mensaje = "error";
    }

    $stmt_eliminar->close();
}



/* =========================================================
   CONSULTAR DATOS DEL USUARIO Y FOTO
   ========================================================= */

$sql = "
    SELECT
        u.documento,
        u.nombres,
        u.apellidos,
        u.usuario,
        u.correo,
        u.telefono,
        u.rol,
        pu.foto_perfil
    FROM usuarios u
    LEFT JOIN perfiles_usuario pu
        ON pu.id_usuario = u.id_usuario
    WHERE u.id_usuario = ?
";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$resultado = $stmt->get_result();


if ($row = $resultado->fetch_assoc()) {

    $nombre_completo = $row['nombres'] . ' ' . $row['apellidos'];
    $cedula          = $row['documento'];
    $usuario         = $row['usuario'];
    $correo          = $row['correo'];
    $telefono        = $row['telefono'];
    $rol             = ucfirst($row['rol']);
    $foto_perfil     = $row['foto_perfil'] ?? "";

} else {

    session_destroy();

    header("Location: index.php");
    exit();
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Perfil - Café Control</title>


    <!-- FUENTE -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- BOOTSTRAP ICONS -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <!-- FONT AWESOME -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <!-- LUCIDE -->
    <script src="https://unpkg.com/lucide@latest"></script>


    <style>

        :root {

            --bg-main: #f5f0eb;
            --bg-sidebar: #ece4db;
            --bg-card: #ffffff;

            --primary-brown: #3d200d;
            --primary-dark: #241307;

            --secondary-green: #244b20;

            --accent-red: #d93829;

            --text-dark: #1f1813;
            --text-muted: #574e47;

            --border-color: #d6cabe;

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


        /* =====================================================
           HEADER
           ===================================================== */

        .top-header {

            background-color: var(--primary-brown);

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            min-height: 72px;

            padding: 0 28px;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 14px;

            width: 250px;
        }

/* logo clases */
     .brand-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
}

.brand-logo .app-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

        .brand-text h1 {

            font-size: 1.15rem;

            font-weight: 800;

            line-height: 1.2;
        }


        .header-center {

            display: flex;

            align-items: center;

            gap: 20px;

            flex: 1;

            padding-left: 20px;
        }


        .menu-toggle {

            background: none;

            border: none;

            color: #fff;

            cursor: pointer;

            display: flex;

            align-items: center;
        }


        .user-welcome h2 {

            font-size: 1.1rem;

            font-weight: 700;
        }


        .user-welcome p {

            font-size: 0.9rem;

            opacity: 0.9;

            font-weight: 500;
        }


        .header-actions {

            display: flex;

            align-items: center;

            gap: 24px;
        }


        .icon-btn {

            background: none;

            border: none;

            color: #fff;

            cursor: pointer;

            position: relative;
        }


        .badge-dot {

            position: absolute;

            top: -2px;

            right: -2px;

            width: 10px;

            height: 10px;

            background-color: #4caf50;

            border-radius: 50%;

            border: 2px solid var(--primary-brown);
        }


        .user-profile {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .avatar-icon {

            width: 42px;

            height: 42px;

            background-color: #fff;

            color: var(--primary-brown);

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;
        }


        .header-avatar {

            width: 42px;

            height: 42px;

            object-fit: cover;

            border-radius: 50%;
        }


        /* =====================================================
           LAYOUT
           ===================================================== */

        .app-layout {

            display: flex;

            flex: 1;
        }


        /* =====================================================
           SIDEBAR
           ===================================================== */

        .sidebar {

            width: 270px;

            background-color: var(--bg-sidebar);

            padding: 24px 18px;

            display: flex;

            flex-direction: column;

            gap: 20px;

            border-right: 1px solid var(--border-color);
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

            color: var(--text-dark);

            text-decoration: none;

            font-size: 0.98rem;

            font-weight: 700;

            transition: 0.2s;
        }


        .nav-item.active a {

            background-color: var(--primary-brown);

            color: #fff;

            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }


        .nav-item:not(.active) a:hover {

            background-color: rgba(0,0,0,0.08);
        }


        /* =====================================================
           CONTENIDO
           ===================================================== */

        .main-content {

            flex: 1;

            padding: 40px 24px;

            display: flex;

            justify-content: center;

            align-items: flex-start;
        }


        .contenido-perfil {

            width: 100%;

            max-width: 620px;
        }


        /* =====================================================
           MENSAJES
           ===================================================== */

        .mensaje {

            width: 100%;

            margin-bottom: 18px;

            padding: 14px 18px;

            border-radius: 10px;

            font-weight: 700;

            text-align: center;
        }


        .mensaje.exito {

            background-color: #dff3e3;

            color: #1f6b35;

            border: 1px solid #b7dfbf;
        }


        .mensaje.error {

            background-color: #fce2df;

            color: #a32920;

            border: 1px solid #efb6b0;
        }


        /* =====================================================
           TARJETA
           ===================================================== */

        .card-profile {

            background-color: var(--bg-card);

            width: 100%;

            border-radius: 20px;

            box-shadow: 0 8px 24px rgba(0,0,0,0.06);

            overflow: hidden;

            border: 1px solid var(--border-color);
        }


        .header-card {

            background-color: var(--primary-brown);

            color: #fff;

            padding: 36px 28px;

            text-align: center;
        }


        /* =====================================================
           FOTO DE PERFIL
           ===================================================== */

        .avatar-wrapper {

            position: relative;

            width: 120px;

            height: 120px;

            margin: 0 auto 16px;
        }


        .avatar-container {

            width: 120px;

            height: 120px;

            border-radius: 50%;

            border: 4px solid #fff;

            overflow: hidden;

            background-color: #e2d7cd;

            display: flex;

            align-items: center;

            justify-content: center;

            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }


        .avatar-container img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .btn-camera {

            position: absolute;

            right: -2px;

            bottom: 4px;

            width: 42px;

            height: 42px;

            border-radius: 50%;

            border: 3px solid #fff;

            background-color: var(--secondary-green);

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            box-shadow: 0 3px 8px rgba(0,0,0,0.22);

            transition: 0.2s;
        }


        .btn-camera:hover {

            transform: scale(1.05);
        }


        .input-foto {

            display: none;
        }


        .form-foto {

            margin-top: 15px;

            display: flex;

            flex-direction: column;

            align-items: center;

            gap: 9px;
        }


        .nombre-archivo {

            color: #fff;

            font-size: 0.85rem;

            font-weight: 600;

            word-break: break-word;

            max-width: 100%;
        }


        .btn-guardar-foto {

            display: none;

            align-items: center;

            gap: 8px;

            padding: 10px 18px;

            border: none;

            border-radius: 10px;

            background-color: #fff;

            color: var(--primary-brown);

            font-weight: 800;

            cursor: pointer;
        }


        .btn-guardar-foto:hover {

            background-color: #f1e7dd;
        }


        .texto-foto {

            font-size: 0.88rem;

            font-weight: 600;

            margin-top: 4px;
        }


        .caficultor-name {

            margin: 0;

            font-size: 1.6rem;

            font-weight: 800;
        }


        /* =====================================================
           INFORMACIÓN
           ===================================================== */

        .body-card {

            padding: 32px 36px;
        }


        .section-title {

            font-size: 1.25rem;

            color: var(--primary-brown);

            border-bottom: 3px solid var(--secondary-green);

            padding-bottom: 8px;

            margin-bottom: 24px;

            font-weight: 800;
        }


        .info-grid {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 24px;
        }


        .info-item {

            display: flex;

            flex-direction: column;

            gap: 4px;
        }


        .label {

            font-size: 0.85rem;

            color: var(--text-muted);

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.8px;
        }


        .value {

            font-size: 1.15rem;

            font-weight: 700;

            color: var(--text-dark);

            word-break: break-word;
        }


        /* =====================================================
           FOOTER
           ===================================================== */

        .app-footer {

            text-align: center;

            padding: 20px;

            font-size: 0.9rem;

            font-weight: 600;

            color: var(--text-muted);

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            border-top: 1px solid var(--border-color);

            background-color: #efe8e0;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 768px) {

            .top-header {

                padding: 12px 16px;

                flex-wrap: wrap;

                gap: 12px;
            }


            .brand {

                width: auto;
            }


            .header-center {

                order: 3;

                width: 100%;

                padding-left: 0;
            }


            .header-actions {

                margin-left: auto;
            }


            .app-layout {

                flex-direction: column;
            }


            .sidebar {

                width: 100%;

                padding: 12px;
            }


            .nav-list {

                display: grid;

                grid-template-columns: repeat(2, 1fr);
            }


            .main-content {

                padding: 20px 16px;
            }


            .info-grid {

                grid-template-columns: 1fr;

                gap: 18px;
            }


            .body-card {

                padding: 24px 20px;
            }


            .user-welcome p {

                display: none;
            }
        }


        @media (max-width: 520px) {

            .header-actions .icon-btn {

                display: none;
            }


            .nav-list {

                grid-template-columns: 1fr;
            }


            .header-card {

                padding: 28px 18px;
            }


            .caficultor-name {

                font-size: 1.3rem;
            }


            .value {

                font-size: 1rem;
            }
        }

    

        /* =====================================================
           MODAL EDITAR FOTO DE PERFIL
           ===================================================== */

        .perfil-editable {
            cursor: pointer;
        }

        .perfil-editable:hover .avatar-icon {
            transform: scale(1.03);
        }

        .avatar-icon {
            transition: 0.2s ease;
        }

        .modal-perfil-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(0,0,0,0.58);
            backdrop-filter: blur(2px);
        }

        .modal-perfil-overlay.mostrar {
            display: flex;
        }

        .modal-perfil {
            width: min(700px, 100%);
            max-height: 94vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 28px 70px rgba(0,0,0,0.28);
            padding: 30px 35px 32px;
        }

        .modal-perfil-heading {
            display: grid;
            grid-template-columns: 74px 1fr 55px;
            gap: 16px;
            align-items: start;
        }

        .modal-icono-camera {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e4f1df;
            color: #275e2d;
            font-size: 28px;
        }

        .modal-titulos h2 {
            margin: 0;
            padding-top: 2px;
            color: #285d2b;
            font-size: 1.95rem;
            line-height: 1.1;
            font-weight: 800;
        }

        .modal-titulos p {
            margin: 9px 0 0;
            color: #6b7791;
            font-size: 1.02rem;
            line-height: 1.35;
            font-weight: 600;
            max-width: 475px;
        }

        .modal-btn-cerrar {
            width: 55px;
            height: 55px;
            border: 0;
            border-radius: 50%;
            background: #f0eee9;
            color: #285d2b;
            cursor: pointer;
            font-size: 23px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-btn-cerrar:hover {
            background: #e7e3dc;
        }

        .modal-preview-wrap {
            display: flex;
            justify-content: center;
            margin: 24px 0 28px;
        }

        .modal-preview {
            width: 245px;
            height: 245px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #a6d99b;
            background: #f0f4ee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7a8a7c;
            font-size: 80px;
            box-shadow: 0 2px 9px rgba(0,0,0,0.08);
        }

        .modal-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .modal-seleccionar {
            min-height: 104px;
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 18px 24px;
            border: 2px dashed #b6d0af;
            border-radius: 17px;
            background: #fafcf9;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .modal-seleccionar:hover {
            background: #f4f9f1;
            border-color: #8fbc87;
        }

        .modal-icono-subir {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e4f1df;
            color: #275e2d;
            font-size: 23px;
            flex: 0 0 50px;
        }

        .modal-seleccionar-texto {
            display: flex;
            flex-direction: column;
        }

        .modal-seleccionar-texto strong {
            color: #285d2b;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .modal-seleccionar-texto span {
            color: #5f5f5f;
            font-size: 0.92rem;
            margin-top: 4px;
            font-weight: 600;
        }

        .modal-seleccionar-texto em {
            display: none;
            color: #285d2b;
            font-size: 0.86rem;
            font-weight: 700;
            margin-top: 5px;
            font-style: normal;
            overflow-wrap: anywhere;
        }

        .modal-acciones {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13px;
            margin-top: 19px;
        }

        .modal-btn {
            min-height: 60px;
            border: 0;
            border-radius: 13px;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 11px;
            font-size: 1.04rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .modal-btn-cancelar {
            background: #ece8e4;
            color: #2e3545;
        }

        .modal-btn-cancelar:hover {
            background: #dfdad4;
        }

        .modal-btn-guardar {
            background: #245b27;
            color: #fff;
        }

        .modal-btn-guardar:hover {
            background: #1d4b20;
        }

        .modal-btn-quitar {
            width: 100%;
            min-height: 60px;
            margin-top: 16px;
            border: 0;
            border-radius: 13px;
            background: #fdecec;
            color: #d02e2a;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 11px;
            font-size: 1.04rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .modal-btn-quitar:hover {
            background: #f9dcdc;
        }

        @media (max-width: 700px) {
            .modal-perfil {
                padding: 24px 20px 23px;
                border-radius: 21px;
            }

            .modal-perfil-heading {
                grid-template-columns: 58px 1fr 46px;
                gap: 11px;
            }

            .modal-icono-camera {
                width: 58px;
                height: 58px;
                font-size: 23px;
            }

            .modal-btn-cerrar {
                width: 46px;
                height: 46px;
                font-size: 20px;
            }

            .modal-titulos h2 {
                font-size: 1.45rem;
            }

            .modal-titulos p {
                font-size: 0.88rem;
            }

            .modal-preview {
                width: 190px;
                height: 190px;
            }
        }

        @media (max-width: 520px) {
            .modal-acciones {
                grid-template-columns: 1fr;
            }

            .modal-preview {
                width: 165px;
                height: 165px;
            }

            .modal-seleccionar {
                padding: 15px;
            }
        }

    </style>

</head>


<body>


   <!-- header -->

   <header class="top-header">

    <div class="brand">
        <div class="brand-logo">
            <!-- Reemplaza el <i data-lucide="coffee"></i> por esto: -->
            <img 
                src="images/logo.jpeg" 
                alt="Café Control Logo" 
                class="app-logo"
            >
        </div>

        <div class="brand-text">
            <h1>Café Control</h1>
        </div>
    </div>

    <div class="header-center">
        <button
            class="menu-toggle"
            type="button"
            aria-label="Abrir menú"
        >
            <i data-lucide="menu" size="24"></i>
        </button>

        <div class="user-welcome">
            <h2>
                Bienvenido,
                <?php echo htmlspecialchars($nombre_completo); ?>
            </h2>

            <p>
                Administra tus fincas y consulta su información
            </p>
        </div>
    </div>

    <div class="header-actions">

        <button
            class="icon-btn"
            type="button"
            aria-label="Notificaciones"
        >
            <i data-lucide="bell" size="22"></i>
            <span class="badge-dot"></span>
        </button>

        <div
            class="user-profile"
            aria-label="Perfil del usuario"
        >
            <div class="avatar-icon">
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

            <i
                data-lucide="chevron-down"
                size="18"
            ></i>
        </div>

    </div>

</header>



    <!-- =====================================================
         LAYOUT
         ===================================================== -->

    <div class="app-layout">


        <!-- =================================================
             SIDEBAR
             ================================================= -->

        <aside class="sidebar">


            <ul class="nav-list">


                <li class="nav-item active">

                    <a href="panel_caficultor.php">

                        <i
                            data-lucide="home"
                            size="20"
                        ></i>

                        Perfil

                    </a>

                </li>


                <li class="nav-item">

                    <a href="fincas.php">

                        <i
                            data-lucide="map-pin"
                            size="20"
                        ></i>

                        Fincas

                    </a>

                </li>


                <li class="nav-item">

                    <a href="recolecciones_c.php">

                        <i
                            data-lucide="archive"
                            size="20"
                        ></i>

                        Recolecciones

                    </a>

                </li>


                <li class="nav-item">

                    <a href="recolectores_c.php">

                        <i
                            data-lucide="users"
                            size="20"
                        ></i>

                        Trabajadores

                    </a>

                </li>


                <li class="nav-item">

                    <a href="resumen_c.php">

                        <i
                            data-lucide="bar-chart-2"
                            size="20"
                        ></i>

                        Resumen General

                    </a>

                </li>


                <li class="nav-item">

                    <a href="precio.php">

                        <i
                            data-lucide="dollar-sign"
                            size="20"
                        ></i>

                        Precio del Kilo

                    </a>

                </li>


                <li class="nav-item">

                    <a href="asignar_m.php">

                        <i
                            class="fa-solid fa-user-gear"
                            style="font-size:18px;"
                        ></i>

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

                        <i
                            data-lucide="log-out"
                            size="20"
                        ></i>

                        Cerrar Sesión

                    </a>

                </li>


            </ul>


        </aside>



        <!-- =================================================
             CONTENIDO PRINCIPAL
             ================================================= -->

        <main class="main-content">


            <div class="contenido-perfil">


                <!-- MENSAJE -->

                <?php if (!empty($mensaje)) { ?>

                    <div
                        class="mensaje <?php echo htmlspecialchars($tipo_mensaje); ?>"
                    >

                        <?php
                        echo htmlspecialchars($mensaje);
                        ?>

                    </div>

                <?php } ?>



                <!-- TARJETA -->

                <div class="card-profile">


                    <!-- =================================================
                         CABECERA DEL PERFIL
                         ================================================= -->

                    <div class="header-card">


                        <!-- AVATAR -->

                        <div
                            class="avatar-wrapper perfil-editable"
                            id="abrirModalPerfilAvatar"
                            title="Editar foto de perfil"
                        >

                            <div
                                class="avatar-container"
                                id="avatarPreview"
                            >

                                <?php if (!empty($foto_perfil)) { ?>

                                    <img
                                        src="<?php echo htmlspecialchars($foto_perfil); ?>"
                                        alt="Foto de perfil"
                                    >

                                <?php } else { ?>

                                    <i
                                        data-lucide="user"
                                        size="56"
                                    ></i>

                                <?php } ?>

                            </div>

                            <span
                                class="btn-camera"
                                title="Editar foto"
                            >
                                <i
                                    data-lucide="camera"
                                    size="20"
                                ></i>
                            </span>

                        </div>


                        <!-- NOMBRE -->

                        <h2 class="caficultor-name">

                            <?php
                            echo htmlspecialchars($nombre_completo);
                            ?>

                        </h2>                        <p class="texto-foto">
                            Haz clic en tu foto para editarla.
                        </p>

</div>



                    <!-- =================================================
                         CUERPO DE LA TARJETA
                         ================================================= -->

                    <div class="body-card">


                        <div class="section-title">

                            Información del Caficultor

                        </div>


                        <div class="info-grid">


                            <!-- NOMBRE -->

                            <div class="info-item">

                                <span class="label">

                                    Nombre:

                                </span>


                                <span class="value">

                                    <?php
                                    echo htmlspecialchars($nombre_completo);
                                    ?>

                                </span>

                            </div>



                            <!-- CÉDULA -->

                            <div class="info-item">

                                <span class="label">

                                    Cédula:

                                </span>


                                <span class="value">

                                    <?php
                                    echo htmlspecialchars($cedula);
                                    ?>

                                </span>

                            </div>



                            <!-- USUARIO -->

                            <div class="info-item">

                                <span class="label">

                                    Usuario:

                                </span>


                                <span class="value">

                                    <?php
                                    echo htmlspecialchars($usuario);
                                    ?>

                                </span>

                            </div>



                            <!-- CORREO -->

                            <div class="info-item">

                                <span class="label">

                                    Correo:

                                </span>


                                <span class="value">

                                    <?php
                                    echo htmlspecialchars($correo);
                                    ?>

                                </span>

                            </div>



                            <!-- TELÉFONO -->

                            <div class="info-item">

                                <span class="label">

                                    Teléfono:

                                </span>


                                <span class="value">

                                    <?php
                                    echo htmlspecialchars($telefono);
                                    ?>

                                </span>

                            </div>



                            <!-- ROL -->

                            <div class="info-item">

                                <span class="label">

                                    Rol:

                                </span>


                                <span class="value">

                                    <?php
                                    echo htmlspecialchars($rol);
                                    ?>

                                </span>

                            </div>


                        </div>

                    </div>


                </div>


            </div>


        </main>


    </div>



    <!-- =====================================================
         FOOTER
         ===================================================== -->

    <footer class="app-footer">

        <i
            data-lucide="coffee"
            size="16"
        ></i>


        <span>

            <strong>Café Control</strong>

            © 2026

            Developers: Gloria Tatiana Sanchez & Tatiana Silva Penagos

        </span>

    </footer>





    <!-- =====================================================
         MODAL EDITAR FOTO DE PERFIL
         ===================================================== -->

    <div
        class="modal-perfil-overlay"
        id="modalPerfil"
        aria-hidden="true"
    >

        <div
            class="modal-perfil"
            role="dialog"
            aria-modal="true"
            aria-labelledby="tituloModalPerfil"
        >

            <div class="modal-perfil-heading">

                <div class="modal-icono-camera">
                    <i class="fa-solid fa-camera"></i>
                </div>

                <div class="modal-titulos">

                    <h2 id="tituloModalPerfil">
                        Editar foto de perfil
                    </h2>

                    <p>
                        Puedes cambiar tu foto de perfil. Se recomienda una imagen en formato JPG, PNG o WEBP (máximo 5 MB).
                    </p>

                </div>

                <button
                    type="button"
                    class="modal-btn-cerrar"
                    id="cerrarModalPerfil"
                    aria-label="Cerrar"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>

            <div class="modal-preview-wrap">

                <div class="modal-preview" id="modalPreview">

                    <?php if (!empty($foto_perfil)) { ?>

                        <img
                            src="<?php echo htmlspecialchars($foto_perfil); ?>"
                            alt="Foto de perfil"
                        >

                    <?php } else { ?>

                        <i class="fa-solid fa-user"></i>

                    <?php } ?>

                </div>

            </div>

            <form
                method="POST"
                action=""
                enctype="multipart/form-data"
                id="formPerfil"
            >

                <input
                    type="file"
                    name="foto_perfil"
                    id="fotoPerfilInput"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    hidden
                >

                <div
                    class="modal-seleccionar"
                    id="botonSeleccionarImagen"
                >

                    <div class="modal-icono-subir">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>

                    <div class="modal-seleccionar-texto">

                        <strong>
                            Seleccionar imagen
                        </strong>

                        <span>
                            JPG, PNG o WEBP — máximo 5 MB
                        </span>

                        <em id="nombreArchivoPerfil"></em>

                    </div>

                </div>

                <div class="modal-acciones">

                    <button
                        type="button"
                        class="modal-btn modal-btn-cancelar"
                        id="cancelarModalPerfil"
                    >
                        <i class="fa-solid fa-xmark"></i>
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="modal-btn modal-btn-guardar"
                        name="subir_foto"
                        id="guardarFotoPerfil"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Guardar
                    </button>

                </div>

            </form>

            <?php if (!empty($foto_perfil)) { ?>

                <form
                    method="POST"
                    action=""
                    id="formEliminarFoto"
                >

                    <button
                        type="submit"
                        name="eliminar_foto"
                        class="modal-btn-quitar"
                    >
                        <i class="fa-solid fa-trash"></i>
                        Quitar foto de perfil
                    </button>

                </form>

            <?php } ?>

        </div>

    </div>


    <!-- =====================================================
         JAVASCRIPT
         ===================================================== -->

    <script>

        lucide.createIcons();

        const modalPerfil = document.getElementById('modalPerfil');
        const abrirModalAvatar = document.getElementById('abrirModalPerfilAvatar');
        const cerrarModalPerfil = document.getElementById('cerrarModalPerfil');
        const cancelarModalPerfil = document.getElementById('cancelarModalPerfil');
        const botonSeleccionarImagen = document.getElementById('botonSeleccionarImagen');
        const inputFoto = document.getElementById('fotoPerfilInput');
        const modalPreview = document.getElementById('modalPreview');
        const nombreArchivoPerfil = document.getElementById('nombreArchivoPerfil');
        const formEliminarFoto = document.getElementById('formEliminarFoto');

        function abrirModalPerfil() {

            if (!modalPerfil) {
                return;
            }

            modalPerfil.classList.add('mostrar');
            modalPerfil.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalPerfilFuncion() {

            if (!modalPerfil) {
                return;
            }

            modalPerfil.classList.remove('mostrar');
            modalPerfil.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (abrirModalAvatar) {
            abrirModalAvatar.addEventListener('click', abrirModalPerfil);
        }

        if (cerrarModalPerfil) {
            cerrarModalPerfil.addEventListener('click', cerrarModalPerfilFuncion);
        }

        if (cancelarModalPerfil) {
            cancelarModalPerfil.addEventListener('click', cerrarModalPerfilFuncion);
        }

        if (modalPerfil) {
            modalPerfil.addEventListener('click', function (evento) {
                if (evento.target === modalPerfil) {
                    cerrarModalPerfilFuncion();
                }
            });
        }

        document.addEventListener('keydown', function (evento) {
            if (
                evento.key === 'Escape' &&
                modalPerfil &&
                modalPerfil.classList.contains('mostrar')
            ) {
                cerrarModalPerfilFuncion();
            }
        });

        if (botonSeleccionarImagen && inputFoto) {
            botonSeleccionarImagen.addEventListener('click', function () {
                inputFoto.click();
            });
        }

        if (inputFoto) {

            inputFoto.addEventListener('change', function () {

                const archivo = this.files[0];

                if (!archivo) {
                    return;
                }

                if (archivo.size > 5 * 1024 * 1024) {
                    alert('La imagen no puede superar los 5 MB.');
                    this.value = '';
                    return;
                }

                const tiposPermitidos = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (!tiposPermitidos.includes(archivo.type)) {
                    alert('Solo se permiten imágenes JPG, PNG o WEBP.');
                    this.value = '';
                    return;
                }

                if (nombreArchivoPerfil) {
                    nombreArchivoPerfil.textContent = archivo.name;
                    nombreArchivoPerfil.style.display = 'block';
                }

                const lector = new FileReader();

                lector.onload = function (evento) {

                    if (!modalPreview) {
                        return;
                    }

                    modalPreview.innerHTML = '';

                    const imagen = document.createElement('img');
                    imagen.src = evento.target.result;
                    imagen.alt = 'Vista previa de la foto';

                    modalPreview.appendChild(imagen);
                };

                lector.readAsDataURL(archivo);
            });
        }

        if (formEliminarFoto) {

            formEliminarFoto.addEventListener('submit', function (evento) {

                if (!confirm('¿Seguro que deseas quitar tu foto de perfil?')) {
                    evento.preventDefault();
                }

            });
        }

    </script>
</body>
</html>