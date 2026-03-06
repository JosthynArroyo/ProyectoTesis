document.addEventListener('click', (event) => {
  const chip = event.target.closest('[data-motivo-chip]');
  if (!chip) return;

  event.preventDefault();

  const group = chip.closest('[data-motivo-chip-group]');
  const targetSelector = group?.dataset.target || '';
  const form = chip.closest('form');

  let input = null;
  if (targetSelector) {
    input = document.querySelector(targetSelector);
  }
  if (!input && form) {
    input = form.querySelector('input[name="motivo_consulta"], textarea[name="motivo_consulta"]');
  }
  if (!input) return;

  const value = (chip.getAttribute('data-motivo-chip') || chip.textContent || '').trim();
  if (!value) return;

  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.focus();

  if (typeof input.setSelectionRange === 'function') {
    const pos = input.value.length;
    input.setSelectionRange(pos, pos);
  }
});

