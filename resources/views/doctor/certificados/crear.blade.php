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
      <p class="text-xs uppercase tracking-widest text-slate-500">Paciente</p>
      <p class="mt-2 font-semibold text-slate-900">{{ $cita->paciente?->name ?? '-' }}</p>
      <p class="text-sm text-slate-600">{{ $cita->paciente?->dni ?: 'Documento no registrado' }}</p>
    </article>
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-slate-500">Doctor</p>
      <p class="mt-2 font-semibold text-slate-900">{{ $cita->doctor?->name ?? '-' }}</p>
      <p class="text-sm text-slate-600">{{ $cita->especialidad?->nombre ?? 'Especialidad no registrada' }}</p>
    </article>
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-slate-500">Cita</p>
      <p class="mt-2 font-semibold text-slate-900">#{{ $cita->id }}</p>
      <p class="text-sm text-slate-600">Estado: {{ $cita->estado }}</p>
    </article>
  </section>

  <form method="POST" action="{{ route('doctor.certificados.store', $cita) }}" class="card p-6 space-y-5">
    @csrf

    <div>
      <label for="texto_constancia" class="form-label">Texto clinico o constancia medica</label>
      <textarea id="texto_constancia" name="texto_constancia" required class="form-textarea min-h-40">{{ old('texto_constancia', $textoSugerido) }}</textarea>
      <p class="mt-2 text-xs text-slate-500">Este texto quedara como documento emitido y no se editara libremente despues de guardar.</p>
      @error('texto_constancia')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div>
        <label for="dias_reposo" class="form-label">Dias de reposo</label>
        <input id="dias_reposo" name="dias_reposo" type="number" min="0" max="365" value="{{ old('dias_reposo', 0) }}" class="form-input">
        @error('dias_reposo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label for="reposo_desde" class="form-label">Reposo desde</label>
        <input id="reposo_desde" name="reposo_desde" type="date" value="{{ old('reposo_desde') }}" class="form-input">
        @error('reposo_desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label for="reposo_hasta" class="form-label">Reposo hasta</label>
        <input id="reposo_hasta" name="reposo_hasta" type="date" value="{{ old('reposo_hasta') }}" class="form-input">
        @error('reposo_hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
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
      <button type="submit" class="btn btn-primary">
        <i class="ri-file-shield-2-line"></i> Emitir certificado
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection
