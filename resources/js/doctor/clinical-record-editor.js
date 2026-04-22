document.addEventListener('DOMContentLoaded', () => {
  const groups = document.querySelectorAll('[data-repeatable-group]');

  groups.forEach((group) => {
    const addButton = group.querySelector('[data-repeatable-add]');
    const target = group.querySelector('[data-repeatable-target]');
    const template = group.querySelector('template[data-repeatable-template]');

    if (!addButton || !target || !template) {
      return;
    }

    addButton.addEventListener('click', () => {
      const nextIndex = Number.parseInt(group.dataset.nextIndex ?? `${target.children.length}`, 10);
      const rowNumber = nextIndex + 1;
      const html = template.innerHTML
        .replace(/__INDEX__/g, `${nextIndex}`)
        .replace(/__NUMBER__/g, `${rowNumber}`);

      const fragment = document.createRange().createContextualFragment(html);
      target.appendChild(fragment);
      group.dataset.nextIndex = `${nextIndex + 1}`;

      let details = addButton.closest('details');
      while (details) {
        details.open = true;
        details = details.parentElement?.closest('details') ?? null;
      }

      const newItem = target.querySelector('[data-repeatable-item]:last-child');
      const firstField = newItem?.querySelector('input:not([type="hidden"]), select, textarea');
      firstField?.focus();
    });
  });
});
