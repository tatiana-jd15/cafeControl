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

function fecha_larga_es(DateTimeInterface $date): string {
    $dias = [
        'Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes',
        'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes',
        'Saturday' => 'sábado'
    ];
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $dia = $dias[$date->format('l')] ?? $date->format('l');
    return $dia . ' ' . $date->format('d') . ' de ' . $meses[(int)$date->format('n')] . ' de ' . $date->format('Y');
}

function inicio_semana_sabado(DateTimeImmutable $fecha): DateTimeImmutable {
    $dias_desde_sabado = ((int)$fecha->format('w') + 1) % 7;
    return $fecha->modify("-{$dias_desde_sabado} days");
}

$mensaje = '';
$tipo_mensaje = 'success';

/* Token CSRF para guardar el perfil */
if (empty($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}

/*
 * Se crea una tabla auxiliar para guardar la foto de perfil.
 * Así no es necesario modificar la tabla usuarios existente.
 */
$con->query(
    "CREATE TABLE IF NOT EXISTS perfiles_usuario (
        id_usuario INT NOT NULL PRIMARY KEY,
        foto_perfil VARCHAR(255) NULL,
        fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

/* Guardar cambios del perfil */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_perfil'])) {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_perfil'], (string)$_POST['csrf'])) {
        $mensaje = 'La sesión del formulario no es válida. Recarga la página e inténtalo nuevamente.';
        $tipo_mensaje = 'danger';
    } else {
        $nuevos_nombres = trim((string)($_POST['nombres'] ?? ''));
        $nuevos_apellidos = trim((string)($_POST['apellidos'] ?? ''));

        if ($nuevos_nombres === '' || $nuevos_apellidos === '') {
            $mensaje = 'Los nombres y apellidos son obligatorios.';
            $tipo_mensaje = 'danger';
        } else {
            $foto_guardada = null;
            $foto_anterior = null;

            $stmt_foto = $con->prepare("SELECT foto_perfil FROM perfiles_usuario WHERE id_usuario = ? LIMIT 1");
            if ($stmt_foto) {
                $stmt_foto->bind_param('i', $id_usuario);
                if ($stmt_foto->execute()) {
                    $res_foto = $stmt_foto->get_result();
                    if ($row_foto = $res_foto->fetch_assoc()) {
                        $foto_anterior = (string)($row_foto['foto_perfil'] ?? '');
                    }
                }
                $stmt_foto->close();
            }

            /* Procesar imagen solo cuando el usuario seleccionó una */
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
                $archivo = $_FILES['foto_perfil'];

                if ($archivo['error'] !== UPLOAD_ERR_OK) {
                    $mensaje = 'No se pudo subir la imagen seleccionada.';
                    $tipo_mensaje = 'danger';
                } elseif ((int)$archivo['size'] > 2 * 1024 * 1024) {
                    $mensaje = 'La imagen no puede superar 2 MB.';
                    $tipo_mensaje = 'danger';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($archivo['tmp_name']);

                    $permitidos = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($permitidos[$mime])) {
                        $mensaje = 'Solo se permiten imágenes JPG, PNG o WEBP.';
                        $tipo_mensaje = 'danger';
                    } else {
                        $directorio = __DIR__ . '/uploads/perfiles';
                        if (!is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                            $mensaje = 'No se pudo crear la carpeta para las imágenes.';
                            $tipo_mensaje = 'danger';
                        } else {
                            $nombre_archivo = 'usuario_' . $id_usuario . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
                            $destino = $directorio . '/' . $nombre_archivo;

                            if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
                                $mensaje = 'No se pudo guardar la imagen.';
                                $tipo_mensaje = 'danger';
                            } else {
                                $foto_guardada = 'uploads/perfiles/' . $nombre_archivo;
                            }
                        }
                    }
                }
            }

            if ($mensaje === '') {
                $con->begin_transaction();

                try {
                    $stmt_user = $con->prepare(
                        "UPDATE usuarios
                         SET nombres = ?, apellidos = ?
                         WHERE id_usuario = ?"
                    );

                    if (!$stmt_user) {
                        throw new RuntimeException('No fue posible preparar la actualización del usuario.');
                    }

                    $stmt_user->bind_param('ssi', $nuevos_nombres, $nuevos_apellidos, $id_usuario);
                    if (!$stmt_user->execute()) {
                        throw new RuntimeException('No fue posible actualizar los datos del usuario.');
                    }
                    $stmt_user->close();

                    if ($foto_guardada !== null) {
                        $stmt_perfil = $con->prepare(
                            "INSERT INTO perfiles_usuario (id_usuario, foto_perfil)
                             VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE foto_perfil = VALUES(foto_perfil)"
                        );

                        if (!$stmt_perfil) {
                            throw new RuntimeException('No fue posible guardar la foto del perfil.');
                        }

                        $stmt_perfil->bind_param('is', $id_usuario, $foto_guardada);
                        if (!$stmt_perfil->execute()) {
                            throw new RuntimeException('No fue posible guardar la foto del perfil.');
                        }
                        $stmt_perfil->close();
                    }

                    $con->commit();

                    /* Elimina la imagen anterior solo después de confirmar la transacción. */
                    if ($foto_guardada !== null && $foto_anterior !== '') {
                        $ruta_anterior = __DIR__ . '/' . ltrim($foto_anterior, '/');
                        if (is_file($ruta_anterior)) {
                            @unlink($ruta_anterior);
                        }
                    }

                    $mensaje = 'Perfil actualizado correctamente.';
                    $tipo_mensaje = 'success';
                    $_SESSION['nombre_usuario'] = $nuevos_nombres;
                    $_SESSION['apellido_usuario'] = $nuevos_apellidos;
                } catch (Throwable $e) {
                    $con->rollback();

                    if ($foto_guardada !== null) {
                        $ruta_nueva = __DIR__ . '/' . ltrim($foto_guardada, '/');
                        if (is_file($ruta_nueva)) {
                            @unlink($ruta_nueva);
                        }
                    }

                    $mensaje = 'No se pudieron guardar los cambios del perfil.';
                    $tipo_mensaje = 'danger';
                }
            }
        }
    }
}

