@extends('layouts.doctor')
@section('title', 'Generar Receta')
@section('activeSidebar', 'citas')
@section('header-title','Generar receta')
@section('header-subtitle','Crea y envia una receta')

@section('content')
<div class="rx-wrap space-y-6">
  <div class="card p-6">
    <h1 class="text-2xl font-semibold text-slate-900">Generar Receta</h1>
    <div class="mt-2 text-sm text-slate-600">
      <strong>Paciente:</strong> {{ optional($cita->paciente)->name ?? '-' }} |
      <strong>Fecha cita:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
    </div>
  </div>

  @if ($errors->any())
    <x-ui.alert tone="error" class="rx-alert">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-ui.alert>
  @endif

  <form method="POST" action="{{ route('doctor.recetas.store') }}" class="card p-6 space-y-4">
    @csrf
    <input type="hidden" name="cita_id" value="{{ $cita->id }}">

    <div class="grid gap-4">
      <div>
        <label for="diagnostico" class="form-label">Diagnóstico / Motivo</label>
        <textarea id="diagnostico" name="diagnostico" required class="form-textarea">{{ old('diagnostico') }}</textarea>
        @error('diagnostico')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="medicamentos" class="form-label">Medicamentos (dosis y frecuencia)</label>
        <textarea id="medicamentos" name="medicamentos" required class="form-textarea">{{ old('medicamentos') }}</textarea>
        @error('medicamentos')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="indicaciones" class="form-label">Indicaciones adicionales</label>
        <textarea id="indicaciones" name="indicaciones" required class="form-textarea">{{ old('indicaciones') }}</textarea>
        @error('indicaciones')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>

    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Cancelar</a>
      </x-slot>
      <button type="submit" class="btn btn-primary">
        <i class="ri-medicine-bottle-line"></i> Generar y enviar
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection
