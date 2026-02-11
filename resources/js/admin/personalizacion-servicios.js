document.addEventListener('DOMContentLoaded', () => {
  const list = document.querySelector('[data-especialidad-list]');
  const template = document.querySelector('[data-especialidad-template]');
  const addButton = document.querySelector('[data-add-especialidad]');

  if (!list || !template || !addButton) return;

  const normalize = (value) =>
    (value || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');

  const initIconPicker = (picker) => {
    if (!picker || picker.dataset.ready === 'true') return;
    picker.dataset.ready = 'true';

    const search = picker.querySelector('[data-icon-search]');
    const listEl = picker.querySelector('[data-icon-list]');
    const hidden = picker.querySelector('[data-icon-value]');
    const preview = picker.querySelector('[data-icon-preview]');
    const label = picker.querySelector('[data-icon-selected-label]');
    const idLabel = picker.querySelector('[data-icon-selected-id]');
    const options = Array.from(picker.querySelectorAll('[data-icon-option]'));

    if (!search || !listEl || !hidden || !preview || !label || !idLabel || !options.length) return;

    const applySelection = (id) => {
      const option = options.find((item) => item.dataset.iconId === id);
      const labelText = option.dataset.iconLabel || 'Usar icono por defecto';
      hidden.value = id || '';
      label.textContent = labelText;
      idLabel.textContent = id ? id : 'Defecto';
      preview.innerHTML = id
        ? `<i class="${id}"></i>`
        : '<span class="text-xs text-slate-400">Sin icono</span>';

      options.forEach((item) => {
        const isSelected = item.dataset.iconId === id;
        item.classList.toggle('ring-2', isSelected);
        item.classList.toggle('ring-teal-500', isSelected);
        item.classList.toggle('border-teal-400', isSelected);
        item.classList.toggle('bg-teal-50', isSelected);
      });
    };

    const filterOptions = () => {
      const query = normalize(search.value);
      options.forEach((item) => {
        const haystack = normalize(
          `${item.dataset.iconLabel || ''} ${item.dataset.iconKeywords || ''} ${item.dataset.iconId || ''}`
        );
        const match = !query || haystack.includes(query);
        item.classList.toggle('hidden', !match);
      });
    };

    options.forEach((item) => {
      item.addEventListener('click', () => {
        applySelection(item.dataset.iconId || '');
      });
    });

    search.addEventListener('input', filterOptions);

    applySelection(hidden.value || '');
    filterOptions();
  };

  const initIconPickers = (root = document) => {
    root.querySelectorAll('[data-icon-picker]').forEach(initIconPicker);
  };

  initIconPickers();

  let index = Number(list.dataset.nextIndex || 0);

  addButton.addEventListener('click', () => {
    const html = template.innerHTML.replace(/__INDEX__/g, String(index));
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const node = wrapper.firstElementChild;
    if (node) {
      list.appendChild(node);
      index += 1;
      initIconPickers(node);
    }
  });

  list.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-item]');
    if (!removeButton) return;
    const row = removeButton.closest('[data-especialidad-row]');
    row.remove();
  });
});
