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

/*
 * =========================================================
 * FUNCIONES AUXILIARES
 * =========================================================
 */

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function formato_fecha(string $fecha): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

    if ($dt instanceof DateTimeImmutable) {
        return $dt->format('d/m/Y');
    }

    return $fecha;
}

function formato_numero(float $numero, int $decimales = 1): string
{
    return number_format($numero, $decimales, ',', '.');
}

function formato_dinero(float $valor): string
{
    return '$' . number_format($valor, 0, ',', '.');
}

function version_foto(string $ruta): string
{
    $archivo = __DIR__ . '/' . ltrim($ruta, '/');

    if (is_file($archivo)) {
        $mtime = @filemtime($archivo);

        if ($mtime !== false) {
            return (string) $mtime;
        }
    }

    return (string) time();
}

/*
 * =========================================================
 * TABLA DE PERFILES
 * =========================================================
 *
 * Se guarda aparte para no modificar la estructura existente
 * de la tabla usuarios.
 */

$con->query("
    CREATE TABLE IF NOT EXISTS perfiles_usuario (
        id_usuario INT NOT NULL PRIMARY KEY,
        foto_perfil VARCHAR(255) NULL,
        fecha_actualizacion TIMESTAMP NOT NULL
            DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
");

/*
 * =========================================================
 * MIGRACIÓN COMPATIBLE
 * =========================================================
 *
 * Si existe la tabla antigua perfiles_recolectores,
 * se intenta copiar la fotografía.
 */

try {

    $existe_antigua = false;

    $stmt_tabla = $con->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'perfiles_recolectores'
    ");

    if ($stmt_tabla) {

        $stmt_tabla->execute();

        $resultado_tabla = $stmt_tabla->get_result();

        $fila_tabla = $resultado_tabla->fetch_assoc();

        $existe_antigua = (
            (int) ($fila_tabla['total'] ?? 0) > 0
        );

        $stmt_tabla->close();
    }

    if ($existe_antigua) {

        $con->query("
            INSERT INTO perfiles_usuario (
                id_usuario,
                foto_perfil
            )
            SELECT
                id_usuario,
                foto_perfil
            FROM perfiles_recolectores
            WHERE foto_perfil IS NOT NULL
              AND foto_perfil <> ''
            ON DUPLICATE KEY UPDATE
                foto_perfil = CASE
                    WHEN perfiles_usuario.foto_perfil IS NULL
                         OR perfiles_usuario.foto_perfil = ''
                    THEN VALUES(foto_perfil)
                    ELSE perfiles_usuario.foto_perfil
                END
        ");
    }

} catch (Throwable $e) {
    /*
     * La migración es opcional.
     * Si falla, no se interrumpe el panel.
     */
}

/*
 * =========================================================
 * VARIABLES DEL PERFIL
 * =========================================================
 */

$perfil_mensaje = '';
$perfil_tipo = '';

$directorio_perfiles = __DIR__ . '/uploads/perfiles';

if (!is_dir($directorio_perfiles)) {
    @mkdir($directorio_perfiles, 0755, true);
}

/*
 * =========================================================
 * GUARDAR PERFIL
 * =========================================================
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['guardar_perfil'])
) {

    $nuevo_nombre = trim(
        (string) ($_POST['perfil_nombres'] ?? '')
    );

    $nuevo_apellido = trim(
        (string) ($_POST['perfil_apellidos'] ?? '')
    );

    if ($nuevo_nombre === '' || $nuevo_apellido === '') {

        $perfil_mensaje =
            'El nombre y el apellido son obligatorios.';

        $perfil_tipo = 'error';

    } else {

        $ruta_foto_nueva = null;
        $error_foto = '';

        /*
         * =====================================================
         * PROCESAR FOTOGRAFÍA
         * =====================================================
         */

        if (
            isset($_FILES['foto_perfil'])
            && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK
            ) {

                $error_foto =
                    'No se pudo cargar la imagen seleccionada.';

            } elseif (
                (int) $_FILES['foto_perfil']['size']
                > 2 * 1024 * 1024
            ) {

                $error_foto =
                    'La imagen no puede superar 2 MB.';

            } else {

                $archivo_temporal =
                    $_FILES['foto_perfil']['tmp_name'];

                $info_imagen = @getimagesize(
                    $archivo_temporal
                );

                $mime = $info_imagen['mime'] ?? '';

                $mime_ext = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (
                    !$info_imagen
                    || !isset($mime_ext[$mime])
                ) {

                    $error_foto =
                        'Solo se permiten imágenes JPG, PNG o WEBP.';

                } elseif (
                    !is_dir($directorio_perfiles)
                    || !is_writable($directorio_perfiles)
                ) {

                    $error_foto =
                        'La carpeta uploads/perfiles no tiene permisos de escritura.';

                } else {

                    try {

                        $nombre_archivo =
                            'perfil_'
                            . $id_usuario
                            . '_'
                            . bin2hex(random_bytes(8))
                            . '.'
                            . $mime_ext[$mime];

                    } catch (Throwable $e) {

                        $nombre_archivo =
                            'perfil_'
                            . $id_usuario
                            . '_'
                            . uniqid('', true)
                            . '.'
                            . $mime_ext[$mime];
                    }

                    $destino =
                        $directorio_perfiles
                        . '/'
                        . $nombre_archivo;

                    if (
                        !move_uploaded_file(
                            $archivo_temporal,
                            $destino
                        )
                    ) {

                        $error_foto =
                            'No se pudo guardar la imagen en el servidor.';

                    } else {

                        $ruta_foto_nueva =
                            'uploads/perfiles/'
                            . $nombre_archivo;
                    }
                }
            }
        }

        /*
         * =====================================================
         * GUARDAR LOS DATOS
         * =====================================================
         */

        if ($error_foto === '') {

            $foto_anterior = null;

            $stmt_foto = $con->prepare("
                SELECT foto_perfil
                FROM perfiles_usuario
                WHERE id_usuario = ?
                LIMIT 1
            ");

            if ($stmt_foto) {

                $stmt_foto->bind_param(
                    'i',
                    $id_usuario
                );

                $stmt_foto->execute();

                $res_foto =
                    $stmt_foto->get_result();

                if ($fila_foto = $res_foto->fetch_assoc()) {

                    $foto_anterior =
                        $fila_foto['foto_perfil'];
                }

                $stmt_foto->close();
            }

            $transaccion_iniciada = false;

            try {

                $con->begin_transaction();

                $transaccion_iniciada = true;

                /*
                 * Actualizar usuario
                 */

                $stmt_usuario = $con->prepare("
                    UPDATE usuarios
                    SET
                        nombres = ?,
                        apellidos = ?
                    WHERE id_usuario = ?
                ");

                if (!$stmt_usuario) {

                    throw new RuntimeException(
                        'No fue posible preparar la actualización del perfil.'
                    );
                }

                $stmt_usuario->bind_param(
                    'ssi',
                    $nuevo_nombre,
                    $nuevo_apellido,
                    $id_usuario
                );

                if (!$stmt_usuario->execute()) {

                    $stmt_usuario->close();

                    throw new RuntimeException(
                        'No fue posible actualizar los datos del perfil.'
                    );
                }

                $stmt_usuario->close();

                /*
                 * Guardar fotografía
                 */

                if ($ruta_foto_nueva !== null) {

                    $stmt_perfil = $con->prepare("
                        INSERT INTO perfiles_usuario (
                            id_usuario,
                            foto_perfil
                        )
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE
                            foto_perfil = VALUES(foto_perfil)
                    ");

                    if (!$stmt_perfil) {

                        throw new RuntimeException(
                            'No fue posible guardar la fotografía del perfil.'
                        );
                    }

                    $stmt_perfil->bind_param(
                        'is',
                        $id_usuario,
                        $ruta_foto_nueva
                    );

                    if (!$stmt_perfil->execute()) {

                        $stmt_perfil->close();

                        throw new RuntimeException(
                            'No fue posible guardar la fotografía del perfil.'
                        );
                    }

                    $stmt_perfil->close();

                } else {

                    /*
                     * Crear registro si todavía no existe,
                     * pero conservar la fotografía anterior.
                     */

                    $stmt_perfil = $con->prepare("
                        INSERT INTO perfiles_usuario (
                            id_usuario,
                            foto_perfil
                        )
                        VALUES (?, NULL)
                        ON DUPLICATE KEY UPDATE
                            id_usuario = VALUES(id_usuario)
                    ");

                    if (!$stmt_perfil) {

                        throw new RuntimeException(
                            'No fue posible guardar el perfil.'
                        );
                    }

                    $stmt_perfil->bind_param(
                        'i',
                        $id_usuario
                    );

                    if (!$stmt_perfil->execute()) {

                        $stmt_perfil->close();

                        throw new RuntimeException(
                            'No fue posible guardar el perfil.'
                        );
                    }

                    $stmt_perfil->close();
                }

                /*
                 * Confirmar
                 */

                $con->commit();

                $transaccion_iniciada = false;

                /*
                 * Eliminar fotografía anterior únicamente
                 * después de guardar correctamente la nueva.
                 */

                if (
                    $ruta_foto_nueva !== null
                    && $foto_anterior
                ) {

                    $archivo_anterior =
                        __DIR__
                        . '/'
                        . ltrim(
                            (string) $foto_anterior,
                            '/'
                        );

                    $ruta_real_anterior =
                        realpath($archivo_anterior);

                    $directorio_real =
                        realpath($directorio_perfiles);

                    if (
                        $ruta_real_anterior !== false
                        && $directorio_real !== false
                        && is_file($ruta_real_anterior)
                        && strpos(
                            $ruta_real_anterior,
                            $directorio_real
                        ) === 0
                    ) {

                        @unlink(
                            $ruta_real_anterior
                        );
                    }
                }

                /*
                 * Redirección para evitar reenvío del formulario.
                 */

                header(
                    'Location: panel_r.php?perfil=guardado'
                );

                exit;

            } catch (Throwable $e) {

                if ($transaccion_iniciada) {
                    try {
                        $con->rollback();
                    } catch (Throwable $rollbackError) {
                    }
                }

                /*
                 * Eliminar nueva imagen si falló la transacción.
                 */

                if ($ruta_foto_nueva !== null) {

                    $archivo_nuevo =
                        __DIR__
                        . '/'
                        . ltrim(
                            $ruta_foto_nueva,
                            '/'
                        );

                    if (is_file($archivo_nuevo)) {
                        @unlink($archivo_nuevo);
                    }
                }

                $perfil_mensaje =
                    'No se pudo guardar el perfil. Inténtalo nuevamente.';

                $perfil_tipo = 'error';
            }

        } else {

            $perfil_mensaje = $error_foto;
            $perfil_tipo = 'error';
        }
    }
}

/*
 * =========================================================
 * LEER FOTOGRAFÍA ACTUAL
 * =========================================================
 */

$foto_perfil = '';

$stmt_perfil_actual = $con->prepare("
    SELECT foto_perfil
    FROM perfiles_usuario
    WHERE id_usuario = ?
    LIMIT 1
");

if ($stmt_perfil_actual) {

    $stmt_perfil_actual->bind_param(
        'i',
        $id_usuario
    );

    $stmt_perfil_actual->execute();

    $res_perfil_actual =
        $stmt_perfil_actual->get_result();

    if (
        $fila_perfil_actual =
        $res_perfil_actual->fetch_assoc()
    ) {

        $foto_perfil =
            trim(
                (string) (
                    $fila_perfil_actual['foto_perfil']
                    ?? ''
                )
            );
    }

    $stmt_perfil_actual->close();
}

/*
 * =========================================================
 * DATOS DEL USUARIO
 * =========================================================
 */

$nombre_usuario = 'Usuario';
$apellido_usuario = '';
$rol_usuario = 'Recolector';
$estado_usuario = 'activo';

$stmt_usuario_actual = $con->prepare("
    SELECT
        nombres,
        apellidos,
        rol,
        estado
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
");

if ($stmt_usuario_actual) {

    $stmt_usuario_actual->bind_param(
        'i',
        $id_usuario
    );

    $stmt_usuario_actual->execute();

    $resultado_usuario =
        $stmt_usuario_actual->get_result();

    $fila_usuario =
        $resultado_usuario->fetch_assoc();

    if ($fila_usuario) {

        $nombre_usuario =
            trim(
                (string) $fila_usuario['nombres']
            );

        $apellido_usuario =
            trim(
                (string) $fila_usuario['apellidos']
            );

        $rol_usuario =
            (string) $fila_usuario['rol'];

        $estado_usuario =
            strtolower(
                trim(
                    (string) (
                        $fila_usuario['estado']
                        ?? 'activo'
                    )
                )
            );

    } else {

        $stmt_usuario_actual->close();

        session_destroy();

        header('Location: index.php');
        exit;
    }

    $stmt_usuario_actual->close();
}

/*
 * =========================================================
 * NOMBRE Y ESTADO
 * =========================================================
 */

$nombre_completo =
    trim(
        $nombre_usuario
        . ' '
        . $apellido_usuario
    );

$roles_mostrados = [
    'caficultor' => 'Caficultor',
    'mayordomo'  => 'Mayordomo',
    'recolector' => 'Recolector'
];

$rol_mostrado =
    $roles_mostrados[$rol_usuario]
    ?? ucfirst($rol_usuario);

$usuario_activo =
    ($estado_usuario === 'activo');

$estado_mostrado =
    $usuario_activo
    ? 'Activo'
    : 'Inactivo';

/*
 * =========================================================
 * SEMANA DE TRABAJO
 * SÁBADO A VIERNES
 * =========================================================
 */

$hoy =
    new DateTimeImmutable(
        'today',
        new DateTimeZone('America/Bogota')
    );

$dias_desde_sabado =
    ((int) $hoy->format('w') + 1) % 7;

$inicio_semana =
    $hoy->modify(
        "-{$dias_desde_sabado} days"
    );

$fin_semana =
    $inicio_semana->modify('+6 days');

$proximo_pago =
    $inicio_semana->modify('+7 days');

$inicio_sql =
    $inicio_semana->format('Y-m-d');

$fin_sql =
    $fin_semana->format('Y-m-d');

$proximo_pago_sql =
    $proximo_pago->format('d/m/Y');

/*
 * =========================================================
 * FINCA ASIGNADA
 * =========================================================
 */

$id_finca = 0;
$nombre_finca = '';

$stmt_finca = $con->prepare("
    SELECT
        r.id_finca,
        f.nombre
    FROM recolectores r
    INNER JOIN fincas f
        ON f.id_finca = r.id_finca
    WHERE r.id_usuario = ?
      AND f.estado = 'activa'
    ORDER BY
        r.fecha_vinculacion DESC
    LIMIT 1
");

if ($stmt_finca) {

    $stmt_finca->bind_param(
        'i',
        $id_usuario
    );

    $stmt_finca->execute();

    $resultado_finca =
        $stmt_finca->get_result();

    if ($fila_finca = $resultado_finca->fetch_assoc()) {

        $id_finca =
            (int) $fila_finca['id_finca'];

        $nombre_finca =
            (string) $fila_finca['nombre'];
    }

    $stmt_finca->close();
}

/*
 * =========================================================
 * TOTAL ENTREGADO DE LA SEMANA
 * =========================================================
 */

$total_kilos = 0.0;
$total_entregas = 0;

$stmt_total = $con->prepare("
    SELECT
        COALESCE(
            SUM(kilos_recolectados),
            0
        ) AS total_kilos,

        COUNT(*) AS total_entregas

    FROM recolecciones

    WHERE id_recolector = ?

      AND fecha BETWEEN ?
      AND ?
");

if ($stmt_total) {

    $stmt_total->bind_param(
        'iss',
        $id_usuario,
        $inicio_sql,
        $fin_sql
    );

    $stmt_total->execute();

    $resultado_total =
        $stmt_total->get_result();

    if (
        $fila_total =
        $resultado_total->fetch_assoc()
    ) {

        $total_kilos =
            (float) $fila_total['total_kilos'];

        $total_entregas =
            (int) $fila_total['total_entregas'];
    }

    $stmt_total->close();
}

/*
 * =========================================================
 * PRECIO DEL KILO
 * =========================================================
 */

$precio_kilo = 0.0;

if ($id_finca > 0) {

    $stmt_precio = $con->prepare("
        SELECT precio_kilo
        FROM historial_precios
        WHERE id_finca = ?
        ORDER BY
            fecha_actualizacion DESC,
            id_precio DESC
        LIMIT 1
    ");

    if ($stmt_precio) {

        $stmt_precio->bind_param(
            'i',
            $id_finca
        );

        $stmt_precio->execute();

        $resultado_precio =
            $stmt_precio->get_result();

        if (
            $fila_precio =
            $resultado_precio->fetch_assoc()
        ) {

            $precio_kilo =
                (float) $fila_precio['precio_kilo'];
        }

        $stmt_precio->close();
    }
}

/*
 * =========================================================
 * TOTAL A PAGAR
 * =========================================================
 */

$total_pagar =
    $total_kilos * $precio_kilo;

/*
 * =========================================================
 * ÚLTIMAS ENTREGAS
 * =========================================================
 */

$ultimas_entregas = [];

$stmt_entregas = $con->prepare("
    SELECT
        id_recoleccion,
        fecha,
        kilos_recolectados,
        precio_kilo_aplicado,
        pago_calculado
    FROM recolecciones
    WHERE id_recolector = ?
    ORDER BY
        fecha DESC,
        id_recoleccion DESC
    LIMIT 6
");

if ($stmt_entregas) {

    $stmt_entregas->bind_param(
        'i',
        $id_usuario
    );

    $stmt_entregas->execute();

    $resultado_entregas =
        $stmt_entregas->get_result();

    while (
        $fila_entrega =
        $resultado_entregas->fetch_assoc()
    ) {

        $ultimas_entregas[] =
            $fila_entrega;
    }

    $stmt_entregas->close();
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

    <title>Café Control</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>

        /*
         * =====================================================
         * CONFIGURACIÓN GENERAL
         * =====================================================
         */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, "Segoe UI", sans-serif;
        }

        :root {

            --sidebar-bg: #17100c;
            --sidebar-active-bg: #fff4e8;
            --sidebar-active-text: #111111;
            --sidebar-text: #ffffff;

            --bg-main: #ffffff;
            --card-bg: #ffffff;
            --card-cream-bg: #f9f5ed;

            --text-dark: #111111;
            --text-muted: #333333;

            --border-color: #b8b8b8;

            --accent-green: #2a693c;
            --accent-orange: #d97706;
            --accent-blue: #2563eb;
            --accent-green-light: #487e58;

            --pill-green-bg: #d9f5df;
            --pill-green-text: #075d1d;
        }

        body {

            background-color: var(--bg-main);

            color: var(--text-dark);

            display: flex;

            min-height: 100vh;

            font-size: 18px;

            line-height: 1.55;
        }

        a,
        button,
        select {
            min-height: 44px;
        }

        /*
         * =====================================================
         * MENÚ LATERAL
         * =====================================================
         */

        aside {

            width: 250px;

            background-color: var(--sidebar-bg);

            color: #ffffff;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            padding: 20px 15px;

            flex-shrink: 0;
        }

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 25px;

            padding: 0 5px;
        }

        .brand-icon {

            width: 42px;
            height: 42px;

            background-color: #ffffff;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #5c3016;

            font-size: 1.3rem;

            flex-shrink: 0;
        }

        .brand-text h1 {

            font-size: 1rem;

            font-weight: 800;

            line-height: 1.1;

            letter-spacing: 0.2px;
        }

        .brand-text p {

            font-size: 0.88rem;

            color: #ffffff;
        }

        /*
         * =====================================================
         * PERFIL
         * =====================================================
         */

        .user-card {

            display: flex;

            align-items: center;

            gap: 12px;

            background-color: rgba(255, 255, 255, 0.08);

            padding: 12px;

            border-radius: 12px;

            margin-bottom: 25px;
        }

        .profile-trigger {

            display: block;

            width: 45px;
            height: 45px;

            flex: 0 0 45px;

            border: 0;

            padding: 0;

            background: transparent;

            color: inherit;

            cursor: pointer;

            border-radius: 50%;
        }

        .profile-trigger .user-avatar-container {

            width: 45px;
            height: 45px;
        }

        .profile-trigger:focus {

            outline: 3px solid #ffb000;

            outline-offset: 4px;

            border-radius: 10px;
        }

        .avatar-click-hint {

            position: absolute;

            inset: 0;

            border-radius: 50%;

            background: rgba(0, 0, 0, 0.35);

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            opacity: 0;

            transition: opacity 0.2s;
        }

        .profile-trigger:hover .avatar-click-hint,
        .profile-trigger:focus .avatar-click-hint {

            opacity: 1;
        }

        .user-avatar-container {

            position: relative;
        }

        .user-avatar {

            width: 45px;
            height: 45px;

            border-radius: 50%;

            object-fit: cover;
        }

        .user-avatar-placeholder {

            width: 45px;
            height: 45px;

            border-radius: 50%;

            background-color: #e9e9e9;

            color: #17100c;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            font-size: 1.15rem;
        }

        .status-dot {

            width: 10px;
            height: 10px;

            background-color: #22c55e;

            border: 2px solid var(--sidebar-bg);

            border-radius: 50%;

            position: absolute;

            bottom: 2px;
            right: 0;
        }

        .status-dot.inactive {

            background-color: #ef4444;
        }

        .user-details {

            min-width: 0;
        }

        .user-details h3 {

            font-size: 1rem;

            font-weight: 700;

            overflow-wrap: anywhere;
        }

        .user-details p {

            font-size: 0.9rem;

            color: #ffffff;
        }

        .status-text {

            font-size: 0.85rem;

            color: #55e878;

            display: flex;

            align-items: center;

            gap: 4px;
        }

        .status-text.inactive {

            color: #ff8b8b;
        }

        /*
         * =====================================================
         * NAVEGACIÓN
         * =====================================================
         */

        .nav-menu {

            list-style: none;

            display: flex;

            flex-direction: column;

            gap: 6px;
        }

        .nav-item a {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 14px 16px;

            color: var(--sidebar-text);

            text-decoration: none;

            font-size: 1.05rem;

            font-weight: 700;

            border-radius: 12px;

            border: 2px solid transparent;

            transition: all 0.2s ease;
        }

        .nav-item a:hover {

            background-color: rgba(255, 255, 255, 0.12);

            color: #ffffff;
        }

        .nav-item.active a {

            background-color: var(--sidebar-active-bg);

            color: var(--sidebar-active-text);

            border: 3px solid #ffb000;

            font-weight: 800;
        }

        .nav-item a:focus {

            outline: 4px solid #ffb000;

            outline-offset: 2px;
        }

        /*
         * =====================================================
         * IMAGEN INFERIOR DEL MENÚ
         * =====================================================
         */

        .sidebar-footer-img {

            text-align: center;

            margin: 20px 0 10px;
        }

        .sidebar-footer-img img {

            width: 100%;

            max-width: 160px;

            height: 160px;

            object-fit: cover;

            border-radius: 12px;

            opacity: 0.9;
        }

        /*
         * =====================================================
         * CERRAR SESIÓN
         * =====================================================
         */

        .logout-container {

            margin-top: 15px;
        }

        .logout-link {

            text-decoration: none;

            display: block;
        }

        .btn-logout {

            width: 100%;

            background: rgba(255, 255, 255, 0.08);

            border: 2px solid rgba(255, 255, 255, 0.3);

            color: #ffffff;

            padding: 12px;

            border-radius: 20px;

            font-size: 0.95rem;

            font-weight: 700;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            transition: background 0.2s ease;
        }

        .btn-logout:hover {

            background: rgba(255, 255, 255, 0.18);
        }

        .btn-logout:focus {

            outline: 4px solid #ffb000;

            outline-offset: 2px;
        }

        /*
         * =====================================================
         * CONTENIDO PRINCIPAL
         * =====================================================
         */

        main {

            flex: 1;

            min-height: 100vh;

            padding: 20px 30px;

            display: flex;

            flex-direction: column;

            gap: 20px;

            min-width: 0;
        }

        /*
         * =====================================================
         * ENCABEZADO
         * =====================================================
         */

        header {

            display: flex;

            justify-content: flex-end;

            align-items: center;
        }

        .header-datetime {

            display: flex;

            align-items: center;

            gap: 22px;

            font-size: 18px;

            line-height: 1;

            font-weight: 800;

            color: var(--text-dark);

            white-space: nowrap;
        }

        .header-datetime div {

            display: flex;

            align-items: center;

            gap: 8px;
        }

        .header-datetime i {

            font-size: 19px;
        }

        /*
         * =====================================================
         * BANNER
         * =====================================================
         */

        .welcome-banner {

            background-color: var(--card-cream-bg);

            border-radius: 20px;

            padding: 25px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            position: relative;

            overflow: hidden;

            border: 2px solid var(--border-color);

            gap: 25px;
        }

        .welcome-text {

            min-width: 0;
        }

        .welcome-text h2 {

            font-size: 2rem;

            font-weight: 800;

            color: var(--text-dark);

            margin-bottom: 6px;
        }

        .welcome-text p {

            font-size: 1rem;

            color: #222222;
        }

        .welcome-image-container {

            width: 320px;

            height: 110px;

            border-radius: 15px;

            overflow: hidden;

            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);

            flex-shrink: 0;
        }

        .welcome-image-container img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }

        /*
         * =====================================================
         * TARJETAS DE MÉTRICAS
         * =====================================================
         */

        .metrics-grid {

            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 15px;
        }

        .metric-card {

            background-color: #ffffff;

            border: 2px solid #b0b0b0;

            border-radius: 16px;

            padding: 20px;

            display: flex;

            align-items: center;

            gap: 15px;

            min-width: 0;
        }

        .metric-icon {

            width: 50px;
            height: 50px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ffffff;

            font-size: 1.35rem;

            flex-shrink: 0;
        }

        .icon-green {
            background-color: var(--accent-green);
        }

        .icon-orange {
            background-color: var(--accent-orange);
        }

        .icon-blue {
            background-color: var(--accent-blue);
        }

        .icon-olive {
            background-color: var(--accent-green-light);
        }

        .metric-info {
            min-width: 0;
        }

        .metric-info label {

            font-size: 0.9rem;

            font-weight: 800;

            color: var(--text-dark);

            display: block;

            margin-bottom: 4px;
        }

        .metric-value {

            font-size: 1.45rem;

            font-weight: 800;

            color: var(--text-dark);

            line-height: 1.1;

            overflow-wrap: anywhere;
        }

        .metric-sub {

            font-size: 0.9rem;

            color: #222222;

            margin-top: 4px;
        }

        /*
         * =====================================================
         * CONTENIDO
         * =====================================================
         */

        .dashboard-content-grid {

            display: grid;

            grid-template-columns: 1.8fr 1.2fr;

            gap: 20px;
        }

        .card-panel {

            background-color: var(--card-cream-bg);

            border: 2px solid #b0b0b0;

            border-radius: 16px;

            padding: 20px;

            min-width: 0;
        }

        .panel-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 15px;
        }

        .panel-title {

            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 1.05rem;

            font-weight: 800;

            color: var(--text-dark);
        }

        .btn-view-all {

            background-color: #ebdcd0;

            color: var(--text-dark);

            border: 2px solid #888888;

            padding: 6px 14px;

            border-radius: 15px;

            font-size: 0.9rem;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            gap: 6px;

            white-space: nowrap;
        }

        .btn-view-all:focus {

            outline: 4px solid #ffb000;

            outline-offset: 2px;
        }

        /*
         * =====================================================
         * TABLA
         * =====================================================
         */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;

            -webkit-overflow-scrolling: touch;
        }

        .deliveries-table {

            width: 100%;

            border-collapse: collapse;

            font-size: 0.98rem;

            min-width: 650px;
        }

        .deliveries-table th {

            text-align: left;

            padding: 12px;

            color: #111111;

            font-weight: 800;

            font-size: 0.95rem;
        }

        .deliveries-table td {

            padding: 12px;

            border-top: 1px dashed #999999;

            color: var(--text-dark);

            font-size: 0.98rem;
        }

        .deliveries-table td strong {

            font-weight: 800;
        }

        /*
         * =====================================================
         * ESTADO REGISTRADO
         * =====================================================
         */

        .status-pill-registered {

            background-color: var(--pill-green-bg);

            color: var(--pill-green-text);

            border: 2px solid #238636;

            padding: 5px 10px;

            border-radius: 12px;

            font-size: 0.9rem;

            font-weight: 800;

            display: inline-flex;

            align-items: center;

            gap: 4px;
        }

        /*
         * =====================================================
         * AVISO
         * =====================================================
         */

        .info-alert-box {

            background-color: #ffffff;

            border: 2px solid #888888;

            border-radius: 12px;

            padding: 12px 15px;

            margin-top: 15px;

            display: flex;

            align-items: flex-start;

            gap: 10px;

            font-size: 0.9rem;

            color: #222222;
        }

        .info-alert-box i {

            color: var(--text-dark);

            margin-top: 2px;
        }

        /*
         * =====================================================
         * COLUMNA DERECHA
         * =====================================================
         */

        .right-column-stack {

            display: flex;

            flex-direction: column;

            gap: 20px;
        }

        .summary-date-sub {

            font-size: 0.9rem;

            color: #222222;

            margin-bottom: 15px;
        }

        .summary-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 12px;

            font-size: 1rem;
        }

        .summary-row .label {

            display: flex;

            align-items: center;

            gap: 8px;

            color: var(--text-dark);
        }

        .bullet-dot {

            width: 10px;

            height: 10px;

            border-radius: 50%;

            flex-shrink: 0;
        }

        .summary-row strong {

            font-size: 1.05rem;

            font-weight: 800;

            white-space: nowrap;
        }

        /*
         * =====================================================
         * CONSEJO
         * =====================================================
         */

        .tip-card {

            background-color: var(--card-cream-bg);

            border: 2px solid #b0b0b0;

            border-radius: 16px;

            padding: 18px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;
        }

        .tip-content {

            min-width: 0;
        }

        .tip-content h4 {

            font-size: 1rem;

            font-weight: 800;

            display: flex;

            align-items: center;

            gap: 6px;

            margin-bottom: 6px;
        }

        .tip-content p {

            font-size: 0.9rem;

            color: #222222;

            line-height: 1.4;
        }

        .tip-img {

            width: 60px;
            height: 60px;

            border-radius: 50%;

            object-fit: cover;

            flex-shrink: 0;
        }

        /*
         * =====================================================
         * PIE DE PÁGINA
         * =====================================================
         */

        footer {

            width: 100%;

            margin-top: auto;

            padding: 18px 10px 14px;

            border-top: 2px solid #b0b0b0;

            display: flex;

            justify-content: center;

            align-items: center;

            background: transparent;

            color: #222222;

            font-size: 0.95rem;

            line-height: 1.4;
        }

        .footer-brand {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            text-align: center;

            flex-wrap: wrap;
        }

        .footer-brand i {

            font-size: 1.1rem;

            color: var(--accent-green);
        }

        .footer-brand span {

            font-weight: 600;
        }

        .footer-brand strong {

            font-weight: 800;

            color: var(--text-dark);
        }

        /*
         * =====================================================
         * MODAL EDITAR PERFIL
         * =====================================================
         */

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

        .profile-modal.open {

            display: flex;
        }

        .profile-modal-card {

            width: min(480px, 100%);

            max-height: 90vh;

            overflow-y: auto;

            background: #ffffff;

            border-radius: 18px;

            border: 2px solid #b8b8b8;

            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);

            padding: 24px;

            position: relative;
        }

        .profile-modal-head {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 20px;
        }

        .profile-modal-head h3 {

            font-size: 1.35rem;

            font-weight: 800;
        }

        .profile-close {

            border: 0;

            background: #f1f1f1;

            border-radius: 50%;

            width: 42px;
            height: 42px;

            cursor: pointer;

            font-size: 1.1rem;

            flex-shrink: 0;
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

            border: 4px solid #ffffff;

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

        .profile-field {

            display: flex;

            flex-direction: column;

            gap: 6px;

            margin-bottom: 12px;
        }

        .profile-field label {

            font-weight: 800;

            font-size: 0.95rem;
        }

        .profile-field input {

            width: 100%;

            padding: 11px 12px;

            border-radius: 10px;

            border: 2px solid #c0c0c0;

            font-size: 1rem;

            outline: none;
        }

        .profile-field input:focus {

            border-color: #2a693c;

            box-shadow:
                0 0 0 3px rgba(
                    42,
                    105,
                    60,
                    0.12
                );
        }

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

        .profile-btn.cancel {

            background: #eeeeee;

            border-color: #c6c6c6;
        }

        .profile-btn.save {

            background: #2a693c;

            color: #ffffff;
        }

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

            position: absolute;

            width: 1px;
            height: 1px;

            padding: 0;

            margin: -1px;

            overflow: hidden;

            clip: rect(0, 0, 0, 0);

            white-space: nowrap;

            border: 0;
        }

        /*
         * =====================================================
         * RESPONSIVE
         * =====================================================
         */

        @media (max-width: 1100px) {

            .metrics-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .dashboard-content-grid {

                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {

            body {

                flex-direction: column;
            }

            aside {

                width: 100%;

                padding: 16px;
            }

            main {

                min-height: auto;

                padding: 16px;
            }

            .welcome-banner {

                flex-direction: column;

                gap: 15px;

                text-align: center;

                padding: 20px;
            }

            .welcome-image-container {

                width: 100%;

                height: 150px;
            }

            .metrics-grid {

                grid-template-columns: 1fr;
            }

            .header-datetime {

                flex-direction: column;

                align-items: flex-end;

                gap: 4px;

                font-size: 0.9rem;
            }

            .metric-card {

                padding: 20px;
            }

            .panel-header {

                align-items: flex-start;

                flex-direction: column;
            }

            .btn-view-all {

                align-self: flex-start;
            }

            footer {

                margin-top: 10px;

                padding:
                    16px
                    8px
                    12px;

                font-size: 0.82rem;
            }

            .profile-modal {

                padding: 12px;
            }

            .profile-modal-card {

                padding: 18px;
            }
        }

        @media (max-width: 480px) {

            .header-datetime {

                width: 100%;

                align-items: flex-end;
            }

            .welcome-text h2 {

                font-size: 1.55rem;
            }

            .metric-value {

                font-size: 1.25rem;
            }

            .profile-actions {

                flex-direction: column;
            }

            .profile-btn {

                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                scroll-behavior: auto !important;

                transition: none !important;
            }
        }

    </style>

</head>

<body>

    <!-- =====================================================
         MENÚ LATERAL
         ===================================================== -->

    <aside>

        <div>

            <!-- LOGO -->

            <div class="brand">

                <div class="brand-icon">

                    <i class="fa-solid fa-mug-hot"></i>

                </div>

                <div class="brand-text">

                    <h1>Café Control</h1>

                </div>

            </div>


            <!-- PERFIL -->

            <div class="user-card">

                <button
                    type="button"
                    class="profile-trigger"
                    id="abrirPerfil"
                    aria-label="Editar perfil"
                >

                    <div class="user-avatar-container">

                        <?php if (
                            $foto_perfil !== ''
                            && is_file(
                                __DIR__
                                . '/'
                                . ltrim(
                                    $foto_perfil,
                                    '/'
                                )
                            )
                        ): ?>

                            <img
                                class="user-avatar"
                                src="<?php echo e($foto_perfil); ?>?v=<?php echo e(version_foto($foto_perfil)); ?>"
                                alt="Foto de <?php echo e($nombre_completo); ?>"
                            >

                        <?php else: ?>

                            <div
                                class="user-avatar-placeholder"
                                aria-hidden="true"
                            >
                                <i class="fa-solid fa-user"></i>
                            </div>

                        <?php endif; ?>

                        <span
                            class="avatar-click-hint"
                            aria-hidden="true"
                        >
                            <i class="fa-solid fa-pen"></i>
                        </span>

                        <span
                            class="status-dot <?php echo $usuario_activo ? '' : 'inactive'; ?>"
                        ></span>

                    </div>

                </button>


                <div class="user-details">

                    <h3>
                        <?php echo e($nombre_usuario); ?><br>
                        <?php echo e($apellido_usuario); ?>
                    </h3>

                    <p>
                        <?php echo e($rol_mostrado); ?>
                    </p>

                    <span
                        class="status-text <?php echo $usuario_activo ? '' : 'inactive'; ?>"
                    >

                        <i
                            class="fa-solid fa-circle"
                            style="font-size: 0.4rem;"
                        ></i>

                        <?php echo e($estado_mostrado); ?>

                    </span>

                </div>

            </div>


            <!-- NAVEGACIÓN -->

            <ul class="nav-menu">

                <li class="nav-item active">

                    <a href="panel_r.php">

                        <i class="fa-solid fa-house"></i>

                        Inicio

                    </a>

                </li>

                <li class="nav-item">

                    <a href="entregas_r.php">

                        <i class="fa-solid fa-basket-shopping"></i>

                        Mis Entregas

                    </a>

                </li>

                <li class="nav-item">

                    <a href="informacion_r.php">

                        <i class="fa-solid fa-circle-info"></i>

                        Información

                    </a>

                </li>

            </ul>


            <!-- CERRAR SESIÓN DEBAJO DE INFORMACIÓN -->

            <div class="logout-container">

                <a
                    href="cerrar.php"
                    class="logout-link"
                >

                    <button
                        type="button"
                        class="btn-logout"
                    >
                        <i class="fa-solid fa-power-off"></i>
                        Cerrar sesión
                    </button>

                </a>

            </div>

        </div>

    </aside>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
         ===================================================== -->

    <main>

        <!-- ENCABEZADO -->

        <header>

            <div
                class="header-datetime"
                aria-label="Fecha y hora actual"
            >

                <div>

                    <i class="fa-regular fa-calendar"></i>

                    <span id="fecha-actual">
                        <?php
                        echo e(
                            $hoy->format('d/m/Y')
                        );
                        ?>
                    </span>

                </div>

                <span>|</span>

                <div>

                    <i class="fa-regular fa-clock"></i>

                    <span id="hora-actual">
                        <?php
                        echo e(
                            (
                                new DateTimeImmutable(
                                    'now',
                                    new DateTimeZone(
                                        'America/Bogota'
                                    )
                                )
                            )->format('g:i a')
                        );
                        ?>
                    </span>

                </div>

            </div>

        </header>


        <!-- =================================================
             BANNER DE BIENVENIDA
             ================================================= -->

        <div class="welcome-banner">

            <div class="welcome-text">

                <h2>

                    ¡Hola,
                    <?php echo e($nombre_usuario); ?>! 👋

                </h2>

                <p>

                    Aquí puedes ver el resumen de tus
                    entregas de café recolectado
                    y tus pagos semanales.

                </p>

            </div>


            <div class="welcome-image-container">

                <img src="images/cafe_r.jpg" alt="Cafe">

            </div>

        </div>


        <!-- =================================================
             TARJETAS DE INFORMACIÓN
             ================================================= -->

        <div class="metrics-grid">


            <!-- TOTAL ENTREGADO -->

            <div class="metric-card">

                <div class="metric-icon icon-green">

                    <i class="fa-solid fa-scale-balanced"></i>

                </div>

                <div class="metric-info">

                    <label>

                        Total entregado<br>
                        esta semana

                    </label>

                    <div class="metric-value">

                        <?php
                        echo e(
                            formato_numero(
                                $total_kilos,
                                1
                            )
                        );
                        ?>
                        kg

                    </div>

                    <div class="metric-sub">

                        <?php echo e(
                            (string) $total_entregas
                        ); ?>

                        <?php
                        echo $total_entregas === 1
                            ? 'entrega realizada'
                            : 'entregas realizadas';
                        ?>

                    </div>

                </div>

            </div>


            <!-- TOTAL A PAGAR -->

            <div class="metric-card">

                <div class="metric-icon icon-orange">

                    <i class="fa-solid fa-sack-dollar"></i>

                </div>

                <div class="metric-info">

                    <label>

                        Total a pagar<br>
                        esta semana

                    </label>

                    <div class="metric-value">

                        <?php
                        echo e(
                            formato_dinero(
                                $total_pagar
                            )
                        );
                        ?>

                    </div>

                    <div class="metric-sub">

                        Por
                        <?php
                        echo e(
                            formato_numero(
                                $total_kilos,
                                1
                            )
                        );
                        ?>
                        kg de café

                    </div>

                </div>

            </div>


            <!-- PRÓXIMO PAGO -->

            <div class="metric-card">

                <div class="metric-icon icon-blue">

                    <i class="fa-regular fa-calendar-days"></i>

                </div>

                <div class="metric-info">

                    <label>
                        Próximo pago
                    </label>

                    <div class="metric-value">

                        <?php echo e(
                            $proximo_pago_sql
                        ); ?>

                    </div>

                    <div class="metric-sub">

                        Pago semanal

                    </div>

                </div>

            </div>


            <!-- PRECIO DEL KILO -->

            <div class="metric-card">

                <div class="metric-icon icon-olive">

                    <i class="fa-solid fa-coins"></i>

                </div>

                <div class="metric-info">

                    <label>
                        Precio del kilo
                    </label>

                    <div class="metric-value">

                        <?php echo e(
                            formato_dinero(
                                $precio_kilo
                            )
                        ); ?>

                    </div>

                    <div class="metric-sub">

                        Precio puesto por el caficultor

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             CONTENIDO PRINCIPAL
             ================================================= -->

        <div class="dashboard-content-grid">


            <!-- =================================================
                 ENTREGAS RECIENTES
                 ================================================= -->

            <div class="card-panel">

                <div class="panel-header">

                    <div class="panel-title">

                        <i class="fa-solid fa-basket-shopping"></i>

                        Mis entregas recientes

                    </div>


                    <a
                        href="entregas_r.php"
                        class="btn-view-all"
                    >

                        Ver todas

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>


                <div class="table-wrapper">

                    <table class="deliveries-table">

                        <thead>

                            <tr>

                                <th>
                                    Fecha
                                </th>

                                <th>
                                    Entrega
                                </th>

                                <th>
                                    Peso (kg)
                                </th>

                                <th>
                                    Estado
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (
                                empty($ultimas_entregas)
                            ): ?>

                                <tr>

                                    <td colspan="4">

                                        Todavía no tienes
                                        entregas registradas.

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach (
                                    $ultimas_entregas
                                    as $indice => $entrega
                                ): ?>

                                    <tr>

                                        <td>

                                            <i class="fa-regular fa-calendar"></i>

                                            <?php echo e(
                                                formato_fecha(
                                                    (string) $entrega['fecha']
                                                )
                                            ); ?>

                                        </td>


                                        <td>

                                            Entrega
                                            <?php
                                            echo e(
                                                (string) (
                                                    count(
                                                        $ultimas_entregas
                                                    )
                                                    - $indice
                                                )
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?php
                                                echo e(
                                                    formato_numero(
                                                        (float) $entrega[
                                                            'kilos_recolectados'
                                                        ],
                                                        1
                                                    )
                                                );
                                                ?>

                                                kg

                                            </strong>

                                        </td>


                                        <td>

                                            <span
                                                class="status-pill-registered"
                                            >

                                                Registrada

                                                <i
                                                    class="fa-solid fa-circle-check"
                                                ></i>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- AVISO -->

                <div class="info-alert-box">

                    <i
                        class="fa-solid fa-circle-info"
                    ></i>

                    <div>

                        Los datos de tus entregas son
                        registrados por el mayordomo o
                        administrador. Si tienes alguna
                        duda, consulta con ellos.

                    </div>

                </div>

            </div>


            <!-- =================================================
                 COLUMNA DERECHA
                 ================================================= -->

            <div class="right-column-stack">


                <!-- RESUMEN -->

                <div class="card-panel">

                    <div class="panel-header">

                        <div class="panel-title">

                            <i
                                class="fa-solid fa-chart-column"
                            ></i>

                            Resumen de la semana

                        </div>

                    </div>


                    <div class="summary-date-sub">

                        <?php
                        echo e(
                            $inicio_semana->format(
                                'd/m/Y'
                            )
                        );
                        ?>

                        -

                        <?php
                        echo e(
                            $fin_semana->format(
                                'd/m/Y'
                            )
                        );
                        ?>

                    </div>


                    <!-- TOTAL ENTREGADO -->

                    <div class="summary-row">

                        <div class="label">

                            <span
                                class="bullet-dot"
                                style="
                                    background-color:
                                        var(--accent-green);
                                "
                            ></span>

                            Total entregado

                        </div>

                        <strong>

                            <?php
                            echo e(
                                formato_numero(
                                    $total_kilos,
                                    1
                                )
                            );
                            ?>

                            kg

                        </strong>

                    </div>


                    <!-- TOTAL A PAGAR -->

                    <div class="summary-row">

                        <div class="label">

                            <span
                                class="bullet-dot"
                                style="
                                    background-color:
                                        var(--accent-orange);
                                "
                            ></span>

                            Total a pagar

                        </div>

                        <strong>

                            <?php echo e(
                                formato_dinero(
                                    $total_pagar
                                )
                            ); ?>

                        </strong>

                    </div>

                </div>


                <!-- CONSEJO -->

                <div class="tip-card">

                    <div class="tip-content">

                        <h4>

                            <i
                                class="fa-solid fa-leaf"
                                style="
                                    color:
                                        var(--accent-green);
                                "
                            ></i>

                            Consejo del día

                        </h4>

                        <p>

                            La calidad comienza en el campo.
                            Sigue haciendo un excelente trabajo
                            para que nuestro café sea el mejor.

                        </p>

                    </div>


                    <img
                        class="tip-img"
                        src="https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?q=80&w=150&auto=format&fit=crop"
                        alt="Café recolectado"
                    >

                </div>

            </div>

        </div>


        <!-- =================================================
             PIE DE PÁGINA
             ================================================= -->

        <footer>

            <div class="footer-brand">

                <i
                    class="fa-solid fa-mug-hot"
                    aria-hidden="true"
                ></i>

                <span>

                    Café Control
                    &copy;
                    <?php echo date('Y'); ?>

                    Developers:

                    <strong>
                        Gloria Tatiana Sanchez
                        &amp;
                        Tatiana Silva Penagos
                    </strong>

                </span>

            </div>

        </footer>

    </main>


    <!-- =====================================================
         MODAL EDITAR PERFIL
         ===================================================== -->

    <div
        class="profile-modal"
        id="perfilModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloPerfil"
        aria-hidden="true"
    >

        <div class="profile-modal-card">


            <!-- CABECERA DEL MODAL -->

            <div class="profile-modal-head">

                <h3 id="tituloPerfil">

                    Editar perfil

                </h3>

                <button
                    type="button"
                    class="profile-close"
                    id="cerrarPerfil"
                    aria-label="Cerrar"
                >

                    <i
                        class="fa-solid fa-xmark"
                    ></i>

                </button>

            </div>


            <!-- MENSAJE -->

            <?php if (
                $perfil_mensaje !== ''
            ): ?>

                <div class="profile-message">

                    <?php
                    echo e(
                        $perfil_mensaje
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- FORMULARIO -->

            <form
                action=""
                method="POST"
                enctype="multipart/form-data"
                id="formPerfil"
            >


                <!-- FOTO -->

                <div class="profile-photo-wrap">

                    <?php if (
                        $foto_perfil !== ''
                        && is_file(
                            __DIR__
                            . '/'
                            . ltrim(
                                $foto_perfil,
                                '/'
                            )
                        )
                    ): ?>

                        <img
                            class="profile-photo-preview"
                            id="vistaPerfil"
                            src="<?php echo e($foto_perfil); ?>?v=<?php echo e(version_foto($foto_perfil)); ?>"
                            alt="Vista previa de la foto de perfil"
                        >

                    <?php else: ?>

                        <div
                            class="profile-photo-preview"
                            id="vistaPerfil"
                            style="
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                color:#17100c;
                                font-size:2rem;
                            "
                        >

                            <i
                                class="fa-solid fa-user"
                            ></i>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- CAMBIAR FOTO -->

                <label
                    class="profile-photo-label"
                    for="fotoPerfilInput"
                >

                    <i
                        class="fa-solid fa-camera"
                    ></i>

                    Cambiar fotografía

                </label>


                <input
                    class="sr-only"
                    type="file"
                    id="fotoPerfilInput"
                    name="foto_perfil"
                    accept="image/jpeg,image/png,image/webp"
                >


                <!-- NOMBRES -->

                <div class="profile-field">

                    <label for="perfilNombres">

                        Nombres

                    </label>

                    <input
                        type="text"
                        id="perfilNombres"
                        name="perfil_nombres"
                        value="<?php echo e($nombre_usuario); ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- APELLIDOS -->

                <div class="profile-field">

                    <label for="perfilApellidos">

                        Apellidos

                    </label>

                    <input
                        type="text"
                        id="perfilApellidos"
                        name="perfil_apellidos"
                        value="<?php echo e($apellido_usuario); ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- BOTONES -->

                <div class="profile-actions">

                    <button
                        type="button"
                        class="profile-btn cancel"
                        id="cancelarPerfil"
                    >

                        Cancelar

                    </button>

                    <button
                        type="submit"
                        class="profile-btn save"
                        name="guardar_perfil"
                        value="1"
                    >

                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>


    <!-- =====================================================
         JAVASCRIPT
         ===================================================== -->

    <script>

        /*
         * =====================================================
         * MODAL DE PERFIL
         * =====================================================
         */

        (function () {

            const modal =
                document.getElementById(
                    'perfilModal'
                );

            const abrir =
                document.getElementById(
                    'abrirPerfil'
                );

            const cerrar =
                document.getElementById(
                    'cerrarPerfil'
                );

            const cancelar =
                document.getElementById(
                    'cancelarPerfil'
                );

            const inputFoto =
                document.getElementById(
                    'fotoPerfilInput'
                );

            const vista =
                document.getElementById(
                    'vistaPerfil'
                );


            function abrirModal() {

                if (!modal) {
                    return;
                }

                modal.classList.add('open');

                modal.setAttribute(
                    'aria-hidden',
                    'false'
                );
            }


            function cerrarModal() {

                if (!modal) {
                    return;
                }

                modal.classList.remove('open');

                modal.setAttribute(
                    'aria-hidden',
                    'true'
                );
            }


            if (abrir) {

                abrir.addEventListener(
                    'click',
                    abrirModal
                );
            }


            if (cerrar) {

                cerrar.addEventListener(
                    'click',
                    cerrarModal
                );
            }


            if (cancelar) {

                cancelar.addEventListener(
                    'click',
                    cerrarModal
                );
            }


            if (modal) {

                modal.addEventListener(
                    'click',
                    function (event) {

                        if (
                            event.target === modal
                        ) {

                            cerrarModal();
                        }
                    }
                );
            }


            document.addEventListener(
                'keydown',
                function (event) {

                    if (
                        event.key === 'Escape'
                    ) {

                        cerrarModal();
                    }
                }
            );


            /*
             * =================================================
             * VISTA PREVIA DE FOTO
             * =================================================
             */

            if (inputFoto) {

                inputFoto.addEventListener(
                    'change',
                    function () {

                        const archivo =
                            this.files &&
                            this.files[0];

                        if (
                            !archivo
                            || !vista
                        ) {

                            return;
                        }


                        /*
                         * Tamaño máximo: 2 MB
                         */

                        if (
                            archivo.size
                            > 2 * 1024 * 1024
                        ) {

                            alert(
                                'La imagen no puede superar 2 MB.'
                            );

                            this.value = '';

                            return;
                        }


                        /*
                         * Tipos permitidos
                         */

                        const tiposPermitidos = [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ];

                        if (
                            !tiposPermitidos.includes(
                                archivo.type
                            )
                        ) {

                            alert(
                                'Solo se permiten imágenes JPG, PNG o WEBP.'
                            );

                            this.value = '';

                            return;
                        }


                        const url =
                            URL.createObjectURL(
                                archivo
                            );


                        /*
                         * Si ya existe una etiqueta IMG
                         */

                        if (
                            vista.tagName === 'IMG'
                        ) {

                            vista.src = url;


                        } else {

                            vista.innerHTML = '';

                            vista.style.display =
                                'block';

                            const img =
                                document.createElement(
                                    'img'
                                );

                            img.src = url;

                            img.alt =
                                'Vista previa de la nueva foto';

                            img.style.width =
                                '100%';

                            img.style.height =
                                '100%';

                            img.style.objectFit =
                                'cover';

                            img.style.borderRadius =
                                '50%';

                            vista.appendChild(
                                img
                            );
                        }

                    }
                );
            }


            /*
             * =================================================
             * ABRIR DESPUÉS DE GUARDAR
             * =================================================
             */

            <?php if (
                isset($_GET['perfil'])
                && $_GET['perfil'] === 'guardado'
            ): ?>

                abrirModal();

            <?php endif; ?>

        })();


        /*
         * =====================================================
         * FECHA Y HORA DE COLOMBIA
         * =====================================================
         */

        (function () {

            const fechaEl =
                document.getElementById(
                    'fecha-actual'
                );

            const horaEl =
                document.getElementById(
                    'hora-actual'
                );


            if (
                !fechaEl
                || !horaEl
            ) {

                return;
            }


            function actualizarFechaHora() {

                const ahora =
                    new Date();


                /*
                 * FECHA
                 */

                const partesFecha =
                    new Intl.DateTimeFormat(
                        'es-CO',
                        {
                            timeZone:
                                'America/Bogota',

                            day:
                                '2-digit',

                            month:
                                '2-digit',

                            year:
                                'numeric'
                        }
                    ).formatToParts(
                        ahora
                    );


                const obtenerParte =
                    (tipo) => {

                        const parte =
                            partesFecha.find(
                                p =>
                                    p.type ===
                                    tipo
                            );

                        return parte
                            ? parte.value
                            : '';
                    };


                const fecha =
                    `${obtenerParte('day')}/` +
                    `${obtenerParte('month')}/` +
                    `${obtenerParte('year')}`;


                /*
                 * HORA
                 */

                let hora =
                    new Intl.DateTimeFormat(
                        'es-CO',
                        {
                            timeZone:
                                'America/Bogota',

                            hour:
                                'numeric',

                            minute:
                                '2-digit',

                            hour12:
                                true
                        }
                    ).format(
                        ahora
                    );


                hora =
                    hora
                    .toLowerCase()
                    .replace(
                        'a. m.',
                        'am'
                    )
                    .replace(
                        'p. m.',
                        'pm'
                    );


                fechaEl.textContent =
                    fecha;

                horaEl.textContent =
                    hora;
            } 


            actualizarFechaHora();


            setInterval(
                actualizarFechaHora,
                1000
            );

        })();

    </script>

</body>

</html>