document.addEventListener('DOMContentLoaded', () => {
  const sanitizeNumeric = (input) => {
    const max = Number.parseInt(input.getAttribute('data-digits') || input.getAttribute('maxlength') || '0', 10);
    let value = input.value.replace(/\D+/g, '');
    if (max > 0) value = value.slice(0, max);
    if (input.value !== value) input.value = value;
  };

  document.querySelectorAll('input[data-digits]').forEach((input) => {
    sanitizeNumeric(input);
    input.addEventListener('input', () => sanitizeNumeric(input));
    input.addEventListener('blur', () => sanitizeNumeric(input));
    input.addEventListener('paste', () => {
      requestAnimationFrame(() => sanitizeNumeric(input));
    });
  });
});
