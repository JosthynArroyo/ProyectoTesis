{{-- resources/views/emails/contacto_recibido.blade.php --}}
@php
  // Colores de marca inspirados en el logo
  $brandDark  = '#14532d';   // verde oscuro
  $brandMain  = '#2e7d32';   // verde principal
  $brandLite  = '#eaf5ee';   // fondo suave
  $border     = '#d7e6db';
  $text       = '#0f172a';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nuevo contacto</title>
</head>
<body style="margin:0;padding:0;background:{{ $brandLite }};font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial,'Noto Sans','Apple Color Emoji','Segoe UI Emoji';color:{{ $text }};">

  <!-- Wrapper -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $brandLite }};padding:24px 0;">
    <tr>
      <td align="center">
        <!-- Card -->
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:92%;background:#ffffff;border:1px solid {{ $border }};border-radius:12px;overflow:hidden;">
          <!-- Header -->
          <tr>
            <td style="padding:18px 20px;background:linear-gradient(0deg, #ffffff 0%, #ffffff 60%, {{ $brandLite }} 100%);border-bottom:1px solid {{ $border }};">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="width:56px;" valign="middle">
                    <img src="{{ asset('img/logo-welcomeBlanco.jpg') }}" alt="Don Bosco" width="48" height="48" style="display:block;border:0;outline:none;">
                  </td>
                  <td valign="middle" align="left">
                    <div style="font-size:18px;line-height:1.2;font-weight:800;color:{{ $brandDark }};">Don Bosco</div>
                    <div style="font-size:12px;color:{{ $brandMain }};">Nuevo mensaje de contacto</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Title -->
          <tr>
            <td style="padding:18px 20px 6px;">
              <h1 style="margin:0;font-size:18px;line-height:1.35;color:{{ $brandDark }};">Detalles del mensaje</h1>
            </td>
          </tr>

          <!-- Summary chips -->
          <tr>
            <td style="padding:0 20px 10px;">
              <div style="display:inline-block;margin:6px 6px 0 0;padding:6px 10px;border:1px solid {{ $border }};border-radius:999px;font-size:12px;">
                <strong>Motivo:</strong>
                <span>{{ $datos['motivo'] ?? 'No indicado' }}</span>
              </div>
              <div style="display:inline-block;margin:6px 6px 0 0;padding:6px 10px;border:1px solid {{ $border }};border-radius:999px;font-size:12px;">
                <strong>Consentimiento:</strong>
                <span style="color:{{ ($datos['consentimiento'] ?? 'no') === 'si' ? $brandMain : '#9ca3af' }}">
                  {{ ($datos['consentimiento'] ?? 'no') === 'si' ? 'Sí' : 'No' }}
                </span>
              </div>
            </td>
          </tr>

          <!-- Info table -->
          <tr>
            <td style="padding:8px 20px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }};border-radius:10px;">
                @php
                  $rows = [
                    'Nombre'   => $datos['nombre'] ?? '—',
                    'Email'    => $datos['email'] ?? '—',
                    'Teléfono' => $datos['telefono'] ?? 'No indicado',
                    'Asunto'   => $datos['asunto'] ?? 'Sin asunto',
                  ];
                @endphp
                @foreach($rows as $k => $v)
                  <tr>
                    <td style="width:160px;padding:10px 12px;border-bottom:1px solid {{ $border }};background:{{ $brandLite }};font-weight:700;font-size:13px;">{{ $k }}</td>
                    <td style="padding:10px 12px;border-bottom:1px solid {{ $border }};font-size:13px;">
                      @if($k === 'Email' && !empty($datos['email']))
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

          <!-- Message -->
          <tr>
            <td style="padding:16px 20px 8px;">
              <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:{{ $brandDark }};">Mensaje</div>
              <div style="font-size:14px;line-height:1.55;white-space:pre-wrap;border:1px solid {{ $border }};border-radius:10px;padding:12px;background:#fff;">
                {{ $datos['mensaje'] }}
              </div>
            </td>
          </tr>

          <!-- Actions -->
          <tr>
            <td style="padding:10px 20px 22px;">
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td>
                    <a href="mailto:{{ $datos['email'] ?? '' }}"
                       style="display:inline-block;background:{{ $brandMain }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:10px;font-size:14px;font-weight:700;">
                      Responder
                    </a>
                  </td>
                  @if(($datos['consentimiento'] ?? 'no') === 'si')
                  <td style="width:8px;"></td>
                  <td>
                    <span style="display:inline-block;padding:10px 14px;border:1px solid {{ $border }};border-radius:10px;font-size:12px;color:{{ $brandDark }};">
                      Autorizó creación de cuenta
                    </span>
                  </td>
                  @endif
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:12px 20px;background:#ffffff;border-top:1px solid {{ $border }};border-bottom-left-radius:12px;border-bottom-right-radius:12px;">
              <div style="font-size:12px;color:#64748b;">
                Este correo fue generado por el formulario de contacto del sitio.
              </div>
            </td>
          </tr>

        </table>
        <!-- /Card -->
      </td>
    </tr>
  </table>
  <!-- /Wrapper -->

</body>
</html>
