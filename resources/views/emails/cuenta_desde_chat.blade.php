<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cuenta creada</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:92%; background:#ffffff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 10px 30px rgba(15,23,42,0.08);">
          <tr>
            <td style="background:#0f766e; padding:22px 26px; text-align:center;">
              @php($logoSrc = isset($message) ? $message->embed(public_path('img/logo-welcomeBlanco.jpg')) : asset('img/logo-welcomeBlanco.jpg'))
              <img src="{{ $logoSrc }}" alt="Clínica Don Bosco" width="180" style="display:inline-block;">
            </td>
          </tr>
          <tr>
            <td style="padding:26px;">
              <h2 style="margin:0 0 12px; font-size:20px;">Bienvenido(a) a la Clínica Don Bosco</h2>
              <p style="margin:0 0 12px;">Hola {{ $user->name }},</p>
              <p style="margin:0 0 12px; color:#475569; line-height:1.6;">
                Hemos creado una cuenta para ti en nuestro sistema de gestión de citas médicas,
                a partir de los datos que ingresaste en el asistente de la web.
              </p>

              <p style="margin:0 0 12px; font-weight:600;">Estos son tus datos de acceso:</p>
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                <tr>
                  <td style="padding:10px 12px; background:#f1f5f9; font-weight:600; width:35%;">Correo</td>
                  <td style="padding:10px 12px;">{{ $user->email }}</td>
                </tr>
                <tr>
                  <td style="padding:10px 12px; background:#f1f5f9; font-weight:600;">Contraseña temporal</td>
                  <td style="padding:10px 12px;">{{ $passwordPlano }}</td>
                </tr>
              </table>

              <p style="margin:16px 0 12px; color:#475569; line-height:1.6;">
                Te recomendamos iniciar sesión lo antes posible y cambiar tu contraseña desde la sección
                de perfil o configuración de cuenta.
              </p>

              <p style="margin:0; color:#475569; line-height:1.6;">
                Si no reconoces esta acción o crees que se trata de un error, por favor contáctanos.
              </p>

              <p style="margin:20px 0 0;">Atentamente,<br>Clínica Don Bosco</p>
            </td>
          </tr>
          <tr>
            <td style="padding:12px; text-align:center; background:#f1f5f9; font-size:12px; color:#64748b;">
              © {{ date('Y') }} Clínica Don Bosco · Este es un correo automático, no responder.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>