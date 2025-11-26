document.addEventListener('DOMContentLoaded', () => {
  const chips = document.querySelectorAll('.filter-chip');

  const sync = (chip) => {
    const input = chip.querySelector('input[type="checkbox"]');
    const active = input.checked;
    chip.classList.toggle('is-active', active);
    chip.setAttribute('aria-pressed', String(active));
    const icon = chip.querySelector('.icon');
    if (icon) icon.textContent = active ? 'check_circle' : 'radio_button_unchecked';
  };

  chips.forEach((chip) => {
    const input = chip.querySelector('input[type="checkbox"]');
    sync(chip);
    input.addEventListener('change', () => sync(chip));
    chip.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        input.checked = !input.checked;
        sync(chip);
        // chip.closest('form')?.submit(); // si quieres aplicar al instante
      }
    });
  });

  window.openSuspend = (id) => {
    const row = document.getElementById('susp-row-' + id);
    if (row) row.style.display = 'table-row';
  };
  window.closeSuspend = (id) => {
    const row = document.getElementById('susp-row-' + id);
    if (row) row.style.display = 'none';
  };

  const mobileToggle = () => {
    const isMobile = window.matchMedia('(max-width: 720px)').matches;
    document.querySelectorAll('.users tbody tr').forEach((row) => {
      const name = row.querySelector('.user-name');
      if (!name) return;
      if (isMobile) {
        if (!name.dataset.bound) {
          name.addEventListener('click', () => {
            document.querySelectorAll('.users tbody tr.is-open').forEach((r) => {
              if (r !== row) r.classList.remove('is-open');
            });
            row.classList.toggle('is-open');
          });
          name.dataset.bound = '1';
        }
      } else {
        row.classList.remove('is-open');
      }
    });
  };

  mobileToggle();
  window.addEventListener('resize', mobileToggle);
});
