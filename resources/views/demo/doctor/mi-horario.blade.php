@extends('layouts.demo')
@section('title','Mi horario - Demo')
@section('activeSidebar','horario')
@section('header-title','Mi horario')
@section('header-subtitle','Configura tus bloques de atención (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@php
  $desde = request('desde', now()->startOfWeek()->toDateString());
  $hasta = request('hasta', now()->endOfWeek()->toDateString());

  $items = collect($scheduleBlocks)->map(function($b) {
      $h = new \App\Models\Horario();
      $h->id = $b['id'];
      
      $h->fecha = now()->startOfWeek()->addDays($h->id - 1);
      
      $parts = explode(' - ', $b['range']);
      $h->hora_inicio = count($parts) > 0 ? $parts[0] : '08:00';
      $h->hora_fin = count($parts) > 1 ? $parts[1] : '17:00';
      $h->intervalo_minutos = 30;
      return $h;
  });
@endphp

@section('main')
  <div class="space-y-6">
    @if(session('success')) <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert> @endif

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Filtrar</h3>
      <form method="GET" action="{{ route('demo.doctor.horario.index') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label class="form-label" for="doctor-horario-filtro-desde">Desde</label>
          <input class="form-input" id="doctor-horario-filtro-desde" type="date" name="desde" value="{{ $desde }}" required>
        </div>
        <div>
          <label class="form-label" for="doctor-horario-filtro-hasta">Hasta</label>
          <input class="form-input" id="doctor-horario-filtro-hasta" type="date" name="hasta" value="{{ $hasta }}" required>
        </div>
        <div class="sm:col-span-3">
          <button class="btn btn-primary">Aplicar</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Generar por rango</h3>
          <p class="text-sm text-gray-500">Opción recomendada para cargar varios días de una vez.</p>
        </div>
        <span class="badge info">Recomendado</span>
      </div>
      <form method="POST" action="{{ route('demo.doctor.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label" for="doctor-horario-rango-desde">Desde</label>
          <input class="form-input" id="doctor-horario-rango-desde" type="date" name="desde" value="{{ now()->toDateString() }}" required>
        </div>
        <div>
          <label class="form-label" for="doctor-horario-rango-hasta">Hasta</label>
          <input class="form-input" id="doctor-horario-rango-hasta" type="date" name="hasta" value="{{ now()->addDays(7)->toDateString() }}" required>
        </div>

        <div class="sm:col-span-4">
          <label class="form-label">Días</label>
          @php
            $dias=[1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'];
          @endphp
          <div class="mt-2 flex flex-wrap gap-2" id="dias-wrap">
            @foreach($dias as $k=>$v)
              <label class="flex items-center gap-2 rounded-full border border-gray-200 px-3 py-2 text-xs font-semibold cursor-pointer">
                <input type="checkbox" name="dias[]" value="{{ $k }}" {{ $k <= 5 ? 'checked' : '' }}>
                <span>{{ $v }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div>
          <label class="form-label">Inicio</label>
          <select class="form-select" name="hora_inicio" required>
            <option value="08:00">08:00</option>
            <option value="09:00">09:00</option>
          </select>
        </div>
        <div>
          <label class="form-label">Fin</label>
          <select class="form-select" name="hora_fin" required>
            <option value="16:00">16:00</option>
            <option value="17:00">17:00</option>
          </select>
        </div>

        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4 mt-3">
          <button class="btn btn-primary">Generar</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Mis horarios</h3>
      </div>
      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th><th>Inicio</th><th>Fin</th><th>Intervalo</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $h)
              <tr>
                <td data-label="Fecha">
                  {{ $h->fecha->toDateString() }}
                </td>
                <td data-label="Inicio">{{ substr($h->hora_inicio,0,5) }}</td>
                <td data-label="Fin">{{ substr($h->hora_fin,0,5) }}</td>
                <td data-label="Intervalo"><span class="badge neutral">{{ $h->intervalo_minutos ?? 30 }} min</span></td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-outline" href="{{ route('demo.doctor.horario.edit',$h->id) }}">Editar</a>
                    <form action="{{ route('demo.doctor.horario.destroy',$h->id) }}" method="POST" data-confirm-title="Eliminar horario" data-confirm-message="¿Estás seguro de que deseas eliminar este horario?" data-confirm-action="eliminar" data-confirm-btn="Sí, eliminar">
                      @csrf @method('DELETE')
                      <button class="btn btn-danger" type="submit">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5">Sin horarios en el rango.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
