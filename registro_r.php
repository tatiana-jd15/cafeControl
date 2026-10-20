<?php
// Conexión a la base de datos
include('assets/inc/conexion.php');

$mensaje_error = "";

// Verifica el envío del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registro'])) {
    
    $nombres = htmlspecialchars(trim($_POST['nombres']));
    $apellidos = htmlspecialchars(trim($_POST['apellidos']));
    $cedula = htmlspecialchars(trim($_POST['cedula'])); 
    $telefono = htmlspecialchars(trim($_POST['telefono']));
    $correo = htmlspecialchars(trim($_POST['correo']));
    $finca = htmlspecialchars(trim($_POST['finca']));
    $usuario = htmlspecialchars(trim($_POST['usuario'])); 
    $rol = "recolector"; // Rol predeterminado según ENUM
    $estado_usuario = "activo"; // Estado por defecto
    $clave_plana = $_POST['clave'];
    $clave_confirmar = $_POST['confirmar_clave'];

    // Validaciones en backend
    if (!empty($nombres) && !empty($apellidos) && !empty($cedula) && !empty($telefono) && !empty($correo) && !empty($finca) && !empty($usuario) && !empty($clave_plana)) {
        
        if ($clave_plana !== $clave_confirmar) {
            $mensaje_error = "Las contraseñas no coinciden.";
        } elseif (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            
            // Verificar si ya existe el documento o el usuario en la tabla 'usuarios'
            $stmtCheck = $con->prepare("SELECT documento, usuario FROM usuarios WHERE documento = ? OR usuario = ?");
            $stmtCheck->bind_param("ss", $cedula, $usuario);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            
            if ($stmtCheck->num_rows > 0) {
                $mensaje_error = "El documento o el usuario ya se encuentran registrados.";
                $stmtCheck->close();
            } else {
                $stmtCheck->close();

                // Hash de la contraseña
                $clave_encriptada = password_hash($clave_plana, PASSWORD_BCRYPT);

                // Iniciamos una transacción para asegurar que ambos registros (usuario y finca) se guarden o ninguno
                $con->begin_transaction();

                try {
                    // 1. Insertar el nuevo caficultor en la tabla 'usuarios'
                    $sqlUsuario = "INSERT INTO usuarios (documento, nombres, apellidos, telefono, correo, usuario, contrasena, rol, estado) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmtUser = $con->prepare($sqlUsuario);
                    $stmtUser->bind_param("sssssssss", $cedula, $nombres, $apellidos, $telefono, $correo, $usuario, $clave_encriptada, $rol, $estado_usuario);
                    $stmtUser->execute();

                    // Obtener el ID autoincremental asignado a este usuario
                    $id_caficultor = $con->insert_id;
                    $stmtUser->close();

                    // 2. Insertar la finca relacionada en la tabla 'fincas'
                    $estado_finca = "activa"; // Según el ENUM de la tabla fincas
                    $sqlFinca = "INSERT INTO fincas (id_caficultor, nombre, estado) 
                                 VALUES (?, ?, ?)";
                    
                    $stmtFinca = $con->prepare($sqlFinca);
                    $stmtFinca->bind_param("iss", $id_caficultor, $finca, $estado_finca);
                    $stmtFinca->execute();
                    $stmtFinca->close();

                    // Si todo fue exitoso, confirmamos la transacción
                    $con->commit();

                    echo "<script>
                        alert('¡Registro exitoso! Ya puedes iniciar sesión.');
                        window.location.href = 'index.php';
                        </script>";
                    exit;

                } catch (Exception $e) {
                    // Si ocurre un error, revertimos los cambios
                    $con->rollback();
                    $mensaje_error = "Error al guardar el registro: " . $e->getMessage();
                }
            }
        } else {
            $mensaje_error = "Introduce un correo electrónico válido.";
        }
    } else {
        $mensaje_error = "Por favor, completa todos los campos del formulario.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Control - Registro</title>
    <!-- Fuentes Google para máxima legibilidad -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome para los íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- Reset & Fuentes Legibles --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        :root {
            --bg-canvas: #e8ded1;
            --card-bg-light: #ffffff;
            --card-bg-subtle: #f5ebe0;
            --text-dark: #1d130c;
            --text-muted: #524336;
            --btn-brown: #3b200e;
            --btn-brown-hover: #211106;
            --border-color: #d8c8b8;
            --input-border: #bdab9c;
            --focus-ring: #8b5a2b;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 30px 0 0 0;
            font-size: 16px;
        }

        /* --- Contenedor Principal --- */
        .page-container {
            width: 92%;
            max-width: 1080px;
            display: flex;
            background-color: transparent;
            margin-bottom: 40px;
            gap: 20px;
        }

        /* --- Columna Izquierda --- */
        .left-column {
            flex: 1;
            padding: 10px 20px 20px 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 25px;
        }

        .logo-circle {
            width: 72px;
            height: 72px;
            background-color: #fff;
            border: 3px solid #6b472e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: #8b261d;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .header-logo h1 {
            font-size: 2.6rem;
            color: var(--text-dark);
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .brand-title {
            text-align: center;
            margin-bottom: 22px;
        }

        .brand-title h2 {
            font-size: 1.85rem;
            line-height: 1.25;
            color: var(--text-dark);
            margin-top: 8px;
            font-weight: 800;
        }

        .brand-subtitle {
            text-align: center;
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-top: 10px;
            font-weight: 600;
            line-height: 1.4;
        }

        .coffee-divider {
            text-align: center;
            margin: 12px 0;
            color: #8b5a2b;
            font-size: 1.2rem;
        }

        /* --- Ajustes de la Imagen de Cabecera --- */
        .hero-banner {
            width: 500px;
            height: 450px;
            border-radius: 18px;
            margin-bottom: 22px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            overflow: hidden;
        }

        .hero-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .info-card {
            background-color: var(--card-bg-subtle);
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .info-icon {
            width: 56px;
            height: 56px;
            background-color: var(--btn-brown);
            color: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }

        .info-text h3 {
            font-size: 1.15rem;
            color: var(--text-dark);
            margin-bottom: 4px;
            font-weight: 700;
        }

        .info-text p {
            font-size: 0.98rem;
            color: var(--text-muted);
            line-height: 1.45;
            font-weight: 500;
        }

        /* --- Columna Derecha --- */
        .right-column {
            flex: 1.15;
            background-color: var(--card-bg-light);
            border-radius: 22px;
            padding: 40px 35px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.07);
            border: 1.5px solid var(--border-color);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group label i {
            font-size: 1.2rem;
            color: var(--btn-brown);
            width: 20px;
            text-align: center;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            background-color: #faf8f5;
            font-size: 1.05rem;
            color: var(--text-dark);
            font-weight: 500;
            outline: none;
            transition: all 0.2s ease;
        }

        .input-wrapper input:focus {
            border-color: var(--focus-ring);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(139, 90, 43, 0.15);
        }

        .input-wrapper input::placeholder {
            color: #928477;
            font-weight: 400;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b5c50;
            cursor: pointer;
            font-size: 1.3rem;
            padding: 4px;
        }

        .toggle-password:hover {
            color: var(--text-dark);
        }

        .alert-error {
            background-color: #fde8e8;
            color: #9b1c1c;
            padding: 14px 18px;
            border-radius: 10px;
            border: 1.5px solid #f8b4b4;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit {
            background-color: var(--btn-brown);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 1.15rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
            transition: background-color 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 12px rgba(59, 32, 14, 0.2);
        }

        .btn-submit:hover {
            background-color: var(--btn-brown-hover);
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .footer-login {
            text-align: center;
            margin-top: 20px;
        }

        .divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 14px 0;
            color: #8b5a2b;
            font-size: 1.2rem;
        }

        .footer-login p {
            font-size: 1.05rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .footer-login a {
            color: var(--text-dark);
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        footer {
            width: 100%;
            background-color: #211106;
            color: #e0d1c3;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1rem;
            font-weight: 500;
            margin-top: auto;
        }

        footer .footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        @media (max-width: 880px) {
            .page-container {
                flex-direction: column;
            }
            .left-column {
                padding-right: 0;
            }
            .right-column {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body>

    <div class="page-container">
        
        <!-- Columna Izquierda: Información -->
        <div class="left-column">
            
            <div class="header-logo">
                <div class="logo-circle">
                    <i class="fa-solid fa-seedling"></i>
                </div>
                <h1>Registro</h1>
            </div>

            <div class="brand-title">
                <div style="font-size: 2rem; color: #5c381d;"><i class="fa-solid fa-hand-holding-heart"></i></div>
                <h2>Café control</h2>
                <div class="coffee-divider"><i class="fa-solid fa-cookie-bite"></i></div>
                <p class="brand-subtitle">Lleva el control de tu café,<br>tu esfuerzo vale.</p>
            </div>

            <!-- Banner con etiqueta img -->
            <div class="hero-banner">
                <img src="images/recolec.jpeg" alt="Recolección de café">
            </div>

            <div class="info-card">
                <div class="info-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="info-text">
                    <h3>Tus datos están seguros</h3>
                    <p>La información que registras será protegida y utilizada únicamente dentro del sistema.</p>
                </div>
            </div>

            <div class="info-card">
                <div class="info-icon">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="info-text">
                    <h3>Crea tu cuenta de Caficultor</h3>
                    <p>Regístrate para comenzar a llevar el control de tu finca y trabajadores.</p>
                </div>
            </div>

        </div>

        <!-- Columna Derecha: Formulario -->
        <div class="right-column">

            <?php if (!empty($mensaje_error)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $mensaje_error; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <div class="form-group">
                    <label for="nombres"><i class="fa-solid fa-user"></i> Nombres</label>
                    <div class="input-wrapper">
                        <input type="text" id="nombres" name="nombres" placeholder="Ingresa tus nombres" required value="<?php echo isset($nombres) ? $nombres : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="apellidos"><i class="fa-solid fa-user"></i> Apellidos</label>
                    <div class="input-wrapper">
                        <input type="text" id="apellidos" name="apellidos" placeholder="Ingresa tus apellidos" required value="<?php echo isset($apellidos) ? $apellidos : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="documento"><i class="fa-solid fa-id-card"></i> Documento de Identidad</label>
                    <div class="input-wrapper">
                        <input type="text" id="documento" name="cedula" placeholder="Ingresa tu número de documento" required value="<?php echo isset($cedula) ? $cedula : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefono"><i class="fa-solid fa-phone"></i> Teléfono</label>
                    <div class="input-wrapper">
                        <input type="tel" id="telefono" name="telefono" placeholder="Ingresa tu número de teléfono" required value="<?php echo isset($telefono) ? $telefono : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="correo"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <div class="input-wrapper">
                        <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required value="<?php echo isset($correo) ? $correo : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="finca"><i class="fa-solid fa-house-chimney"></i> Nombre de la Finca</label>
                    <div class="input-wrapper">
                        <input type="text" id="finca" name="finca" placeholder="Ingresa el nombre de tu finca" required value="<?php echo isset($finca) ? $finca : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="usuario"><i class="fa-solid fa-user-gear"></i> Usuario</label>
                    <div class="input-wrapper">
                        <input type="text" id="usuario" name="usuario" placeholder="Crea un nombre de usuario" required value="<?php echo isset($usuario) ? $usuario : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="contrasena"><i class="fa-solid fa-lock"></i> Contraseña</label>
                    <div class="input-wrapper">
                        <input type="password" id="contrasena" name="clave" placeholder="Crea una contraseña" required>
                        <i class="fa-solid fa-eye-slash toggle-password"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmar-contrasena"><i class="fa-solid fa-lock"></i> Confirmar contraseña</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmar-contrasena" name="confirmar_clave" placeholder="Repite tu contraseña" required>
                        <i class="fa-solid fa-eye-slash toggle-password"></i>
                    </div>
                </div>

                <button type="submit" name="registro" class="btn-submit">
                    <i class="fa-solid fa-user-plus"></i> Crear mi cuenta
                </button>

            </form>

            <div class="footer-login">
                <div class="divider"><i class="fa-solid fa-cookie-bite"></i></div>
                <p>¿Ya tienes una cuenta? <a href="index.php">Inicia sesión</a></p>
            </div>
        </div>

    </div>

    <!-- Pie de página -->
    <footer>
        <div class="footer-brand">
            <i class="fa-solid fa-seedling"></i>
            <span>Café control</span>
        </div>
        <div>
            <i class="fa-solid fa-coffee"></i>
        </div>
    </footer>

    <!-- Script para alternar visibilidad de contraseñas -->
    <script>
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling;
                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                } else {
                    input.type = 'password';
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                }
            });
        });
    </script>

</body>
</html>