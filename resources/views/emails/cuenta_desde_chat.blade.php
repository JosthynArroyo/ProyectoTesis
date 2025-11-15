{{-- resources/views/emails/cuenta_desde_chat.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cuenta creada</title>
</head>
<body style="font-family:system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f9fafb; padding:24px;">
  <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;padding:24px;">
    <h2 style="margin-top:0;margin-bottom:16px;color:#0f172a;">Bienvenido(a) a la Clínica Don Bosco</h2>
    <p style="margin-bottom:12px;">Hola {{ $user->name }},</p>
    <p style="margin-bottom:12px;">
      Hemos creado una cuenta para ti en nuestro sistema de gestión de citas médicas,
      a partir de los datos que ingresaste en el asistente de la web.
    </p>

    <p style="margin-bottom:12px;">Estos son tus datos de acceso:</p>

    <ul style="margin:0 0 16px 18px;padding:0;">
      <li><strong>Correo:</strong> {{ $user->email }}</li>
      <li><strong>Contraseña temporal:</strong> {{ $passwordPlano }}</li>
    </ul>

    <p style="margin-bottom:12px;">
      Te recomendamos iniciar sesión lo antes posible y cambiar tu contraseña desde la sección
      de perfil o configuración de cuenta.
    </p>

    <p style="margin-bottom:12px;">
      Si no reconoces esta acción o crees que se trata de un error, por favor contáctanos.
    </p>

    <p style="margin-top:20px;margin-bottom:0;">Atentamente,<br>Clínica Don Bosco</p>
  </div>
</body>
</html>
