<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>{{ $asunto ?? 'Estado de Cita' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937}
    .card{max-width:640px;margin:22px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
    .header{padding:0;background:#111827;color:#fff;text-align:center}
    .header img{display:block;width:100%;max-width:640px;height:auto}
    .body{padding:24px}
    .k{font-weight:700;color:#374151}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px}
    .pill.reagendada{background:#eef2ff;color:#3730a3;border:1px solid #e0e7ff}
    .pill.cancelada{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .pill.aceptada{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
    .pill.agendada{background:#e0f2fe;color:#075985;border:1px solid #bae6fd}
    .btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:800;margin-top:14px}
    .muted{color:#6b7280}
    .footer{padding:12px;text-align:center;background:#f3f4f6;font-size:12px;color:#6b7280}
    ul{margin:8px 0 0 20px;padding:0}
    li{margin:4px 0}
  </style>
</head>
<body>
  <div class="card">
    <!-- Cabecera con imagen -->
    <div class="header">
      <img src="{{ asset('img/img.jpg') }}" alt="Clínica Don Bosco">
    </div>

    <div class="body">
      @php
        $nombreReceptor = $rolReceptor === 'doctor' ? ($cita->doctor->name ?? 'Doctor/a') : ($cita->paciente->name ?? 'Paciente');
        $esAutor = ($rolReceptor === $quien);

        $textoEvento = [
          'agendada'  => 'agendada',
          'reagendada'=> 'reagendada',
          'cancelada' => 'cancelada',
          'aceptada'  => 'aceptada',
        ][$evento] ?? 'actualizada';

        $mensaje = '';

        if ($evento === 'agendada') {
            if ($rolReceptor === 'paciente' && $esAutor)       $mensaje = 'Agendaste una cita.';
            elseif ($rolReceptor === 'doctor')                  $mensaje = 'Se registró una nueva cita en tu agenda.';
            else                                                $mensaje = 'Tu cita fue agendada.';
        } elseif ($evento === 'reagendada') {
            if ($rolReceptor === 'doctor') {
                $mensaje = $esAutor ? 'Reagendaste la cita.' : 'Se reagendó una cita en tu agenda.';
            } else {
                $mensaje = $esAutor ? 'Reagendaste la cita.' : 'Tu cita fue reagendada.';
            }
        } elseif ($evento === 'cancelada') {
            if ($rolReceptor === 'doctor') {
                $mensaje = $esAutor ? 'Cancelaste la cita.' : 'Se canceló una cita en tu agenda.';
            } else {
                $mensaje = $esAutor ? 'Cancelaste la cita.' : 'Tu cita fue cancelada.';
            }
        } elseif ($evento === 'aceptada') {
            if ($rolReceptor === 'doctor') {
                $mensaje = $esAutor ? 'Aceptaste la cita.' : 'Se aceptó una cita en tu agenda.';
            } else {
                $mensaje = $esAutor ? 'Aceptaste la cita.' : 'Tu cita fue aceptada.';
            }
        } else {
            $mensaje = $esAutor ? 'Actualizaste la cita.' : ($rolReceptor === 'doctor' ? 'Se actualizó una cita en tu agenda.' : 'Tu cita fue actualizada.');
        }

        $pillClass = $evento;
      @endphp

      <p><strong>Hola {{ $nombreReceptor }}</strong>,</p>
      <p>
        {{ $mensaje }}
        <span class="pill {{ $pillClass }}">{{ ucfirst($textoEvento) }}</span>
      </p>

      <div style="margin-top:8px">
        <div><span class="k">Paciente:</span> {{ $cita->paciente->name ?? 'Paciente' }}</div>
        <div><span class="k">Doctor:</span> {{ $cita->doctor->name ?? 'Doctor' }}</div>
        <div><span class="k">Especialidad:</span> {{ $cita->especialidad->nombre ?? '—' }}</div>
        <div><span class="k">Fecha:</span> {{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }}</div>
        <div><span class="k">Hora:</span> {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</div>
        <div><span class="k">Estado actual:</span> {{ ucfirst($cita->estado) }}</div>
      </div>

      @if ($rolReceptor === 'doctor')
        <a href="{{ route('doctor.citas') }}" class="btn">Ir a mi agenda</a>
      @else
        <a href="{{ route('paciente.citas') }}" class="btn">Ver mis citas</a>
      @endif

      <p class="muted" style="margin-top:10px;">Si no solicitaste este cambio, por favor comunícate con la clínica.</p>
    </div>

    <div class="footer">
      © {{ date('Y') }} Clínica Don Bosco · Este es un correo automático, no responder.
    </div>
  </div>
</body>
</html>