/* Datos actualizados del usuario desde la base de datos */
$nombre_usuario = 'Usuario';
$apellido_usuario = '';
$rol_usuario = 'recolector';
$estado_usuario = 'activo';

$stmt = $con->prepare(
    "SELECT nombres, apellidos, rol, estado
     FROM usuarios
     WHERE id_usuario = ?
     LIMIT 1"
);

if (!$stmt) {
    die('No fue posible consultar los datos del usuario.');
}

$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$res = $stmt->get_result();

if ($fila = $res->fetch_assoc()) {
    $nombre_usuario = trim((string)$fila['nombres']);
    $apellido_usuario = trim((string)$fila['apellidos']);
    $rol_usuario = (string)$fila['rol'];
    $estado_usuario = (string)$fila['estado'];
} else {
    session_destroy();
    header('Location: index.php');
    exit;
}
$stmt->close();

$nombre_completo = trim($nombre_usuario . ' ' . $apellido_usuario);

$roles_mostrados = [
    'caficultor' => 'Caficultor',
    'mayordomo' => 'Mayordomo',
    'recolector' => 'Recolector'
];
$rol_mostrado = $roles_mostrados[$rol_usuario] ?? ucfirst($rol_usuario);

$es_activo = ($estado_usuario === 'activo');
$texto_estado = $es_activo ? 'Activo' : 'Inactivo';

/* Foto actual */
$foto_perfil = '';
$stmt = $con->prepare("SELECT foto_perfil FROM perfiles_usuario WHERE id_usuario = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($fila = $res->fetch_assoc()) {
        $foto_perfil = trim((string)($fila['foto_perfil'] ?? ''));
    }
    $stmt->close();
}

$foto_url = ($foto_perfil !== '') ? $foto_perfil : '';

/*
 * Semana de trabajo: sábado a viernes.
 * Por eso la lista se actualiza sola cada sábado.
 */
$hoy = new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
$inicio_semana_actual = inicio_semana_sabado($hoy);
$fin_semana_actual = $inicio_semana_actual->modify('+6 days');

/* Fecha mínima de las entregas del usuario para construir semanas históricas */
$fecha_minima_entrega = null;
$stmt = $con->prepare("SELECT MIN(fecha) AS fecha_minima FROM recolecciones WHERE id_recolector = ?");
if ($stmt) {
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($fila = $res->fetch_assoc()) {
        $valor = (string)($fila['fecha_minima'] ?? '');
        if ($valor !== '') {
            $dt_min = DateTimeImmutable::createFromFormat('Y-m-d', $valor, new DateTimeZone('America/Bogota'));
            if ($dt_min) {
                $fecha_minima_entrega = inicio_semana_sabado($dt_min);
            }
        }
    }
    $stmt->close();
}

