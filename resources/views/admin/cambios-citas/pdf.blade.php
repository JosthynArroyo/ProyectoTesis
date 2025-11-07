<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cambios de citas</title>
  <style>
    body{font-family: DejaVu Sans, sans-serif; font-size:11px; color:#111}
    h2{margin:0 0 8px 0}
    p{margin:0 0 8px 0}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #ddd;padding:6px 8px}
    th{background:#f2f4f8;text-align:left}
  </style>
</head>
<body>
  <h2>Auditoría de cambios de citas</h2>
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
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->created_at?->format('Y-m-d H:i') }}</td>
          <td>{{ ucfirst($r->tipo) }}</td>
          <td>#{{ $r->cita_id }}</td>
          <td>{{ $r->cita?->paciente?->name ?? '—' }}</td>
          <td>{{ $r->cita?->doctor?->name ?? '—' }}</td>
          <td>
            @if($r->tipo==='reprogramada')
              {{ $r->de_fecha?->format('Y-m-d') }} {{ $r->de_hora }}
            @else
              {{ $r->de_estado ?? '—' }}
            @endif
          </td>
          <td>
            @if($r->tipo==='reprogramada')
              {{ $r->a_fecha?->format('Y-m-d') }} {{ $r->a_hora }}
            @else
              {{ $r->a_estado ?? '—' }}
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center">Sin registros</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
