// resources/js/servicios.js
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('svcGrid');
  const chips = Array.from(document.querySelectorAll('.chip'));
  const search = document.getElementById('svcSearch');
  const empty = document.getElementById('svcEmpty');

  if (!grid) return;

  const cards = Array.from(grid.querySelectorAll('.card'));

  let currentFilter = 'all';
  let currentQuery = '';

  function apply() {
    const q = currentQuery.trim().toLowerCase();
    let visibleCount = 0;
    cards.forEach(card => {
      const tags = (card.getAttribute('data-tags') || '').toLowerCase();
      const text = card.innerText.toLowerCase();

      const byFilter = currentFilter === 'all' || tags.includes(currentFilter);
      const byQuery = q === '' || text.includes(q);

      const isVisible = byFilter && byQuery;
      card.style.display = isVisible ? '' : 'none';
      if (isVisible) visibleCount += 1;
    });
    if (empty) empty.hidden = visibleCount > 0;
  }

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      currentFilter = chip.dataset.filter || 'all';
      apply();
    });
  });

  search.addEventListener('input', (e) => {
    currentQuery = e.target.value || '';
    apply();
  });
});
