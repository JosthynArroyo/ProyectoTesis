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
  // asegura contenedores para focus-within
  document.querySelectorAll('.form .grid > div').forEach(div => {
    if (!div.classList.contains('field-wrap')) div.classList.add('field-wrap');
  });
  // nombre de archivo en ayudas
  document.querySelectorAll('.form input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
      const help = input.closest('.field-wrap')?.querySelector('.help');
      if (help) help.textContent = input.files?.length ? input.files[0].name : '';
    });
  });
});
