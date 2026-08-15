import {
  createPreviewModalController,
  escapeHtml,
  escapeSelector,
  getHiddenValue,
  getValue,
  isImageFile,
  normalizeKey,
  resolveImageUrl,
} from './personalizacion-preview-utils';

document.addEventListener('DOMContentLoaded', () => {
  const formRoot = document.querySelector('[data-services-form]');

  if (!formRoot) return;

  const form = formRoot.closest('form') || formRoot.querySelector('form');

  if (!form) return;

  const uploadLimitsEnabled = formRoot.dataset.uploadLimitsEnabled === '1';
  const maxFileBytes = Number(formRoot.dataset.uploadMaxFileBytes || 0);
  const maxTotalBytes = Number(formRoot.dataset.uploadMaxTotalBytes || 0);
  const list = formRoot.querySelector('[data-especialidad-list]');
  const template = formRoot.querySelector('[data-especialidad-template]');
  const addButton = formRoot.querySelector('[data-add-especialidad]');
  const previewConfig = parseJson(formRoot.querySelector('[data-services-preview-config]')?.textContent || '{}');
  const modalController = createPreviewModalController(formRoot);
  const filePreviews = new Map();
  const uploadAlert = formRoot.querySelector('[data-upload-alert]');

  let renderToken = null;
  let index = Number(list?.dataset.nextIndex || 0);
  const fileInputs = () => Array.from(formRoot.querySelectorAll('input[type="file"]'));

  const clearUploadAlert = () => {
    if (!uploadAlert) return;
    uploadAlert.hidden = true;
    uploadAlert.textContent = '';
  };

  const showUploadAlert = (message) => {
    if (!uploadAlert) return;
    uploadAlert.textContent = message;
    uploadAlert.hidden = false;
  };

  const validateUploadLimits = ({ announce = false } = {}) => {
    if (!uploadLimitsEnabled) {
      return true;
    }

    let totalBytes = 0;

    for (const input of fileInputs()) {
      input.setCustomValidity('');

      const file = input.files?.[0];
      if (!file) continue;

      if (!isImageFile(file)) {
        const message = 'Selecciona una imagen valida.';
        input.setCustomValidity(message);
        if (announce) {
          showUploadAlert(message);
          input.reportValidity();
        }
        return false;
      }

      if (maxFileBytes > 0 && file.size > maxFileBytes) {
        const message = `El archivo "${file.name}" supera el limite maximo permitido de 10 MB.`;
        input.setCustomValidity(message);
        if (announce) {
          showUploadAlert(message);
          input.reportValidity();
        }
        return false;
      }

      totalBytes += file.size;
    }

    if (maxTotalBytes > 0 && totalBytes > maxTotalBytes) {
      const message = 'Las imagenes seleccionadas superan el limite total permitido de 50 MB. Reduce el tamano o selecciona menos imagenes.';
      if (announce) {
        showUploadAlert(message);
        uploadAlert?.focus();
      }
      return false;
    }

    clearUploadAlert();
    return true;
  };

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

  const rowFileKey = (row) => {
    if (!row) return '';
    if (row.dataset.previewTarget) return row.dataset.previewTarget;
    if (row.dataset.serviceId) return `service-${row.dataset.serviceId}`;
    if (row.dataset.rowKey) {
      return String(row.dataset.rowKey).startsWith('service-')
        ? row.dataset.rowKey
        : `service-${row.dataset.rowKey}`;
    }
    return '';
  };

  const inputPreviewTarget = (input) => {
    if (input.name === 'services_hero_image') {
      return input.dataset.previewTarget || 'services-hero';
    }

    const row = input.closest('[data-especialidad-row]');
    return input.dataset.previewTarget || rowFileKey(row);
  };

  const findPreviewImage = (target) => {
    if (!target) return null;

    return (
      formRoot.querySelector(`img[data-preview-id="${escapeSelector(target)}"]`) ||
      formRoot.querySelector(`[data-preview-id="${escapeSelector(target)}"]`)
    );
  };

  const revokePreviewUrl = (key) => {
    const previousUrl = filePreviews.get(key);
    if (previousUrl && previousUrl.startsWith('blob:')) {
      URL.revokeObjectURL(previousUrl);
    }
    filePreviews.delete(key);
  };

  const setFilePreview = (key, file) => {
    if (!file) {
      revokePreviewUrl(key);
      return '';
    }

    revokePreviewUrl(key);
    const nextUrl = URL.createObjectURL(file);
    filePreviews.set(key, nextUrl);
    return nextUrl;
  };

  const updateInlineImagePreview = (input, imageUrl) => {
    const target = inputPreviewTarget(input);
    const previewImage = findPreviewImage(target);

    if (previewImage?.tagName === 'IMG') {
      previewImage.removeAttribute('srcset');
      previewImage.removeAttribute('sizes');
      previewImage.src = imageUrl;
      return;
    }

    const row = input.closest('[data-especialidad-row]');
    const inlineWrap = row?.querySelector('[data-public-preview-inline-image]');
    const image = inlineWrap?.querySelector('img');

    if (image) {
      image.removeAttribute('srcset');
      image.removeAttribute('sizes');
      image.src = imageUrl;
      return;
    }

    if (inlineWrap) {
      inlineWrap.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="Preview del servicio" class="w-full aspect-[4/3] object-cover" data-preview-id="${escapeHtml(target)}">`;
    }
  };

  const resolveCurrentPreviewUrl = (input) => {
    if (input.name === 'services_hero_image') {
      const heroWrap = formRoot.querySelector('[data-public-preview-inline-image]');
      return (
        filePreviews.get('services-hero') ||
        heroWrap?.dataset?.currentLargeUrl ||
        heroWrap?.dataset?.currentUrl ||
        heroWrap?.dataset?.currentMediumUrl ||
        previewConfig.hero_image_url ||
        ''
      );
    }

    const row = input.closest('[data-especialidad-row]');
    const key = rowFileKey(row);
    const imagePathField = row?.querySelector('input[type="hidden"][name$="[image_path]"]');
    const meta = previewMetaForName(String(row?.querySelector('input[name$="[nombre]"]')?.value || '').trim());

    return (
      filePreviews.get(key) ||
      row?.dataset?.currentMediumUrl ||
      row?.dataset?.currentUrl ||
      (imagePathField?.value ? resolveImageUrl(formRoot, imagePathField.value) : '') ||
      meta.image_url ||
      previewConfig.fallback?.image_url ||
      ''
    );
  };

  const handleFileInput = (input) => {
    const row = input.closest('[data-especialidad-row]');
    const key = inputPreviewTarget(input);
    const file = input.files?.[0];
    if (!file) {
      revokePreviewUrl(key);
      updateInlineImagePreview(input, resolveCurrentPreviewUrl(input));
      scheduleRender();
      if (uploadLimitsEnabled) {
        validateUploadLimits();
      }
      return;
    }

    if (!isImageFile(file)) {
      input.value = '';
      input.setCustomValidity('Selecciona una imagen valida.');
      input.reportValidity();
      updateInlineImagePreview(input, resolveCurrentPreviewUrl(input));
      return;
    }

    input.setCustomValidity('');

    if (uploadLimitsEnabled && file.size > maxFileBytes && maxFileBytes > 0) {
      const message = `El archivo "${file.name}" supera el limite maximo permitido de 10 MB.`;
      input.setCustomValidity(message);
      showUploadAlert(message);
      input.reportValidity();
      updateInlineImagePreview(input, resolveCurrentPreviewUrl(input));
      return;
    }

    try {
      const imageUrl = setFilePreview(key, file);

      updateInlineImagePreview(input, imageUrl);
      scheduleRender();

      if (uploadLimitsEnabled) {
        validateUploadLimits();
      }
    } catch (_error) {
      input.setCustomValidity('No se pudo leer la imagen seleccionada.');
      input.reportValidity();
      updateInlineImagePreview(input, resolveCurrentPreviewUrl(input));
    }
  };

  const readRows = () => {
    const rows = Array.from(formRoot.querySelectorAll('[data-especialidad-row]'));
    const anyChecked = rows.some((row) => {
      const cb = row.querySelector('input[type="checkbox"][name$="[activo]"]');
      return cb ? cb.checked : false;
    });

    return rows
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
          row.dataset.currentMediumUrl ||
          row.dataset.currentUrl ||
          (imagePathField?.value ? resolveImageUrl(formRoot, imagePathField.value) : '') ||
          meta.image_url ||
          previewConfig.fallback?.image_url ||
          '';

        const isActive = anyChecked ? (activeField ? activeField.checked : true) : true;

        return {
          key: key || `row-${rowIndex}`,
          name,
          description: String(descriptionField?.value || '').trim(),
          icon: String(iconField?.value || meta.icon || previewConfig.fallback?.icon || 'ri-stethoscope-line').trim(),
          order: Number.parseInt(String(orderField?.value || rowIndex), 10) || 0,
          active: isActive,
          imageUrl,
          meta,
        };
      })
      .filter((item) => item.active && (item.name || item.description || item.imageUrl));
  };

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
    const heroWrap = formRoot.querySelector('[data-public-preview-inline-image]');
    const heroImage =
      filePreviews.get('services-hero') ||
      heroWrap?.dataset?.currentLargeUrl ||
      heroWrap?.dataset?.currentUrl ||
      heroWrap?.dataset?.currentMediumUrl ||
      previewConfig.hero_image_url ||
      '';
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
                      service.description || meta.descripcion || 'Descripcion de servicio pendiente.',
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

  const overlay = document.querySelector('[data-media-processing-overlay]');
  const overlayTitle = overlay?.querySelector('[data-media-processing-title]');
  const overlayStatusText = overlay?.querySelector('[data-media-processing-status-text]');
  const overlayProgressBar = overlay?.querySelector('[data-media-processing-progress-bar]');
  const overlayPercentageText = overlay?.querySelector('[data-media-processing-percentage-text]');
  const overlaySpinner = overlay?.querySelector('[data-media-processing-spinner]');
  const overlaySuccessIcon = overlay?.querySelector('[data-media-processing-success-icon]');
  const overlayErrorIcon = overlay?.querySelector('[data-media-processing-error-icon]');
  const batchStorageKey = 'services-personalization-batch-uuid';
  const batchStatusTemplate = formRoot.dataset.batchStatusUrlTemplate || '';
  const pollDelayMs = 1500;
  const successMessageDelayMs = 1800;

  let pollTimeout = null;
  let successTimeout = null;
  let activeBatchUuid = null;
  let isSubmitting = false;
  let isPolling = false;
  let pollInFlight = false;

  const clearTimers = () => {
    if (pollTimeout !== null) {
      window.clearTimeout(pollTimeout);
      pollTimeout = null;
    }

    if (successTimeout !== null) {
      window.clearTimeout(successTimeout);
      successTimeout = null;
    }
  };

  const clearStoredBatch = () => {
    sessionStorage.removeItem(batchStorageKey);
  };

  const storeBatchUuid = (uuid) => {
    sessionStorage.setItem(batchStorageKey, uuid);
  };

  const resolveStatusUrl = (uuid) => batchStatusTemplate.replace('__UUID__', encodeURIComponent(uuid));

  const formatElapsed = (seconds) => {
    const total = Math.max(0, Math.round(Number(seconds) || 0));

    if (total === 0) return '0 segundos';
    if (total === 1) return '1 segundo';
    if (total < 60) return `${total} segundos`;

    const minutes = Math.floor(total / 60);
    const remaining = total % 60;
    return remaining > 0 ? `${minutes} min ${remaining} s` : `${minutes} min`;
  };

  const setFormDisabled = (disabled) => {
    form.querySelectorAll('input:not([type="hidden"]), textarea, select, button').forEach((element) => {
      element.disabled = disabled;
    });
  };

  const setOverlayMessage = (title, message) => {
    if (overlayTitle) overlayTitle.textContent = title;
    if (overlayStatusText) overlayStatusText.textContent = message;
  };

  const showOverlay = () => {
    if (!overlay) return;
    overlay.hidden = false;
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    if (overlaySpinner) overlaySpinner.classList.remove('hidden');
    if (overlaySuccessIcon) overlaySuccessIcon.classList.add('hidden');
    if (overlayErrorIcon) overlayErrorIcon.classList.add('hidden');
  };

  const hideOverlay = () => {
    if (!overlay) return;
    overlay.hidden = true;
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
  };

  const setOverlayPending = (processed = 0, total = 0, elapsedSeconds = 0) => {
    if (!overlay) return;
    showOverlay();
    setOverlayMessage(
      'Preparando imagenes...',
      total > 0
        ? `Procesando imagenes ${processed} de ${total}... ${formatElapsed(elapsedSeconds)}`
        : `Preparando imagenes... ${formatElapsed(elapsedSeconds)}`,
    );
    if (overlayProgressBar) overlayProgressBar.style.width = total > 0 ? `${Math.max(0, Math.min(99, Math.round((processed / total) * 100)))}%` : '0%';
    if (overlayPercentageText) overlayPercentageText.textContent = total > 0 ? `${Math.max(0, Math.min(99, Math.round((processed / total) * 100)))}% completado` : '0% completado';
    if (overlaySpinner) overlaySpinner.classList.remove('hidden');
    if (overlaySuccessIcon) overlaySuccessIcon.classList.add('hidden');
    if (overlayErrorIcon) overlayErrorIcon.classList.add('hidden');
  };

  const setOverlayCompleted = (elapsedSeconds = 0) => {
    if (!overlay) return;
    showOverlay();
    if (overlaySpinner) overlaySpinner.classList.add('hidden');
    if (overlaySuccessIcon) overlaySuccessIcon.classList.remove('hidden');
    if (overlayErrorIcon) overlayErrorIcon.classList.add('hidden');
    setOverlayMessage('Completado', `Imagenes guardadas correctamente en ${formatElapsed(elapsedSeconds)}.`);
    if (overlayProgressBar) overlayProgressBar.style.width = '100%';
    if (overlayPercentageText) overlayPercentageText.textContent = '100% completado';
  };

  const setOverlayError = (message) => {
    if (!overlay) return;
    showOverlay();
    if (overlaySpinner) overlaySpinner.classList.add('hidden');
    if (overlaySuccessIcon) overlaySuccessIcon.classList.add('hidden');
    if (overlayErrorIcon) overlayErrorIcon.classList.remove('hidden');
    setOverlayMessage('Ocurrio un problema', message);
  };

  const unlockForm = () => {
    setFormDisabled(false);
    isSubmitting = false;
  };

  const stopPolling = () => {
    clearTimers();
    isPolling = false;
    pollInFlight = false;
  };

  const finishSuccess = (data) => {
    stopPolling();
    clearStoredBatch();
    activeBatchUuid = null;
    setOverlayCompleted(data.elapsed_seconds || 0);
    successTimeout = window.setTimeout(() => {
      hideOverlay();
      window.location.reload();
    }, successMessageDelayMs);
  };

  const finishFailure = (message) => {
    stopPolling();
    clearStoredBatch();
    activeBatchUuid = null;
    setOverlayError(message);
    successTimeout = window.setTimeout(() => {
      hideOverlay();
      unlockForm();
    }, 2200);
  };

  const updateOverlayFromResponse = (data) => {
    const processed = Number(data.processed || 0);
    const total = Number(data.total || 0);
    const percentage = Number(data.percentage || 0);
    const elapsedSeconds = Number(data.elapsed_seconds || 0);
    const status = String(data.status || '');

    if (status === 'completed') {
      finishSuccess(data);
      return;
    }

    if (status === 'failed') {
      finishFailure(data.message || 'No pudimos procesar todas las imagenes. Tus imagenes anteriores se conservaron. Intenta nuevamente.');
      return;
    }

    setOverlayPending(processed, total, elapsedSeconds);
    if (overlayProgressBar) overlayProgressBar.style.width = `${Math.max(0, Math.min(percentage, 99))}%`;
    if (overlayPercentageText) overlayPercentageText.textContent = `${Math.max(0, Math.min(percentage, 99))}% completado`;
  };

  const pollBatchStatus = async (uuid) => {
    if (!uuid || pollInFlight || activeBatchUuid !== uuid) return;

    isPolling = true;
    pollInFlight = true;

    try {
      const response = await fetch(resolveStatusUrl(uuid), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        finishFailure(data.message || 'No pudimos consultar el progreso de las imagenes.');
        return;
      }

      updateOverlayFromResponse(data);

      if (data.status === 'pending' || data.status === 'processing') {
        pollTimeout = window.setTimeout(() => {
          pollBatchStatus(uuid);
        }, pollDelayMs);
      }
    } catch (_error) {
      finishFailure('Error de conexion al consultar el progreso de las imagenes.');
    } finally {
      isPolling = false;
      pollInFlight = false;
    }
  };

  const resumeStoredBatch = async () => {
    const storedUuid = sessionStorage.getItem(batchStorageKey);

    if (!storedUuid) {
      return;
    }

    if (!batchStatusTemplate) {
      clearStoredBatch();
      return;
    }

    activeBatchUuid = storedUuid;
    setFormDisabled(true);
    setOverlayPending(0, 0, 0);
    await pollBatchStatus(storedUuid);
  };

  list?.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-item]');
    if (!removeButton) return;

    const row = removeButton.closest('[data-especialidad-row]');
    const key = row ? rowFileKey(row) : '';
    if (key) {
      revokePreviewUrl(key);
    }
    row?.remove();
    scheduleRender();
  });

  form.addEventListener('input', (event) => {
    if (event.target.matches('input[type="file"]')) return;
    scheduleRender();
  });

  form.addEventListener('change', (event) => {
    if (event.target.matches('input[type="file"]')) {
      handleFileInput(event.target);
      return;
    }

    scheduleRender();
  });

  window.addEventListener('beforeunload', () => {
    filePreviews.forEach((url) => {
      if (url.startsWith('blob:')) {
        URL.revokeObjectURL(url);
      }
    });
    filePreviews.clear();
    clearTimers();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (isSubmitting) {
      return;
    }

    if (uploadLimitsEnabled && !validateUploadLimits({ announce: true })) {
      return;
    }

    const fileInputsList = fileInputs();
    const hasNewImages = fileInputsList.some((input) => input.files && input.files.length > 0);

    if (hasNewImages && !batchStatusTemplate) {
      showUploadAlert('No se pudo iniciar el seguimiento del procesamiento de imagenes.');
      return;
    }

    try {
      const formData = new FormData(form);
      isSubmitting = true;
      setFormDisabled(true);
      activeBatchUuid = null;
      clearTimers();

      if (hasNewImages) {
        setOverlayPending(0, 0, 0);
      } else if (window.ActionLock?.startOperation) {
        window.ActionLock.startOperation({
          title: 'Guardando personalización...',
          description: 'Por favor, espera. No cierres esta página.',
        });
      }

      const action = form.getAttribute('action') || window.location.pathname;

      const response = await fetch(action, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const errorMsg = data.message || 'No se pudieron guardar los cambios de imagen. Intenta nuevamente.';
        clearStoredBatch();
        if (hasNewImages) {
          finishFailure(errorMsg);
        } else {
          window.ActionLock?.unlock();
          unlockForm();
        }
        showUploadAlert(errorMsg);
        return;
      }

      if (data.ok && data.batch_uuid) {
        activeBatchUuid = data.batch_uuid;
        storeBatchUuid(data.batch_uuid);
        setOverlayPending(0, data.total || 0, 0);
        await pollBatchStatus(data.batch_uuid);
        return;
      }

      clearStoredBatch();
      if (hasNewImages) {
        finishSuccess({ elapsed_seconds: data.elapsed_seconds || 0 });
      } else {
        window.ActionLock?.unlock();
        window.location.reload();
      }
    } catch (_error) {
      clearStoredBatch();
      if (hasNewImages) {
        finishFailure('Error de conexion al enviar el formulario. Intenta nuevamente.');
      } else {
        window.ActionLock?.unlock();
        unlockForm();
      }
      showUploadAlert('Error de conexion al enviar el formulario. Intenta nuevamente.');
    }
  });

  void resumeStoredBatch();

});

function parseJson(raw) {
  try {
    return JSON.parse(raw);
  } catch (_error) {
    return {};
  }
}
