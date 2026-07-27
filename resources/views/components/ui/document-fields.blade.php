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
  <div id="{{ $nacWrapId }}" data-nacionalidad-wrap style="{{ $currentTipo === 'pasaporte' ? '' : 'display: none;' }}">
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

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-document-fields-wrap]').forEach(function (wrap) {
    const tipoSelect = wrap.querySelector('[data-tipo-doc-select]');
    const nacWrap = wrap.querySelector('[data-nacionalidad-wrap]');
    const nacSelect = wrap.querySelector('[data-nacionalidad-select]');
    const docLabel = wrap.querySelector('[data-doc-label]');
    const docInput = wrap.querySelector('[data-doc-input]');
    const docHelp = wrap.querySelector('[data-doc-help]');

    if (!tipoSelect || !docInput) return;

    function updateFields() {
      const isPasaporte = tipoSelect.value === 'pasaporte';

      if (nacWrap) {
        nacWrap.style.display = isPasaporte ? '' : 'none';
      }
      if (nacSelect) {
        if (isPasaporte) {
          nacSelect.setAttribute('required', 'required');
        } else {
          nacSelect.removeAttribute('required');
        }
      }

      if (isPasaporte) {
        if (docLabel) docLabel.innerHTML = 'Número de pasaporte <span class="text-rose-500">*</span>';
        docInput.setAttribute('inputmode', 'text');
        docInput.setAttribute('pattern', '[A-Za-z0-9-]{5,20}');
        docInput.setAttribute('minlength', '5');
        docInput.setAttribute('maxlength', '20');
        docInput.setAttribute('placeholder', 'AB123456');
        if (docHelp) docHelp.textContent = 'De 5 a 20 caracteres (letras, números o guion).';
      } else {
        if (docLabel) docLabel.innerHTML = 'Número de cédula <span class="text-rose-500">*</span>';
        docInput.setAttribute('inputmode', 'numeric');
        docInput.setAttribute('pattern', '\\d{10}');
        docInput.setAttribute('minlength', '10');
        docInput.setAttribute('maxlength', '10');
        docInput.setAttribute('placeholder', '1721820659');
        if (docHelp) docHelp.textContent = 'Ingresa exactamente 10 dígitos.';
      }
    }

    tipoSelect.addEventListener('change', function () {
      updateFields();
      if (tipoSelect.value === 'cedula' && docInput.value) {
        docInput.value = docInput.value.replace(/[^0-9]/g, '');
      } else if (tipoSelect.value === 'pasaporte' && docInput.value) {
        docInput.value = docInput.value.toUpperCase().replace(/\s+/g, '');
      }
    });

    docInput.addEventListener('input', function () {
      if (tipoSelect.value === 'pasaporte') {
        this.value = this.value.toUpperCase().replace(/\s+/g, '');
      }
    });

    updateFields();
  });
});
</script>
@endpush
@endonce
