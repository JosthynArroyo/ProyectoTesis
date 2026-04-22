@extends('layouts.admin')
@section('title', 'Agendar cita con excepcion')
@section('header-title', 'Agendar cita con excepcion')
@section('header-subtitle', 'Uso exclusivo para casos especiales')

@push('scripts')
  @vite('resources/js/admin/override-create.js')
@endpush

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('admin.pagos.index') }}" class="btn btn-outline">
      <i class="ri-wallet-3-line"></i> Ver pagos
    </a>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <x-ui.alert tone="warning">
    Este formulario debe usarse solo para casos especiales. Si se fuerza la creacion con pagos pendientes, el motivo queda registrado.
  </x-ui.alert>

  <section class="card p-6">
    <form
      method="POST"
      action="{{ route('admin.citas.override.store') }}"
      class="space-y-5"
      data-endpoint-template="{{ route('especialidades.doctores', ['especialidad' => 'ESP_ID']) }}"
      data-old-esp="{{ old('especialidad_id') }}"
      data-old-doc="{{ old('doctor_id') }}"
      data-old-hora="{{ old('hora') }}"
      data-slots-template="{{ url('/api/doctor/DOC_ID/fecha/FECHA/slots') }}"
      data-laboratorio-id="{{ $labId ?? '' }}"
    >
      @csrf

      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-user-line"></i></span>
              Paciente y profesion
            </h2>
            <p class="panel-form-section__hint">Selecciona el paciente, la especialidad y el profesional antes de elegir fecha y hora.</p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="form-label" for="paciente_id">Paciente</label>
            <select id="paciente_id" name="paciente_id" class="form-select" required>
              <option value="">Seleccione un paciente</option>
              @foreach($pacientes as $paciente)
                <option value="{{ $paciente->id }}" @selected((string) old('paciente_id') === (string) $paciente->id)>
                  {{ $paciente->name }} - {{ $paciente->dni }} - {{ $paciente->email }}
                </option>
              @endforeach
            </select>
            @error('paciente_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label class="form-label" for="especialidad_id">Especialidad</label>
            <select id="especialidad_id" name="especialidad_id" class="form-select" required>
              <option value="">Seleccione especialidad</option>
              @foreach($especialidades as $esp)
                <option value="{{ $esp->id }}" @selected((string) old('especialidad_id') === (string) $esp->id)>{{ $esp->nombre }}</option>
              @endforeach
            </select>
            @error('especialidad_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label class="form-label" for="doctor_id">Profesional</label>
            <select id="doctor_id" name="doctor_id" class="form-select" required disabled>
              <option value="">{{ old('especialidad_id') ? 'Cargando...' : 'Seleccione una especialidad primero' }}</option>
            </select>
            @error('doctor_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
        </div>
      </section>

      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-calendar-check-line"></i></span>
              Agenda de la cita
            </h2>
            <p class="panel-form-section__hint">Solo se muestran horarios disponibles para la combinacion elegida.</p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="fecha">Fecha</label>
            <div class="relative mt-1">
              <input
                id="fecha"
                type="date"
                name="fecha"
                value="{{ old('fecha') }}"
                class="form-input form-input-native-date mt-0 pr-11"
                min="{{ now('America/Guayaquil')->toDateString() }}"
                placeholder="AAAA-MM-DD"
                autocomplete="off"
                required
              >
              <button id="admin-override-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha" aria-label="Abrir calendario para la cita por excepcion">
                <i class="ri-calendar-line"></i>
              </button>
            </div>
            @error('fecha')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            <div class="text-xs text-slate-500">Solo se permiten fechas desde hoy.</div>
          </div>

          <div>
            <label class="form-label" for="hora">Hora disponible</label>
            <select id="hora" name="hora" class="form-select" required disabled>
              <option value="">{{ old('doctor_id') && old('fecha') ? 'Cargando horarios...' : 'Seleccione profesional y fecha' }}</option>
            </select>
            @error('hora')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            <div id="horaHelp" class="text-xs text-slate-500">Se muestran solo los horarios realmente disponibles.</div>
          </div>

          <div class="md:col-span-2">
            <label class="form-label" for="motivo_consulta">Motivo de consulta</label>
            <input
              id="motivo_consulta"
              type="text"
              name="motivo_consulta"
              value="{{ old('motivo_consulta') }}"
              required
              minlength="3"
              maxlength="80"
              class="form-input"
              placeholder="Ej: dolor de garganta"
            >
            @error('motivo_consulta')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            <div class="mt-1 text-xs text-slate-500">Campo obligatorio, breve y en una sola linea (3-80 caracteres).</div>
            <div class="mt-2 flex flex-wrap gap-2" data-motivo-chip-group data-target="#motivo_consulta">
              @foreach(['Fiebre','Dolor de garganta','Dolor abdominal','Tos','Dolor de cabeza','Nauseas','Diarrea','Malestar general'] as $chip)
                <button type="button" class="chip" data-motivo-chip="{{ $chip }}">{{ $chip }}</button>
              @endforeach
            </div>
          </div>
        </div>
      </section>

      <section class="panel-form-section" id="labSection" hidden>
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-flask-line"></i></span>
              Datos opcionales para laboratorio
            </h2>
            <p class="panel-form-section__hint">Completa estos datos solo si la especialidad corresponde a laboratorio.</p>
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="form-label" for="tipo_examen">Tipo de examen</label>
            <input id="tipo_examen" type="text" name="tipo_examen" value="{{ old('tipo_examen') }}" class="form-input" data-lab-required>
            @error('tipo_examen')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label class="form-label" for="prioridad">Prioridad</label>
            <select id="prioridad" name="prioridad" class="form-select" data-lab-required>
              <option value="">Seleccione</option>
              <option value="normal" @selected(old('prioridad') === 'normal')>Normal</option>
              <option value="urgente" @selected(old('prioridad') === 'urgente')>Urgente</option>
            </select>
            @error('prioridad')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label class="form-label" for="indicaciones">Indicaciones</label>
            <textarea id="indicaciones" name="indicaciones" class="form-textarea" rows="3">{{ old('indicaciones') }}</textarea>
            @error('indicaciones')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label class="form-label" for="preparacion">Preparacion</label>
            <textarea id="preparacion" name="preparacion" class="form-textarea" rows="3">{{ old('preparacion') }}</textarea>
            @error('preparacion')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
        </div>
      </section>

      <section class="panel-form-section panel-form-section--warning">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-shield-check-line"></i></span>
              Justificacion del override
            </h2>
            <p class="panel-form-section__hint">Activa esta opcion solo si realmente debes crear la cita aun con pagos pendientes.</p>
          </div>
        </div>

        <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900">
          <input type="checkbox" name="forzar_bloqueo" value="1" class="h-4 w-4" @checked(old('forzar_bloqueo'))>
          Forzar creacion aun con pagos pendientes
        </label>
        @error('forzar_bloqueo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror

        <div class="mt-3">
          <label class="form-label" for="override_reason">Motivo del override (obligatorio si se fuerza)</label>
          <textarea id="override_reason" name="override_reason" class="form-textarea" rows="3">{{ old('override_reason') }}</textarea>
          @error('override_reason')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
      </section>

      <x-ui.form-actions>
        <button type="submit" class="btn btn-primary">
          <i class="ri-calendar-check-line"></i> Crear cita
        </button>
      </x-ui.form-actions>
    </form>
  </section>
</div>
@endsection
