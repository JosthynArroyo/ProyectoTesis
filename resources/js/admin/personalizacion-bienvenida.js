document.addEventListener('DOMContentLoaded', () => {
  const initRepeater = ({ listSelector, templateSelector, addSelector, rowSelector }) => {
    const list = document.querySelector(listSelector);
    const template = document.querySelector(templateSelector);
    const addButton = document.querySelector(addSelector);

    if (!list || !template || !addButton) return;

    let index = Number(list.dataset.nextIndex || 0);

    addButton.addEventListener('click', () => {
      const content = template.innerHTML.replace(/__INDEX__/g, String(index));
      const wrapper = document.createElement('div');
      wrapper.innerHTML = content.trim();
      const node = wrapper.firstElementChild;
      if (node) {
        list.appendChild(node);
        index += 1;
      }
      refreshPreview();
    });

    list.addEventListener('click', (event) => {
      const removeBtn = event.target.closest('[data-remove-item]');
      if (!removeBtn) return;
      const row = removeBtn.closest(rowSelector);
      row.remove();
      refreshPreview();
    });
  };

  initRepeater({
    listSelector: '[data-stat-list]',
    templateSelector: '[data-stat-template]',
    addSelector: '[data-add-stat]',
    rowSelector: '[data-stat-row]',
  });

  initRepeater({
    listSelector: '[data-slide-list]',
    templateSelector: '[data-slide-template]',
    addSelector: '[data-add-slide]',
    rowSelector: '[data-slide-row]',
  });

  initRepeater({
    listSelector: '[data-doctor-list]',
    templateSelector: '[data-doctor-template]',
    addSelector: '[data-add-doctor]',
    rowSelector: '[data-doctor-row]',
  });

  initRepeater({
    listSelector: '[data-price-list]',
    templateSelector: '[data-price-template]',
    addSelector: '[data-add-price]',
    rowSelector: '[data-price-row]',
  });

  const previewRoot = document.querySelector('[data-bienvenida-preview]');
  const formRoot = document.querySelector('[data-bienvenida-form]');
  const featuredOptionsEl = document.querySelector('[data-featured-options]');
  let featuredOptions = [];
  if (featuredOptionsEl) {
    try {
      featuredOptions = JSON.parse(featuredOptionsEl.textContent || '[]');
    } catch (error) {
      featuredOptions = [];
      console.warn('No se pudo parsear data-featured-options para la vista previa.', error);
    }
  }
  const filePreviews = new Map();

  const resolveImageUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const assetBase = previewRoot?.dataset.assetBase || '';
    const storageBase = previewRoot?.dataset.storageBase || '';
    if (path.startsWith('img/') || path.startsWith('storage/')) {
      return `${assetBase}${path}`;
    }
    return `${storageBase}/${path.replace(/^\//, '')}`;
  };

  const getSortedRows = (selector) => {
    const rows = Array.from(document.querySelectorAll(selector));
    return rows.sort((a, b) => {
      const aInput = a.querySelector('input[name$=\"[sort_order]\"]');
      const bInput = b.querySelector('input[name$=\"[sort_order]\"]');
      const aOrder = Number(aInput ? aInput.value : 0);
      const bOrder = Number(bInput ? bInput.value : 0);
      return aOrder - bOrder;
    });
  };

  const updateHeroPreview = () => {
    if (!previewRoot || !formRoot) return;
    const badgeInput = formRoot.querySelector('input[name=\"hero_badge\"]');
    const titleInput = formRoot.querySelector('input[name=\"hero_title\"]');
    const subtitleInput = formRoot.querySelector('textarea[name=\"hero_subtitle\"]');
    const primaryInput = formRoot.querySelector('input[name=\"hero_primary_text\"]');
    const secondaryInput = formRoot.querySelector('input[name=\"hero_secondary_text\"]');
    const showPrimaryInput = formRoot.querySelector('input[type=\"checkbox\"][name=\"hero_show_primary\"]');
    const showSecondaryInput = formRoot.querySelector('input[type=\"checkbox\"][name=\"hero_show_secondary\"]');

    const badge = badgeInput ? badgeInput.value : '';
    const title = titleInput ? titleInput.value : '';
    const subtitle = subtitleInput ? subtitleInput.value : '';
    const primaryText = primaryInput ? primaryInput.value : '';
    const secondaryText = secondaryInput ? secondaryInput.value : '';
    const showPrimary = showPrimaryInput ? showPrimaryInput.checked : false;
    const showSecondary = showSecondaryInput ? showSecondaryInput.checked : false;

    const badgeEl = previewRoot.querySelector('[data-preview-hero-badge]');
    const titleEl = previewRoot.querySelector('[data-preview-hero-title]');
    const subtitleEl = previewRoot.querySelector('[data-preview-hero-subtitle]');
    const primaryEl = previewRoot.querySelector('[data-preview-hero-primary]');
    const secondaryEl = previewRoot.querySelector('[data-preview-hero-secondary]');

    if (badgeEl) badgeEl.textContent = badge;
    if (titleEl) titleEl.textContent = title;
    if (subtitleEl) subtitleEl.textContent = subtitle;
    if (primaryEl) {
      primaryEl.textContent = primaryText;
      primaryEl.classList.toggle('hidden', !showPrimary);
    }
    if (secondaryEl) {
      secondaryEl.textContent = secondaryText;
      secondaryEl.classList.toggle('hidden', !showSecondary);
    }
  };

  const updateSlidesPreview = () => {
    if (!previewRoot) return;
    const container = previewRoot.querySelector('[data-preview-slides]');
    if (!container) return;

    const rows = getSortedRows('[data-slide-row]').filter((row) => {
      const checkbox = row.querySelector('input[type=\"checkbox\"][name$=\"[is_active]\"]');
      return checkbox ? checkbox.checked : true;
    });

    container.innerHTML = '';

    rows.forEach((row) => {
      const key = row.dataset.rowKey || '';
      const hiddenInput = row.querySelector('input[name$=\"[image_path]\"]');
      const hidden = hiddenInput ? hiddenInput.value : '';
      const imageUrl = filePreviews.get(key) || resolveImageUrl(hidden);

      const card = document.createElement('div');
      card.className = 'h-24 w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50';
      if (imageUrl) {
        card.innerHTML = `<img src=\"${imageUrl}\" class=\"h-full w-full object-cover\" alt=\"Preview\">`;
      } else {
        card.innerHTML = '<div class=\"flex h-full items-center justify-center text-xs text-slate-400\">Sin imagen</div>';
      }
      container.appendChild(card);
    });
  };

  const updateCardsPreview = () => {
    if (!previewRoot) return;
    const container = previewRoot.querySelector('[data-preview-cards]');
    if (!container) return;

    const rows = getSortedRows('[data-card-row]').filter((row) => {
      const checkbox = row.querySelector('input[type=\"checkbox\"][name$=\"[is_active]\"]');
      return checkbox ? checkbox.checked : true;
    });

    const styles = [
      { bg: 'bg-teal-50', text: 'text-teal-700' },
      { bg: 'bg-sky-50', text: 'text-sky-700' },
      { bg: 'bg-emerald-50', text: 'text-emerald-700' },
      { bg: 'bg-amber-50', text: 'text-amber-700' },
    ];

    container.innerHTML = '';

    rows.forEach((row, index) => {
      const title = row.querySelector('input[name$=\"[title]\"]').value || '';
      const value = row.querySelector('input[name$=\"[value]\"]').value || '';
      const description = row.querySelector('textarea[name$=\"[description]\"]').value || '';
      const icon = row.querySelector('input[name$=\"[icon]\"]').value || 'ri-information-line';
      const style = styles[index % styles.length];

      const card = document.createElement('div');
      card.className = 'flex items-start gap-3 rounded-2xl border border-slate-200 p-3';
      card.innerHTML = `
        <span class=\"inline-flex h-9 w-9 items-center justify-center rounded-xl ${style.bg} ${style.text}\">
          <i class=\"${icon}\"></i>
        </span>
        <div>
          <p class=\"text-sm font-semibold text-slate-900\">${title}</p>
          ${value ? `<p class=\"text-xs text-slate-500\">${value}</p>` : ''}
          <p class=\"text-xs text-slate-500\">${description}</p>
        </div>
      `;
      container.appendChild(card);
    });
  };

  const updateStatsPreview = () => {
    if (!previewRoot) return;
    const container = previewRoot.querySelector('[data-preview-stats]');
    if (!container) return;

    const rows = getSortedRows('[data-stat-row]').filter((row) => {
      const checkbox = row.querySelector('input[type=\"checkbox\"][name$=\"[is_active]\"]');
      return checkbox ? checkbox.checked : true;
    });

    container.innerHTML = '';

    rows.forEach((row) => {
      const label = row.querySelector('input[name$=\"[label]\"]').value || '';
      const value = row.querySelector('input[name$=\"[value]\"]').value || '';
      const note = row.querySelector('input[name$=\"[note]\"]').value || '';

      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 p-3';
      card.innerHTML = `
        <p class=\"text-xs uppercase tracking-wide text-slate-500\">${label}</p>
        <p class=\"mt-1 text-xl font-semibold text-slate-900\">${value}</p>
        <p class=\"text-xs text-slate-500\">${note}</p>
      `;
      container.appendChild(card);
    });
  };

  const updatePricesPreview = () => {
    if (!previewRoot || !formRoot) return;
    const container = previewRoot.querySelector('[data-preview-prices]');
    const subtitle = previewRoot.querySelector('[data-preview-prices-subtitle]');
    if (!container) return;

    const subtitleField = formRoot.querySelector('textarea[name=\"prices_subtitle\"]');
    const subtitleInput = subtitleField ? subtitleField.value : '';
    if (subtitle) subtitle.textContent = subtitleInput;

    const rows = getSortedRows('[data-price-row]').filter((row) => {
      const checkbox = row.querySelector('input[type=\"checkbox\"][name$=\"[is_active]\"]');
      return checkbox ? checkbox.checked : true;
    });

    container.innerHTML = '';

    rows.forEach((row) => {
      const service = row.querySelector('input[name$=\"[service]\"]').value || '';
      const price = row.querySelector('input[name$=\"[price]\"]').value || '';

      const line = document.createElement('div');
      line.className = 'flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-sm';
      line.innerHTML = `<span>${service}</span><span class=\"font-semibold\">${price}</span>`;
      container.appendChild(line);
    });
  };

  const updateDoctorsPreview = () => {
    if (!previewRoot) return;
    const container = previewRoot.querySelector('[data-preview-doctors]');
    if (!container) return;

    const rows = getSortedRows('[data-doctor-row]').filter((row) => {
      const checkbox = row.querySelector('input[type=\"checkbox\"][name$=\"[is_active]\"]');
      return checkbox ? checkbox.checked : true;
    });

    container.innerHTML = '';

    rows.forEach((row) => {
      const key = row.dataset.rowKey || '';
      const name = row.querySelector('input[name$=\"[name]\"]').value || '';
      const specialty = row.querySelector('input[name$=\"[specialty]\"]').value || '';
      const hiddenInput = row.querySelector('input[name$=\"[photo_path]\"]');
      const hidden = hiddenInput ? hiddenInput.value : '';
      const imageUrl = filePreviews.get(key) || resolveImageUrl(hidden);

      const card = document.createElement('div');
      card.className = 'overflow-hidden rounded-2xl border border-slate-200';
      card.innerHTML = `
        <div class=\"h-24 w-full bg-slate-50\">${imageUrl ? `<img src=\"${imageUrl}\" class=\"h-full w-full object-cover\" alt=\"${name}\">` : '<div class=\"flex h-full items-center justify-center text-xs text-slate-400\">Sin imagen</div>'}</div>
        <div class=\"p-3\">
          <p class=\"text-sm font-semibold\">${name}</p>
          <p class=\"text-xs text-slate-500\">${specialty}</p>
        </div>
      `;
      container.appendChild(card);
    });
  };

  const updateSpecialtiesPreview = () => {
    if (!previewRoot || !formRoot) return;
    const container = previewRoot.querySelector('[data-preview-specialties]');
    if (!container) return;

    const showBlockField = formRoot.querySelector('input[type=\"checkbox\"][name=\"show_services_block\"]');
    const showBlock = showBlockField ? showBlockField.checked : false;
    const selections = Array.from(formRoot.querySelectorAll('select[name=\"featured_specialties[]\"]'))
      .map((select) => select.value)
      .filter(Boolean);

    container.innerHTML = '';

    if (!showBlock) {
      container.innerHTML = '<div class=\"text-xs text-slate-500\">Bloque desactivado.</div>';
      return;
    }

    if (!selections.length) {
      container.innerHTML = '<div class=\"text-xs text-slate-500\">Selecciona 3 especialidades para mostrar.</div>';
      return;
    }

    selections.forEach((id) => {
      const esp = featuredOptions.find((item) => String(item.id) === String(id));
      if (!esp) return;

      const icon = esp.icono || 'ri-stethoscope-line';
      const card = document.createElement('div');
      card.className = 'flex items-start gap-3 rounded-2xl border border-slate-200 p-3';
      card.innerHTML = `
        <span class=\"inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700\">
          <i class=\"${icon}\"></i>
        </span>
        <div>
          <p class=\"text-sm font-semibold\">${esp.nombre}</p>
          <p class=\"text-xs text-slate-500\">${esp.descripcion || ''}</p>
        </div>
      `;
      container.appendChild(card);
    });
  };

  const refreshPreview = () => {
    updateHeroPreview();
    updateSlidesPreview();
    updateCardsPreview();
    updateStatsPreview();
    updatePricesPreview();
    updateDoctorsPreview();
    updateSpecialtiesPreview();
  };

  const handleFileInput = (input) => {
    const row = input.closest('[data-row-key]');
    if (!row) return;
    const key = row.dataset.rowKey || '';
    const file = input.files && input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
      filePreviews.set(key, reader.result);
      refreshPreview();
    };
    reader.readAsDataURL(file);
  };

  if (formRoot) {
    formRoot.addEventListener('input', (event) => {
      if (event.target.matches('input[type=\"file\"]')) return;
      refreshPreview();
    });
    formRoot.addEventListener('change', (event) => {
      if (event.target.matches('input[type=\"file\"]')) {
        handleFileInput(event.target);
        return;
      }
      refreshPreview();
    });
  }

  refreshPreview();
});
