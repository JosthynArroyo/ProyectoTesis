{{-- resources/views/admin/cambios-citas/index.blade.php --}}
@extends('layouts.admin')
@section('title','Cambios de citas | Administración')
@section('header-title','Cambios de citas')
@section('header-subtitle','Auditoría y trazabilidad')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Auditoría</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Auditoría de cambios de citas</h1>
        <p class="text-slate-600">Agendadas, confirmadas, canceladas, realizadas, no presentadas y reprogramadas.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-outline" data-filter-toggle>
          <i class="ri-filter-3-line"></i> Mostrar filtros
        </button>
        <span class="badge info"><i class="ri-shield-user-line"></i> Administrador</span>
      </div>
    </div>
  </section>

  <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
    <form method="GET" action="{{ url()->current() }}" class="card p-6" data-filter-panel>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Panel de filtros</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Filtra eventos</h3>
        <p class="text-sm text-slate-500">Combina evento/estado, doctor/paciente y fechas.</p>
      </div>

      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="form-label">Evento</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-event-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" name="tipo" required>
              <option value="all" @selected(($tipo ?? '')==='' || ($tipo ?? '')==='all')>Todos los eventos</option>
              @foreach(['agendada','confirmada','cancelada','realizada','no_se_presento','reprogramada'] as $t)
                <option value="{{ $t }}" @selected(($tipo ?? '')===$t)>{{ $t === 'no_se_presento' ? 'No se presentó' : ucfirst($t) }}</option>
              @endforeach
            </select>
          </div>
          @error('tipo')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Estado</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-flag-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" name="estado" required>
              <option value="all" @selected(($estado ?? '')==='' || ($estado ?? '')==='all')>Todos los estados</option>
              @foreach(['pendiente','confirmada','cancelada','realizada','no_se_presento'] as $e)
                <option value="{{ $e }}" @selected(($estado ?? '')===$e)>{{ $e === 'no_se_presento' ? 'No se presentó' : ucfirst($e) }}</option>
              @endforeach
            </select>
          </div>
          @error('estado')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Doctor</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-stethoscope-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" name="doctor_id" required>
              <option value="all" @selected(($doctorId ?? '')==='' || ($doctorId ?? '')==='all')>Todos los doctores</option>
              @foreach($doctores as $d)
                <option value="{{ $d->id }}" @selected(($doctorId ?? '')===$d->id)>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          @error('doctor_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Paciente</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-user-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="text" name="paciente" value="{{ $paciente ?? '' }}" placeholder="Nombre del paciente" required>
          </div>
          @error('paciente')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Desde</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="date" name="desde" value="{{ $desde ?? '' }}" required>
          </div>
          @error('desde')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div>
          <label class="form-label">Hasta</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="date" name="hasta" value="{{ $hasta ?? '' }}" required>
          </div>
          @error('hasta')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div class="sm:col-span-2">
          <label class="form-label">Búsqueda</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-search-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="search" name="q" value="{{ $q ?? '' }}" placeholder="#cita, correo, cédula, doctor" required>
          </div>
          @error('q')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <button class="btn btn-primary" type="submit">Aplicar filtros</button>
        <a class="btn btn-ghost" href="{{ route('admin.cambios-citas.index') }}">Limpiar</a>
      </div>
    </form>

    <div class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Resultados</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Eventos encontrados</h3>
          <span class="text-sm text-slate-500">{{ $ev->total() }} eventos</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <a class="btn btn-outline" href="{{ route('admin.cambios-citas.export.excel', request()->query()) }}">
            <i class="ri-file-excel-2-line"></i> Excel
          </a>
          <a class="btn btn-primary" href="{{ route('admin.cambios-citas.export.pdf', request()->query()) }}">
            <i class="ri-file-pdf-line"></i> PDF
          </a>
        </div>
      </div>

      <div class="mt-4 table-shell">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha/Hora</th>
              <th>Evento</th>
              <th>Cita</th>
              <th>Paciente</th>
              <th>Doctor</th>
              <th class="text-center">De</th>
              <th class="text-center">A</th>
            </tr>
          </thead>
          <tbody>
          @forelse($ev as $r)
            @php
              $pillMap = ['agendada'=>'info','confirmada'=>'success','cancelada'=>'danger','realizada'=>'success','no_se_presento'=>'danger','reprogramada'=>'warning','pendiente'=>'warning'];
              $pillClass = $pillMap[$r->tipo] ?? 'neutral';
              $iconMap = ['agendada'=>'ri-calendar-event-line','confirmada'=>'ri-check-line','cancelada'=>'ri-close-line','realizada'=>'ri-check-double-line','no_se_presento'=>'ri-close-circle-line','reprogramada'=>'ri-swap-line'];
              $icon = $iconMap[$r->tipo] ?? 'ri-history-line';
            @endphp
            <tr>
              <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
              <td>
                <span class="badge {{ $pillClass }}"><i class="{{ $icon }}"></i> {{ $r->tipo === 'no_se_presento' ? 'No se presentó' : ucfirst($r->tipo) }}</span>
              </td>
              <td>#{{ $r->cita_id }}</td>
              <td>{{ optional($r->cita->paciente)->name ?? '-' }}</td>
              <td>{{ optional($r->cita->doctor)->name ?? '-' }}</td>
              <td class="text-center">
                @if($r->tipo==='reprogramada')
                  {{ $r->de_fecha->format('Y-m-d') }} {{ $r->de_hora }}
                @else
                  <span class="badge neutral">{{ $r->de_estado ?? '-' }}</span>
                @endif
              </td>
              <td class="text-center">
                @if($r->tipo==='reprogramada')
                  {{ $r->a_fecha->format('Y-m-d') }} {{ $r->a_hora }}
                @else
                  <span class="badge neutral">{{ $r->a_estado ?? '-' }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-slate-500">Sin registros</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>

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