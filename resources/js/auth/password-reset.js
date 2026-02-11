document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-toggle="pw"]').forEach((button) => {
    const selector = button.getAttribute('data-target');
    const input = selector ? document.querySelector(selector) : null;
    const icon = button.querySelector('i');
    if (!input) return;

    button.addEventListener('click', () => {
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      if (icon) {
        icon.classList.toggle('ri-eye-line', !isPassword);
        icon.classList.toggle('ri-eye-off-line', isPassword);
      }
    });
  });
});
