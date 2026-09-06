document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-pago-form]').forEach((form) => {
    const metodoSelect = form.querySelector('[data-metodo-select]');
    const comprobanteWrapper = form.querySelector('[data-comprobante-wrapper]');
    const comprobanteInput = form.querySelector('[data-comprobante-input]');
    const comprobanteError = form.querySelector('[data-comprobante-error]');
    const comprobantePreview = form.querySelector('[data-comprobante-preview]');
    const unconfirmedMsg = form.querySelector('[data-efectivo-msg-unconfirmed]');
    const confirmedMsg = form.querySelector('[data-efectivo-msg-confirmed]');
    const submitButton = form.querySelector('[data-submit-label]');

    if (!metodoSelect || !submitButton) {
      return;
    }

    if (comprobanteInput) {
      comprobanteInput.addEventListener('change', () => {
        if (comprobanteError) comprobanteError.classList.add('hidden');
        if (comprobantePreview) comprobantePreview.classList.add('hidden');

        const file = comprobanteInput.files ? comprobanteInput.files[0] : null;
        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png'];
        const ext = file.name.split('.').pop().toLowerCase();
        const validExts = ['jpg', 'jpeg', 'png'];

        if (!validTypes.includes(file.type) || !validExts.includes(ext) || file.size > 5 * 1024 * 1024) {
          if (comprobanteError) {
            comprobanteError.textContent = 'Formatos permitidos: JPG, JPEG y PNG. Tamaño máximo: 5 MB';
            comprobanteError.classList.remove('hidden');
          }
          comprobanteInput.value = '';
          return;
        }

        if (comprobantePreview) {
          const img = comprobantePreview.querySelector('img');
          if (img) {
            const reader = new FileReader();
            reader.onload = (e) => {
              img.src = e.target.result;
              comprobantePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
          }
        }
      });
    }

    form.addEventListener('submit', (e) => {
      if (submitButton.disabled) {
        e.preventDefault();
        return;
      }
      submitButton.disabled = true;
      setTimeout(() => { submitButton.disabled = false; }, 4000);
    });

    const applyMode = () => {
      const metodo = metodoSelect.value;
      const savedMetodo = form.getAttribute('data-saved-metodo');
      const isTransferencia = metodo === 'transferencia';
      const isEfectivo = metodo === 'efectivo';

      if (comprobanteWrapper) {
        comprobanteWrapper.classList.toggle('hidden', !isTransferencia);
      }

      if (comprobanteInput) {
        comprobanteInput.disabled = !isTransferencia;
        comprobanteInput.required = isTransferencia;
        if (!isTransferencia) {
          comprobanteInput.value = '';
          if (comprobantePreview) comprobantePreview.classList.add('hidden');
          if (comprobanteError) comprobanteError.classList.add('hidden');
        }
      }

      if (unconfirmedMsg) unconfirmedMsg.classList.add('hidden');
      if (confirmedMsg) confirmedMsg.classList.add('hidden');

      if (isEfectivo) {
        if (savedMetodo === 'efectivo') {
          if (confirmedMsg) confirmedMsg.classList.remove('hidden');
          submitButton.classList.add('hidden');
        } else {
          if (unconfirmedMsg) unconfirmedMsg.classList.remove('hidden');
          submitButton.classList.remove('hidden');
        }
      } else {
        submitButton.classList.remove('hidden');
      }

      submitButton.textContent = isTransferencia
        ? 'Guardar y enviar'
        : 'Confirmar que pagaré en clínica';
    };

    metodoSelect.addEventListener('change', applyMode);
    applyMode();
  });
});
