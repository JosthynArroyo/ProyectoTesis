document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactoForm');
  const phoneInput = document.getElementById('telefono');
  const submitButtons = form ? Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')) : [];
  const loadingText = 'Enviando...';

  if (phoneInput) {
    phoneInput.addEventListener('input', () => {
      const sanitized = phoneInput.value.replace(/[^0-9]/g, '').slice(0, 10);
      if (phoneInput.value !== sanitized) {
        phoneInput.value = sanitized;
      }
    });
  }

  const getButtonLabel = (button) => button.querySelector('[data-contacto-submit-label]');

  const setButtonState = (button, locked) => {
    if (!button) {
      return;
    }

    const label = getButtonLabel(button);
    const isInput = button.tagName === 'INPUT';

    button.disabled = locked;
    button.setAttribute('aria-disabled', locked ? 'true' : 'false');
    button.classList.toggle('opacity-60', locked);
    button.classList.toggle('cursor-not-allowed', locked);

    if (label) {
      if (!label.dataset.originalText) {
        label.dataset.originalText = label.textContent || '';
      }
      label.textContent = locked ? loadingText : label.dataset.originalText;
      return;
    }

    if (isInput) {
      if (!button.dataset.originalValue) {
        button.dataset.originalValue = button.value;
      }
      button.value = locked ? loadingText : button.dataset.originalValue;
      return;
    }

    if (!button.dataset.originalHtml) {
      button.dataset.originalHtml = button.innerHTML;
    }

    button.innerHTML = locked ? loadingText : button.dataset.originalHtml;
  };

  const lockForm = () => {
    if (!form) {
      return;
    }

    form.dataset.contactoSubmitting = '1';
    submitButtons.forEach((button) => setButtonState(button, true));
  };

  const unlockForm = () => {
    if (!form) {
      return;
    }

    delete form.dataset.contactoSubmitting;
    submitButtons.forEach((button) => setButtonState(button, false));
  };

  if (form) {
    form.addEventListener('submit', (event) => {
      if (form.dataset.contactoSubmitting === '1') {
        event.preventDefault();
        return;
      }

      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        if (typeof form.reportValidity === 'function') {
          form.reportValidity();
        }
        return;
      }

      lockForm();
    });
  }

  window.addEventListener('pageshow', unlockForm);

  const firstError = document.querySelector('.err');
  if (firstError) {
    const field = firstError.closest('.form-group')?.querySelector('input,textarea,select');
    if (field) {
      field.focus();
      field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
});
