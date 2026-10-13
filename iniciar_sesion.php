<?php
session_start();

// Si el usuario ya está autenticado, redirigir directamente
if (isset($_SESSION['id_usuario']) && isset($_SESSION['rol'])) {
    switch ($_SESSION['rol']) {
        case 'caficultor':
            header("Location: panel_caficultor.php");
            break;
        case 'mayordomo':
            header("Location: panel_m.php");
            break;
        case 'recolector':
            header("Location: panel_r.php");
            break;
    }
    exit;
}

include('assets/inc/conexion.php');

$mensaje_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ingresar'])) {
    
    $usuario = htmlspecialchars(trim($_POST['usuario']));
    $password_ingresada = $_POST['password'];

    if (!empty($usuario) && !empty($password_ingresada)) {
        
        // 1. Consulta principal en la tabla 'usuarios'
        $stmt = $con->prepare("SELECT id_usuario, documento, nombres, apellidos, contrasena, rol, estado FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario_db = $resultado->fetch_assoc();

            // 2. Verificación de estado del usuario
            if ($usuario_db['estado'] !== 'activo') {
                $mensaje_error = "Tu cuenta se encuentra inactiva. Contacta al caficultor.";
            } else {
                // 3. Verificación de contraseña hash
                if (password_verify($password_ingresada, $usuario_db['contrasena'])) {
                    
                    $id_usuario = $usuario_db['id_usuario'];
                    $rol = strtolower($usuario_db['rol']);

                    // Guardar datos generales en $_SESSION
                    $_SESSION['id_usuario']     = $id_usuario;
                    $_SESSION['documento']      = $usuario_db['documento'];
                    $_SESSION['nombre_completo'] = $usuario_db['nombres'] . " " . $usuario_db['apellidos'];
                    $_SESSION['rol']            = $rol;

                    // 4. Lógica de consulta extendida según el modelo relacional (ER) y REDIRECCIÓN
                    switch ($rol) {
                        case 'caficultor':
                            // Obtener las fincas administradas por el caficultor
                            $stmt_finca = $con->prepare("SELECT id_finca FROM fincas WHERE id_caficultor = ? LIMIT 1");
                            $stmt_finca->bind_param("i", $id_usuario);
                            $stmt_finca->execute();
                            $res_finca = $stmt_finca->get_result();
                            
                            if ($finca = $res_finca->fetch_assoc()) {
                                $_SESSION['id_finca'] = $finca['id_finca'];
                            }
                            $stmt_finca->close();

                            header("Location: panel_caficultor.php");
                            exit;

                        case 'mayordomo':
                            // Consultar permisos y finca asignada en 'permisos_mayordomo'
                            $stmt_mayor = $con->prepare("SELECT id_finca, registrar_recolecciones, editar_recolecciones, eliminar_recolectores, ver_reportes, gestionar_mayordomos FROM permisos_mayordomo WHERE id_mayordomo = ? LIMIT 1");
                            $stmt_mayor->bind_param("i", $id_usuario);
                            $stmt_mayor->execute();
                            $res_mayor = $stmt_mayor->get_result();

                            if ($res_mayor->num_rows === 1) {
                                $permisos = $res_mayor->fetch_assoc();
                                $_SESSION['id_finca']              = $permisos['id_finca'];
                                $_SESSION['permiso_registrar']     = $permisos['registrar_recolecciones'];
                                $_SESSION['permiso_editar']        = $permisos['editar_recolecciones'];
                                $_SESSION['permiso_eliminar_rec']  = $permisos['eliminar_recolectores'];
                                $_SESSION['permiso_reportes']      = $permisos['ver_reportes'];
                                $_SESSION['permiso_mayordomos']    = $permisos['gestionar_mayordomos'];

                                $stmt_mayor->close();
                                header("Location: panel_m.php");
                                exit;
                            } else {
                                session_destroy();
                                $mensaje_error = "El usuario no tiene asignada una finca o permisos de mayordomo.";
                            }
                            break;

                        case 'recolector':
                            header("Location: panel_r.php");
                            exit;

                        default:
                            session_destroy();
                            $mensaje_error = "El rol asignado no es válido.";
                            break;
                    }

                } else {
                    $mensaje_error = "La contraseña ingresada es incorrecta.";
                }
            }
        } else {
            $mensaje_error = "El usuario ingresado no está registrado.";
        }
        $stmt->close();

    } else {
        $mensaje_error = "Por favor, completa todos los campos para poder ingresar.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Café control</title>
  <!-- Iconos Lucide (CDN) -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <style>
    :root {
      --bg-cream: #f6f0e6;
      --card-cream: #f4eee3;
      --dark-brown: #2e1c0c;
      --medium-brown: #3d2712;
      --gold-accent: #c49a51;
      --green-btn: #425832;
      --green-btn-hover: #334526;
      --text-dark: #222222;
      --text-muted: #666666;
      --border-color: #e0d7c8;
      --input-bg: #f9f5ee;
      --error-bg: #f8d7da;
      --error-text: #721c24;
      --error-border: #f5c6cb;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    body {
      background-color: var(--bg-cream);
      color: var(--text-dark);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    a {
      text-decoration: none;
    }

    /* --- HEADER / TOPBAR --- */
    .topbar {
      background-color: var(--dark-brown);
      color: #ffffff;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 30px;
    }

    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Corrección para SVG en topbar */
    .topbar-brand i,
    .topbar-brand svg {
      color: #ffffff;
      width: 28px;
      height: 28px;
    }

    .topbar-title h1 {
      font-size: 1.1rem;
      font-weight: 700;
      margin: 0;
    }

    .topbar-slogan {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      color: var(--gold-accent);
    }

    .topbar-slogan i,
    .topbar-slogan svg {
      width: 18px;
      height: 18px;
    }

    /* --- CONTENEDOR PRINCIPAL --- */
    .main-container {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 30px;
    }

    .card {
      display: flex;
      width: 100%;
      max-width: 950px;
      background-color: var(--card-cream);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }

    /* --- PANEL IZQUIERDO --- */
    .left-panel {
      width: 50%;
      background: linear-gradient(180deg, #3d2712 0%, #201206 100%);
      color: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 30px 40px;
      text-align: center;
      position: relative;
    }

    .hero-image-container {
      width: 100%;
      height: 200px;
      border-radius: 15px;
      overflow: hidden;
      margin-bottom: 20px;
    }

    .hero-image-container img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .left-logo {
      margin-bottom: 15px;
    }

    /* Corrección para SVG en logo izquierdo */
    .left-logo i,
    .left-logo svg {
      width: 50px;
      height: 50px;
      color: #ffffff;
    }

    .panel-title {
      font-size: 1.8rem;
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: 5px;
    }

    .divider {
      width: 80%;
      height: 1px;
      background-color: rgba(255,255,255,0.2);
      margin: 10px 0 15px 0;
    }

    .panel-description {
      font-size: 0.9rem;
      opacity: 0.85;
      line-height: 1.4;
      margin-bottom: 30px;
    }

    .features-list {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 20px;
      text-align: left;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .feature-icon {
      width: 42px;
      height: 42px;
      background-color: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* CORRECCIÓN PRINCIPAL PARA LOS ICONOS DEL CÍRCULO BLANCO */
    .feature-icon i,
    .feature-icon svg {
      color: #2e1c0c !important;
      stroke: #2e1c0c !important;
      width: 22px !important;
      height: 22px !important;
      display: block;
    }

    .feature-text h4 {
      font-size: 0.95rem;
      font-weight: 700;
    }

    .feature-text p {
      font-size: 0.8rem;
      opacity: 0.75;
    }

    /* --- PANEL DERECHO (FORMULARIO) --- */
    .right-panel {
      width: 50%;
      padding: 50px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .avatar-icon {
      width: 65px;
      height: 65px;
      background-color: #dce5d5;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px auto;
    }

    /* Corrección para SVG del avatar */
    .avatar-icon i,
    .avatar-icon svg {
      color: var(--green-btn);
      width: 32px;
      height: 32px;
    }

    .login-title {
      text-align: center;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--dark-brown);
      margin-bottom: 5px;
    }

    .login-subtitle {
      text-align: center;
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-bottom: 20px;
    }

    .alert-error {
      background-color: var(--error-bg);
      color: var(--error-text);
      border: 1px solid var(--error-border);
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 0.85rem;
      margin-bottom: 18px;
      text-align: center;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--dark-brown);
      margin-bottom: 8px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    /* Corrección para SVG dentro de los inputs */
    .input-wrapper i.input-icon,
    .input-wrapper svg.input-icon {
      position: absolute;
      left: 12px;
      color: #888888;
      width: 18px;
      height: 18px;
    }

    .input-wrapper i.toggle-password,
    .input-wrapper svg.toggle-password {
      position: absolute;
      right: 12px;
      color: #888888;
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    .input-wrapper input {
      width: 100%;
      padding: 12px 40px 12px 38px;
      border: 1.5px solid var(--border-color);
      border-radius: 8px;
      background-color: var(--input-bg);
      font-size: 0.9rem;
      outline: none;
      transition: border 0.2s;
    }

    .input-wrapper input:focus {
      border-color: var(--green-btn);
    }

    .form-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.8rem;
      margin-bottom: 25px;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--text-muted);
      cursor: pointer;
    }

    .forgot-pass {
      color: var(--dark-brown);
      text-decoration: none;
      font-weight: 600;
    }

    .btn-submit {
      width: 100%;
      padding: 12px;
      background-color: var(--green-btn);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background-color 0.2s;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .btn-submit:hover {
      background-color: var(--green-btn-hover);
    }

    .divider-text {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 20px 0;
      color: #a09080;
      font-size: 0.8rem;
    }

    .divider-text::before,
    .divider-text::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid var(--border-color);
    }

    .divider-text span {
      padding: 0 10px;
    }

    .register-prompt {
      text-align: center;
      font-size: 0.85rem;
      color: var(--text-dark);
      margin-bottom: 12px;
    }

    .btn-register {
      width: 100%;
      padding: 12px;
      background-color: transparent;
      color: var(--green-btn);
      border: 1.5px solid var(--green-btn);
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background-color 0.2s;
    }

    .btn-register:hover {
      background-color: rgba(66, 88, 50, 0.08);
    }

    @media (max-width: 768px) {
      .card {
        flex-direction: column;
      }
      .left-panel, .right-panel {
        width: 100%;
      }
    }
  </style>
</head>
<body>

  <!-- BARRA SUPERIOR -->
  <header class="topbar">
    <div class="topbar-brand">
      <i data-lucide="coffee"></i>
      <div class="topbar-title">
        <h1>Café control</h1>
      </div>
    </div>
    <div class="topbar-slogan">
      <i data-lucide="bean"></i>
      <span>Café de calidad, trabajo que vale.</span>
    </div>
  </header>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="main-container">
    <div class="card">
      
      <!-- PANEL IZQUIERDO -->
      <section class="left-panel">
        <div class="hero-image-container">
          <img src="https://images.unsplash.com/photo-1587734195503-904fca47e0e9?auto=format&fit=crop&q=80&w=800" alt="Granos de café recién recolectados">
        </div>
        
        <div class="left-logo">
          <i data-lucide="coffee"></i>
        </div>

        <h2 class="panel-title">Café control</h2>
        
        <div class="divider"></div>

        <p class="panel-description">
          Lleva el registro de cuentas de café recolectado por trabajador de manera fácil, rápida y segura.
        </p>

        <div class="features-list">
          <div class="feature-item">
            <div class="feature-icon">
              <i data-lucide="clipboard-list"></i>
            </div>
            <div class="feature-text">
              <h4>Registro de cuentas</h4>
              <p>Lleva el control de cada recolección por trabajador.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-icon">
              <i data-lucide="user"></i>
            </div>
            <div class="feature-text">
              <h4>Gestión de trabajadores</h4>
              <p>Administra la información de tus trabajadores.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-icon">
              <i data-lucide="bar-chart-2"></i>
            </div>
            <div class="feature-text">
              <h4>Reportes y estadísticas</h4>
              <p>Consulta reportes y analiza la producción recolectada.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- PANEL DERECHO: FORMULARIO -->
      <section class="right-panel">
        <div class="avatar-icon">
          <i data-lucide="user"></i>
        </div>

        <h2 class="login-title">Iniciar sesión</h2>
        <p class="login-subtitle">Ingresa tus credenciales para continuar</p>

        <?php if (!empty($mensaje_error)): ?>
          <div class="alert-error">
            <?php echo $mensaje_error; ?>
          </div>
        <?php endif; ?>

        <!-- Formulario configurado para enviar datos a PHP -->
        <form action="" method="POST">
          <div class="form-group">
            <label for="username">Usuario</label>
            <div class="input-wrapper">
              <i data-lucide="user" class="input-icon"></i>
              <input type="text" id="username" name="usuario" placeholder="Ingresa tu usuario" required>
            </div>
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <div class="input-wrapper">
              <i data-lucide="lock" class="input-icon"></i>
              <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>
              <i data-lucide="eye" class="toggle-password" id="btnTogglePassword"></i>
            </div>
          </div>

          <div class="form-options">
            <label class="remember-me">
              <input type="checkbox" name="recordarme">
              <span>Recordarme</span>
            </label>
            <a href="recuperar_contraseña.php" class="forgot-pass">¿Olvidaste tu contraseña?</a>
          </div>

          <button type="submit" name="ingresar" class="btn-submit">
            <i data-lucide="log-in"></i>
            <span>Ingresar</span>
          </button>
        </form>

        <div class="divider-text">
          <span>o</span>
        </div>

        <p class="register-prompt">¿No tienes una cuenta?</p>
        <a href="index.php">
          <button class="btn-register" type="button">
            <i data-lucide="user-plus"></i>
            <span>Crear cuenta</span>
          </button>
        </a>
        
      </section>

    </div>
  </main>

  <script>
    // Inicialización de iconos Lucide
    document.addEventListener('DOMContentLoaded', () => {
      lucide.createIcons();
    });

    // Lógica para alternar la visibilidad de la contraseña
    const btnTogglePassword = document.getElementById('btnTogglePassword');
    const inputPassword = document.getElementById('password');

    if (btnTogglePassword && inputPassword) {
      btnTogglePassword.addEventListener('click', () => {
        const isPassword = inputPassword.getAttribute('type') === 'password';
        inputPassword.setAttribute('type', isPassword ? 'text' : 'password');
        
        // Al reemplazar el ícono creado por Lucide:
        btnTogglePassword.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
        lucide.createIcons();
      });
    }
  </script>
</body>
</html>