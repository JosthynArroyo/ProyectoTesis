@extends('layouts.demo')
@section('title', 'Mis citas médicas - Demo')
@section('body-class', 'paciente-body--citas')
@section('header-title','Mis citas')
@section('header-subtitle','Gestiona tus citas en un solo lugar (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $estadoFiltro = request('estado', '');
  $buscarFiltro = request('q', '');

  $citasCollection = collect($citas)->map(function($c) {
      $cita = new \App\Models\Cita();
      $cita->id = $c['id'];
      $cita->estado = match (strtolower($c['status'])) {
          'confirmada' => 'confirmada',
          'pendiente' => 'pendiente',
          'realizada' => 'realizada',
          'cancelada' => 'cancelada',
          default => 'pendiente',
      };
      $cita->fecha = \Carbon\Carbon::parse(str_replace('/', '-', $c['date']));
      $cita->hora = $c['time'];
      $cita->folio_cita = 'FOL-CITA-' . $c['id'];
      $cita->token_validation = 'VAL-TOKEN-' . $c['id'];
      $cita->dependiente_id = str_contains($c['doctor'], 'Sofia') ? 1 : null; // Mock dependent for Dra. Sofia
      
      $p = new \App\Models\User(['name' => 'Maria Fernanda Vega']);
      $cita->setRelation('paciente', $p);
      
      if ($cita->dependiente_id) {
          $dep = new \App\Models\Dependiente(['nombre' => 'Lucia Vega', 'parentesco' => 'Hijo/a']);
          $cita->setRelation('dependiente', $dep);
      }
      
      $cita->setRelation('doctor', new \App\Models\User(['name' => $c['doctor']]));
      $cita->setRelation('especialidad', new \App\Models\Especialidad(['nombre' => $c['specialty']]));
      return $cita;
  });
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('demo.paciente.crear-cita') }}" class="btn btn-primary btn-full-mobile">
      <i class="ri-add-line"></i>
      <span class="cta-text">Agendar cita</span>
    </a>
  </div>

  <section class="card p-6" aria-label="Barra de búsqueda y filtros">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('demo.paciente.citas') }}">
      <div class="flex flex-1 items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" role="search">
        <i class="ri-search-line text-gray-400"></i>
        <input type="text" name="q" value="{{ $buscarFiltro }}" placeholder="Buscar por doctor o especialidad..." aria-label="Buscar citas" class="w-full bg-transparent text-sm text-gray-700"/>
      </div>
      <div>
        <select name="estado" aria-label="Filtrar por estado" class="form-select">
          <option value="all" @selected($estadoFiltro==='' || $estadoFiltro==='all')>Todos los estados</option>
          <option value="pendiente"  {{ $estadoFiltro==='pendiente' ? 'selected' : '' }}>En revisión</option>
          <option value="confirmada" {{ $estadoFiltro==='confirmada' ? 'selected' : '' }}>Confirmada</option>
          <option value="cancelada"  {{ $estadoFiltro==='cancelada' ? 'selected' : '' }}>Cancelada</option>
          <option value="realizada"  {{ $estadoFiltro==='realizada' ? 'selected' : '' }}>Realizada</option>
        </select>
      </div>
      <button class="btn btn-outline btn-sm" type="submit" aria-label="Aplicar filtros">
        <i class="ri-filter-3-line"></i>
        Filtrar
      </button>
    </form>
  </section>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <section class="grid gap-4" aria-label="Listado de citas">
    @if($citasCollection->isEmpty())
      <x-ui.empty-state title="No tienes citas registradas.">
        <p>Agenda tu primera cita para verla aquí con su estado y acciones.</p>
        <a href="{{ route('demo.paciente.crear-cita') }}" class="btn btn-primary">
          <i class="ri-add-line"></i>
          Agendar cita
        </a>
      </x-ui.empty-state>
    @else
      @foreach($citasCollection as $cita)
        @php
          $vigente = !in_array($cita->estado, ['cancelada','realizada','no_se_presento']);
        @endphp
        <article id="cita-{{ $cita->id }}" class="card p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-sm font-semibold text-gray-600" aria-hidden="true">
                {{ strtoupper(substr($cita->doctor->name ?? 'DR', 0, 2)) }}
              </div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="doctor-name text-base font-semibold text-gray-900">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</h3>
                  @switch($cita->estado)
                    @case('pendiente')  <span class="badge warning">En revisión</span>  @break
                    @case('confirmada') <span class="badge info">Confirmada</span> @break
                    @case('cancelada')  <span class="badge danger">Cancelada</span>   @break
                    @case('realizada')  <span class="badge success">Realizada</span>     @break
                    @default            <span class="badge info">{{ ucfirst($cita->estado) }}</span>
                  @endswitch
                </div>
                <p class="text-sm text-gray-500">{{ optional($cita->especialidad)->nombre ?? 'Sin especialidad' }}</p>
              </div>
            </div>
            <span class="chip" aria-label="Fecha y hora">
              <i class="ri-time-line"></i>
              {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} ·
              {{ $cita->hora }}
            </span>
          </div>

          <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-650">
            <span><strong>Paciente:</strong> {{ $cita->dependiente_id && $cita->dependiente ? $cita->dependiente->nombre . ' (' . ucfirst($cita->dependiente->parentesco) . ')' : 'Mí' }}</span>
            <span><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</span>
            <span><strong>Hora:</strong> {{ $cita->hora }}</span>
          </div>

          <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">Comprobante de cita</p>
                <p class="text-sm font-semibold text-gray-900">{{ $cita->folio_cita }}</p>
                <p class="text-xs text-gray-500">Sirve para validar en recepción que la cita te pertenece.</p>
              </div>
              <span class="badge {{ $vigente ? 'success' : 'danger' }}">
                {{ $vigente ? 'Vigente' : 'Sin vigencia' }}
              </span>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <button class="btn btn-outline btn-sm demo-action-blocked">
                <i class="ri-file-download-line"></i>
                Descargar comprobante
              </button>
            </div>
          </div>

          @if($vigente)
            <div class="mt-4 flex flex-wrap gap-2">
              <form action="{{ route('demo.paciente.citas.cancelar', $cita->id) }}" method="POST" style="display:inline-block">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm" aria-label="Cancelar cita">
                  <i class="ri-close-line"></i>
                  Cancelar
                </button>
              </form>
              <a class="btn btn-outline btn-sm" href="{{ route('demo.paciente.editar-cita', $cita->id) }}" aria-label="Reagendar cita">
                <i class="ri-calendar-line"></i>
                Reagendar
              </a>
            </div>
          @endif
        </article>
      @endforeach
    @endif
  </section>
</div>
@endsection
