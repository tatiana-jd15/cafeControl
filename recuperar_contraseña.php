<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Contraseña | Café Control</title>
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Tipografía Google Fonts (Plus Jakarta Sans) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
  </style>
</head>
<body class="bg-[#F4EBE1] text-gray-800 min-h-screen flex items-center justify-center p-4 md:p-8">

  <!-- Tarjeta Principal -->
  <div class="w-full max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row border border-amber-900/10">
    
    <!-- Panel Izquierdo: Branding & Visuals -->
    <div class="w-full md:w-5/12 bg-cover bg-center relative flex flex-col justify-between p-8 md:p-10 text-white min-h-[360px] md:min-h-[620px]" 
         style="background-image: url('images/cafe.jpg'); background-size: cover; background-position: center;">
      
      <!-- Superposición de Degradado Oscuro Elegante -->
      <div class="absolute inset-0 bg-gradient-to-t from-[#23120B] via-[#23120B]/80 to-[#23120B]/40"></div>

      <!-- Header del Panel: Logo Local + Nombre -->
      <div class="relative z-10 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 p-2 flex items-center justify-center shadow-lg overflow-hidden flex-shrink-0">
          <img src="images/logo.jpeg" alt="Logo Café Control" class="w-full h-full object-cover rounded-xl" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1559056199-641a0ac8b55e?q=80&w=200&auto=format&fit=crop';">
        </div>
        <div>
          <h1 class="font-bold text-xl tracking-wide text-white leading-tight">Café Control</h1>
          <p class="text-sm text-amber-200/90 font-medium">Sistema de Gestión</p>
        </div>
      </div>

      <!-- Contenido Inferior del Banner -->
      <div class="relative z-10 mt-auto pt-8 text-center md:text-left flex flex-col items-center md:items-start">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/20 border border-amber-400/30 text-amber-300 text-sm font-semibold mb-4 backdrop-blur-md">
          <i class="fa-solid fa-shield-halved text-xs"></i> Recuperación Segura
        </div>

        <div class="w-28 h-28 mb-5 rounded-2xl border-2 border-amber-400/40 p-1.5 shadow-2xl bg-white/10 backdrop-blur-sm hidden md:block">
          <img src="images/logo.jpeg" alt="Café Control" class="w-full h-full object-cover rounded-xl" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1559056199-641a0ac8b55e?q=80&w=200&auto=format&fit=crop';">
        </div>

        <h2 class="text-3xl font-extrabold text-white leading-tight mb-3">
          ¿Olvidaste tu contraseña?
        </h2>
        <p class="text-sm md:text-base text-amber-100/90 leading-relaxed max-w-sm">
          No te preocupes. Te ayudaremos a restablecer el acceso a tu cuenta de forma rápida y segura.
        </p>
      </div>
    </div>

    <!-- Panel Derecho: Formulario Moderno -->
    <div class="w-full md:w-7/12 p-8 md:p-12 flex flex-col justify-between bg-white">
      
      <!-- Navegación Superior -->
      <div class="flex justify-end mb-6">
        <a href="iniciar_sesion.php" class="text-sm font-bold text-amber-900 hover:text-amber-700 flex items-center gap-2 transition group py-1.5 px-3.5 rounded-xl hover:bg-amber-50">
          <i class="fa-solid fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
          Volver al inicio de sesión
        </a>
      </div>

      <!-- Cuerpo del Formulario -->
      <div class="max-w-md mx-auto w-full my-auto">
        
        <div class="mb-8 text-center md:text-left">
          <h2 class="text-3xl font-bold text-[#23120B]">Recuperar acceso</h2>
          <p class="text-sm text-gray-600 mt-2 leading-relaxed">
            Ingresa tu usuario o correo electrónico registrado para recibir las instrucciones de restauración.
          </p>
        </div>

        <!-- APUNTA A enviar.php -->
        <form action="enviar.php" method="POST" class="space-y-5">
          
          <!-- Campo Usuario -->
          <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
              Nombre de Usuario
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
                <i class="fa-regular fa-user text-base"></i>
              </span>
              <input type="text" name="usuario" placeholder="Ej. juanperez"
                     class="w-full pl-11 pr-4 py-3.5 bg-gray-50/80 border border-gray-300 rounded-xl text-base focus:outline-none focus:bg-white focus:border-amber-800 focus:ring-2 focus:ring-amber-800/10 transition placeholder:text-gray-400 font-medium">
            </div>
          </div>

          <!-- Separador 'o' -->
          <div class="relative flex items-center justify-center py-1">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="flex-shrink mx-4 text-xs font-bold text-gray-400 uppercase tracking-wider bg-white px-2">o</span>
            <div class="flex-grow border-t border-gray-200"></div>
          </div>

          <!-- Campo Correo Electrónico -->
          <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
              Correo Electrónico <span class="text-amber-700">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
                <i class="fa-regular fa-envelope text-base"></i>
              </span>
              <input type="email" name="correo" required placeholder="tu@correo.com"
                     class="w-full pl-11 pr-4 py-3.5 bg-gray-50/80 border border-gray-300 rounded-xl text-base focus:outline-none focus:bg-white focus:border-amber-800 focus:ring-2 focus:ring-amber-800/10 transition placeholder:text-gray-400 font-medium">
            </div>
          </div>

          <!-- Botones de Acción -->
          <div class="flex gap-4 pt-4">
            <a href="iniciar_sesion.php" class="w-1/3 py-3.5 px-4 border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-100 text-center transition flex items-center justify-center">
              Cancelar
            </a>
            <button type="submit" class="w-2/3 py-3.5 px-4 bg-[#3D2314] hover:bg-[#2A170C] text-white rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition flex items-center justify-center gap-2.5">
              <i class="fa-solid fa-paper-plane text-xs"></i> Enviar instrucciones
            </button>
          </div>

        </form>

        <!-- Bloque Informativo -->
        <div class="mt-8 p-4 rounded-2xl bg-amber-50/80 border border-amber-200 flex gap-4 items-start shadow-sm">
          <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-900 flex items-center justify-center text-sm flex-shrink-0 mt-0.5 font-bold">
            <i class="fa-solid fa-circle-info"></i>
          </div>
          <div class="text-xs md:text-sm text-amber-950 leading-relaxed">
            <strong class="font-bold block text-sm mb-0.5">Información importante:</strong>
            Revisa tu bandeja de entrada o correo no deseado. El enlace enviado vencerá en <strong>30 minutos</strong>.
          </div>
        </div>

      </div>

      <!-- Pie de página --> 
      <div class="mt-8 pt-4 border-t border-gray-100 text-center text-xs text-gray-500 font-medium">
        &copy; <?php echo date('Y'); ?> Café Control. Todos los derechos reservados.
      </div>

    </div>

  </div>

</body>
</html>