$primera_semana = $fecha_minima_entrega ?: $inicio_semana_actual;

/* Semana seleccionada por GET; siempre se valida como sábado */
$semana_solicitada = trim((string)($_GET['semana'] ?? ''));
$inicio_semana = $inicio_semana_actual;

if ($semana_solicitada !== '') {
    $candidata = DateTimeImmutable::createFromFormat('!Y-m-d', $semana_solicitada, new DateTimeZone('America/Bogota'));
    if ($candidata) {
        $candidata = inicio_semana_sabado($candidata);
        if ($candidata >= $primera_semana && $candidata <= $inicio_semana_actual) {
            $inicio_semana = $candidata;
        }
    }
}

$fin_semana = $inicio_semana->modify('+6 days');

/* Lista de semanas desde la primera entrega hasta la semana actual */
$semanas = [];
$cursor = $primera_semana;
$numero_semana = 1;

while ($cursor <= $inicio_semana_actual) {
    $semanas[] = [
        'numero' => $numero_semana++,
        'inicio' => $cursor->format('Y-m-d'),
        'fin' => $cursor->modify('+6 days')->format('Y-m-d'),
        'etiqueta' => $cursor->format('d/m/Y') . ' - ' . $cursor->modify('+6 days')->format('d/m/Y')
    ];
    $cursor = $cursor->modify('+7 days');
}

/* Entregas completas de la semana seleccionada */
$entregas = [];
$stmt = $con->prepare(
    "SELECT id_recoleccion, fecha, kilos_recolectados, precio_kilo_aplicado, pago_calculado
     FROM recolecciones
     WHERE id_recolector = ?
       AND fecha BETWEEN ? AND ?
     ORDER BY fecha ASC, id_recoleccion ASC"
);

if ($stmt) {
    $inicio_sql = $inicio_semana->format('Y-m-d');
    $fin_sql = $fin_semana->format('Y-m-d');

    $stmt->bind_param('iss', $id_usuario, $inicio_sql, $fin_sql);
    $stmt->execute();
    $res = $stmt->get_result();

    $contador = 1;
    while ($fila = $res->fetch_assoc()) {
        $fila['numero_entrega'] = $contador++;
        $entregas[] = $fila;
    }
    $stmt->close();
}

$ahora = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
$fecha_hora_iso = $ahora->format(DATE_ATOM);

/* Rango legible */
$rango_semana = $inicio_semana->format('d/m/Y') . ' - ' . $fin_semana->format('d/m/Y');
$nombre_semana_actual = 'Semana seleccionada';

foreach ($semanas as $semana) {
    if ($semana['inicio'] === $inicio_semana->format('Y-m-d')) {
        $nombre_semana_actual = 'Semana ' . $semana['numero'] . ': ' . $semana['etiqueta'];
        break;
    }
}

