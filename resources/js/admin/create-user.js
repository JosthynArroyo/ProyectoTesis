// resources/js/admin/create-user.js
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form.form');
  if (form) {
    form.addEventListener('submit', () => {
      form.querySelectorAll('input, select, textarea').forEach(el => {
        if (!el.checkValidity()) el.setAttribute('aria-invalid', 'true');
        else el.removeAttribute('aria-invalid');
      });
    });
    form.addEventListener('input', e => {
      const el = e.target;
      if (el && 'checkValidity' in el) {
        if (el.checkValidity()) el.removeAttribute('aria-invalid');
      }
    });
  }
});
