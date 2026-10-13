<?php
session_start();
require_once 'assets/inc/conexion.php';

// 1. Validar sesión de usuario (Caficultor)
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_caficultor = intval($_SESSION['id_usuario']);

// 2. Obtener el ID del recolector (Soporta 'id_usuario' o simplemente 'id')
$id_usuario = 0;
if (isset($_GET['id_usuario'])) {
    $id_usuario = intval($_GET['id_usuario']);
} elseif (isset($_GET['id'])) {
    $id_usuario = intval($_GET['id']);
}

// 3. Obtener el ID de la finca
$id_finca = 0;
if (isset($_GET['id_finca'])) {
    $id_finca = intval($_GET['id_finca']);
} elseif (isset($_SESSION['id_finca'])) {
    // Si la finca está en la sesión del caficultor
    $id_finca = intval($_SESSION['id_finca']);
}

// Validar que tengamos al menos el ID del recolector
if ($id_usuario === 0) {
    header("Location: recolectores_c.php?error=missing_id");
    exit();
}

// 4. Si no venía la finca por parámetro ni sesión, la buscamos directamente en la BD
if ($id_finca === 0) {
    $sql_finca = "SELECT r.id_finca 
                  FROM recolectores r 
                  INNER JOIN fincas f ON r.id_finca = f.id_finca 
                  WHERE r.id_usuario = ? AND f.id_caficultor = ? 
                  LIMIT 1";
    $stmt_finca = $con->prepare($sql_finca);
    $stmt_finca->bind_param("ii", $id_usuario, $id_caficultor);
    $stmt_finca->execute();
    $res_finca = $stmt_finca->get_result();

    if ($row = $res_finca->fetch_assoc()) {
        $id_finca = intval($row['id_finca']);
    }
    $stmt_finca->close();
}

// 5. Verificar que la finca realmente le pertenezca al caficultor logueado
if ($id_finca > 0) {
    $sql_check = "SELECT id_finca FROM fincas WHERE id_finca = ? AND id_caficultor = ?";
    $stmt_check = $con->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_finca, $id_caficultor);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        $stmt_check->close();

        // 6. ELIMINAR EL REGISTRO DE LA TABLA 'recolectores'
        $sql_del = "DELETE FROM recolectores WHERE id_usuario = ? AND id_finca = ?";
        $stmt_del = $con->prepare($sql_del);
        $stmt_del->bind_param("ii", $id_usuario, $id_finca);

        if ($stmt_del->execute()) {
            $stmt_del->close();
            // Eliminación exitosa
            header("Location: recolectores_c.php?status=deleted");
            exit();
        } else {
            $stmt_del->close();
            // Error en la base de datos (Ej: restricciones de clave foránea)
            header("Location: recolectores_c.php?error=db_error");
            exit();
        }

    } else {
        $stmt_check->close();
        // La finca no le pertenece a este caficultor
        header("Location: recolectores_c.php?error=unauthorized");
        exit();
    }
} else {
    // No se encontró una finca válida asociada
    header("Location: recolectores_c.php?error=finca_not_found");
    exit();
}
?>