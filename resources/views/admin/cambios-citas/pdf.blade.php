<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cambios de citas</title>
  @php($cssPath = resource_path('css/admin/cambios-citas-pdf.css'))
  <style>{!! file_exists($cssPath) ? file_get_contents($cssPath) : '' !!}</style>
</head>
<body>
  @php
    $eventLabels = [
      'agendada' => 'Agendada',
      'confirmada' => 'Confirmada',
      'cancelada' => 'Cancelada',
      'realizada' => 'Realizada',
      'no_se_presento' => 'No se presento',
      'reprogramada' => 'Reprogramada',
      'prioridad_manual' => 'Prioridad manual',
    ];
  @endphp

  <h2>Auditoria de cambios de citas</h2>
  <p>
    @if($tipo) tipo={{ $tipo }}; @endif
    @if($estado) estado={{ $estado }}; @endif
    @if($doctorId) doctor_id={{ $doctorId }}; @endif
    @if($paciente) paciente="{{ $paciente }}"; @endif
    @if($desde) desde={{ $desde }}; @endif
    @if($hasta) hasta={{ $hasta }}; @endif
    @if($q) q="{{ $q }}"; @endif
  </p>

  <table>
    <thead>
      <tr>
        <th>Fecha/Hora</th>
        <th>Evento</th>
        <th>Cita</th>
        <th>Paciente</th>
        <th>Doctor</th>
        <th>De</th>
        <th>A</th>
        <th>Detalle</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
          <td>{{ $eventLabels[$r->tipo] ?? ucfirst($r->tipo) }}</td>
          <td>#{{ $r->cita_id }}</td>
          <td>{{ optional($r->cita->paciente)->name ?? '-' }}</td>
          <td>{{ optional($r->cita->doctor)->name ?? '-' }}</td>
          <td>
            @if($r->tipo==='reprogramada' && !blank($r->de_fecha))
              {{ \Illuminate\Support\Carbon::parse($r->de_fecha)->format('Y-m-d') }} {{ $r->de_hora }}
            @else
              {{ $r->de_estado ?? '-' }}
            @endif
          </td>
          <td>
            @if($r->tipo==='reprogramada' && !blank($r->a_fecha))
              {{ \Illuminate\Support\Carbon::parse($r->a_fecha)->format('Y-m-d') }} {{ $r->a_hora }}
            @else
              {{ $r->a_estado ?? '-' }}
            @endif
          </td>
          <td>
            @if($r->tipo === 'prioridad_manual')
              {{ $r->valor_anterior ?? '-' }} -> {{ $r->valor_nuevo ?? '-' }}
              @if(!blank($r->comentario))
                <br>{{ $r->comentario }}
              @endif
            @else
              {{ $r->comentario ?? '-' }}
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center">Sin registros</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
