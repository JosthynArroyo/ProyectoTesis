@extends('layouts.doctor')
@section('title', 'Generar Receta')
@section('activeSidebar', 'citas')
@section('header-title','Generar receta')
@section('header-subtitle','Crea y envia una receta')

@section('main')
<div class="rx-wrap space-y-6">
  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__meta">
      <strong>Paciente:</strong> {{ $cita->nombrePacienteReal() }} |
      <strong>Fecha cita:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
    </div>
  </div>

  <form method="POST" action="{{ route('doctor.recetas.store') }}" class="card p-6 space-y-4">
    @csrf
    <input type="hidden" name="cita_id" value="{{ $cita->id }}">

    <div class="grid gap-4">
      <div>
        <label for="diagnostico" class="form-label">Diagnóstico</label>
        <textarea id="diagnostico" name="diagnostico" required class="form-textarea">{{ old('diagnostico', $diagnosticoSugerido ?? '') }}</textarea>
        <p class="mt-2 text-xs text-gray-500">Tomado de los diagnosticos de la nota clinica firmada.</p>
        @error('diagnostico')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="medicamentos" class="form-label">Medicamentos (dosis y frecuencia)</label>
        <textarea id="medicamentos" name="medicamentos" required class="form-textarea">{{ old('medicamentos', $medicamentosSugeridos ?? '') }}</textarea>
        <p class="mt-2 text-xs text-gray-500">Se cargan automaticamente desde la medicacion activa del expediente cuando existe.</p>
        @error('medicamentos')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="indicaciones" class="form-label">Indicaciones adicionales</label>
        <textarea id="indicaciones" name="indicaciones" class="form-textarea" placeholder="Opcional">{{ old('indicaciones') }}</textarea>
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