/* Para el modal, conservar los valores ya presentes aunque todavía no se haya guardado */
$valor_form_nombres = $nombre_usuario;
$valor_form_apellidos = $apellido_usuario;

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Entregas - Gestor de Cuentas de Café</title>
    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f7f3ef;
            color: #17120f;
            font-size: 18px;
            line-height: 1.55;
        }
        /* MENU LATERAL */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #1c110b;
            color: white;
            padding: 20px 12px;
            overflow-y: auto;
            z-index: 1000;
        }
        /* LOGO */
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 5px 20px;
            border-bottom: 2px solid #382820;
        }
        .logo {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a2b1c;
            font-size: 23px;
        }
        .logo-text h5 {
            margin: 0;
            color: white;
            font-size: 16px;
            line-height: 19px;
            font-weight: 800;
        }
        .logo-text small {
            display: block;
            margin-top: 3px;
            color: #eeeeee;
            font-size: 11px;
        }
        /*  USUARIO */
        .user-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
            padding: 14px 12px;
            border-radius: 12px;
            background-color: #2b201a;
        }
        .user-photo {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 50%;
            background-color: #d9d9d9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #222;
            font-size: 22px;
        }
        .user-info h6 {
            margin: 0;
            color: white;
            font-size: 15px;
            line-height: 19px;
            font-weight: 800;
        }
        .user-info small {
            display: block;
            color: #eeeeee;
            font-size: 13px;
        }
        .activo {
            color: #32df68 !important;
            font-weight: bold;
        }
        /* MENU */
        .menu {
            margin-top: 18px;
        }
        .menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            min-height: 50px;
            margin-bottom: 7px;
            padding: 12px 14px;
            border-radius: 12px;
            text-decoration: none;
            color: white;
            font-size: 16px;
            font-weight: 700;
            transition: 0.2s;
        }
        .menu a:hover {
            background-color: #30231c;
        }
        /* MIS ENTREGAS RESALTADO */
        .menu a.active {
            background-color: #fff1e6;
            color: #1c110b;
        }
        .menu i {
            width: 23px;
            text-align: center;
            font-size: 18px;
        }
        /*  IMAGEN */
        .coffee-image {
            width: 160px;
            height: 210px;
            margin: 50px auto 20px;
            border-radius: 15px;
            background-image: url(
                "https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=500&q=85"
            );
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }
        /* CERRAR SESION */
        .logout {
            width: 100%;
            margin-top: 15px;
            padding-bottom: 10px;
        }
        .logout button {
            width: 100%;
            min-height: 48px;
            border: 2px solid #49372d;
            border-radius: 12px;
            background-color: #2b201a;
            color: white;
            font-size: 15px;
            font-weight: 800;
        }
        .logout button:hover {
            background-color: #412f26;
        }
        /*  CONTENIDO PRINCIPAL */
        .main {
            margin-left: 250px;
            min-height: 100vh;
            padding: 0 25px 25px;
            display: flex;
            flex-direction: column;
        }
                .topbar {
            min-height: 55px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 55px;
        }
        .menu-button {
            border: none;
            background: transparent;
            color: #211b17;
            font-size: 26px;
            padding: 6px 10px;
        }
                .date-time {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 22px;
            color: #211b17;
            font-size: 18px;
            line-height: 1;
            font-weight: 800;
            white-space: nowrap;
            margin-left: auto;
        }
        .date-time > span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .date-time i {
            font-size: 19px;
        }
        /*  CONTENIDO*/
        .content {
            flex: 1 0 auto;
            min-height: calc(100vh - 70px);
            padding: 28px;
            background-color: #fffdfb;
            border: 2px solid #e5ddd6;
            border-radius: 14px;
        }
        /*  BIENVENIDA */
        .welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 22px;
            border-bottom: 2px solid #e4dbd4;
        }
        .welcome h1 {
            margin: 0 0 8px;
            color: #15110e;
            font-size: 34px;
            font-weight: 800;
        }
        .welcome p {
            margin: 0;
            color: #4d433d;
            font-size: 17px;
            line-height: 25px;
        }
        .coffee-top {
            width: 260px;
            height: 95px;
            border-radius: 14px;
            background-image: url(
                "images/cafe2_r.jpg"
            );
            background-size: cover;
            background-position: center;
        }
        /*  TITULO */
        .delivery-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
        }
        .delivery-title i {
            font-size: 24px;
        }
        .delivery-title h2 {
            margin: 0;
            font-size: 25px;
            font-weight: 800;
        }
        /*  SEMANA */
        .month-select {
            width: 220px;
            margin-top: 13px;
        }
        .month-select .input-group-text {
            background-color: white;
            border: 2px solid #cfc4bc;
            color: #17120f;
            font-size: 18px;
        }
        .month-select select {
            height: 45px;
            border: 2px solid #cfc4bc;
            border-left: 0;
            background-color: white;
            color: #17120f;
            font-size: 16px;
            font-weight: 700;
        }
        /*  TABLA */
        .table-container {
            margin-top: 16px;
            overflow-x: auto;
            border: 2px solid #d9d0c9;
            border-radius: 12px;
            background-color: white;
        }
        .delivery-table {
            width: 100%;
            min-width: 850px;
            margin: 0;
            border-collapse: collapse;
        }
        .delivery-table thead {
            background-color: #eee5de;
        }
        .delivery-table th {
            height: 48px;
            padding: 10px 28px;
            border-bottom: 2px solid #cfc4bc;
            color: #17120f;
            font-size: 17px;
            font-weight: 800;
            text-align: left;
        }
        .delivery-table td {
            height: 43px;
            padding: 8px 28px;
            border-bottom: 1px solid #ddd4cd;
            color: #211c18;
            font-size: 17px;
            font-weight: 600;
        }
        .delivery-table tbody tr:nth-child(even) {
            background-color: #faf7f4;
        }
        .delivery-table tbody tr:hover {
            background-color: #f1e8e0;
        }
        .weight {
            color: #11100e;
            font-weight: 800;
        }
        /*  ESTADO*/
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 11px;
            border-radius: 20px;
            background-color: #dff3e4;
            color: #075f2b;
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
        }
        /* ALTA VISIBILIDAD */
        a:focus,
        button:focus,
        select:focus {
            outline: 4px solid #e69b38;
            outline-offset: 3px;
        }
        /* TABLET / CELULAR */
        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
                transition: 0.25s;
                box-shadow: 5px 0 20px rgba(0, 0, 0, 0.35);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main {
                margin-left: 0;
                padding: 0 12px 15px;
            }
            .content {
                padding: 18px;
            }
            .welcome {
                display: block;
            }
            .welcome h1 {
                font-size: 29px;
            }
            .welcome p {
                font-size: 16px;
            }
            .coffee-top {
                display: none;
            }
            .delivery-table {
                min-width: 850px;
            }
        }
        @media (max-width: 480px) {
            .content {
                border: none;
                background-color: transparent;
                padding: 5px;
            }
            .welcome h1 {
                font-size: 26px;
            }
            .welcome p {
                font-size: 16px;
            }
            .date-time {
                font-size: 15px;
                gap: 10px;
            }
            .date-time span:first-child {
                display: none;
            }
        }
    
        /* PERFIL INTERACTIVO */
        .profile-trigger {
            width: 100%;
            border: none;
            text-align: left;
            cursor: pointer;
            color: inherit;
        }
        .profile-trigger:focus {
            outline: 3px solid #ffb000;
            outline-offset: 2px;
        }
        .user-photo-wrap {
            position: relative;
            width: 50px;
            height: 50px;
            flex: 0 0 50px;
        }
        .user-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }
        .user-photo-wrap > .user-photo:not(img) {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-camera {
            position: absolute;
            right: -2px;
            bottom: -2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffffff;
            color: #1c110b;
            border: 2px solid #2b201a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
        }
        .status-dot {
            position: absolute;
            right: 0;
            bottom: 2px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #2b201a;
        }
        .dot-active { background: #22c55e; }
        .dot-inactive { background: #ef4444; }
        .inactivo {
            color: #ff7777 !important;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .status-mini-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
        }
        .selected-week-label {
            margin-top: 10px;
            color: #4d433d;
            font-size: 14px;
            font-weight: 700;
        }
        .empty-row {
            text-align: center !important;
            padding: 30px !important;
            color: #655a53 !important;
            font-weight: 700 !important;
        }
        body.modal-open {
            overflow: hidden;
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
        @media (max-width: 600px) {
            .profile-form-grid {
                grid-template-columns: 1fr;
            }
            .profile-dialog {
                padding: 20px;
            }
        }

    
        /* PIE DE PÁGINA UNIFICADO */
        .app-footer {
            width: 100%;
            margin-top: auto;
            padding: 18px 24px;
            border-top: 1px solid #d8cfc7;
            background: #f4eee8;
            color: #4a403a;
            box-sizing: border-box;
        }
        .footer-inner {
            min-height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            text-align: center;
            font-size: 15px;
            line-height: 1.4;
            font-weight: 600;
        }
        .footer-inner i {
            font-size: 18px;
            flex: 0 0 auto;
        }
        @media (max-width: 600px) {
            .app-footer { padding: 14px 12px; }
            .footer-inner { font-size: 13px; }
        }
</style>
</head>
<body>
    <!--  MENU LATERAL -->
    <aside class="sidebar" id="sidebar">
        <!-- LOGO -->
        <div class="logo-area">
            <div class="logo">
                <i class="fa-solid fa-mug-hot"></i>
            </div>
            <div class="logo-text">
                <h5>
                   Café control
                </h5>
            </div>
        </div>
        <!-- USUARIO -->
        <button type="button" class="user-box profile-trigger" id="profileTrigger" aria-label="Editar perfil">
            <div class="user-photo-wrap">
                <?php if ($foto_url !== ''): ?>
                    <img class="user-photo" src="<?php echo e($foto_url); ?>?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/' . ltrim($foto_url, '/'))); ?>" alt="Foto de <?php echo e($nombre_completo); ?>">
                <?php else: ?>
                    <div class="user-photo"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <span class="profile-camera"><i class="fa-solid fa-camera"></i></span>
                <span class="status-dot <?php echo $es_activo ? 'dot-active' : 'dot-inactive'; ?>"></span>
            </div>
            <div class="user-info">
                <h6><?php echo e($nombre_usuario); ?><br><?php echo e($apellido_usuario); ?></h6>
                <small><?php echo e($rol_mostrado); ?></small>
                <small class="<?php echo $es_activo ? 'activo' : 'inactivo'; ?>">
                    <span class="status-mini-dot"></span><?php echo e($texto_estado); ?>
                </small>
            </div>
        </button>
        <!-- MENU -->
        <nav class="menu">
            <a href="panel_r.php">
                <i class="fa-solid fa-house"></i>
                <span>Inicio</span>
            </a>
            <a href="entregas_r.php" class="active">
                <i class="fa-solid fa-basket-shopping"></i>
                <span>Mis Entregas</span>
            </a> 
            <a href="informacion_r.php">
                <i class="fa-solid fa-circle-info"></i>
                <span>Información</span>
            </a>
        </nav>
        <!-- CERRAR SESION -->
        <div class="logout">
           <a href="cerrar.php">
             <button type="button">
                <i class="fa-solid fa-power-off"></i>
                &nbsp; Cerrar sesión
            </button>
           </a>
        </div>
    </aside>
    <!-- =================================
         CONTENIDO PRINCIPAL
    ================================= -->
    <main class="main">
        <header class="topbar">
            <div class="date-time">
                <span>
                    <i class="fa-regular fa-calendar"></i>
                    <span id="fechaActual"><?php echo e($ahora->format("d/m/Y")); ?></span>
                </span>
                <span>|</span>
                <span>
                    <i class="fa-regular fa-clock"></i>
                    <span id="horaActual"><?php echo e($ahora->format('g:i a')); ?></span>
                </span>
            </div>
        </header>
        <section class="content">
            <!-- BIENVENIDA -->
            <div class="welcome">
                <div>
                    <h1>
                        ¡Hola, <?php echo e($nombre_usuario); ?>! 👋
                    </h1>
                    <p>
                        Aquí puedes ver el resumen de tus entregas
                        de café recolectado y tus pagos semanales.
                    </p>
                </div>
                <div class="coffee-top"></div>
            </div>
            <!-- TITULO -->
            <div class="delivery-title">
                <i class="fa-solid fa-briefcase"></i>
                <h2>
                    Mis entregas
                </h2>
            </div>
            <!-- SEMANA -->
          
            <div class="selected-week-label">
                <?php echo e($rango_semana); ?>
            </div>
            <!-- TABLA: TODAS LAS ENTREGAS DE LA SEMANA -->
            <div class="table-container">
                <table class="delivery-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Entrega</th>
                            <th>Peso (kg)</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="4" class="empty-row">
                                    No hay entregas registradas en esta semana.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td>
                                        <i class="fa-regular fa-calendar"></i>
                                        <?php
                                        $dt_fecha = DateTimeImmutable::createFromFormat(
                                            'Y-m-d',
                                            (string)$entrega['fecha'],
                                            new DateTimeZone('America/Bogota')
                                        );
                                        echo e($dt_fecha ? $dt_fecha->format('d/m/Y') : (string)$entrega['fecha']);
                                        ?>
                                    </td>
                                    <td>Entrega <?php echo (int)$entrega['numero_entrega']; ?></td>
                                    <td class="weight"><?php echo number_format((float)$entrega['kilos_recolectados'], 1, ',', '.'); ?> kg</td>
                                    <td>
                                        <span class="status">
                                            Registrada <i class="fa-solid fa-check"></i>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <!-- MODAL EDITAR PERFIL: mismo componente visual en todas las interfaces -->
    <div class="profile-modal" id="perfilModal" role="dialog" aria-modal="true" aria-labelledby="tituloPerfil" aria-hidden="true">
        <div class="profile-modal-card">
            <div class="profile-modal-head">
                <h3 id="tituloPerfil">Editar perfil</h3>
                <button type="button" class="profile-close" id="cerrarPerfil" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div class="profile-message <?php echo e($tipo_mensaje); ?>"><?php echo e($mensaje); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formPerfil">
                <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_perfil']); ?>">
                <input type="hidden" name="guardar_perfil" value="1">

                <div class="profile-photo-wrap">
                    <?php if ($foto_url !== ''): ?>
                        <img class="profile-photo-preview" id="vistaPerfil" src="<?php echo e($foto_url); ?>?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/' . ltrim($foto_url, '/'))); ?>" alt="Vista previa de la foto de perfil">
                    <?php else: ?>
                        <div class="profile-photo-preview" id="vistaPerfil" style="display:flex;align-items:center;justify-content:center;color:#17100c;font-size:2rem;"><i class="fa-solid fa-user"></i></div>
                    <?php endif; ?>
                </div>

                <label class="profile-photo-label" for="fotoPerfilInput">
                    <i class="fa-solid fa-camera"></i> Cambiar fotografía
                </label>
                <input class="sr-only" type="file" id="fotoPerfilInput" name="foto_perfil" accept="image/jpeg,image/png,image/webp">

                <div class="profile-field">
                    <label for="perfilNombres">Nombres</label>
                    <input type="text" id="perfilNombres" name="nombres" value="<?php echo e($valor_form_nombres); ?>" maxlength="100" required>
                </div>
                <div class="profile-field">
                    <label for="perfilApellidos">Apellidos</label>
                    <input type="text" id="perfilApellidos" name="apellidos" value="<?php echo e($valor_form_apellidos); ?>" maxlength="100" required>
                </div>

                <div class="profile-actions">
                    <button type="button" class="profile-btn cancel" id="cancelarPerfil">Cancelar</button>
                    <button type="submit" class="profile-btn save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fecha y hora sincronizadas con Colombia.
        (function () {
            const fechaEl = document.getElementById('fechaActual');
            const horaEl = document.getElementById('horaActual');
            if (!fechaEl || !horaEl) return;

            function actualizarReloj() {
                const ahora = new Date();

                const partesFecha = new Intl.DateTimeFormat('es-CO', {
                    timeZone: 'America/Bogota',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }).formatToParts(ahora);

                const obtener = (tipo) => {
                    const parte = partesFecha.find(p => p.type === tipo);
                    return parte ? parte.value : '';
                };

                const fecha = `${obtener('day')}/${obtener('month')}/${obtener('year')}`;

                const hora = new Intl.DateTimeFormat('es-CO', {
                    timeZone: 'America/Bogota',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                }).format(ahora)
                  .toLowerCase()
                  .replace('a. m.', 'am')
                  .replace('p. m.', 'pm');

                fechaEl.textContent = fecha;
                horaEl.textContent = hora;
            }

            actualizarReloj();
            setInterval(actualizarReloj, 1000);
        })();

        function cambiarSemana(fechaInicio) {
            if (!fechaInicio) return;
            window.location.href = 'entregas_r.php?semana=' + encodeURIComponent(fechaInicio);
        }

        // Componente de perfil compartido con el panel de inicio.
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
            }
            function cerrarModal() {
                if (!modal) return;
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }

            abrir?.addEventListener('click', abrirModal);
            cerrar?.addEventListener('click', cerrarModal);
            cancelar?.addEventListener('click', cerrarModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) cerrarModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') cerrarModal();
            });

            inputFoto?.addEventListener('change', function () {
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
                    vista.innerHTML = '';
                    vista.style.display = 'block';
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Vista previa de la nueva foto';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '50%';
                    vista.appendChild(img);
                }
            });

<?php if (isset($_GET['perfil']) && $_GET['perfil'] === 'guardado'): ?>
            abrirModal();
<?php endif; ?>
        })();
    </script>

        <footer class="app-footer">
            <div class="footer-inner">
                <i class="fa-solid fa-mug-hot"></i>
                <span><strong>Café Control</strong> © 2026 Developers: Gloria Tatiana Sanchez &amp; Tatiana Silva Penagos</span>
            </div>
        </footer>
</body>
</html>