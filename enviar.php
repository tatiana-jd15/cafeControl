<?php
$mensajeResultado = null;
$tipoResultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturamos y sanitizamos los datos del formulario
    $correoIngresado = filter_var($_POST['correo'] ?? '', FILTER_SANITIZE_EMAIL);
    $user            = htmlspecialchars($_POST['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

    if (filter_var($correoIngresado, FILTER_VALIDATE_EMAIL)) {
        // Configuración del correo
        $para   = $correoIngresado;
        $titulo = 'Instrucciones para restablecer contraseña';

        // Generación dinámica de la URL con la ruta exacta 'nueva_contraseña.php' en formato URL Encoded (%C3%B1)
        $protocolo  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host       = $_SERVER['HTTP_HOST'];
        
        $urlDestino  = "{$protocolo}://{$host}/cafeControl/nueva_contrase%C3%B1a.php?correo=" . urlencode($correoIngresado);
        $urlEscapada = htmlspecialchars($urlDestino, ENT_QUOTES, 'UTF-8');

        // Plantilla HTML del correo
        $mensaje = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='margin:0; padding:40px 0; background-color:#f8f6f0; font-family:Arial, Helvetica, sans-serif; color:#333333; -webkit-font-smoothing:antialiased;'>

            <!-- Contenedor Principal -->
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color:#f8f6f0;'>
                <tr>
                    <td align='center'>

                        <table width='600' cellpadding='0' cellspacing='0' border='0' style='width:100%; max-width:600px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05);'>
                            
                            <!-- LOGO Y TÍTULO -->
                            <tr>
                                <td align='center' style='padding:40px 30px 25px;'>
                                    <table cellpadding='0' cellspacing='0' border='0'>
                                        <tr>
                                            <td align='center' style='padding:30px 25px 20px; background-color:#ffffff;' valign='middle'>
                                                <div style='font-size:32px; font-weight:bold; color:#2c2c2c; line-height:1; letter-spacing:-0.5px;'>
                                                    Café Control
                                                </div>
                                                <div style='font-size:15px; color:#4a6838; font-weight:bold; margin-top:4px;'>
                                                    Gestiona, controla y crece
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- CABECERA DESTACADA -->
                            <tr>
                                <td style='padding:0 35px;'>
                                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color:#f7f0e6; border-radius:20px; overflow:hidden;'>
                                        <tr>
                                            <td width='100%' valign='middle' style='padding:30px; text-align:center;'>
                                                <div style='display:inline-block; width:52px; height:52px; background-color:#d7e2ca; border-radius:50%; text-align:center; line-height:52px;'>
                                                    <span style='font-size:24px;'>🔒</span>
                                                </div>
                                                <div style='font-size:22px; line-height:1.2; font-weight:bold; color:#2d2d2d; margin-top:15px; margin-bottom:12px;'>
                                                    Restablecer contraseña
                                                </div>
                                                <div style='width:45px; height:3px; background-color:#637e48; margin:0 auto 15px auto;'></div>
                                                <p style='margin:0; font-size:13px; line-height:1.5; color:#333333;'>
                                                    Solicitamos restablecer la contraseña de tu cuenta en <strong style='color:#3a5427;'>Café Control</strong>.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- CUERPO DEL MENSAJE -->
                            <tr>
                                <td style='padding:35px 35px 25px;'>
                                    
                                    <h2 style='margin:0 0 15px; font-size:22px; color:#2a401c; font-weight:bold;'>
                                        ¡Hola {$user}!
                                    </h2>

                                    <p style='margin:0 0 15px; font-size:15px; line-height:1.6; color:#333333;'>
                                        Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong style='color:#3a5427;'>Café Control</strong>.
                                    </p>

                                    <p style='margin:0 0 25px; font-size:15px; line-height:1.6; color:#333333;'>
                                        Para crear una nueva contraseña, haz clic en el siguiente botón:
                                    </p>

                                    <!-- BOTÓN VERDE -->
                                    <table cellpadding='0' cellspacing='0' border='0' style='margin-bottom:35px;'>
                                        <tr>
                                            <td align='center' style='background-color:#384e2a; border-radius:12px;'>
                                                <a href='{$urlEscapada}' target='_blank' style='display:inline-block; padding:16px 32px; color:#ffffff; text-decoration:none; font-size:16px; font-weight:bold; border-radius:12px;'>
                                                    🔒 &nbsp; Restablecer contraseña
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- DIVISOR CON ÍCONO LINK -->
                                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:30px;'>
                                        <tr>
                                            <td valign='middle'><div style='height:1px; background-color:#e2ded4;'></div></td>
                                            <td width='40' align='center' valign='middle'>
                                                <div style='width:32px; height:32px; background-color:#e8f0df; border-radius:50%; line-height:32px; font-size:16px; text-align:center;'>
                                                    🔗
                                                </div>
                                            </td>
                                            <td valign='middle'><div style='height:1px; background-color:#e2ded4;'></div></td>
                                        </tr>
                                    </table>

                                    <p style='margin:0 0 15px; font-size:14px; color:#333333; line-height:1.5;'>
                                        Si no puedes hacer clic en el botón, copia y pega este enlace en tu navegador:
                                    </p>

                                    <!-- BLOQUE ENLACE -->
                                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color:#f5ede1; border-radius:14px; margin-bottom:20px;'>
                                        <tr>
                                            <td width='55' align='center' valign='middle' style='padding:16px 0 16px 16px;'>
                                                <div style='width:38px; height:38px; background-color:#5c783c; border-radius:50%; text-align:center; line-height:38px;'>
                                                    <span style='color:#ffffff; font-size:18px;'>🌐</span>
                                                </div>
                                            </td>
                                            <td valign='middle' style='padding:16px 16px 16px 10px; font-size:13px; line-height:1.4; color:#333333; word-break:break-all;'>
                                                <a href='{$urlEscapada}' target='_blank' style='color:#333333; text-decoration:none; font-weight:bold;'>
                                                    {$urlEscapada}
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- BLOQUE AVISO DE SEGURIDAD -->
                                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color:#f2f5eb; border-radius:14px;'>
                                        <tr>
                                            <td width='55' align='center' valign='middle' style='padding:16px 0 16px 16px;'>
                                                <div style='width:38px; height:38px; background-color:#4a6838; border-radius:50%; text-align:center; line-height:38px;'>
                                                    <span style='color:#ffffff; font-size:18px;'>🛡️</span>
                                                </div>
                                            </td>
                                            <td valign='middle' style='padding:16px 20px 16px 10px; font-size:13px; line-height:1.5; color:#2d2d2d;'>
                                                <strong>Si no solicitaste este cambio, puedes ignorar este mensaje.</strong><br>
                                                <span style='color:#555555;'>Tu cuenta seguirá segura.</span>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td align='center' style='background-color:#314524; padding:30px 20px;'>
                                    <div style='color:#ffffff; font-size:15px; font-weight:bold;'>
                                        Gracias por confiar en Café Control
                                    </div>
                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>

        </body>
        </html>
        ";

        // Cabeceras de correo en formato HTML UTF-8
        $cabeceras  = "MIME-Version: 1.0" . "\r\n";
        $cabeceras .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $cabeceras .= "From: soporte@cafecontrol.com" . "\r\n";
        $cabeceras .= "Reply-To: soporte@cafecontrol.com" . "\r\n";
        $cabeceras .= "X-Mailer: PHP/" . phpversion();

        // Enviar el correo
        if (mail($para, $titulo, $mensaje, $cabeceras)) {
            $mensajeResultado = "El correo con las instrucciones se envió correctamente a " . htmlspecialchars($para, ENT_QUOTES, 'UTF-8');
            $tipoResultado = "success";
        } else {
            $mensajeResultado = "No se pudo enviar el correo. Por favor, verifica la configuración de correo de tu servidor.";
            $tipoResultado = "error";
        }
    } else {
        $mensajeResultado = "Por favor, ingresa una dirección de correo electrónico válida.";
        $tipoResultado = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Control</title>
    <!-- Carga de SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #382519;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>

<?php if ($mensajeResultado): ?>
<script>
    Swal.fire({
        title: '<?php echo $tipoResultado === "success" ? "¡Correo enviado!" : "Error de envío"; ?>',
        text: '<?php echo addslashes($mensajeResultado); ?>',
        icon: '<?php echo $tipoResultado; ?>',
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#384e2a',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php';
        }
    });
</script>
<?php endif; ?>

</body>
</html>