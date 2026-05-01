@extends('layouts.doctor')
@section('title','Mi horario | Doctor')
@section('activeSidebar','horario')
@section('header-title','Mi horario')
@section('header-subtitle','Configura tus bloques de atención')

@section('main')
  <div class="space-y-6">
    @if(session('success')) <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert> @endif

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Filtrar</h3>
      <form method="GET" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label class="form-label" for="doctor-horario-filtro-desde">Desde</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="doctor-horario-filtro-desde" type="date" name="desde" value="{{ $desde }}" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-filtro-desde-trigger" type="button" class="field-action-button" data-native-date-open="#doctor-horario-filtro-desde" aria-label="Abrir calendario para fecha inicial">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="doctor-horario-filtro-hasta">Hasta</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="doctor-horario-filtro-hasta" type="date" name="hasta" value="{{ $hasta }}" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-filtro-hasta-trigger" type="button" class="field-action-button" data-native-date-open="#doctor-horario-filtro-hasta" aria-label="Abrir calendario para fecha final">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
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
      <form method="POST" action="{{ route('doctor.horario.generar') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label" for="doctor-horario-rango-desde">Desde</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="doctor-horario-rango-desde" type="date" name="desde" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-rango-desde-trigger" type="button" class="field-action-button" data-native-date-open="#doctor-horario-rango-desde" aria-label="Abrir calendario para fecha inicial del rango">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="doctor-horario-rango-hasta">Hasta</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="doctor-horario-rango-hasta" type="date" name="hasta" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-rango-hasta-trigger" type="button" class="field-action-button" data-native-date-open="#doctor-horario-rango-hasta" aria-label="Abrir calendario para fecha final del rango">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="sm:col-span-4">
          <label class="form-label">Días</label>
          @php($dias=[1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'])
          <div class="mt-2 flex flex-wrap gap-2">
            @foreach($dias as $k=>$v)
              <label class="flex items-center gap-2 rounded-full border border-gray-200 px-3 py-2 text-xs font-semibold">
                <input type="checkbox" name="dias[]" value="{{ $k }}" {{ $k <= 5 ? 'checked' : '' }}>
                <span>{{ $v }}</span>
              </label>
            @endforeach
          </div>
          @error('dias')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Inicio</label>
          <input class="form-input" type="time" name="hora_inicio" required>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input class="form-input" type="time" name="hora_fin" required>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="hidden" name="sobrescribir" value="0">
            <input type="checkbox" name="sobrescribir" value="1">
            Sobrescribir días existentes
          </label>
          @error('sobrescribir')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="sm:col-span-4">
          <button class="btn btn-primary">Generar</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Crear horario (un día)</h3>
      <form method="POST" action="{{ route('doctor.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label" for="doctor-horario-fecha">Fecha</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="doctor-horario-fecha" type="date" name="fecha" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#doctor-horario-fecha" aria-label="Abrir calendario para el bloque de un día">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <input class="form-input" type="time" name="hora_inicio" required>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input class="form-input" type="time" name="hora_fin" required>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <button class="btn btn-primary">Crear</button>
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
                <td data-label="Fecha">{{ $h->fecha }}</td>
                <td data-label="Inicio">{{ substr($h->hora_inicio,0,5) }}</td>
                <td data-label="Fin">{{ substr($h->hora_fin,0,5) }}</td>
                <td data-label="Intervalo"><span class="badge neutral">30 min</span></td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-outline" href="{{ route('doctor.horario.edit',$h) }}">Editar</a>
                    <form action="{{ route('doctor.horario.destroy',$h) }}" method="POST" onsubmit="return confirm('Eliminar horario')">
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
