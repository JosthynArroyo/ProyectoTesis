@php
  $brandDark = '#0f172a';
  $brandMain = '#0f766e';
  $brandLite = '#f8fafc';
  $border = '#e2e8f0';
  $text = '#0f172a';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nuevo contacto</title>
</head>
<body style="margin:0;padding:0;background:{{ $brandLite }};font-family:'Segoe UI', Arial, sans-serif;color:{{ $text }};">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $brandLite }};padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:92%;background:#ffffff;border:1px solid {{ $border }};border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
          <tr>
            <td style="padding:18px 20px;background:#0f766e;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="width:56px;" valign="middle">
                    <img src="{{ asset('img/logo-welcomeBlanco.jpg') }}" alt="Don Bosco" width="48" height="48" style="display:block;border:0;outline:none;">
                  </td>
                  <td valign="middle" align="left">
                    <div style="font-size:18px;line-height:1.2;font-weight:800;color:#ffffff;">Don Bosco</div>
                    <div style="font-size:12px;color:#ccfbf1;">Nuevo mensaje de contacto</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 20px 6px;">
              <h1 style="margin:0;font-size:18px;line-height:1.35;color:{{ $brandDark }};">Detalles del mensaje</h1>
            </td>
          </tr>

          <tr>
            <td style="padding:8px 20px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }};border-radius:12px;overflow:hidden;">
                @php
                  $rows = [
                    'Nombre' => $datos['nombre'] ?? '-',
                    'Correo electrónico' => $datos['email'] ?? '-',
                    'Teléfono' => $datos['telefono'] ?? 'No indicado',
                    'Asunto' => $datos['asunto'] ?? 'Sin asunto',
                  ];
                @endphp
                @foreach($rows as $k => $v)
                  <tr>
                    <td style="width:160px;padding:10px 12px;border-bottom:1px solid {{ $border }};background:#f1f5f9;font-weight:700;font-size:13px;">{{ $k }}</td>
                    <td style="padding:10px 12px;border-bottom:1px solid {{ $border }};font-size:13px;">
                      @if($k === 'Correo electrónico' && !empty($datos['email']))
                        <a href="mailto:{{ $datos['email'] }}" style="color:{{ $brandMain }};text-decoration:none;">{{ $v }}</a>
                      @else
                        {{ $v }}
                      @endif
                    </td>
                  </tr>
                @endforeach
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 20px 8px;">
              <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:{{ $brandDark }};">Mensaje</div>
              <div style="font-size:14px;line-height:1.55;white-space:pre-wrap;border:1px solid {{ $border }};border-radius:12px;padding:12px;background:#ffffff;">
                {{ $datos['mensaje'] }}
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:10px 20px 22px;">
              <a href="mailto:{{ $datos['email'] ?? '' }}"
                 style="display:inline-block;background:{{ $brandMain }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:10px;font-size:14px;font-weight:700;">
                Responder
              </a>
            </td>
          </tr>

          <tr>
            <td style="padding:12px 20px;background:#f1f5f9;border-top:1px solid {{ $border }};">
              <div style="font-size:12px;color:#64748b;">
                Este correo fue generado por el formulario de contacto del sitio.
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
