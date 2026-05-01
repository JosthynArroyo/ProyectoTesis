import {
  createPreviewModalController,
  escapeHtml,
  getHiddenValue,
  getValue,
  isImageFile,
  normalizeKey,
  readFileAsDataUrl,
  resolveImageUrl,
} from './personalizacion-preview-utils';

document.addEventListener('DOMContentLoaded', () => {
  const formRoot = document.querySelector('[data-services-form]');

  if (!formRoot) return;

  const list = formRoot.querySelector('[data-especialidad-list]');
  const template = formRoot.querySelector('[data-especialidad-template]');
  const addButton = formRoot.querySelector('[data-add-especialidad]');
  const previewConfig = parseJson(formRoot.querySelector('[data-services-preview-config]')?.textContent || '{}');
  const modalController = createPreviewModalController(formRoot);
  const filePreviews = new Map();

  let renderToken = null;
  let index = Number(list?.dataset.nextIndex || 0);

  const imageMarkup = (url, alt, icon = 'ri-image-line', placeholder = '') => {
    if (url) {
      return `<img src="${escapeHtml(url)}" alt="${escapeHtml(alt)}">`;
    }

    return `
      <div class="public-site-preview__image-fallback">
        <i class="${escapeHtml(icon)}"></i>
        <p class="public-site-preview__text">${escapeHtml(placeholder || 'Imagen pendiente')}</p>
      </div>
    `;
  };

  const scheduleRender = () => {
    if (renderToken !== null) return;

    renderToken = window.requestAnimationFrame(() => {
      renderToken = null;
      renderPreview();
      modalController?.syncScale();
    });
  };

  const initIconPicker = (picker) => {
    if (!picker || picker.dataset.ready === 'true') return;
    picker.dataset.ready = 'true';

    const search = picker.querySelector('[data-icon-search]');
    const hidden = picker.querySelector('[data-icon-value]');
    const preview = picker.querySelector('[data-icon-preview]');
    const label = picker.querySelector('[data-icon-selected-label]');
    const idLabel = picker.querySelector('[data-icon-selected-id]');
    const options = Array.from(picker.querySelectorAll('[data-icon-option]'));

    if (!search || !hidden || !preview || !label || !idLabel || !options.length) return;

    const applySelection = (id) => {
      const option = options.find((item) => item.dataset.iconId === id);
      hidden.value = id || '';
      label.textContent = option?.dataset.iconLabel || 'Usar icono por defecto';
      idLabel.textContent = id || 'Defecto';
      preview.innerHTML = id
        ? `<i class="${escapeHtml(id)}"></i>`
        : '<span class="text-xs text-gray-400">Sin icono</span>';

      options.forEach((item) => {
        const isSelected = item.dataset.iconId === id;
        item.classList.toggle('ring-2', isSelected);
        item.classList.toggle('ring-teal-500', isSelected);
        item.classList.toggle('border-teal-400', isSelected);
        item.classList.toggle('bg-gray-100', isSelected);
      });

      scheduleRender();
    };

    const filterOptions = () => {
      const query = normalizeKey(search.value);
      options.forEach((item) => {
        const haystack = normalizeKey(
          `${item.dataset.iconLabel || ''} ${item.dataset.iconKeywords || ''} ${item.dataset.iconId || ''}`,
        );
        item.classList.toggle('hidden', Boolean(query) && !haystack.includes(query));
      });
    };

    options.forEach((item) => {
      item.addEventListener('click', () => applySelection(item.dataset.iconId || ''));
    });

    search.addEventListener('input', filterOptions);

    applySelection(hidden.value || '');
    filterOptions();
  };

  const initIconPickers = (root = formRoot) => {
    root.querySelectorAll('[data-icon-picker]').forEach(initIconPicker);
  };

  const rowFileKey = (row) => row.dataset.serviceId || row.dataset.rowKey || '';

  const updateInlineImagePreview = (input, imageUrl) => {
    if (input.name === 'services_hero_image') {
      const heroImage = formRoot.querySelector('[data-services-inline-image="hero"]');
      if (heroImage) {
        heroImage.src = imageUrl;
      }
      return;
    }

    const row = input.closest('[data-especialidad-row]');
    const inlineWrap = row?.querySelector('[data-public-preview-inline-image]');
    const image = inlineWrap?.querySelector('img');

    if (image) {
      image.src = imageUrl;
      return;
    }

    if (inlineWrap) {
      inlineWrap.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="Preview del servicio" class="w-full aspect-[4/3] object-cover">`;
    }
  };

  const handleFileInput = async (input) => {
    const file = input.files?.[0];
    if (!file) return;

    if (!isImageFile(file)) {
      input.value = '';
      input.setCustomValidity('Selecciona una imagen valida.');
      input.reportValidity();
      return;
    }

    input.setCustomValidity('');

    try {
      const imageUrl = await readFileAsDataUrl(file);
      const row = input.closest('[data-especialidad-row]');
      const key = row ? rowFileKey(row) : 'hero';

      if (key) {
        filePreviews.set(key, imageUrl);
      }

      updateInlineImagePreview(input, imageUrl);
      scheduleRender();
    } catch (_error) {
      input.setCustomValidity('No se pudo leer la imagen seleccionada.');
      input.reportValidity();
    }
  };

  const readRows = () =>
    Array.from(formRoot.querySelectorAll('[data-especialidad-row]'))
      .map((row, rowIndex) => {
        const nameField = row.querySelector('input[name$="[nombre]"]');
        const descriptionField = row.querySelector('textarea[name$="[descripcion]"]');
        const iconField = row.querySelector('input[data-icon-value]');
        const orderField = row.querySelector('input[name$="[orden]"]');
        const activeField = row.querySelector('input[type="checkbox"][name$="[activo]"]');
        const imagePathField = row.querySelector('input[type="hidden"][name$="[image_path]"]');
        const name = String(nameField?.value || '').trim();
        const meta = previewMetaForName(name);
        const key = rowFileKey(row);
        const imageUrl =
          filePreviews.get(key) ||
          resolveImageUrl(formRoot, imagePathField?.value || meta.image_url || previewConfig.fallback?.image_url || '');

        return {
          key: key || `row-${rowIndex}`,
          name,
          description: String(descriptionField?.value || '').trim(),
          icon: String(iconField?.value || meta.icon || previewConfig.fallback?.icon || 'ri-stethoscope-line').trim(),
          order: Number.parseInt(String(orderField?.value || rowIndex), 10) || 0,
          active: activeField ? activeField.checked : true,
          imageUrl,
          meta,
        };
      })
      .filter((item) => item.active && (item.name || item.description || item.imageUrl));

  const previewMetaForName = (name) => {
    const normalizedName = normalizeKey(name);
    const catalog = previewConfig.catalog || {};

    return catalog[normalizedName] || previewConfig.fallback || {};
  };

  const renderPreview = () => {
    if (!modalController?.previewRoot) return;

    const title = getValue(
      formRoot,
      'services_title',
      'Especialidades y servicios disponibles',
    );
    const subtitle = getValue(
      formRoot,
      'services_subtitle',
      'Explora las opciones de la clinica y agenda una cita segun los horarios registrados en el sistema.',
    );
    const ctaText = getValue(formRoot, 'services_cta_text', 'Agendar cita');
    const heroImage =
      filePreviews.get('hero') ||
      resolveImageUrl(
        formRoot,
        getHiddenValue(formRoot, 'services_hero_image_path', previewConfig.hero_image_url || ''),
      );
    const brand = previewConfig.branding || {};
    const footer = previewConfig.footer || {};
    const services = readRows().sort((left, right) => {
      if (left.order === right.order) {
        return left.name.localeCompare(right.name);
      }

      return left.order - right.order;
    });

    const cardsMarkup = services.length
      ? services
          .map((service) => {
            const meta = service.meta || {};
            return `
              <article class="public-site-preview__service-card">
                <div class="public-site-preview__service-image">
                  ${imageMarkup(
                    service.imageUrl,
                    service.name || 'Servicio',
                    'ri-image-line',
                    'Imagen del servicio',
                  )}
                </div>
                <div class="public-site-preview__service-card-copy">
                  <div class="public-site-preview__service-top">
                    <span class="public-site-preview__service-icon"><i class="${escapeHtml(service.icon)}"></i></span>
                    <span class="public-site-preview__badge">${escapeHtml(meta.tag_label || 'Especialidad')}</span>
                  </div>
                  <div>
                    <h3 class="public-site-preview__title">${escapeHtml(service.name || 'Especialidad')}</h3>
                    <p class="public-site-preview__text">${escapeHtml(
                      service.description || 'Descripcion de servicio pendiente.',
                    )}</p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <span class="public-site-preview__pill">${escapeHtml(meta.badge || 'Cita presencial')}</span>
                    <span class="public-site-preview__pill">${escapeHtml(meta.badge2 || 'Segun disponibilidad')}</span>
                  </div>
                  <span class="public-site-preview__button public-site-preview__button--secondary">${escapeHtml(
                    'Agendar',
                  )}</span>
                </div>
              </article>
            `;
          })
          .join('')
      : '<div class="public-site-preview__empty">Agrega al menos un servicio activo para ver las cards en esta vista previa.</div>';

    const footerLinks = (footer.links || [])
      .map((label) => `<span class="public-site-preview__footer-link">${escapeHtml(label)}</span>`)
      .join('');
    const footerLegal = (footer.legal || [])
      .map((label) => `<span class="public-site-preview__footer-link">${escapeHtml(label)}</span>`)
      .join('');
    const navigation = (previewConfig.navigation || [])
      .map((label) => `<span class="public-site-preview__nav-item">${escapeHtml(label)}</span>`)
      .join('');

    modalController.previewRoot.innerHTML = `
      <div class="public-site-preview">
        <div class="public-site-preview__shell">
          <section class="public-site-preview__card">
            <header class="public-site-preview__header">
              <div class="public-site-preview__brand">
                <div class="public-site-preview__brand-mark">
                  ${imageMarkup(brand.logo, brand.name || 'Clinica', 'ri-hospital-line', 'Logo')}
                </div>
                <div class="public-site-preview__brand-copy">
                  <p class="public-site-preview__title">${escapeHtml(brand.name || 'Nombre de la clinica')}</p>
                  <p class="public-site-preview__meta">${escapeHtml(brand.navbar_text || 'Tu salud, nuestra mision')}</p>
                </div>
              </div>
              <nav class="public-site-preview__nav">
                ${navigation || '<span class="public-site-preview__meta">Sin enlaces visibles</span>'}
              </nav>
              <span class="public-site-preview__ghost-button">${escapeHtml(brand.login_text || 'Ingresar')}</span>
            </header>

            <div class="public-site-preview__hero-grid">
              <div class="public-site-preview__hero-copy">
                <span class="public-site-preview__eyebrow">Servicios</span>
                <h1 class="public-site-preview__heading">${escapeHtml(title)}</h1>
                <p class="public-site-preview__text">${escapeHtml(subtitle)}</p>
                <div class="flex flex-wrap gap-3">
                  <span class="public-site-preview__chip">
                    <i class="ri-calendar-check-line"></i> Agenda segun disponibilidad
                  </span>
                  <span class="public-site-preview__chip">
                    <i class="ri-shield-check-line"></i> Especialidades activas del sistema
                  </span>
                </div>
                <div class="flex flex-wrap gap-3">
                  <span class="public-site-preview__button">${escapeHtml(ctaText)}</span>
                </div>
              </div>
              <div class="public-site-preview__hero-media">
                <div class="public-site-preview__media-frame">
                  ${imageMarkup(heroImage, 'Hero de servicios', 'ri-image-line', 'Imagen principal del hero')}
                </div>
              </div>
            </div>
          </section>

          <section class="public-site-preview__toolbar">
            <div class="public-site-preview__toolbar-row">
              <label class="public-site-preview__search">
                <i class="ri-search-line"></i>
                <input class="public-site-preview__search-input" type="text" value="Buscar servicio o especialidad..." readonly>
              </label>
              <div class="public-site-preview__tabs">
                <span class="public-site-preview__tab is-active">Todos</span>
                <span class="public-site-preview__tab">General</span>
                <span class="public-site-preview__tab">Especialidades</span>
                <span class="public-site-preview__tab">Diagnostico</span>
              </div>
            </div>
          </section>

          <section class="public-site-preview__panel">
            <div class="public-site-preview__panel-body">
              <div class="public-site-preview__cards-grid">
                ${cardsMarkup}
              </div>
            </div>
          </section>

          <section class="public-site-preview__footer">
            <div class="public-site-preview__footer-grid">
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">${escapeHtml(footer.name || 'Nombre de la clinica')}</p>
                <p class="public-site-preview__footer-text">${escapeHtml(
                  footer.text || '© 2026 - Todos los derechos reservados.',
                )}</p>
              </article>
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">Enlaces</p>
                <div class="public-site-preview__footer-links">
                  ${footerLinks || '<span class="public-site-preview__meta">Sin enlaces configurados</span>'}
                </div>
              </article>
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">Legales</p>
                <div class="public-site-preview__footer-links">
                  ${footerLegal || '<span class="public-site-preview__meta">Sin legales configurados</span>'}
                </div>
              </article>
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">Contacto</p>
                <p class="public-site-preview__contact-text">${escapeHtml(footer.address || '')}</p>
                <p class="public-site-preview__contact-text">${escapeHtml(footer.phone || '')}</p>
                <p class="public-site-preview__contact-text">${escapeHtml(footer.email || '')}</p>
              </article>
            </div>
          </section>
        </div>
      </div>
    `;
  };

  const addRow = () => {
    if (!list || !template) return;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index)).trim();
    const node = wrapper.firstElementChild;

    if (!node) return;

    list.appendChild(node);
    index += 1;
    initIconPickers(node);
    scheduleRender();
  };

  initIconPickers();
  renderPreview();

  addButton?.addEventListener('click', addRow);

  list?.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-item]');
    if (!removeButton) return;

    const row = removeButton.closest('[data-especialidad-row]');
    const key = row ? rowFileKey(row) : '';
    if (key) {
      filePreviews.delete(key);
    }
    row?.remove();
    scheduleRender();
  });

  formRoot.addEventListener('input', (event) => {
    if (event.target.matches('input[type="file"]')) return;
    scheduleRender();
  });

  formRoot.addEventListener('change', (event) => {
    if (event.target.matches('input[type="file"]')) {
      handleFileInput(event.target);
      return;
    }

    scheduleRender();
  });

});

function parseJson(raw) {
  try {
    return JSON.parse(raw);
  } catch (_error) {
    return {};
  }
}
