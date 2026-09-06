@extends('layouts.doctor')
@section('title', 'Emitir certificado medico')
@section('activeSidebar', 'citas')
@section('header-title','Emitir certificado medico')
@section('header-subtitle','Documento clinico asociado a una cita realizada')

@section('main')
<div class="space-y-6">
  @if ($errors->any())
    <x-ui.alert tone="error">
      <div class="space-y-1">
        <p class="font-semibold">No se pudo emitir el certificado.</p>
        <ul class="list-disc pl-5 text-sm">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </x-ui.alert>
  @endif

  <section class="grid gap-4 lg:grid-cols-3">
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-gray-500">Paciente</p>
      <p class="mt-2 font-semibold text-gray-900">{{ $cita->nombrePacienteReal() }}</p>
      <p class="text-sm text-gray-600">{{ $cita->dniPacienteReal() !== 'N/D' ? 'DNI: ' . $cita->dniPacienteReal() : 'Documento no registrado' }}</p>
      @if($cita->dependiente_id && $cita->paciente)
        <p class="mt-1 text-xs text-gray-500">Titular/Responsable: {{ $cita->paciente->name }}</p>
      @endif
    </article>
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-gray-500">Doctor</p>
      <p class="mt-2 font-semibold text-gray-900">{{ $cita->doctor?->name ?? '-' }}</p>
      <p class="text-sm text-gray-600">{{ $cita->especialidad?->nombre ?? 'Especialidad no registrada' }}</p>
    </article>
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-gray-500">Cita</p>
      <p class="mt-2 font-semibold text-gray-900">#{{ $cita->id }}</p>
      <p class="text-sm text-gray-600">Estado: {{ $cita->estado }}</p>
    </article>
  </section>

  @php($reposoEsObligatorio = (int) old('dias_reposo', 0) > 0)
  <form method="POST" action="{{ route('doctor.certificados.store', $cita) }}" class="card p-6 space-y-5" data-certificado-reposo-form>
    @csrf

    <div>
      <label for="texto_constancia" class="form-label">Texto clinico o constancia medica</label>
      <textarea id="texto_constancia" name="texto_constancia" required class="form-textarea min-h-40">{{ old('texto_constancia', $textoSugerido) }}</textarea>
      <p class="mt-2 text-xs text-gray-500">Este texto quedara como documento emitido y no se editara libremente despues de guardar.</p>
      @error('texto_constancia')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div>
        <label for="dias_reposo" class="form-label">Dias de reposo</label>
        <input id="dias_reposo" name="dias_reposo" type="number" min="0" max="365" value="{{ old('dias_reposo', 0) }}" class="form-input">
        @error('dias_reposo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label for="reposo_desde" class="form-label">
          Reposo desde <span class="text-rose-600 {{ $reposoEsObligatorio ? '' : 'hidden' }}" data-reposo-required-indicator aria-hidden="true">*</span>
        </label>
        <input id="reposo_desde" name="reposo_desde" type="date" value="{{ old('reposo_desde') }}" class="form-input" aria-describedby="reposo-fechas-requeridas" aria-required="{{ $reposoEsObligatorio ? 'true' : 'false' }}" data-reposo-date @required($reposoEsObligatorio)>
        @error('reposo_desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label for="reposo_hasta" class="form-label">
          Reposo hasta <span class="text-rose-600 {{ $reposoEsObligatorio ? '' : 'hidden' }}" data-reposo-required-indicator aria-hidden="true">*</span>
        </label>
        <input id="reposo_hasta" name="reposo_hasta" type="date" value="{{ old('reposo_hasta') }}" class="form-input" aria-describedby="reposo-fechas-requeridas" aria-required="{{ $reposoEsObligatorio ? 'true' : 'false' }}" data-reposo-date @required($reposoEsObligatorio)>
        @error('reposo_hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <p id="reposo-fechas-requeridas" class="text-sm font-medium text-rose-700 md:col-span-3 {{ $reposoEsObligatorio ? '' : 'hidden' }}" data-reposo-required-message aria-live="polite">
        Al indicar dias de reposo, las fechas "Reposo desde" y "Reposo hasta" son obligatorias.
      </p>
    </div>

    <div>
      <label for="observaciones" class="form-label">Observaciones o recomendaciones</label>
      <textarea id="observaciones" name="observaciones" class="form-textarea">{{ old('observaciones') }}</textarea>
      @error('observaciones')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </div>

    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Cancelar</a>
      </x-slot>
      <button type="submit" class="btn btn-primary" data-action-lock-title="Emitiendo certificado médico...">
        <i class="ri-file-shield-2-line"></i> Emitir certificado
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/doctor/certificados-form.js')
@endpush
