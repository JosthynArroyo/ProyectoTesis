@props([
    'model' => null,
    'tipoDocumento' => null,
    'nacionalidad' => null,
    'numeroDocumento' => null,
    'idPrefix' => '',
])

@php
    $m = $model ?? null;
    $currentTipo = old('tipo_documento', $tipoDocumento ?? ($m->tipo_documento ?? 'cedula'));
    if (! in_array($currentTipo, ['cedula', 'pasaporte'], true)) {
        $currentTipo = 'cedula';
    }
    $currentNac = old('nacionalidad', $nacionalidad ?? ($m->nacionalidad ?? ''));
    $currentDoc = old('dni', $numeroDocumento ?? ($m->dni ?? ''));
    $countries = \App\Support\CountryCatalog::getSelectOptions();
    $prefix = $idPrefix ? $idPrefix . '_' : '';
    $tipoId = $prefix . 'tipo_documento';
    $nacId = $prefix . 'nacionalidad';
    $docId = $prefix . 'dni';
    $nacWrapId = $prefix . 'nacionalidad_wrapper';
    $helpId = $prefix . 'dni_help';
    $labelId = $prefix . 'dni_label';
@endphp

<div class="col-span-full grid grid-cols-1 md:grid-cols-2 gap-4" data-document-fields-wrap>
  <!-- Tipo de Documento -->
  <div>
    <label for="{{ $tipoId }}" class="form-label">Tipo de documento <span class="text-rose-500">*</span></label>
    <select class="form-select" id="{{ $tipoId }}" name="tipo_documento" data-tipo-doc-select required>
      <option value="cedula" @selected($currentTipo === 'cedula')>Cédula</option>
      <option value="pasaporte" @selected($currentTipo === 'pasaporte')>Pasaporte</option>
    </select>
    @error('tipo_documento')<small class="text-xs text-rose-600 block mt-1">{{ $message }}</small>@enderror
  </div>

  <!-- Nacionalidad (Solo para Pasaporte) -->
  <div id="{{ $nacWrapId }}" class="{{ $currentTipo === 'pasaporte' ? '' : 'hidden' }}" data-nacionalidad-wrap>
    <label for="{{ $nacId }}" class="form-label">Nacionalidad <span class="text-rose-500">*</span></label>
    <select class="form-select" id="{{ $nacId }}" name="nacionalidad" data-nacionalidad-select {{ $currentTipo === 'pasaporte' ? 'required' : '' }}>
      <option value="">Seleccione una nacionalidad</option>
      @foreach($countries as $c)
        <option value="{{ $c['code'] }}" @selected($currentNac === $c['code'])>
          {{ $c['demonym'] }} ({{ $c['country'] }})
        </option>
      @endforeach
    </select>
    @error('nacionalidad')<small class="text-xs text-rose-600 block mt-1">{{ $message }}</small>@enderror
  </div>

  <!-- Número de Documento (Cédula o Pasaporte) -->
  <div class="{{ $currentTipo === 'pasaporte' ? '' : 'md:col-span-1' }}">
    <label for="{{ $docId }}" id="{{ $labelId }}" class="form-label" data-doc-label>
      {{ $currentTipo === 'pasaporte' ? 'Número de pasaporte' : 'Número de cédula' }} <span class="text-rose-500">*</span>
    </label>
    <input
      class="form-input uppercase-input-if-passport"
      id="{{ $docId }}"
      name="dni"
      value="{{ $currentDoc }}"
      inputmode="{{ $currentTipo === 'pasaporte' ? 'text' : 'numeric' }}"
      pattern="{{ $currentTipo === 'pasaporte' ? '[A-Za-z0-9-]{5,20}' : '\d{10}' }}"
      minlength="{{ $currentTipo === 'pasaporte' ? '5' : '10' }}"
      maxlength="{{ $currentTipo === 'pasaporte' ? '20' : '10' }}"
      placeholder="{{ $currentTipo === 'pasaporte' ? 'AB123456' : '1721820659' }}"
      data-doc-input
      required
    >
    <p class="mt-1 text-xs text-gray-500" id="{{ $helpId }}" data-doc-help>
      {{ $currentTipo === 'pasaporte' ? 'De 5 a 20 caracteres (letras, números o guion).' : 'Ingresa exactamente 10 dígitos.' }}
    </p>
    @error('dni')<small class="text-xs text-rose-600 block mt-1">{{ $message }}</small>@enderror
  </div>
</div>
