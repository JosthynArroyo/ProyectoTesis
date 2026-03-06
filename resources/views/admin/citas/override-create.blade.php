@extends('layouts.admin')
@section('title', 'Agendar cita (override)')
@section('header-title', 'Agendar cita con excepcion')
@section('header-subtitle', 'Uso exclusivo para casos especiales')

@push('scripts')
  @vite('resources/js/admin/override-create.js')
@endpush

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <p class="text-xs uppercase tracking-widest text-slate-500">Override administrativo</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Nueva cita por excepcion</h1>
        <p class="text-slate-600">Si el paciente tiene pagos pendientes, active el override y justifique el motivo.</p>
      </div>
      <div class="page-header__actions">
        <a href="{{ route('admin.pagos.index') }}" class="btn btn-outline">Ver pagos</a>
      </div>
    </div>
  </section>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif


  <section class="card p-6">
    <form
      method="POST"
      action="{{ route('admin.citas.override.store') }}"
      class="grid gap-4 md:grid-cols-2"
      data-endpoint-template="{{ route('especialidades.doctores', ['especialidad' => 'ESP_ID']) }}"
      data-old-esp="{{ old('especialidad_id') }}"
      data-old-doc="{{ old('doctor_id') }}"
      data-old-hora="{{ old('hora') }}"
      data-slots-template="{{ url('/api/doctor/DOC_ID/fecha/FECHA/slots') }}"
      data-laboratorio-id="{{ $labId ?? '' }}"
    >
      @csrf

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

      <div>
        <label class="form-label" for="fecha">Fecha</label>
        <input
          id="fecha"
          type="date"
          name="fecha"
          value="{{ old('fecha') }}"
          min="{{ now('America/Guayaquil')->toDateString() }}"
          class="form-input"
          required
        >
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

      <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4" id="labSection" hidden>
        <p class="text-sm font-semibold text-slate-800">Datos opcionales para laboratorio</p>
        <p class="text-xs text-slate-500">Complete estos campos si la especialidad corresponde a laboratorio.</p>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
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
            <textarea id="indicaciones" name="indicaciones" class="form-input" rows="3">{{ old('indicaciones') }}</textarea>
            @error('indicaciones')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label class="form-label" for="preparacion">Preparacion</label>
            <textarea id="preparacion" name="preparacion" class="form-input" rows="3">{{ old('preparacion') }}</textarea>
            @error('preparacion')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
        </div>
      </div>

      <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900">
          <input type="checkbox" name="forzar_bloqueo" value="1" class="h-4 w-4" @checked(old('forzar_bloqueo'))>
          Forzar creacion aun con pagos pendientes
        </label>
        @error('forzar_bloqueo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror

        <div class="mt-3">
          <label class="form-label" for="override_reason">Motivo del override (obligatorio si se fuerza)</label>
          <textarea id="override_reason" name="override_reason" class="form-input" rows="3">{{ old('override_reason') }}</textarea>
          @error('override_reason')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
      </div>

      <div class="md:col-span-2">
        <button type="submit" class="btn btn-primary">Crear cita</button>
      </div>
    </form>
  </section>
</div>
@endsection

