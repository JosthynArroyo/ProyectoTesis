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
          @error('dias')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Inicio</label>
          <select class="form-input" id="hora_inicio_global" name="hora_inicio" data-old="{{ old('hora_inicio') }}" required></select>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <select class="form-input" id="hora_fin_global" name="hora_fin" data-old="{{ old('hora_fin') }}" required></select>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" id="intervalo_minutos" name="intervalo_minutos" value="30">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <p class="text-xs text-teal-650 dark:text-teal-400 font-semibold" id="clinic-hours-info-global"></p>
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

    @if (session('horario_conflicts'))
      <div class="card p-4 border-2 border-amber-500 bg-amber-50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-200 space-y-3">
        <div class="flex items-start gap-3">
          <i class="ri-alert-line text-lg text-amber-600 dark:text-amber-400"></i>
          <div>
            <h4 class="font-semibold">Confirmación requerida</h4>
            <p class="text-sm mt-1">Este cambio dejará <strong>{{ session('horario_conflicts') }} cita(s) futura(s) activa(s)</strong> sin cobertura horaria para este doctor.</p>
          </div>
        </div>
        <form method="POST" action="{{ session('conflict_target_route') ?? route('doctor.horario.store') }}">
          @csrf
          @if(session('conflict_target_method') == 'PUT')
            @method('PUT')
          @endif
          @foreach(session('conflict_payload') ?? [] as $k => $v)
            @if(is_array($v))
              @foreach($v as $subk => $subv)
                <input type="hidden" name="{{ $k }}[{{ $subk }}]" value="{{ $subv }}">
              @endforeach
            @else
              <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
          @endforeach
          <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer">
            <input type="hidden" name="confirmar_conflictos" value="0">
            <input type="checkbox" name="confirmar_conflictos" value="1" required class="rounded border-gray-300 text-teal-650 focus:ring-teal-550 dark:border-gray-700 dark:bg-gray-800">
            Confirmar que deseo proceder y forzar los cambios.
          </label>
          <button type="submit" class="btn btn-primary mt-3">Guardar con confirmación</button>
        </form>
      </div>
    @endif

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Crear horario (un día)</h3>
      <form method="POST" action="{{ route('doctor.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label" for="fecha_single">Fecha</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="fecha_single" type="date" name="fecha" placeholder="AAAA-MM-DD" autocomplete="off" min="{{ now()->toDateString() }}" required>
            <button id="doctor-horario-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha_single" aria-label="Abrir calendario para el bloque de un día">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <select class="form-input" id="hora_inicio_single" name="hora_inicio" data-old="{{ old('hora_inicio') }}" required></select>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <select class="form-input" id="hora_fin_single" name="hora_fin" data-old="{{ old('hora_fin') }}" required></select>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <p class="text-xs text-teal-650 dark:text-teal-400 font-semibold" id="clinic-hours-info-single"></p>
        </div>

        <div class="sm:col-span-4">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>

    <script id="clinica-horarios-config" type="application/json">
      {!! json_encode(collect(range(1, 7))->mapWithKeys(function($day) {
          return [$day => app(App\Services\ProfessionalScheduleService::class)->getClinicHours($day)];
      })) !!}
    </script>

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
              @php
                $hDay = \Carbon\Carbon::parse($h->fecha)->isoWeekday();
                $clinicH = app(App\Services\ProfessionalScheduleService::class)->getClinicHours($hDay);
                $isOutside = ($clinicH['status'] === 0) 
                    || (substr($h->hora_inicio, 0, 5) < $clinicH['opening']) 
                    || (substr($h->hora_fin, 0, 5) > $clinicH['closing']);
              @endphp
              <tr class="{{ $isOutside ? 'bg-amber-500/5' : '' }}">
                <td data-label="Fecha">
                  {{ $h->fecha->toDateString() }}
                  @if($isOutside)
                    <br>
                    <span class="inline-block mt-1 text-xs font-semibold text-amber-600 dark:text-amber-450">
                      <i class="ri-alert-line"></i> Fuera del horario institucional: no genera disponibilidad
                    </span>
                  @endif
                </td>
                <td data-label="Inicio">{{ substr($h->hora_inicio,0,5) }}</td>
                <td data-label="Fin">{{ substr($h->hora_fin,0,5) }}</td>
                <td data-label="Intervalo"><span class="badge neutral">{{ $h->intervalo_minutos ?? 30 }} min</span></td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-outline" href="{{ route('doctor.horario.edit',$h) }}">Editar</a>
                    <form action="{{ route('doctor.horario.destroy',$h) }}" method="POST" data-confirm-title="Eliminar horario" data-confirm-message="¿Estás seguro de que deseas eliminar este bloque de horario?" data-confirm-action="eliminar" data-confirm-btn="Sí, eliminar">
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

@push('scripts')
  @vite('resources/js/doctor/horario.js')
@endpush
