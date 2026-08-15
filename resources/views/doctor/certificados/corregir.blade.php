@extends('layouts.doctor')
@section('title', 'Corregir certificado medico')
@section('activeSidebar', 'citas')
@section('header-title','Corregir certificado medico')
@section('header-subtitle','Emitir nueva version corregida del certificado')

@section('main')
<div class="space-y-6">
  @if ($errors->any())
    <x-ui.alert tone="error">
      <div class="space-y-1">
        <p class="font-semibold">No se pudo emitir la correccion del certificado.</p>
        <ul class="list-disc pl-5 text-sm">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </x-ui.alert>
  @endif

  <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
    <p class="font-semibold text-sm flex items-center gap-1.5">
      <i class="ri-alert-line text-lg"></i> Aviso de correccion de certificado
    </p>
    <p class="mt-1 text-xs text-amber-800 leading-relaxed">
      El certificado original (<strong>{{ $certificado->codigo }}</strong>, Versión {{ $certificado->version ?: 1 }}) no sera modificado. Al confirmar se emitira una nueva version y el documento actual quedara registrado como reemplazado.
    </p>
  </div>

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

  @php($reposoEsObligatorio = (int) old('dias_reposo', $certificado->dias_reposo) > 0)
  <form method="POST" action="{{ route('doctor.certificados.store-corregido', $certificado) }}" class="card p-6 space-y-5" data-certificado-reposo-form>
    @csrf

    <div>
      <label for="motivo_correccion" class="form-label">Motivo de la correccion *</label>
      <textarea id="motivo_correccion" name="motivo_correccion" required class="form-textarea min-h-24" placeholder="Explique la razon por la cual se corrige este certificado (ej. Error en dias de reposo, corrección de texto clinico, etc.)">{{ old('motivo_correccion') }}</textarea>
      <p class="mt-1 text-xs text-gray-500">Este motivo quedara registrado en el historial de trazabilidad del documento.</p>
      @error('motivo_correccion')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </div>

    <div>
      <label for="texto_constancia" class="form-label">Texto clinico o constancia medica</label>
      <textarea id="texto_constancia" name="texto_constancia" required class="form-textarea min-h-40">{{ old('texto_constancia', $certificado->texto_constancia) }}</textarea>
      @error('texto_constancia')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div>
        <label for="dias_reposo" class="form-label">Dias de reposo</label>
        <input id="dias_reposo" name="dias_reposo" type="number" min="0" max="365" value="{{ old('dias_reposo', $certificado->dias_reposo) }}" class="form-input">
        @error('dias_reposo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label for="reposo_desde" class="form-label">
          Reposo desde <span class="text-rose-600 {{ $reposoEsObligatorio ? '' : 'hidden' }}" data-reposo-required-indicator aria-hidden="true">*</span>
        </label>
        <input id="reposo_desde" name="reposo_desde" type="date" value="{{ old('reposo_desde', $certificado->reposo_desde?->format('Y-m-d')) }}" class="form-input" aria-describedby="reposo-fechas-requeridas" aria-required="{{ $reposoEsObligatorio ? 'true' : 'false' }}" data-reposo-date @required($reposoEsObligatorio)>
        @error('reposo_desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label for="reposo_hasta" class="form-label">
          Reposo hasta <span class="text-rose-600 {{ $reposoEsObligatorio ? '' : 'hidden' }}" data-reposo-required-indicator aria-hidden="true">*</span>
        </label>
        <input id="reposo_hasta" name="reposo_hasta" type="date" value="{{ old('reposo_hasta', $certificado->reposo_hasta?->format('Y-m-d')) }}" class="form-input" aria-describedby="reposo-fechas-requeridas" aria-required="{{ $reposoEsObligatorio ? 'true' : 'false' }}" data-reposo-date @required($reposoEsObligatorio)>
        @error('reposo_hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <p id="reposo-fechas-requeridas" class="text-sm font-medium text-rose-700 md:col-span-3 {{ $reposoEsObligatorio ? '' : 'hidden' }}" data-reposo-required-message aria-live="polite">
        Al indicar dias de reposo, las fechas "Reposo desde" y "Reposo hasta" son obligatorias.
      </p>
    </div>

    <div>
      <label for="observaciones" class="form-label">Observaciones o recomendaciones</label>
      <textarea id="observaciones" name="observaciones" class="form-textarea">{{ old('observaciones', $certificado->observaciones) }}</textarea>
      @error('observaciones')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </div>

    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.certificados.show', $certificado) }}" class="btn btn-ghost">Cancelar</a>
      </x-slot>
      <button type="submit" class="btn btn-primary" data-action-lock-title="Emitiendo certificado corregido...">
        <i class="ri-file-shield-2-line"></i> Emitir certificado corregido
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('[data-certificado-reposo-form]');
  if (!form) return;

  const diasReposo = form.querySelector('[name="dias_reposo"]');
  const fechasReposo = form.querySelectorAll('[data-reposo-date]');
  const indicadores = form.querySelectorAll('[data-reposo-required-indicator]');
  const mensaje = form.querySelector('[data-reposo-required-message]');

  function actualizarObligatoriedadReposo() {
    const requiereFechas = Number(diasReposo.value) > 0;

    fechasReposo.forEach(function (campo) {
      campo.toggleAttribute('required', requiereFechas);
      campo.setAttribute('aria-required', requiereFechas ? 'true' : 'false');
    });
    indicadores.forEach(function (indicador) {
      indicador.classList.toggle('hidden', !requiereFechas);
    });
    mensaje.classList.toggle('hidden', !requiereFechas);
  }

  diasReposo.addEventListener('input', actualizarObligatoriedadReposo);
  diasReposo.addEventListener('change', actualizarObligatoriedadReposo);
  actualizarObligatoriedadReposo();
});
</script>
@endpush
