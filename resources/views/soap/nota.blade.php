@php
  $cita = $nota?->cita;
  $estado = $nota?->estado ?? 'draft';
  $estadoLabel = $estado === 'signed' ? 'Firmada' : 'Borrador';
  $badgeTone = $estado === 'signed' ? 'success' : 'warning';
  $ros = is_array($nota?->subjetivo_ros ?? null) ? ($nota->subjetivo_ros['texto'] ?? '') : (string) ($nota?->subjetivo_ros ?? '');
  $signos = $nota?->signos_vitales ?? [];
  $firmadaPor = $nota?->firmadaPor?->name;
  $seguimientoTexto = $nota?->follow_up_notes ?: $nota?->plan_seguimiento;
  $signosLabels = [
    'ta' => 'Presión arterial',
    'fc' => 'Frecuencia cardíaca',
    'fr' => 'Frecuencia respiratoria',
    'temp' => 'Temperatura corporal',
    'spo2' => 'Saturación de oxígeno',
    'peso' => 'Peso',
    'talla' => 'Talla',
  ];
@endphp

<section class="card p-6 space-y-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Nota médica</p>
      <h2 class="mt-2 text-xl font-semibold text-gray-900">Nota clínica de la consulta</h2>
      <p class="text-sm text-gray-600">Documento individual de esta atención. No representa por sí solo el expediente longitudinal.</p>
    </div>
    <x-ui.badge :tone="$badgeTone">{{ $estadoLabel }}</x-ui.badge>
  </div>

  <div class="grid gap-3 md:grid-cols-2">
    <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400">Paciente</div><div class="text-sm font-semibold text-gray-900">{{ $cita?->paciente?->name ?? 'Paciente' }}</div></div>
    <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400">Doctor</div><div class="text-sm font-semibold text-gray-900">{{ $cita?->doctor?->name ?? 'Doctor/a' }}</div></div>
    <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400">Especialidad</div><div class="text-sm font-semibold text-gray-900">{{ $cita?->especialidad?->nombre ?? '-' }}</div></div>
    <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400">Fecha y hora</div><div class="text-sm font-semibold text-gray-900">{{ $cita?->fecha ? \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') : '-' }} {{ $cita?->hora ? \Carbon\Carbon::parse($cita->hora)->format('H:i') : '' }}</div></div>
    <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400">Firmada por</div><div class="text-sm font-semibold text-gray-900">{{ $firmadaPor ?? '-' }}</div></div>
    <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400">Firmada el</div><div class="text-sm font-semibold text-gray-900">{{ $nota?->signed_at?->format('Y-m-d H:i') ?? '-' }}</div></div>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-900">Subjetivo</h3>
      <p class="text-sm text-gray-700"><strong>Motivo:</strong> {{ $nota?->subjetivo_motivo ?? 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Antecedentes relevantes:</strong> {{ $nota?->subjetivo_hpi ?? 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Revisión adicional:</strong> {{ $ros ?: 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Notas subjetivas:</strong> {{ $nota?->subjetivo_notas ?? 'No registrado.' }}</p>
    </div>

    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-900">Objetivo</h3>
      @if(!empty($signos))
        <div class="grid gap-2 sm:grid-cols-2 text-sm text-gray-700">
          @foreach($signos as $label => $valor)
            <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2">
              <span class="text-xs uppercase tracking-widest text-gray-400">{{ $signosLabels[$label] ?? ucfirst((string) $label) }}</span>
              <div class="font-semibold text-gray-900">{{ $valor }}</div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-sm text-gray-700">Sin signos vitales registrados.</p>
      @endif
      <p class="text-sm text-gray-700"><strong>Examen físico:</strong> {{ $nota?->examen_fisico ?? 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Notas objetivas:</strong> {{ $nota?->notas_objetivas ?? 'No registrado.' }}</p>
    </div>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-900">Evaluación</h3>
      <p class="text-sm text-gray-700">{{ $nota?->assessment ?? 'No registrado.' }}</p>
      <div>
        <div class="text-xs uppercase tracking-widest text-gray-400">Diagnosticos</div>
        @forelse(($nota?->diagnosticos ?? []) as $diag)
          <div class="mt-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm text-gray-700">
            <div class="font-semibold text-gray-900">{{ ucfirst($diag->tipo) }}: {{ $diag->texto }}</div>
            @if($diag->cie10)
              <div class="text-xs text-gray-500">CIE-10: {{ $diag->cie10 }}</div>
            @endif
          </div>
        @empty
          <p class="text-sm text-gray-700">No registrados.</p>
        @endforelse
      </div>
    </div>

    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-900">Plan</h3>
      <p class="text-sm text-gray-700"><strong>Tratamiento:</strong> {{ $nota?->plan_general ?? 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Próximo control:</strong> {{ $nota?->follow_up_date?->format('d/m/Y') ?? 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Seguimiento clínico:</strong> {{ $seguimientoTexto ?: 'No registrado.' }}</p>
      <p class="text-sm text-gray-700"><strong>Indicaciones al paciente:</strong> {{ $nota?->plan_notas ?? 'No registrado.' }}</p>
    </div>
  </div>

  @if(collect($nota?->enmiendas ?? [])->isNotEmpty())
    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-900">Enmiendas</h3>
      @foreach($nota?->enmiendas ?? [] as $enmienda)
        <div class="rounded-xl border border-gray-200 bg-white/90 px-3 py-3 text-sm text-gray-700">
          <div class="font-semibold text-gray-900">{{ $enmienda->motivo }}</div>
          <p class="mt-2">{{ $enmienda->contenido }}</p>
          <div class="mt-2 text-xs text-gray-500">{{ $enmienda->autor?->name ?? 'Usuario' }} | {{ $enmienda->created_at?->format('Y-m-d H:i') }}</div>
        </div>
      @endforeach
    </div>
  @endif
</section>
