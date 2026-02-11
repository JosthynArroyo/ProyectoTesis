<!DOCTYPE html>
<html lang=\"es\">
<head>
  <meta charset=\"UTF-8\">
  <title>{{ $asunto ?? 'Estado de cita' }}</title>
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
</head>
@php
  $rol = $rolReceptor ?? 'paciente';
  $mensajeTxt = $mensaje ?? 'Tu cita fue actualizada.';
  $eventoTxt = $textoEvento ?? ucfirst($evento ?? 'actualizada');
  $nombre = $nombreReceptor ?? ($rol === 'doctor' ? 'Doctor/a' : 'Paciente');
  $citaObj = $cita ?? null;
  $fechaTxt = $fechaCita ?? ($citaObj?->fecha ? $citaObj->fecha->format('d/m/Y') : '');
  $horaTxt = $horaCita ?? ($citaObj?->hora ? \Carbon\Carbon::parse($citaObj->hora)->format('H:i') : '');
  $estadoRaw = strtolower($citaObj?->estado ?? ($evento ?? 'actualizada'));
  $isLab = mb_strtolower($citaObj?->especialidad?->nombre ?? '') === 'laboratorio clínico';
  $labelProfesional = $isLab ? 'Laboratorio' : 'Doctor';
  $badgePaleta = [
    'agendada'   => ['#e0f2fe', '#0369a1'],
    'confirmada' => ['#dcfce7', '#15803d'],
    'pendiente'  => ['#fef3c7', '#b45309'],
    'reagendada' => ['#e0e7ff', '#4338ca'],
    'cancelada'  => ['#fee2e2', '#b91c1c'],
    'realizada'  => ['#dcfce7', '#15803d'],
    'no_se_presento' => ['#ffedd5', '#c2410c'],
    'actualizada'=> ['#ccfbf1', '#0f766e'],
  ];
  $estadoLabels = [
    'pendiente' => 'Pendiente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'realizada' => 'Realizada',
    'no_se_presento' => 'No se presentó',
  ];
  [$badgeBg, $badgeColor] = $badgePaleta[$estadoRaw] ?? ['#ccfbf1', '#0f766e'];
  $estadoLabel = $estadoLabels[$estadoRaw] ?? ucfirst($estadoRaw);
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
              <div style=\"font-size:18px; font-weight:600; color:#0f172a; margin-bottom:6px;\">Estado de tu cita</div>
              <div style=\"font-size:14px; color:#475569; line-height:1.6;\">
                {{ $mensajeTxt }}
              </div>
              <div style=\"margin-top:12px;\">
                <span style=\"display:inline-block; padding:6px 12px; border-radius:999px; background:{{ $badgeBg }}; color:{{ $badgeColor }}; font-weight:600; font-size:13px; text-transform:capitalize;\">
                  {{ $eventoTxt }}
                </span>
              </div>
            </td>
          </tr>
          <tr>
            <td style=\"padding:6px 26px 22px;\">
              <div style=\"margin-top:14px; padding:18px 16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc;\">
                <div style=\"font-size:15px; font-weight:600; margin-bottom:10px;\">Hola {{ $nombre }}, estos son los detalles:</div>
                <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"font-size:14px; color:#0f172a;\">
                  <tr>
                    <td style=\"padding:6px 0; width:36%; color:#64748b;\">Paciente</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $citaObj?->paciente?->name ?? 'Paciente' }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">{{ $labelProfesional }}</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $citaObj?->doctor?->name ?? $labelProfesional }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Especialidad</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $citaObj?->especialidad?->nombre ?? '-' }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Fecha</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $fechaTxt }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Hora</td>
                    <td style=\"padding:6px 0; font-weight:600;\">{{ $horaTxt }}</td>
                  </tr>
                  <tr>
                    <td style=\"padding:6px 0; color:#64748b;\">Estado actual</td>
                    <td style=\"padding:6px 0;\">
                      <span style=\"display:inline-block; padding:6px 12px; border-radius:999px; background:{{ $badgeBg }}; color:{{ $badgeColor }}; font-weight:600; font-size:13px; text-transform:capitalize;\">
                        {{ $estadoLabel }}
                      </span>
                    </td>
                  </tr>
                </table>
              </div>
              <div style=\"margin-top:18px;\">
                @php($ctaStyle = 'display:inline-block; background:#0f766e; color:#ffffff; padding:12px 18px; border-radius:10px; font-weight:700; text-decoration:none; font-size:14px;')
                @if ($rol === 'doctor')
                  <a href=\"{{ route('doctor.citas') }}\" style=\"{{ $ctaStyle }}\">Ir a mi agenda</a>
                @else
                  <a href=\"{{ route('paciente.citas') }}\" style=\"{{ $ctaStyle }}\">Ver mis citas</a>
                @endif
              </div>
              <p style=\"margin-top:16px; font-size:13px; color:#94a3b8; line-height:1.5;\">
                Si no solicitaste este cambio, por favor comunícate con la clínica.
              </p>
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
