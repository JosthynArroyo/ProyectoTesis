@php
  $cita = $nota?->cita;
  $estado = $nota?->estado ?? 'draft';
  $estadoLabel = $estado === 'signed' ? 'Firmada' : 'Borrador';
  $badgeTone = $estado === 'signed' ? 'success' : 'warning';
  $ros = '';
  if (is_array($nota?->subjetivo_ros ?? null)) {
      $ros = $nota->subjetivo_ros['texto'] ?? '';
  }
  $signos = $nota?->signos_vitales ?? [];
  $firmadaPor = $nota?->firmadaPor?->name;
@endphp

<section class="card p-6 space-y-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Nota clínica</p>
      <h2 class="mt-2 text-xl font-semibold text-slate-900">Registro SOAP</h2>
      <p class="text-sm text-slate-600">Detalle de atención por cita.</p>
    </div>
    <x-ui.badge :tone="$badgeTone">{{ $estadoLabel }}</x-ui.badge>
  </div>

  <div class="grid gap-3 md:grid-cols-2">
    <div class="card p-4">
      <div class="text-xs uppercase tracking-widest text-slate-400">Paciente</div>
      <div class="text-sm font-semibold text-slate-900">{{ $cita?->paciente?->name ?? 'Paciente' }}</div>
    </div>
    <div class="card p-4">
      <div class="text-xs uppercase tracking-widest text-slate-400">Doctor</div>
      <div class="text-sm font-semibold text-slate-900">{{ $cita?->doctor?->name ?? 'Doctor/a' }}</div>
    </div>
    <div class="card p-4">
      <div class="text-xs uppercase tracking-widest text-slate-400">Especialidad</div>
      <div class="text-sm font-semibold text-slate-900">{{ $cita?->especialidad?->nombre ?? '-' }}</div>
    </div>
    <div class="card p-4">
      <div class="text-xs uppercase tracking-widest text-slate-400">Fecha y hora</div>
      <div class="text-sm font-semibold text-slate-900">
        {{ $cita?->fecha ? \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') : '-' }}
        {{ $cita?->hora ? \Carbon\Carbon::parse($cita->hora)->format('H:i') : '' }}
      </div>
    </div>
    <div class="card p-4">
      <div class="text-xs uppercase tracking-widest text-slate-400">Firmada por</div>
      <div class="text-sm font-semibold text-slate-900">{{ $firmadaPor ?? '-' }}</div>
    </div>
    <div class="card p-4">
      <div class="text-xs uppercase tracking-widest text-slate-400">Firmada el</div>
      <div class="text-sm font-semibold text-slate-900">{{ $nota?->signed_at?->format('Y-m-d H:i') ?? '-' }}</div>
    </div>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-slate-900">S: Subjetivo</h3>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Motivo</div>
        <p class="text-sm text-slate-700">{{ $nota?->subjetivo_motivo ?? 'No registrado.' }}</p>
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">HPI</div>
        <p class="text-sm text-slate-700">{{ $nota?->subjetivo_hpi ?? 'No registrado.' }}</p>
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">ROS</div>
        <p class="text-sm text-slate-700">{{ $ros ?: 'No registrado.' }}</p>
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Notas subjetivas</div>
        <p class="text-sm text-slate-700">{{ $nota?->subjetivo_notas ?? 'No registrado.' }}</p>
      </div>
    </div>

    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-slate-900">O: Objetivo</h3>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Signos vitales</div>
        @if(!empty($signos))
          <div class="mt-2 grid gap-2 sm:grid-cols-2 text-sm text-slate-700">
            @foreach($signos as $label => $valor)
              <div class="rounded-xl border border-slate-200 bg-white/80 px-3 py-2">
                <span class="text-xs uppercase tracking-widest text-slate-400">{{ strtoupper($label) }}</span>
                <div class="font-semibold text-slate-900">{{ $valor }}</div>
              </div>
            @endforeach
          </div>
        @else
          <p class="text-sm text-slate-700">No registrados.</p>
        @endif
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Examen físico</div>
        <p class="text-sm text-slate-700">{{ $nota?->examen_fisico ?? 'No registrado.' }}</p>
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Notas objetivas</div>
        <p class="text-sm text-slate-700">{{ $nota?->notas_objetivas ?? 'No registrado.' }}</p>
      </div>
    </div>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-slate-900">A: Evaluación</h3>
      <p class="text-sm text-slate-700">{{ $nota?->assessment ?? 'No registrado.' }}</p>

      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Diagnósticos</div>
        @if(collect($nota?->diagnosticos ?? [])->isEmpty())
          <p class="text-sm text-slate-700">No registrados.</p>
        @else
          <div class="mt-2 space-y-2">
            @foreach(($nota?->diagnosticos ?? []) as $diag)
              <div class="rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm text-slate-700">
                <div class="font-semibold text-slate-900">{{ ucfirst($diag->tipo) }}: {{ $diag->texto }}</div>
                @if($diag->cie10)
                  <div class="text-xs text-slate-500">CIE-10: {{ $diag->cie10 }}</div>
                @endif
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-slate-900">P: Plan</h3>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Plan general</div>
        <p class="text-sm text-slate-700">{{ $nota?->plan_general ?? 'No registrado.' }}</p>
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Seguimiento</div>
        <p class="text-sm text-slate-700">{{ $nota?->plan_seguimiento ?? 'No registrado.' }}</p>
      </div>
      <div>
        <div class="text-xs uppercase tracking-widest text-slate-400">Notas del plan</div>
        <p class="text-sm text-slate-700">{{ $nota?->plan_notas ?? 'No registrado.' }}</p>
      </div>
    </div>
  </div>

  @if(collect($nota?->enmiendas ?? [])->isNotEmpty())
    <div class="card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-slate-900">Enmiendas</h3>
      <div class="space-y-3">
        @foreach($nota?->enmiendas ?? [] as $enmienda)
          <div class="rounded-xl border border-slate-200 bg-white/90 px-3 py-3 text-sm text-slate-700">
            <div class="text-xs uppercase tracking-widest text-slate-400">Motivo</div>
            <div class="font-semibold text-slate-900">{{ $enmienda->motivo }}</div>
            <div class="mt-2 text-xs uppercase tracking-widest text-slate-400">Detalle</div>
            <p class="text-sm text-slate-700">{{ $enmienda->contenido }}</p>
            <div class="mt-2 text-xs text-slate-500">
              {{ $enmienda->autor?->name ?? 'Usuario' }} &middot; {{ $enmienda->created_at?->format('Y-m-d H:i') }}
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</section>
