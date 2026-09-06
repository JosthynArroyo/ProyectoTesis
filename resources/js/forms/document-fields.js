export function initDocumentFields(root = document) {
  root.querySelectorAll('[data-document-fields-wrap]').forEach((wrap) => {
    if (wrap.dataset.documentFieldsReady === '1') {
      return;
    }
    wrap.dataset.documentFieldsReady = '1';

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
        nacWrap.classList.toggle('hidden', !isPasaporte);
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

    tipoSelect.addEventListener('change', () => {
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
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initDocumentFields(), { once: true });
} else {
  initDocumentFields();
}
