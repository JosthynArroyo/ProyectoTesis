<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Receta médica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" width="620" style="width:620px; max-width:92%; background:#ffffff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 10px 30px rgba(15,23,42,0.08);">
          <tr>
            <td style="background:#0f766e; padding:22px 26px; text-align:left;">
              @php($logoSrc = isset($message) ? $message->embed(public_path('img/logopdf.jpg')) : asset('img/logopdf.jpg'))
              <img src="{{ $logoSrc }}" alt="Clínica Don Bosco" width="160" style="display:inline-block;">
            </td>
          </tr>
          <tr>
            <td style="padding:26px;">
              <h2 style="margin:0 0 8px; font-size:20px;">
                {{ $motivo === 'actualizacion' ? 'Actualización de receta médica' : 'Nueva receta médica' }}
              </h2>

              <p style="margin:0 0 12px;">Estimado/a {{ $cita->paciente->name ?? 'Paciente' }},</p>

              <p style="margin:0 0 12px; color:#475569; line-height:1.6;">
                Adjuntamos su {{ $motivo === 'actualizacion' ? 'receta actualizada' : 'nueva receta' }}
                correspondiente a la cita con el Dr(a).
                <strong>{{ $cita->doctor->name ?? '-' }}</strong>
                del día {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}
                a las {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}.
              </p>

              <p style="margin:0 0 12px; color:#475569; line-height:1.6;">
                Por favor, siga las indicaciones médicas y conserve este documento para futuras consultas.
              </p>

              <p style="margin:20px 0 0;"> Clínica Don Bosco</p>
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
