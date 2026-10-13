<?php
declare(strict_types=1);

session_start();

// Zona horaria
date_default_timezone_set('America/Bogota');

// Conexión
require_once __DIR__ . '/assets/inc/conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = (int) $_SESSION['id_usuario'];

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE IMÁGENES
|--------------------------------------------------------------------------
*/
$carpeta_fotos = __DIR__ . '/uploads/perfiles/';
$url_fotos     = 'uploads/perfiles/';

// Crear carpeta si no existe
if (!is_dir($carpeta_fotos)) {
    if (!mkdir($carpeta_fotos, 0755, true) && !is_dir($carpeta_fotos)) {
        die("No se pudo crear la carpeta de imágenes de perfil.");
    }
}

/*
|--------------------------------------------------------------------------
| PROCESAR ELIMINACIÓN DE FOTO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_foto'])) {

    // Consultar foto actual
    $sql_foto = "SELECT foto_perfil FROM usuarios WHERE id_usuario = ?";
    $stmt_foto = $con->prepare($sql_foto);

    if ($stmt_foto) {
        $stmt_foto->bind_param("i", $id_usuario);
        $stmt_foto->execute();

        $resultado_foto = $stmt_foto->get_result();
        $datos_foto = $resultado_foto->fetch_assoc();

        $stmt_foto->close();

        if (!empty($datos_foto['foto_perfil'])) {

            $archivo_anterior = __DIR__ . '/' . ltrim($datos_foto['foto_perfil'], '/');

            if (is_file($archivo_anterior)) {
                @unlink($archivo_anterior);
            }
        }
    }

    // Quitar referencia de la base de datos
    $sql_eliminar = "UPDATE usuarios SET foto_perfil = NULL WHERE id_usuario = ?";
    $stmt_eliminar = $con->prepare($sql_eliminar);

    if ($stmt_eliminar) {
        $stmt_eliminar->bind_param("i", $id_usuario);
        $stmt_eliminar->execute();
        $stmt_eliminar->close();
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?foto=eliminada");
    exit();
}

/*
|--------------------------------------------------------------------------
| PROCESAR SUBIDA DE FOTO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {

    if (
        !isset($_FILES['foto_perfil']['error']) ||
        $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK
    ) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=subida");
        exit();
    }

    $archivo = $_FILES['foto_perfil'];

    // Tamaño máximo: 5 MB
    $maximo = 5 * 1024 * 1024;

    if ($archivo['size'] > $maximo) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=pesada");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFICAR QUE REALMENTE SEA UNA IMAGEN
    |--------------------------------------------------------------------------
    */
    $info_imagen = @getimagesize($archivo['tmp_name']);

    if ($info_imagen === false) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=imagen");
        exit();
    }

    $mime = $info_imagen['mime'];

    $tipos_permitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($tipos_permitidos[$mime])) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=formato");
        exit();
    }

    $extension = $tipos_permitidos[$mime];

    /*
    |--------------------------------------------------------------------------
    | CONSULTAR FOTO ANTERIOR
    |--------------------------------------------------------------------------
    */
    $sql_anterior = "SELECT foto_perfil FROM usuarios WHERE id_usuario = ?";
    $stmt_anterior = $con->prepare($sql_anterior);

    $foto_anterior = null;

    if ($stmt_anterior) {
        $stmt_anterior->bind_param("i", $id_usuario);
        $stmt_anterior->execute();

        $resultado_anterior = $stmt_anterior->get_result();
        $fila_anterior = $resultado_anterior->fetch_assoc();

        if ($fila_anterior) {
            $foto_anterior = $fila_anterior['foto_perfil'] ?? null;
        }

        $stmt_anterior->close();
    }

    /*
    |--------------------------------------------------------------------------
    | GENERAR NOMBRE ÚNICO
    |--------------------------------------------------------------------------
    */
    $nombre_archivo = 'perfil_' . $id_usuario . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;

    $ruta_fisica = $carpeta_fotos . $nombre_archivo;
    $ruta_bd     = $url_fotos . $nombre_archivo;

    /*
    |--------------------------------------------------------------------------
    | GUARDAR IMAGEN
    |--------------------------------------------------------------------------
    */
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_fisica)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=guardar");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR RUTA EN LA BASE DE DATOS
    |--------------------------------------------------------------------------
    */
    $sql_actualizar = "UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?";
    $stmt_actualizar = $con->prepare($sql_actualizar);

    if (!$stmt_actualizar) {

        // Eliminar archivo si ocurrió un error SQL
        if (is_file($ruta_fisica)) {
            @unlink($ruta_fisica);
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?error=bd");
        exit();
    }

    $stmt_actualizar->bind_param("si", $ruta_bd, $id_usuario);

    if (!$stmt_actualizar->execute()) {

        $stmt_actualizar->close();

        if (is_file($ruta_fisica)) {
            @unlink($ruta_fisica);
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?error=bd");
        exit();
    }

    $stmt_actualizar->close();

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR FOTO ANTERIOR
    |--------------------------------------------------------------------------
    */
    if (!empty($foto_anterior)) {

        $archivo_anterior = __DIR__ . '/' . ltrim($foto_anterior, '/');

        if (is_file($archivo_anterior) && realpath($archivo_anterior) !== realpath($ruta_fisica)) {
            @unlink($archivo_anterior);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?foto=guardada");
    exit();
}

/*
|--------------------------------------------------------------------------
| CONSULTAR DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        documento,
        nombres,
        apellidos,
        usuario,
        correo,
        telefono,
        rol,
        foto_perfil
    FROM usuarios
    WHERE id_usuario = ?
";

$stmt = $con->prepare($sql);

if (!$stmt) {
    die("Error al preparar la consulta de usuario.");
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $nombre_completo = trim($row['nombres'] . ' ' . $row['apellidos']);
    $cedula          = $row['documento'];
    $usuario         = $row['usuario'];
    $correo          = $row['correo'];
    $telefono        = $row['telefono'];
    $rol             = ucfirst((string)$row['rol']);
    $foto_perfil     = $row['foto_perfil'] ?? null;

} else {

    session_destroy();

    header("Location: index.php");
    exit();
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| MENSAJES
|--------------------------------------------------------------------------
*/
$mensaje = '';
$tipo_mensaje = '';

if (isset($_GET['foto']) && $_GET['foto'] === 'guardada') {
    $mensaje = 'La foto de perfil se guardó correctamente.';
    $tipo_mensaje = 'success';
}

if (isset($_GET['foto']) && $_GET['foto'] === 'eliminada') {
    $mensaje = 'La foto de perfil fue eliminada.';
    $tipo_mensaje = 'success';
}

if (isset($_GET['error'])) {

    $tipo_mensaje = 'error';

    switch ($_GET['error']) {

        case 'pesada':
            $mensaje = 'La imagen no puede superar los 5 MB.';
            break;

        case 'imagen':
            $mensaje = 'El archivo seleccionado no es una imagen válida.';
            break;

        case 'formato':
            $mensaje = 'Solo se permiten imágenes JPG, PNG o WEBP.';
            break;

        case 'guardar':
            $mensaje = 'No fue posible guardar la imagen.';
            break;

        case 'bd':
            $mensaje = 'No fue posible actualizar la base de datos.';
            break;

        default:
            $mensaje = 'Ocurrió un error al cargar la imagen.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Café control</title>

    <!-- Fuente -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <!-- Lucide -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>

        :root {
            --bg-main: #f5f0eb;
            --bg-sidebar: #1b3818;
            --bg-card: #ffffff;
            --primary-brown: #244b20;
            --primary-dark: #142c12;
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

        /* =========================================================
           HEADER
        ========================================================= */

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

        .brand-logo {
            width: 40px;
            height: 40px;
            background-color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-brown);
        }

        .brand-text h1 {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: 0.3px;
        }

        .header-center {
            display: flex;
            align-items: center;
            flex: 1;
            padding-left: 20px;
        }

        /* Ya no hay botón de las tres rayas */

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
            gap: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar-icon-header {
            width: 42px;
            height: 42px;
            background-color: #fff;
            color: var(--primary-brown);
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-icon-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* =========================================================
           LAYOUT
        ========================================================= */

        .app-layout {
            display: flex;
            flex: 1;
        }

        /* =========================================================
           SIDEBAR
        ========================================================= */

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
            color: #fff;
            text-decoration: none;
            font-size: 0.98rem;
            font-weight: 700;
            transition: all 0.2s ease-in-out;
        }

        .nav-item.active a {
            background-color: rgba(255, 255, 255, 0.18);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-left: 4px solid #ffffff;
        }

        .nav-item:not(.active) a:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        /* =========================================================
           CONTENIDO
        ========================================================= */

        .main-content {
            flex: 1;
            padding: 40px 24px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        /* =========================================================
           TARJETA PERFIL
        ========================================================= */

        .card-profile {
            background-color: var(--bg-card);
            width: 100%;
            max-width: 620px;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .header-card {
            background-color: var(--primary-brown);
            color: white;
            padding: 36px 28px;
            text-align: center;
        }

        /*
        Avatar clickeable
        */

        .avatar-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            overflow: hidden;
            background-color: #d4ebd0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .avatar-container:hover {
            transform: scale(1.04);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }

        .avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-edit-icon {
            position: absolute;
            right: 1px;
            bottom: 1px;
            width: 31px;
            height: 31px;
            border-radius: 50%;
            background: #ffffff;
            color: var(--primary-brown);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }

        .avatar-edit-icon i {
            font-size: 14px;
        }

        .caficultor-name {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.2px;
        }

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

        /* =========================================================
           FOOTER
        ========================================================= */

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

        /* =========================================================
           MODAL EDITAR FOTO
        ========================================================= */

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .profile-modal {
            width: min(100%, 780px);
            max-width: 780px;
            background: #ffffff;
            border-radius: 26px;
            padding: 32px 38px 34px;
            box-shadow: 0 24px 70px rgba(0,0,0,0.30);
            animation: aparecer 0.2s ease;
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-title {
            display: grid;
            grid-template-columns: 92px 1fr 54px;
            align-items: start;
            gap: 20px;
            margin-bottom: 28px;
        }

        .modal-heading-icon {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: #e3f2df;
            color: var(--primary-brown);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
        }

        .modal-heading-text {
            padding-top: 3px;
        }

        .modal-title h3 {
            color: var(--primary-brown);
            font-size: 2rem;
            line-height: 1.15;
            font-weight: 800;
            margin: 0 0 8px;
        }

        .modal-subtitle {
            color: #68758a;
            font-size: 1.05rem;
            line-height: 1.55;
            font-weight: 600;
            max-width: 610px;
        }

        .close-modal {
            width: 54px;
            height: 54px;
            border: none;
            background: #f1ede8;
            color: var(--primary-brown);
            border-radius: 50%;
            cursor: pointer;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .close-modal:hover {
            background: #e9e2da;
            transform: scale(1.04);
        }

        .modal-preview {
            width: 320px;
            height: 320px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 30px;
            border: 6px solid #ffffff;
            outline: 4px solid #a9d59d;
            background: #f7faf6;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 18px rgba(36,75,32,0.08);
        }

        .modal-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .upload-area {
            border: 3px dashed #b7cdb2;
            border-radius: 18px;
            min-height: 116px;
            padding: 20px 34px;
            display: flex;
            align-items: center;
            gap: 28px;
            text-align: left;
            background: #f8fbf7;
            cursor: pointer;
            transition: 0.2s ease;
            margin-bottom: 20px;
        }

        .upload-area:hover {
            border-color: var(--primary-brown);
            background: #f0f6ee;
            transform: translateY(-1px);
        }

        .upload-area i {
            flex: 0 0 62px;
            width: 62px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: var(--primary-brown);
            background: #e2f1df;
            border-radius: 50%;
            margin: 0;
        }

        .upload-text {
            display: block;
        }

        .upload-area strong {
            display: block;
            color: var(--primary-brown);
            font-size: 1.35rem;
            margin-bottom: 4px;
        }

        .upload-area span {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 500;
        }

        #foto_perfil {
            display: none;
        }

        .modal-buttons {
            display: flex;
            gap: 16px;
            margin-top: 4px;
        }

        .btn-modal {
            flex: 1;
            min-height: 62px;
            border: none;
            padding: 14px 22px;
            border-radius: 15px;
            font-weight: 800;
            cursor: pointer;
            font-size: 1.08rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .btn-modal:hover,
        .btn-eliminar:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        .btn-cancelar {
            background: #eee9e4;
            color: #26303d;
        }

        .btn-guardar {
            background: var(--primary-brown);
            color: #fff;
        }

        .btn-eliminar {
            width: 100%;
            min-height: 62px;
            border: none;
            padding: 14px 16px;
            border-radius: 15px;
            background: #fdeeee;
            color: #c72d25;
            font-weight: 800;
            cursor: pointer;
            margin-top: 20px;
            font-size: 1.08rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        /* =========================================================
           ALERTAS
        ========================================================= */

        .alerta {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 10000;
            min-width: 280px;
            max-width: 400px;
            padding: 15px 18px;
            border-radius: 12px;
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .alerta.success {
            background: #e8f5e9;
            color: #216c2a;
            border: 1px solid #b7dfbd;
        }

        .alerta.error {
            background: #fdecea;
            color: #a52318;
            border: 1px solid #f2b8b1;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 900px) {

            .brand {
                width: auto;
            }

            .header-center {
                padding-left: 15px;
            }

            .user-welcome h2 {
                font-size: 0.95rem;
            }

            .sidebar {
                width: 230px;
            }
        }

        @media (max-width: 768px) {

            .top-header {
                padding: 0 15px;
            }

            .brand-text {
                display: none;
            }

            .header-center {
                justify-content: center;
                padding-left: 10px;
            }

            .user-welcome p {
                display: none;
            }

            .user-welcome h2 {
                font-size: 0.85rem;
            }

            .sidebar {
                width: 220px;
                padding: 18px 10px;
            }

            .nav-item a {
                padding: 11px 12px;
                font-size: 0.88rem;
            }

            .main-content {
                padding: 20px 16px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .app-footer {
                font-size: 0.75rem;
                padding: 15px;
            }
        }

        @media (max-width: 600px) {

            .header-actions {
                gap: 5px;
            }

            .user-profile i {
                display: none;
            }

            .sidebar {
                width: 100%;
                max-width: 185px;
            }

            .body-card {
                padding: 25px 22px;
            }

            .header-card {
                padding: 30px 20px;
            }

            .modal-buttons {
                flex-direction: column;
            }
        }


        /* Ajuste final: modal moderado y responsive */
        .profile-modal {
            width: min(100%, 560px);
            max-width: 560px;
            border-radius: 20px;
            padding: 24px 28px 26px;
        }

        .modal-title {
            grid-template-columns: 60px 1fr 42px;
            gap: 14px;
            margin-bottom: 20px;
        }

        .modal-heading-icon {
            width: 60px;
            height: 60px;
            font-size: 27px;
        }

        .modal-title h3 {
            font-size: 1.45rem;
            margin-bottom: 5px;
        }

        .modal-subtitle {
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .close-modal {
            width: 42px;
            height: 42px;
            font-size: 22px;
        }

        .modal-preview {
            width: 190px;
            height: 190px;
            margin-bottom: 22px;
            border-width: 4px;
            outline-width: 3px;
        }

        .upload-area {
            min-height: 82px;
            padding: 14px 20px;
            gap: 16px;
            border-width: 2px;
            border-radius: 14px;
            margin-bottom: 16px;
        }

        .upload-area i {
            flex-basis: 46px;
            width: 46px;
            height: 46px;
            font-size: 24px;
        }

        .upload-area strong {
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .upload-area span {
            font-size: 0.8rem;
        }

        .modal-buttons {
            gap: 10px;
        }

        .btn-modal {
            min-height: 50px;
            padding: 11px 16px;
            border-radius: 11px;
            font-size: 0.95rem;
        }

        .btn-eliminar {
            min-height: 48px;
            padding: 11px 14px;
            border-radius: 11px;
            margin-top: 14px;
            font-size: 0.92rem;
        }

        @media (max-width: 600px) {
            .modal-overlay {
                padding: 12px;
            }

            .profile-modal {
                max-width: 100%;
                padding: 20px 18px 20px;
                border-radius: 18px;
            }

            .modal-title {
                grid-template-columns: 48px 1fr 38px;
                gap: 10px;
                margin-bottom: 16px;
            }

            .modal-heading-icon {
                width: 48px;
                height: 48px;
                font-size: 22px;
            }

            .modal-title h3 {
                font-size: 1.15rem;
            }

            .modal-subtitle {
                font-size: 0.76rem;
            }

            .close-modal {
                width: 38px;
                height: 38px;
                font-size: 19px;
            }

            .modal-preview {
                width: 145px;
                height: 145px;
                margin-bottom: 18px;
            }

            .upload-area {
                min-height: 70px;
                padding: 12px 14px;
                gap: 12px;
            }

            .upload-area i {
                flex-basis: 40px;
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .upload-area strong {
                font-size: 0.9rem;
            }

            .upload-area span {
                font-size: 0.72rem;
            }

            .modal-buttons {
                flex-direction: row;
            }

            .btn-modal {
                min-height: 46px;
                padding: 10px 12px;
                font-size: 0.86rem;
            }

            .btn-eliminar {
                min-height: 44px;
                font-size: 0.82rem;
            }
        }

    </style>

</head>

<body>

<?php if (!empty($mensaje)): ?>

    <div class="alerta <?php echo htmlspecialchars($tipo_mensaje); ?>"
         id="alerta">

        <?php echo htmlspecialchars($mensaje); ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     ENCABEZADO
========================================================= -->

<header class="top-header">

    <div class="brand">

        <div class="brand-logo">

            <i data-lucide="coffee" size="22"></i>

        </div>

        <div class="brand-text">

            <h1>Café control</h1>

        </div>

    </div>


    <!-- SIN LAS TRES RAYITAS -->

    <div class="header-center">

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

        <!-- SIN CAMPANITA -->

        <div class="user-profile">

            <div class="avatar-icon-header">

                <?php if (!empty($foto_perfil)): ?>

                    <img
                        src="<?php echo htmlspecialchars($foto_perfil); ?>"
                        alt="Foto de perfil"
                    >

                <?php else: ?>

                    <i data-lucide="user" size="22"></i>

                <?php endif; ?>

            </div>

        </div>

    </div>

</header>


<div class="app-layout">


    <!-- =====================================================
         MENÚ LATERAL
    ====================================================== -->

    <aside class="sidebar">
        <ul class="nav-list">
            <li class="nav-item active">
                <a href="panel_m.php">
                    <i data-lucide="home" size="20"></i>
                    Perfil
                </a>
            </li>
            <li class="nav-item">
                <a href="recolecciones_m.php">
                    <i data-lucide="archive" size="20"></i>
                    Recolecciones
                </a>
            </li>
            <li class="nav-item">
                <a href="recolectores_m.php">
                    <i data-lucide="users" size="20"></i>
                    Trabajadores
                </a>
            </li>
            <li class="nav-item">
                <a href="resumen_m.php">
                    <i data-lucide="bar-chart-2" size="20"></i>
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
                    <i class="fa-solid fa-user-gear"></i>
                    Gestionar Usuario
                </a>
            </li>
            <li class="nav-item">
                <a href="cerrar.php">
                    <i data-lucide="log-out" size="20"></i>
                    Cerrar Sesión
                </a>
            </li>
        </ul>
    </aside>


    <!-- =====================================================
         CONTENIDO
    ====================================================== -->

    <main class="main-content">

        <div class="card-profile">


            <!-- =================================================
                 ENCABEZADO DEL PERFIL
            ================================================== -->

            <div class="header-card">


                <!-- FOTO CLICKEABLE -->

                <div
                    class="avatar-container"
                    id="abrirEditarFoto"
                    title="Editar foto de perfil"
                >

                    <?php if (!empty($foto_perfil)): ?>

                        <img
                            id="imagenPerfilPrincipal"
                            src="<?php echo htmlspecialchars($foto_perfil); ?>?v=<?php echo time(); ?>"
                            alt="Foto de perfil"
                        >

                    <?php else: ?>

                        <i
                            data-lucide="user"
                            size="56"
                            style="color: var(--primary-brown);"
                        ></i>

                    <?php endif; ?>


                    <!-- ICONO DE CÁMARA -->

                    <div class="avatar-edit-icon">

                        <i class="fa-solid fa-camera"></i>

                    </div>

                </div>


                <h2 class="caficultor-name">

                    <?php echo htmlspecialchars($nombre_completo); ?>

                </h2>

            </div>


            <!-- =================================================
                 CUERPO
            ================================================== -->

            <div class="body-card">

                <div class="section-title">

                    Información del Administrador

                </div>


                <div class="info-grid">


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


                    <div class="info-item">

                        <span class="label">
                            Cédula:
                        </span>

                        <span class="value">

                            <?php
                            echo htmlspecialchars((string)$cedula);
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <span class="label">
                            Usuario:
                        </span>

                        <span class="value">

                            <?php
                            echo htmlspecialchars((string)$usuario);
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <span class="label">
                            Correo:
                        </span>

                        <span class="value">

                            <?php
                            echo htmlspecialchars((string)$correo);
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <span class="label">
                            Teléfono:
                        </span>

                        <span class="value">

                            <?php
                            echo htmlspecialchars((string)$telefono);
                            ?>

                        </span>

                    </div>


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

    </main>

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="app-footer">

    <i data-lucide="coffee" size="16"></i>

    <span>

        <strong>Café control</strong>

        © 2026 Developers:
        Gloria Tatiana Sanchez &
        Tatiana Silva Penagos

    </span>

</footer>


<!-- =========================================================
     MODAL EDITAR FOTO
========================================================= -->

<div
    class="modal-overlay"
    id="modalEditarFoto"
>

    <div
        class="profile-modal"
        onclick="event.stopPropagation();"
    >


        <div class="modal-title">

            <div class="modal-heading-icon" aria-hidden="true">
                <i class="fa-solid fa-camera"></i>
            </div>

            <div class="modal-heading-text">
                <h3>Editar foto de perfil</h3>
                <div class="modal-subtitle">
                    Puedes cambiar tu foto de perfil. Se recomienda una imagen
                    en formato JPG, PNG o WEBP (máximo 5 MB).
                </div>
            </div>

            <button
                type="button"
                class="close-modal"
                id="cerrarModal"
                aria-label="Cerrar"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <!-- PREVISUALIZACIÓN -->

        <div class="modal-preview" id="previewContainer">

            <?php if (!empty($foto_perfil)): ?>

                <img
                    id="previewImagen"
                    src="<?php echo htmlspecialchars($foto_perfil); ?>?v=<?php echo time(); ?>"
                    alt="Vista previa"
                >

            <?php else: ?>

                <i
                    id="previewIcono"
                    data-lucide="user"
                    size="70"
                    style="color: var(--primary-brown);"
                ></i>

                <img
                    id="previewImagen"
                    src=""
                    alt="Vista previa"
                    style="display:none;"
                >

            <?php endif; ?>

        </div>


        <!-- FORMULARIO -->

        <form
            method="POST"
            enctype="multipart/form-data"
            id="formFoto"
        >


            <label
                for="foto_perfil"
                class="upload-area"
            >

                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>

                <span class="upload-text">
                    <strong>Seleccionar imagen</strong>
                    <span>JPG, PNG o WEBP — máximo 5 MB</span>
                </span>

            </label>


            <input
                type="file"
                name="foto_perfil"
                id="foto_perfil"
                accept="image/jpeg,image/png,image/webp"
                required
            >


            <div class="modal-buttons">

                <button
                    type="button"
                    class="btn-modal btn-cancelar"
                    id="cancelarModal"
                >

                    <i class="fa-solid fa-xmark"></i>
                    Cancelar

                </button>


                <button
                    type="submit"
                    class="btn-modal btn-guardar"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    Guardar

                </button>

            </div>

        </form>


        <!-- ELIMINAR FOTO -->

        <?php if (!empty($foto_perfil)): ?>

            <form
                method="POST"
                onsubmit="return confirmarEliminarFoto();"
            >

                <button
                    type="submit"
                    name="eliminar_foto"
                    value="1"
                    class="btn-eliminar"
                >

                    <i class="fa-solid fa-trash"></i>

                    Quitar foto de perfil

                </button>

            </form>

        <?php endif; ?>


    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    // Inicializar Lucide
    lucide.createIcons();


    const modal = document.getElementById('modalEditarFoto');

    const abrirEditarFoto =
        document.getElementById('abrirEditarFoto');

    const cerrarModal =
        document.getElementById('cerrarModal');

    const cancelarModal =
        document.getElementById('cancelarModal');

    const inputFoto =
        document.getElementById('foto_perfil');

    const previewImagen =
        document.getElementById('previewImagen');

    const previewIcono =
        document.getElementById('previewIcono');


    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL
    |--------------------------------------------------------------------------
    */

    abrirEditarFoto.addEventListener('click', function () {

        modal.classList.add('show');

    });


    /*
    |--------------------------------------------------------------------------
    | CERRAR MODAL
    |--------------------------------------------------------------------------
    */

    function cerrarVentana() {

        modal.classList.remove('show');

    }


    cerrarModal.addEventListener(
        'click',
        cerrarVentana
    );


    cancelarModal.addEventListener(
        'click',
        cerrarVentana
    );


    /*
    |--------------------------------------------------------------------------
    | CERRAR HACIENDO CLICK AFUERA
    |--------------------------------------------------------------------------
    */

    modal.addEventListener('click', function (event) {

        if (event.target === modal) {

            cerrarVentana();

        }

    });


    /*
    |--------------------------------------------------------------------------
    | ESC PARA CERRAR
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {

            cerrarVentana();

        }

    });


    /*
    |--------------------------------------------------------------------------
    | PREVISUALIZAR IMAGEN
    |--------------------------------------------------------------------------
    */

    inputFoto.addEventListener('change', function () {

        const archivo = this.files[0];

        if (!archivo) {
            return;
        }


        const tiposPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];


        if (!tiposPermitidos.includes(archivo.type)) {

            alert(
                'Solo se permiten imágenes JPG, PNG o WEBP.'
            );

            this.value = '';

            return;
        }


        if (archivo.size > 5 * 1024 * 1024) {

            alert(
                'La imagen no puede superar los 5 MB.'
            );

            this.value = '';

            return;
        }


        const lector = new FileReader();


        lector.onload = function (e) {

            previewImagen.src = e.target.result;

            previewImagen.style.display = 'block';


            if (previewIcono) {

                previewIcono.style.display = 'none';

            }

        };


        lector.readAsDataURL(archivo);

    });


    /*
    |--------------------------------------------------------------------------
    | CONFIRMAR ELIMINACIÓN
    |--------------------------------------------------------------------------
    */

    function confirmarEliminarFoto() {

        return confirm(
            '¿Está seguro de que desea quitar la foto de perfil?'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | OCULTAR MENSAJE AUTOMÁTICAMENTE
    |--------------------------------------------------------------------------
    */

    const alerta =
        document.getElementById('alerta');

    if (alerta) {

        setTimeout(function () {

            alerta.style.opacity = '0';

            alerta.style.transition = 'opacity 0.5s ease';


            setTimeout(function () {

                alerta.remove();

            }, 500);

        }, 3500);

    }

</script>

</body>
</html>