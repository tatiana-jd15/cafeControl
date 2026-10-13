<?php
// Conexión a la B.D.
include('assets/inc/conexion.php'); 

$mensaje = '';
$tipo_alerta = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula      = trim($_POST['cedula'] ?? '');
    $nueva_clave = $_POST['nueva_clave'] ?? '';
    $confirmar   = $_POST['nueva'] ?? '';

    // Validar campos vacíos
    if (empty($cedula) || empty($nueva_clave) || empty($confirmar)) {
        $mensaje = 'Por favor, completa todos los campos.';
        $tipo_alerta = 'error';
    } 
    // Validar coincidencia de contraseñas
    elseif ($nueva_clave !== $confirmar) {
        $mensaje = 'Las contraseñas no coinciden. Inténtalo de nuevo.';
        $tipo_alerta = 'error';
    } 
    else {
        try {
            // Verificar si el usuario existe (usando mysqli)
            $stmt = $con->prepare("SELECT id_usuario FROM usuarios WHERE documento = ?");
            $stmt->bind_param("s", $cedula);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Encriptar la nueva contraseña
                $password_hash = password_hash($nueva_clave, PASSWORD_BCRYPT);

                // Actualizar la contraseña en la base de datos
                $updateStmt = $con->prepare("UPDATE usuarios SET contrasena = ? WHERE documento = ?");
                $updateStmt->bind_param("ss", $password_hash, $cedula); 
                $updateStmt->execute();

                // Redirigir si fue exitoso
                header("Location: index.php?status=success_reset");
                exit();
            } else {
                $mensaje = 'El número de cédula ingresado no se encuentra registrado.';
                $tipo_alerta = 'error';
            }
        } catch (Exception $e) {
            $mensaje = 'Ocurrió un error al actualizar la contraseña: ' . $e->getMessage();
            $tipo_alerta = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestor de cuentas - Restablecer contraseña</title>
  
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary-dark: #3A1F0D;
      --primary-brown: #4A2810;
      --accent-gold: #C07D38;
      --green-success: #2E7D32;
      --bg-light: #FDFBF7;
      --card-bg: #FAF5EE;
      --text-main: #2C2C2C;
      --text-muted: #666666;
      --border-color: #D6C7B2;
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: #EFEAE1;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    .app-card {
      width: 100%;
      max-width: 1050px;
      background-color: #fff;
      border-radius: 12px;
      box-shadow: var(--shadow);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .app-header {
      background-color: var(--primary-dark);
      color: #fff;
      padding: 12px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand-section {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-logo {
      font-size: 24px;
      color: #79AC78;
    }

    .brand-title h1 {
      font-size: 1rem;
      font-weight: 600;
      line-height: 1.2;
    }

    .back-link {
      color: #fff;
      text-decoration: none;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: opacity 0.2s;
    }

    .back-link:hover {
      opacity: 0.8;
    }

    .main-content {
      display: flex;
      min-height: 600px;
    }

    /* BANNER LATERAL CON IMAGEN LOCAL DE CAFÉ */
    .left-banner {
      flex: 0 0 38%;
      background: linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.55)),
                  url('images/cafe.jpg') center/cover no-repeat;
      padding: 40px 30px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      color: #fff;
      position: relative;
    }

    .shield-badge {
      background-color: rgba(255, 255, 255, 0.9);
      border: 3px solid var(--primary-brown);
      border-radius: 50% 50% 50% 50% / 12% 12% 88% 88%;
      padding: 20px;
      width: 140px;
      height: 160px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 25px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    .shield-badge i {
      font-size: 60px;
      color: var(--primary-brown);
    }

    .banner-title {
      font-family: 'Merriweather', serif;
      font-size: 1.5rem;
      margin-bottom: 10px;
      color: #ffffff;
      font-weight: 700;
      text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }

    .banner-title span {
      font-style: italic;
      font-weight: 400;
    }

    .divider {
      width: 120px;
      height: 1px;
      background-color: rgba(255, 255, 255, 0.6);
      margin: 15px auto;
      position: relative;
    }

    .divider i {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #5b4636;
      padding: 0 8px;
      font-size: 12px;
    }

    .banner-description {
      font-size: 0.85rem;
      line-height: 1.5;
      color: #E0E0E0;
      max-width: 250px;
      margin-top: 10px;
    }

    .right-form {
      flex: 1;
      padding: 35px 45px;
      background-color: #ffffff;
    }

    .form-header {
      display: flex;
      align-items: flex-start;
      gap: 15px;
      margin-bottom: 20px;
    }

    .icon-bubble {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background-color: var(--primary-dark);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .form-header-text h2 {
      font-family: 'Merriweather', serif;
      font-size: 1.4rem;
      color: var(--primary-dark);
    }

    .form-header-text p {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-top: 4px;
    }

    .alert-message {
      padding: 12px 15px;
      border-radius: 6px;
      font-size: 0.85rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
    }

    .form-label h3 {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-main);
    }

    .form-label p {
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-control {
      width: 100%;
      padding: 10px 40px 10px 15px;
      border: 1px solid var(--border-color);
      border-radius: 6px;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s;
    }

    .input-control:focus {
      border-color: var(--primary-brown);
    }

    .input-icon {
      position: absolute;
      right: 12px;
      color: #888;
      cursor: pointer;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 15px;
      margin-top: 25px;
      margin-bottom: 25px;
    }

    .btn {
      padding: 10px 24px;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
      text-decoration: none;
    }

    .btn-secondary {
      background-color: #fff;
      border: 1px solid var(--primary-dark);
      color: var(--primary-dark);
    }

    .btn-secondary:hover {
      background-color: #F5F5F5;
    }

    .btn-primary {
      background-color: var(--primary-dark);
      border: 1px solid var(--primary-dark);
      color: #fff;
    }

    .btn-primary:hover {
      background-color: var(--primary-brown);
    }

    .info-card {
      background-color: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .info-content {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .info-text h4 {
      font-size: 0.9rem;
      color: var(--primary-dark);
      margin-bottom: 2px;
    }

    .info-text p {
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .info-illustration {
      font-size: 2rem;
      color: var(--primary-brown);
      opacity: 0.8;
    }

    @media (max-width: 768px) {
      .main-content {
        flex-direction: column;
      }
      .left-banner {
        padding: 30px 20px;
      }
      .right-form {
        padding: 25px 20px;
      }
      .form-actions {
        flex-direction: column;
      }
      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

  <div class="app-card">
    
    <!-- Barra Superior -->
    <header class="app-header">
      <div class="brand-section">
        <i class="fa-solid fa-seedling brand-logo"></i>
        <div class="brand-title">
          <h1>Café control</h1>
        </div>
      </div>
      <a href="index.php" class="back-link">
        <i class="fa-solid fa-mug-hot"></i> Volver al inicio de sesión
      </a>
    </header>

    <!-- Contenido Principal -->
    <div class="main-content">
      
      <!-- Banner Izquierdo Decorativo -->
      <aside class="left-banner">
        <div class="shield-badge">
          <i class="fa-solid fa-mug-saucer"></i>
        </div>
        <h2 class="banner-title">
          Estás a un paso de<br>
          <span>recuperar tu cuenta</span>
        </h2>
        
        <div class="divider">
          <i class="fa-solid fa-cookie-bite"></i>
        </div>

        <p class="banner-description">
          Ingresa la información solicitada y confirma tu nueva contraseña para restablecer el acceso a tu cuenta.
        </p>
      </aside>

      <!-- Formulario de Entrada -->
      <main class="right-form">
        
        <!-- Encabezado de la Sección -->
        <div class="form-header">
          <div class="icon-bubble">
            <i class="fa-solid fa-lock"></i>
          </div>
          <div class="form-header-text">
            <h2>Crear nueva contraseña</h2>
            <p>Completa la información solicitada y confirma tu nueva contraseña. Asegúrate de que sea segura y fácil de recordar para ti.</p>
          </div>
        </div>

        <!-- Mensaje de Alerta (Error / Éxito) -->
        <?php if (!empty($mensaje)): ?>
          <div class="alert-message <?= $tipo_alerta === 'error' ? 'alert-error' : 'alert-success' ?>">
            <i class="fa-solid <?= $tipo_alerta === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
            <span><?= htmlspecialchars($mensaje) ?></span>
          </div>
        <?php endif; ?>

        <!-- El formulario apunta a este mismo archivo -->
        <form method="POST" action="">
          
          <!-- Campo 1: Cédula -->
          <div class="form-group">
            <div class="form-label">
              <div class="icon-bubble" style="width: 28px; height: 28px; font-size: 0.75rem;">
                <i class="fa-solid fa-id-card"></i>
              </div>
              <div>
                <h3>Número de cédula</h3>
                <p>Ingresa tu número de cédula.</p>
              </div>
            </div>
            <div class="input-wrapper">
              <input type="text" class="input-control" placeholder="Ej: 1234567890" name="cedula" value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>" required>
              <i class="fa-solid fa-address-card input-icon"></i>
            </div>
          </div>

          <!-- Campo 2: Nueva Contraseña -->
          <div class="form-group">
            <div class="form-label">
              <div class="icon-bubble" style="width: 28px; height: 28px; font-size: 0.75rem;">
                <i class="fa-solid fa-lock"></i>
              </div>
              <div>
                <h3>Nueva contraseña</h3>
                <p>Crea una contraseña segura</p>
              </div>
            </div>
            <div class="input-wrapper">
              <input type="password" class="input-control" name="nueva_clave" placeholder="Ej: t$9PadcT" required>
              <i class="fa-regular fa-eye input-icon"></i>
            </div>
          </div>

          <!-- Campo 3: Confirmar Contraseña -->
          <div class="form-group">
            <div class="form-label">
              <div class="icon-bubble" style="width: 28px; height: 28px; font-size: 0.75rem;">
                <i class="fa-solid fa-lock"></i>
              </div>
              <div>
                <h3>Confirmar contraseña</h3>
                <p>Vuelve a ingresar tu nueva contraseña</p>
              </div>
            </div>
            <div class="input-wrapper">
              <input type="password" class="input-control" name="nueva" placeholder="Ej: t$9PadcT" required>
              <i class="fa-regular fa-eye input-icon"></i>
            </div>
          </div>

          <!-- Botones -->
          <div class="form-actions">
            <a href="index.php" class="btn btn-secondary">
              <i class="fa-solid fa-arrow-left"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fa-regular fa-floppy-disk"></i> Guardar contraseña
            </button>
          </div>

          <!-- Nota Informativa -->
          <div class="info-card">
            <div class="info-content">
              <div class="icon-bubble" style="background-color: var(--primary-brown);">
                <i class="fa-solid fa-info"></i>
              </div>
              <div class="info-text">
                <h4>Importante</h4>
                <p>Una vez guardes tu nueva contraseña, podrás iniciar sesión con normalidad.</p>
              </div>
            </div>
            <div class="info-illustration">
              <i class="fa-solid fa-envelope-open-text"></i>
            </div>
          </div>

        </form>
      </main>

    </div>
  </div>

  <!-- Script para alternar visibilidad de las contraseñas -->
  <script>
    document.querySelectorAll('.input-wrapper .fa-eye, .input-wrapper .fa-eye-slash').forEach(icon => {
      icon.addEventListener('click', function () {
        const input = this.previousElementSibling;
        if (input.type === 'password') {
          input.type = 'text';
          this.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          input.type = 'password';
          this.classList.replace('fa-eye-slash', 'fa-eye');
        }
      });
    });
  </script>
</body>
</html>