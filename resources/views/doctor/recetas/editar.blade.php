@extends('layouts.doctor')
@section('title', 'Editar Receta')
@section('activeSidebar', 'citas')
@section('header-title','Editar receta')
@section('header-subtitle','Actualiza la receta y notifica al paciente')

@section('main')
<div class="rx-wrap space-y-6" @if(session('ask_resend')) data-ask-resend="1" @endif>
  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__meta">
      <strong>Paciente:</strong> {{ optional($cita->paciente)->name ?? '-' }}
      &nbsp;|&nbsp;
      <strong>Fecha cita:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
      @if($receta->enviado_en)
        &nbsp;|&nbsp;
        <strong>Último envío:</strong> {{ $receta->enviado_en->format('d/m/Y H:i') }}
      @endif
    </div>

    <div class="panel-action-bar__actions">
      @if($receta->pdf_path)
        <a class="btn btn-outline" href="{{ route('doctor.recetas.download', $cita->id) }}">
          <i class="ri-download-2-line"></i> Descargar PDF
        </a>
      @endif
      <span id="reenviar-pill" class="badge success">Reenviar activado</span>
    </div>
  </div>

  @if (session('success'))
    <x-ui.alert tone="success" class="rx-alert">{{ session('success') }}</x-ui.alert>
  @endif
  @if (session('info'))
    <x-ui.alert tone="warning" class="rx-alert">{{ session('info') }}</x-ui.alert>
  @endif

  <form id="form-receta" method="POST" action="{{ route('doctor.recetas.update') }}" class="card p-6 space-y-4">
    @csrf
    <input type="hidden" name="cita_id" value="{{ $cita->id }}">

    <div class="grid gap-4">
      <div>
        <label for="diagnostico" class="form-label">Diagnóstico / Motivo</label>
        <textarea id="diagnostico" name="diagnostico" required class="form-textarea">{{ old('diagnostico', $receta->diagnostico) }}</textarea>
        @error('diagnostico')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="medicamentos" class="form-label">Medicamentos (dosis y frecuencia)</label>
        <textarea id="medicamentos" name="medicamentos" required class="form-textarea">{{ old('medicamentos', $receta->medicamentos) }}</textarea>
        @error('medicamentos')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="indicaciones" class="form-label">Indicaciones adicionales</label>
        <textarea id="indicaciones" name="indicaciones" required class="form-textarea">{{ old('indicaciones', $receta->indicaciones) }}</textarea>
        @error('indicaciones')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
      <label class="flex items-center gap-2">
        <input type="hidden" name="regenerar_pdf" value="0">
        <input type="checkbox" name="regenerar_pdf" value="1" {{ old('regenerar_pdf') ? 'checked' : '' }}>
        Regenerar PDF
      </label>
      <label class="flex items-center gap-2">
        <input type="hidden" name="reenviar" value="0">
        <input type="checkbox" id="reenviar" name="reenviar" value="1" {{ old('reenviar') ? 'checked' : '' }}>
        Reenviar correo al paciente
      </label>
      @error('regenerar_pdf')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      @error('reenviar')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      @if($receta->pdf_path)
        <small class="text-xs text-gray-500">Archivo actual: {{ $receta->pdf_path }}</small>
      @endif
    </div>
    <div id="reenviar-note" class="text-xs text-gray-500">Al guardar, se enviará la <strong>receta actualizada</strong> al paciente.</div>

    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Cancelar</a>
      </x-slot>
      <button type="submit" id="btn-guardar" class="btn btn-primary">
        <i class="ri-save-line"></i>
        <span class="btn-text">Guardar cambios</span>
      </button>
    </x-ui.form-actions>
  </form>

  <form id="form-resend-postsave" method="POST" action="{{ route('doctor.recetas.resend', $cita->id) }}" hidden>
    @csrf
  </form>
</div>

@endsection

@push('scripts')
  @vite('resources/js/doctor/recetas-editar.js')
@endpush

