<!DOCTYPE html>
<html lang=\"es\">
<head>
  <meta charset=\"UTF-8\">
  <title>Resultados de laboratorio</title>
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
</head>
@php
  $paciente = $paciente ?? null;
  $nombre = $paciente?->name ?? 'Paciente';
  $citaObj = $cita ?? null;
  $ordenObj = $orden ?? null;
  $fechaTxt = $citaObj?->fecha ? $citaObj->fecha->format('d/m/Y') : '';
  $horaTxt = $citaObj?->hora ? \Carbon\Carbon::parse($citaObj->hora)->format('H:i') : '';
@endphp
<body style=\"margin:0; padding:0; background:#f8fafc; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;\">
  <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background:#f8fafc; padding:24px 0;\">
    <tr>
      <td align=\"center\">
        <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" width=\"620\" style=\"width:620px; max-width:92%; background:#ffffff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 10px 30px rgba(15,23,42,0.08);\">
          <tr>
            <td style=\"background:#0f766e; padding:22px 26px; text-align:center;\">
              @php($logoSrc = isset($message) ? $message->embed(public_path('img/logo-welcomeBlanco.jpg')) : asset('img/logo-welcomeBlanco.jpg'))
              <img src=\"{{ $logoSrc }}\" alt=\"Clínica Don Bosco\" width=\"180\" style=\"display:inline-block;\">
            </td>
          </tr>
          <tr>
            <td style=\"padding:26px 26px 10px;\">
              <div style=\"font-size:18px; font-weight:600; color:#0f172a; margin-bottom:6px;\">
                Resultados de laboratorio disponibles
              </div>
              <div style=\"font-size:14px; color:#475569; line-height:1.6;\">
                Hola {{ $nombre }}, ya puedes revisar tus resultados. Adjuntamos el PDF en este correo.
              </div>
            </td>
          </tr>
          <tr>
            <td style=\"padding:6px 26px 22px;\">
              <div style=\"margin-top:14px; padding:18px 16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc;\">
                <div style=\"font-size:15px; font-weight:600; margin-bottom:10px;\">Detalles de la orden</div>
                <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"font-size:14px; color:#0f172a;\">
                  <tr>
                    <td style=\"padding:6px 0; width:36%; color:#64748b;\">Examen</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $ordenObj?->tipo_examen ?? 'N/D' }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Prioridad</td>
                    <td style=\"padding:6px 0; font-weight:600; text-transform:capitalize;\">{{ $ordenObj?->prioridad ?? 'normal' }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Fecha cita</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $fechaTxt }} {{ $horaTxt }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Especialidad</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $citaObj?->especialidad?->nombre ?? 'Laboratorio' }}</td>
                  </tr>
                </table>
              </div>
              @if(!empty($ordenObj?->resultado_resumen))
                <div style=\"margin-top:16px; padding:14px 16px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0;\">
                  <div style=\"font-size:14px; font-weight:600; margin-bottom:6px;\">Resumen</div>
                  <div style=\"font-size:13px; color:#475569; line-height:1.6;\">
                    {{ $ordenObj->resultado_resumen }}
                  </div>
                </div>
              @endif
              <div style=\"margin-top:18px;\">
                @php($ctaStyle = 'display:inline-block; background:#0f766e; color:#ffffff; padding:12px 18px; border-radius:10px; font-weight:700; text-decoration:none; font-size:14px;')
                <a href=\"{{ route('paciente.laboratorio.index') }}\" style=\"{{ $ctaStyle }}\">Ver resultados en mi panel</a>
                <a href=\"{{ route('paciente.crear-cita') }}\" style=\"{{ $ctaStyle }} margin-left:10px;\">Agendar cita médica</a>
              </div>
            </td>
          </tr>
          <tr>
            <td style=\"padding:14px 26px 18px; background:#0f172a; color:#cbd5e1; font-size:12px; text-align:center;\">
              © {{ date('Y') }} Clínica Don Bosco · Este es un correo automático, no responder.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
