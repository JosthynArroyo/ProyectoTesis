document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toggle-eye, .btn-eye').forEach((button) => {
    const targetId = button.getAttribute('data-target');
    let input = null;

    if (targetId) {
      if (targetId.startsWith('#')) {
        input = document.querySelector(targetId);
      } else {
        input = document.getElementById(targetId);
      }
    }

    if (!input) {
      const wrap = button.closest('[data-password-wrap]');
      if (wrap) {
        input = wrap.querySelector('input[type=\"password\"], input[type=\"text\"]');
      }
    }

    const icon = button.querySelector('i');
    if (!input) return;

    button.addEventListener('click', () => {
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      button.classList.toggle('is-on', !isPassword);
      if (icon) {
        icon.classList.toggle('ri-eye-line', !isPassword);
        icon.classList.toggle('ri-eye-off-line', isPassword);
      }
    });
  });
});
