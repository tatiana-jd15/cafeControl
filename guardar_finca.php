<?php
session_start();
require_once 'assets/inc/conexion.php';

// Validar que exista una sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $id_caficultor = $_SESSION['id_usuario'];
    $nombre = trim($_POST['nombre']);
    $ubicacion_vereda = trim($_POST['ubicacion_vereda']);
    $estado = isset($_POST['estado']) ? $_POST['estado'] : 'activa';

    // Comprobar que los campos requeridos no estén vacíos
    if (!empty($nombre) && !empty($ubicacion_vereda)) {
        
        // sql
        $sql = "INSERT INTO fincas (id_caficultor, nombre, ubicacion_vereda, estado) VALUES (?, ?, ?, ?)";
        
        if ($stmt = $con->prepare($sql)) {
            $stmt->bind_param("isss", $id_caficultor, $nombre, $ubicacion_vereda, $estado);
            
            if ($stmt->execute()) {
                // Éxito: Redirecciona al listado de fincas
                header("Location: fincas.php?status=success");
                exit();
            } else {
                echo "Error al registrar la finca: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error en la consulta SQL: " . $con->error;
        }
    } else {
        echo "Por favor, complete todos los campos obligatorios.";
    }
} else {
    header("Location: agregar_finca.php");
    exit();
}
?>