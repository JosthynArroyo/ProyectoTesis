{{-- resources/views/admin/cambios-citas/index.blade.php --}}
@extends('layouts.admin')
@section('title','Cambios de citas | Administración')
@section('header-title','Cambios de citas')
@section('header-subtitle','Auditoría y trazabilidad')

@section('main')
@php
  $eventLabels = [
    'agendada' => 'Agendada',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'realizada' => 'Realizada',
    'no_se_presento' => 'No se presentó',
    'reprogramada' => 'Reprogramada',
    'prioridad_manual' => 'Prioridad manual',
  ];
  $stateLabels = [
    'pendiente' => 'Pendiente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'realizada' => 'Realizada',
    'no_se_presento' => 'No se presentó',
  ];
@endphp
<div class="space-y-6">
  <div class="panel-action-bar">
    <button type="button" class="btn btn-outline shrink-0" data-filter-toggle>
      <i class="ri-filter-3-line"></i> Mostrar filtros
    </button>
  </div>

  <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)] 2xl:grid-cols-[360px_minmax(0,1fr)]">
    <form method="GET" action="{{ url()->current() }}" class="card min-w-0 p-6 xl:sticky xl:top-24" data-filter-panel>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Panel de filtros</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Filtra eventos</h3>
        <p class="text-sm text-slate-500">Combina evento, estado, doctor, paciente y fechas.</p>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
        <div>
          <label class="form-label">Evento</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-event-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" name="tipo">
              <option value="all" @selected(($tipo ?? '') === '' || ($tipo ?? '') === 'all')>Todos los eventos</option>
              @foreach(['agendada','confirmada','cancelada','realizada','no_se_presento','reprogramada','prioridad_manual'] as $t)
                <option value="{{ $t }}" @selected(($tipo ?? '') === $t)>{{ $eventLabels[$t] ?? ucfirst($t) }}</option>
              @endforeach
            </select>
          </div>
          @error('tipo')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Estado</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-flag-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" name="estado">
              <option value="all" @selected(($estado ?? '') === '' || ($estado ?? '') === 'all')>Todos los estados</option>
              @foreach(['pendiente','confirmada','cancelada','realizada','no_se_presento'] as $e)
                <option value="{{ $e }}" @selected(($estado ?? '') === $e)>{{ $stateLabels[$e] ?? ucfirst($e) }}</option>
              @endforeach
            </select>
          </div>
          @error('estado')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Doctor</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-stethoscope-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" name="doctor_id">
              <option value="all" @selected(($doctorId ?? '') === '' || ($doctorId ?? '') === 'all')>Todos los doctores</option>
              @foreach($doctores as $doctor)
                <option value="{{ $doctor->id }}" @selected(($doctorId ?? '') === $doctor->id)>{{ $doctor->name }}</option>
              @endforeach
            </select>
          </div>
          @error('doctor_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Paciente</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-user-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="text" name="paciente" value="{{ $paciente ?? '' }}" placeholder="Nombre del paciente">
          </div>
          @error('paciente')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Desde</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="date" name="desde" value="{{ $desde ?? '' }}">
          </div>
          @error('desde')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Hasta</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="date" name="hasta" value="{{ $hasta ?? '' }}">
          </div>
          @error('hasta')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div class="sm:col-span-2">
          <label class="form-label">Búsqueda</label>
          <div class="flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-search-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="search" name="q" value="{{ $q ?? '' }}" placeholder="#cita, correo, cédula, doctor">
          </div>
          @error('q')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <button class="btn btn-primary" type="submit">
          <i class="ri-filter-3-line"></i> Aplicar filtros
        </button>
        <a class="btn btn-ghost" href="{{ route('admin.cambios-citas.index') }}">
          <i class="ri-refresh-line"></i> Limpiar
        </a>
      </div>
    </form>

    <div class="card min-w-0 overflow-visible p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Resultados</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Eventos encontrados</h3>
          <span class="text-sm text-slate-500">{{ $ev->total() }} eventos</span>
        </div>
        <div class="relative">
          <button type="button" class="btn btn-outline btn-sm" data-kebab="audit-export-menu">
            <i class="ri-more-2-fill"></i> Exportar
          </button>
          <div id="audit-export-menu" class="kebab-menu" role="menu">
            <a class="btn btn-ghost btn-sm justify-start" href="{{ route('admin.cambios-citas.export.excel', request()->query()) }}" role="menuitem">
              <i class="ri-file-excel-2-line"></i> Excel
            </a>
            <a class="btn btn-ghost btn-sm justify-start" href="{{ route('admin.cambios-citas.export.pdf', request()->query()) }}" role="menuitem">
              <i class="ri-file-pdf-line"></i> PDF
            </a>
          </div>
        </div>
      </div>

      @if($ev->count())
        <div class="mt-4 table-shell table-responsive-cards overflow-x-auto">
          <table class="table w-full min-w-[980px] xl:min-w-[900px] 2xl:min-w-full">
            <thead>
              <tr>
                <th>Fecha/Hora</th>
                <th>Evento</th>
                <th>Cita</th>
                <th>Paciente</th>
                <th>Doctor</th>
                <th class="text-center">De</th>
                <th class="text-center">A</th>
                <th>Detalle</th>
              </tr>
            </thead>
            <tbody>
            @foreach($ev as $registro)
              @php
                $pillMap = ['agendada'=>'info','confirmada'=>'success','cancelada'=>'danger','realizada'=>'success','no_se_presento'=>'danger','reprogramada'=>'warning','pendiente'=>'warning','prioridad_manual'=>'warning'];
                $pillClass = $pillMap[$registro->tipo] ?? 'neutral';
                $iconMap = ['agendada'=>'ri-calendar-event-line','confirmada'=>'ri-check-line','cancelada'=>'ri-close-line','realizada'=>'ri-check-double-line','no_se_presento'=>'ri-close-circle-line','reprogramada'=>'ri-swap-line','prioridad_manual'=>'ri-flag-2-line'];
                $icon = $iconMap[$registro->tipo] ?? 'ri-history-line';
                $fromStateLabel = $stateLabels[$registro->de_estado] ?? ($registro->de_estado ?: '-');
                $toStateLabel = $stateLabels[$registro->a_estado] ?? ($registro->a_estado ?: '-');
              @endphp
              <tr>
                <td data-label="Fecha / hora" class="whitespace-nowrap">
                  <div>{{ $registro->created_at->format('d/m/Y') }}</div>
                  <div class="text-xs text-slate-500">{{ $registro->created_at->format('H:i') }}</div>
                </td>
                <td data-label="Evento">
                  <span class="badge {{ $pillClass }} whitespace-nowrap"><i class="{{ $icon }}"></i> {{ $eventLabels[$registro->tipo] ?? ucfirst($registro->tipo) }}</span>
                </td>
                <td data-label="Cita" class="whitespace-nowrap">#{{ $registro->cita_id }}</td>
                <td data-label="Paciente" class="max-w-[11rem] break-words">{{ optional($registro->cita->paciente)->name ?? '-' }}</td>
                <td data-label="Doctor" class="max-w-[12rem] break-words">{{ optional($registro->cita->doctor)->name ?? '-' }}</td>
                <td data-label="De">
                  @if($registro->tipo === 'reprogramada' && !blank($registro->de_fecha))
                    <div class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($registro->de_fecha)->format('d/m/Y') }}</div>
                    <div class="text-xs text-slate-500">{{ $registro->de_hora }}</div>
                  @else
                    <span class="badge neutral whitespace-nowrap">{{ $fromStateLabel }}</span>
                  @endif
                </td>
                <td data-label="A">
                  @if($registro->tipo === 'reprogramada' && !blank($registro->a_fecha))
                    <div class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($registro->a_fecha)->format('d/m/Y') }}</div>
                    <div class="text-xs text-slate-500">{{ $registro->a_hora }}</div>
                  @else
                    <span class="badge neutral whitespace-nowrap">{{ $toStateLabel }}</span>
                  @endif
                </td>
                <td data-label="Detalle" class="max-w-[18rem] break-words text-sm text-slate-600">
                  @if($registro->tipo === 'prioridad_manual')
                    <div><strong>{{ $registro->valor_anterior ?? '-' }}</strong></div>
                    <div class="mt-1 text-xs text-slate-500">-> {{ $registro->valor_nuevo ?? '-' }}</div>
                    @if(!blank($registro->comentario))
                      <div class="mt-1 text-xs text-slate-500">{{ $registro->comentario }}</div>
                    @endif
                  @elseif(!blank($registro->comentario))
                    <span class="text-xs text-slate-500">{{ $registro->comentario }}</span>
                  @else
                    <span class="text-xs text-slate-400">Sin comentario adicional.</span>
                  @endif
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="mt-4">
          <x-ui.empty-state title="No hay eventos con estos filtros." message="Ajusta el rango, cambia el evento o limpia los filtros para revisar otros movimientos de agenda.">
            <div class="mt-4 flex flex-wrap justify-center gap-3">
              <a class="btn btn-primary" href="{{ route('admin.cambios-citas.index') }}">Limpiar filtros</a>
            </div>
          </x-ui.empty-state>
        </div>
      @endif

      <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
        <div>Página {{ $ev->currentPage() }} de {{ $ev->lastPage() }}</div>
        {!! $ev->withQueryString()->links() !!}
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/cambios-citas.js')
@endpush
