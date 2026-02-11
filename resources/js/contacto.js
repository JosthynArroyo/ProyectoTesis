document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactoForm');
  const submitButton = document.getElementById('btnSubmit');
  const phoneInput = document.getElementById('telefono');

  if (phoneInput) {
    phoneInput.addEventListener('input', () => {
      const sanitized = phoneInput.value.replace(/[^0-9]/g, '').slice(0, 10);
      if (phoneInput.value !== sanitized) {
        phoneInput.value = sanitized;
      }
    });
  }

  if (form && submitButton) {
    form.addEventListener('submit', () => {
      submitButton.disabled = true;
    });
  }

  const firstError = document.querySelector('.err');
  if (firstError) {
    const field = firstError.closest('.form-group').querySelector('input,textarea,select');
    if (field) {
      field.focus();
      field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
});
