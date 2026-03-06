<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Mis citas</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
      h1 { font-size: 18px; margin: 0 0 6px; }
      .meta { margin: 0 0 12px; color: #475569; }
      .meta span { display: inline-block; margin-right: 12px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
      th { background: #f1f5f9; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.04em; }
      tbody tr:nth-child(odd) { background: #f8fafc; }
    </style>
  </head>
  <body>
    <h1>Mis citas</h1>
    <p class="meta">
      <span>Doctor: {{ $doctor->name ?? 'Doctor' }}</span>
      <span>Filtro estado:
        @if($estado)
          {{ $estado === 'no_se_presento' ? 'No se presento' : ucfirst($estado) }}
        @else
          Todos
        @endif
      </span>
      <span>Filtro prioridad: {{ $prioridad ?: 'Todas' }}</span>
      <span>Generado: {{ now()->format('Y-m-d H:i') }}</span>
    </p>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Paciente</th>
          <th>Especialidad</th>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Estado</th>
          <th>Prioridad</th>
        </tr>
      </thead>
      <tbody>
        @forelse($citas as $index => $cita)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ optional($cita->paciente)->name ?? 'Sin paciente' }}</td>
            <td>{{ optional($cita->especialidad)->nombre ?? 'Sin especialidad' }}</td>
            <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
            <td>{{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</td>
            <td>{{ $cita->estado === 'no_se_presento' ? 'No se presento' : ucfirst($cita->estado) }}</td>
            <td>
              {{ $cita->prioridad_nivel ?? 'BAJA' }}
              @if($cita->prioridad_red_flag)
                (Red flag)
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7">Sin citas para el filtro seleccionado.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </body>
</html>
