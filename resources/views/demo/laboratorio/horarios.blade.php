@extends('layouts.demo')
@section('title','Mi horario | Laboratorio - Demo')
@section('activeSidebar','horario')
@section('header-title','Mi horario')
@section('header-subtitle','Configura y revisa tus bloques de atención (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-laboratorio-demo')
@endsection

@php
  $weekStart = now()->startOfWeek();
  $weekEnd = now()->endOfWeek();
  $desde = request('desde', $weekStart->toDateString());
  $hasta = request('hasta', $weekEnd->toDateString());

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
    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <!-- Filtros -->
    <section class="card p-6 bg-white">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Filtros</h2>
          <p>Acota el rango visible de la tabla sin perder la vista semanal de referencia.</p>
        </div>
      </div>

      <form method="GET" action="{{ route('demo.laboratorio.horario.index') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="form-label" for="lab-horario-filtro-desde">Desde</label>
          <input id="lab-horario-filtro-desde" type="date" name="desde" value="{{ $desde }}" class="form-input">
        </div>
        <div>
          <label class="form-label" for="lab-horario-filtro-hasta">Hasta</label>
          <input id="lab-horario-filtro-hasta" type="date" name="hasta" value="{{ $hasta }}" class="form-input">
        </div>
        <div class="xl:col-span-2 flex items-end gap-2">
          <button class="btn btn-primary w-full sm:w-auto" type="submit">
            <i class="ri-filter-3-line"></i> Aplicar
          </button>
        </div>
      </form>
    </section>

    <!-- Crear Horario -->
    <div class="card p-6 bg-white">
      <h3 class="text-lg font-semibold text-gray-900">Crear horario de un día</h3>
      <form method="POST" action="{{ route('demo.laboratorio.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label" for="fecha">Fecha</label>
          <input id="fecha" type="date" name="fecha" value="{{ now()->addDay()->toDateString() }}" required class="form-input">
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <input type="time" name="hora_inicio" value="08:00" required class="form-input">
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input type="time" name="hora_fin" value="16:00" required class="form-input">
        </div>
        <div>
          <label class="form-label">Intervalo</label>
          <div class="mt-2 inline-flex items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600">30 min</div>
        </div>
        <div class="md:col-span-4">
          <button class="btn btn-primary" type="submit">
            <i class="ri-save-line"></i> Crear horario
          </button>
        </div>
      </form>
    </div>

    <!-- Generar por Rango -->
    <div class="card p-6 bg-white">
      <h3 class="text-lg font-semibold text-gray-900">Generar por rango</h3>
      <form method="POST" action="{{ route('demo.laboratorio.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label">Desde</label>
          <input type="date" name="desde" value="{{ now()->toDateString() }}" required class="form-input">
        </div>
        <div>
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" value="{{ now()->addDays(7)->toDateString() }}" required class="form-input">
        </div>

        <div class="md:col-span-4">
          <label class="form-label">Días</label>
          @php($dias=[1=>'Lun',2=>'Mar',3=>'Mie',4=>'Jue',5=>'Vie',6=>'Sab',7=>'Dom'])
          <div class="mt-2 flex flex-wrap gap-2">
            @foreach($dias as $k => $v)
              <label class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 cursor-pointer">
                <input type="checkbox" name="dias[]" value="{{ $k }}" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" {{ $k <= 5 ? 'checked' : '' }}>
                <span>{{ $v }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div>
          <label class="form-label">Inicio</label>
          <input type="time" name="hora_inicio" value="08:00" required class="form-input">
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input type="time" name="hora_fin" value="16:00" required class="form-input">
        </div>

        <div class="md:col-span-4 mt-3">
          <button class="btn btn-primary" type="submit">
            <i class="ri-calendar-check-line"></i> Generar horarios
          </button>
        </div>
      </form>
    </div>

    <!-- Lista de Horarios -->
    <section class="card p-6 bg-white">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Mis horarios</h2>
          <p>Bloques registrados en el rango activo.</p>
        </div>
      </div>
      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Intervalo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $horario)
              <tr>
                <td data-label="Fecha">{{ optional($horario->fecha)->format('d/m/Y') }}</td>
                <td data-label="Inicio">{{ substr((string) $horario->hora_inicio, 0, 5) }}</td>
                <td data-label="Fin">{{ substr((string) $horario->hora_fin, 0, 5) }}</td>
                <td data-label="Intervalo"><x-ui.badge tone="info">30 min</x-ui.badge></td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-ghost btn-sm" href="{{ route('demo.laboratorio.horario.edit', $horario->id) }}">
                      <i class="ri-edit-line"></i> Editar
                    </a>
                    <form action="{{ route('demo.laboratorio.horario.destroy', $horario->id) }}" method="POST" onsubmit="return confirm('Eliminar horario')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-danger btn-sm" type="submit">
                        <i class="ri-delete-bin-line"></i> Eliminar
                      </button>
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
    </section>
  </div>
@endsection
