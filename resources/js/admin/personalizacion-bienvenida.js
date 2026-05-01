document.addEventListener('DOMContentLoaded', () => {
  const formRoot = document.querySelector('[data-bienvenida-form]');

  if (!formRoot) return;

  const livePreviewRoot = formRoot.querySelector('[data-live-preview-root]');
  const livePreviewStage = formRoot.querySelector('[data-live-preview-stage]');
  const livePreviewFrame = formRoot.querySelector('[data-live-preview-frame]');
  const livePreviewSurface = formRoot.querySelector('[data-live-preview-surface]');
  const previewModal = formRoot.querySelector('[data-preview-modal]');
  const previewOpenButtons = Array.from(formRoot.querySelectorAll('[data-preview-open]'));
  const previewCloseButtons = Array.from(formRoot.querySelectorAll('[data-preview-close]'));
  const activeTabInput = formRoot.querySelector('[data-active-tab-input]');
  const featuredOptions = parseFeaturedOptions(
    formRoot.querySelector('[data-featured-options]')?.textContent || '[]',
  );
  const featuredOptionsById = new Map(featuredOptions.map((option) => [String(option.id), option]));
  const filePreviews = new Map();
  const currentYear = formRoot.dataset.currentYear || String(new Date().getFullYear());

  const defaults = {
    brandingName: 'Nombre de la clínica',
    navbarText: 'Sistema web de gestión médica',
    footerBadge: 'Atención médica organizada y cercana.',
    introBadge: 'Bienvenida',
    heroTitle: 'Gestiona tus citas médicas desde un solo lugar.',
    heroSubtitle:
      'Agenda una cita, revisa resultados y consulta documentos médicos desde tu cuenta.',
    heroPrimaryText: 'Agendar cita',
    heroSecondaryText: 'Explorar servicios',
    heroCardOneTitle: 'Organiza tus atenciones',
    heroCardOneText: 'Agenda, modifica y revisa tus citas desde tu cuenta.',
    heroCardTwoTitle: 'No te pierdas nada',
    heroCardTwoText: 'Recibe avisos y recordatorios sobre tus citas y resultados.',
    servicesBadge: 'Servicios',
    servicesTitle: 'Especialidades disponibles',
    servicesSubtitle:
      'Explora las especialidades de la clínica y agenda una cita según los horarios registrados.',
    servicesButtonText: 'Ver todos',
    pricesBadge: 'Tarifario',
    pricesTitle: 'Valores de referencia',
    pricesSubtitle: 'Revisa los valores registrados para orientar tu agendamiento.',
    pricesHighlightTitle: 'Atención en clínica',
    pricesHighlightSubtitle:
      'El sistema organiza la cita y la información necesaria para tu atención presencial.',
    pricesVisitTitle: 'Agenda paso a paso',
    pricesVisitSubtitle:
      'Selecciona especialidad, profesional, fecha y hora disponible antes de confirmar tu cita.',
    doctorsBadge: 'Nuestro equipo médico',
    doctorsTitle: 'Profesionales comprometidos con tu salud',
    doctorsSubtitle:
      'Contamos con especialistas para brindarte una atención médica segura y confiable.',
    doctorsPill: 'Atención segura y confidencial',
    footerLegalText: '© {year} - Todos los derechos reservados.',
    footerInstitutionalText:
      'Atención médica cercana, organizada y segura para cada paciente.',
    footerAddress: '',
    footerPhone: '',
    footerEmail: '',
    colors: {
      branding_accent: '#0f766e',
      branding_accent_strong: '#14b8a6',
      branding_accent_soft: '#ccfbf1',
      visual_soft_primary: '#dff6f2',
      visual_soft_secondary: '#e8f8ef',
      visual_gradient_start: '#dff4ff',
      visual_gradient_end: '#ecfdf5',
      visual_badge_soft: '#d9f7ef',
    },
  };

  const footerLinks = [
    { section: 'navigation', toggle: 'footer_show_home_link', field: 'footer_home_label', fallback: 'Inicio' },
    { section: 'navigation', toggle: 'footer_show_services_link', field: 'footer_services_label', fallback: 'Servicios' },
    { section: 'navigation', toggle: 'footer_show_contact_link', field: 'footer_contact_label', fallback: 'Contacto' },
    { section: 'navigation', toggle: 'footer_show_assistant_link', field: 'footer_assistant_label', fallback: 'Asistente virtual' },
    { section: 'legal', toggle: 'footer_show_privacy_link', field: 'footer_privacy_label', fallback: 'Políticas de privacidad' },
    { section: 'legal', toggle: 'footer_show_terms_link', field: 'footer_terms_label', fallback: 'Términos de servicio' },
  ];

  const navigationLabels = { home: 'Inicio', services: 'Servicios', contact: 'Contacto' };

  const serviceIconMap = {
    dermatologia: 'ri-user-heart-line',
    ginecologia: 'ri-women-line',
    laboratorioclinico: 'ri-test-tube-line',
    medicinageneral: 'ri-stethoscope-line',
    odontologia: 'ri-tooth-line',
    pediatria: 'ri-bear-smile-line',
  };

  const heroSlideCopy = [
    {
      title: 'Agenda de citas',
      subtitle: '',
      text: 'Elige especialidad, profesional, fecha y hora según la disponibilidad registrada.',
    },
    {
      title: 'Seguimiento de atenciones',
      subtitle: '',
      text: 'Revisa el estado de tus citas y los documentos asociados a tu cuenta.',
    },
    {
      title: 'Laboratorio y resultados',
      subtitle: '',
      text: 'Consulta solicitudes y resultados cuando estén disponibles en el sistema.',
    },
  ];

  const quickActionCards = [
    {
      title: 'Citas médicas',
      text: 'Agenda, modifica o cancela tus citas según disponibilidad.',
      icon: 'ri-calendar-2-line',
    },
    {
      title: 'Documentos médicos',
      text: 'Consulta resultados, recetas y comprobantes.',
      icon: 'ri-file-text-line',
    },
    {
      title: 'Recordatorios',
      text: 'Recibe avisos sobre tus próximas citas y resultados.',
      icon: 'ri-notification-3-line',
    },
  ];

  const visitSteps = [
    {
      number: '1',
      icon: 'ri-stethoscope-line',
      title: 'Elige especialidad',
      text: 'Selecciona el servicio que necesitas para iniciar tu cita.',
    },
    {
      number: '2',
      icon: 'ri-user-heart-line',
      title: 'Selecciona profesional',
      text: 'Escoge el doctor o área disponible según el horario registrado.',
    },
    {
      number: '3',
      icon: 'ri-calendar-check-line',
      title: 'Define fecha y hora',
      text: 'Revisa la disponibilidad y confirma el horario más conveniente.',
    },
    {
      number: '4',
      icon: 'ri-shield-check-line',
      title: 'Confirma tu cita',
      text: 'Verifica los datos y deja registrada tu atención en pocos pasos.',
    },
  ];

  let renderToken = null;

  const escapeSelector = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }

    return String(value).replace(/["\\]/g, '\\$&');
  };

  const getEditableField = (scope, name) =>
    scope.querySelector(
      `input:not([type="hidden"])[name="${escapeSelector(name)}"], textarea[name="${escapeSelector(name)}"], select[name="${escapeSelector(name)}"]`,
    );

  const getHiddenField = (scope, name) =>
    scope.querySelector(`input[type="hidden"][name="${escapeSelector(name)}"]`);

  const getCheckboxField = (scope, name) =>
    scope.querySelector(`input[type="checkbox"][name="${escapeSelector(name)}"]`);

  const getValue = (name, fallback = '') => {
    const field = getEditableField(formRoot, name);
    const value = field ? String(field.value || '').trim() : '';
    return value !== '' ? value : fallback;
  };

  const getHiddenValue = (name, fallback = '') => {
    const field = getHiddenField(formRoot, name);
    const value = field ? String(field.value || '').trim() : '';
    return value !== '' ? value : fallback;
  };

  const getCheckboxValue = (name, fallback = false) => {
    const field = getCheckboxField(formRoot, name);
    return field ? field.checked : fallback;
  };

  const rowValue = (row, suffix, fallback = '') => {
    const field = row.querySelector(
      `input:not([type="hidden"])[name$="${suffix}"], textarea[name$="${suffix}"], select[name$="${suffix}"]`,
    );
    const value = field ? String(field.value || '').trim() : '';
    return value !== '' ? value : fallback;
  };

  const rowHiddenValue = (row, suffix, fallback = '') => {
    const field = row.querySelector(`input[type="hidden"][name$="${suffix}"]`);
    const value = field ? String(field.value || '').trim() : '';
    return value !== '' ? value : fallback;
  };

  const rowChecked = (row, suffix, fallback = false) => {
    const field = row.querySelector(`input[type="checkbox"][name$="${suffix}"]`);
    return field ? field.checked : fallback;
  };

  const parseNumber = (value, fallback = 0) => {
    const parsed = Number.parseInt(String(value || '').trim(), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  const normalizeKey = (value) =>
    String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '');

  const escapeHtml = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const resolveImageUrl = (path) => {
    if (!path) return '';
    if (/^(https?:|data:)/i.test(path)) return path;

    const assetBase = formRoot.dataset.assetBase || '';
    const storageBase = formRoot.dataset.storageBase || '';

    if (path.startsWith('img/') || path.startsWith('storage/')) {
      return `${assetBase}${path}`;
    }

    return `${storageBase}/${path.replace(/^\//, '')}`;
  };

  const sortByOrder = (items) =>
    [...items].sort((left, right) => {
      if (left.sortOrder === right.sortOrder) {
        return String(left.label || left.title || left.name || left.service || '').localeCompare(
          String(right.label || right.title || right.name || right.service || ''),
          'es',
        );
      }

      return left.sortOrder - right.sortOrder;
    });

  const mediaMarkup = (url, alt, icon = 'ri-image-line', placeholder = '') => {
    if (url) {
      return `<img src="${escapeHtml(url)}" alt="${escapeHtml(alt)}">`;
    }

    return `
      <div class="welcome-live-preview__image-placeholder" style="width:100%;height:100%;">
        <i class="${escapeHtml(icon)}"></i>
        ${placeholder ? `<span class="welcome-live-preview__microcopy">${escapeHtml(placeholder)}</span>` : ''}
      </div>
    `;
  };

  const scheduleRender = () => {
    if (!livePreviewRoot || renderToken !== null) return;

    renderToken = window.requestAnimationFrame(() => {
      renderToken = null;
      renderLivePreview();
      window.requestAnimationFrame(syncPreviewScale);
    });
  };

  const syncPreviewScale = () => {
    if (!livePreviewStage || !livePreviewFrame || !livePreviewSurface) return;

    const desktopWidth = 1180;
    const availableWidth = livePreviewStage.clientWidth;
    if (!availableWidth) return;

    const scale = Math.min(1, availableWidth / desktopWidth);
    const frameHeight = Math.max(
      livePreviewSurface.scrollHeight,
      livePreviewSurface.offsetHeight,
      livePreviewRoot.scrollHeight,
    );

    livePreviewStage.style.setProperty('--preview-desktop-width', `${desktopWidth}px`);
    livePreviewStage.style.setProperty('--preview-scale', `${scale}`);
    livePreviewStage.style.setProperty('--preview-frame-height', `${frameHeight}px`);
  };

  const syncColorPreview = (input) => {
    const value = input.value || defaults.colors[input.name] || '#ffffff';
    const swatch = formRoot.querySelector(`[data-preview-swatch="${escapeSelector(input.name)}"]`);
    const label = formRoot.querySelector(`[data-color-value-for="${escapeSelector(input.name)}"]`);

    if (swatch) {
      swatch.style.setProperty('--swatch-start', value);
      swatch.style.setProperty('--swatch-end', value);
    }

    if (label) {
      label.textContent = value.toUpperCase();
    }
  };

  const syncAllColors = () => {
    formRoot.querySelectorAll('[data-color-input]').forEach((input) => syncColorPreview(input));
  };

  const updateFaviconPreview = (imageUrl) => {
    const wrap = formRoot.querySelector('[data-form-preview-favicon-wrap]');
    if (!wrap) return;

    wrap.innerHTML = imageUrl
      ? `<img src="${imageUrl}" alt="Favicon" class="h-14 w-14 rounded-2xl object-cover" data-form-preview-image="favicon">`
      : '<div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 text-gray-400"><i class="ri-global-line text-xl"></i></div>';
  };

  const renderInlineImageFrame = (imageUrl, icon, placeholder, alt = '') => {
    if (imageUrl) {
      return `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(alt || placeholder || 'Vista previa')}">`;
    }

    return `
      <div class="welcome-cms-media-placeholder">
        <i class="${escapeHtml(icon || 'ri-image-line')}"></i>
        <span>${escapeHtml(placeholder || 'Vista previa')}</span>
      </div>
    `;
  };

  const updateInlineImagePreview = (input, imageUrl) => {
    const row = input.closest('[data-row-key]');
    if (row) {
      const preview = row.querySelector('[data-inline-image-preview]');
      if (preview) {
        const altField = row.querySelector('input[name$="[alt]"], input[name$="[name]"]');
        preview.innerHTML = renderInlineImageFrame(
          imageUrl,
          preview.dataset.previewIcon,
          preview.dataset.previewPlaceholder,
          String(altField?.value || '').trim(),
        );
      }
      return;
    }

    if (input.name === 'branding_favicon') {
      updateFaviconPreview(imageUrl);
      return;
    }

    if (input.name !== 'header_logo') return;

    const image = formRoot.querySelector('[data-form-preview-image="header-logo"]');
    if (image) {
      image.src = imageUrl;
    }
  };

  const handleFileInput = (input) => {
    const file = input.files?.[0];
    if (!file) return;

    const row = input.closest('[data-row-key]');
    const key = row ? row.dataset.rowKey || '' : input.name;
    if (!key) return;

    const reader = new FileReader();
    reader.onload = () => {
      const imageUrl = String(reader.result || '');
      filePreviews.set(key, imageUrl);
      updateInlineImagePreview(input, imageUrl);
      syncDoctorActiveSummary();
      scheduleRender();
    };
    reader.readAsDataURL(file);
  };

  const initTabs = () => {
    const buttons = Array.from(formRoot.querySelectorAll('[data-tab-target]'));
    const panels = Array.from(formRoot.querySelectorAll('[data-tab-panel]'));

    if (!buttons.length || !panels.length) return;

    const activateTab = (target) => {
      const nextTarget =
        buttons.find((button) => button.dataset.tabTarget === target)?.dataset.tabTarget ||
        buttons[0]?.dataset.tabTarget ||
        'identidad';

      buttons.forEach((button) => {
        const isActive = button.dataset.tabTarget === nextTarget;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== nextTarget;
      });

      if (activeTabInput) {
        activeTabInput.value = nextTarget;
      }
    };

    buttons.forEach((button) => {
      button.addEventListener('click', () => activateTab(button.dataset.tabTarget || 'identidad'));
    });

    activateTab(formRoot.dataset.initialTab || activeTabInput?.value || 'identidad');
  };

  const initPreviewModal = () => {
    if (!previewModal) return;

    const openModal = () => {
      previewModal.hidden = false;
      document.body.classList.add('overflow-hidden');
      renderLivePreview();
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(syncPreviewScale);
      });
    };

    const closeModal = () => {
      previewModal.hidden = true;
      document.body.classList.remove('overflow-hidden');
    };

    previewOpenButtons.forEach((button) => {
      button.addEventListener('click', openModal);
    });

    previewCloseButtons.forEach((button) => {
      button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !previewModal.hidden) {
        closeModal();
      }
    });

    window.addEventListener('resize', () => {
      if (!previewModal.hidden) {
        syncPreviewScale();
      }
    });
  };

  const initRepeater = ({
    listSelector,
    templateSelector,
    addSelector,
    rowSelector,
    afterAdd,
    afterRemove,
  }) => {
    const list = formRoot.querySelector(listSelector);
    const template = formRoot.querySelector(templateSelector);
    const addButton = formRoot.querySelector(addSelector);

    if (!list || !template || !addButton) return;

    let index = Number(list.dataset.nextIndex || 0);

    addButton.addEventListener('click', () => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index)).trim();
      const node = wrapper.firstElementChild;

      if (!node) return;

      list.appendChild(node);
      index += 1;
      if (typeof afterAdd === 'function') {
        afterAdd(node);
      }
      scheduleRender();
    });

    list.addEventListener('click', (event) => {
      const removeButton = event.target.closest('[data-remove-item]');
      if (!removeButton) return;

      const row = removeButton.closest(rowSelector);
      if (!row) return;

      filePreviews.delete(row.dataset.rowKey || '');
      row.remove();
      if (typeof afterRemove === 'function') {
        afterRemove();
      }
      scheduleRender();
    });
  };

  const syncNavigationSelectOptions = () => {
    const selects = Array.from(formRoot.querySelectorAll('[data-navigation-select]'));
    const selectedValues = selects
      .map((select) => String(select.value || '').trim())
      .filter(Boolean);

    selects.forEach((select) => {
      const currentValue = String(select.value || '').trim();

      Array.from(select.options).forEach((option) => {
        if (!option.value) {
          option.disabled = false;
          return;
        }

        option.disabled = option.value !== currentValue && selectedValues.includes(option.value);
      });
    });
  };

  const syncFeaturedSpecialtyFields = ({ hydrate = false } = {}) => {
    const slots = Array.from(formRoot.querySelectorAll('[data-featured-specialty-slot]'));
    const selectedIds = slots
      .map((slot) =>
        String(slot.querySelector('[data-featured-specialty-select]')?.value || '').trim(),
      )
      .filter(Boolean);

    slots.forEach((slot) => {
      const select = slot.querySelector('[data-featured-specialty-select]');
      const textarea = slot.querySelector('[data-featured-specialty-description]');

      if (!select || !textarea) return;

      const currentId = String(select.value || '').trim();
      const previousId = slot.dataset.selectedId || '';
      const option = featuredOptionsById.get(currentId);

      Array.from(select.options).forEach((entry) => {
        if (!entry.value) {
          entry.disabled = false;
          return;
        }

        entry.disabled = entry.value !== currentId && selectedIds.includes(entry.value);
      });

      if (!currentId) {
        textarea.disabled = true;
        if (!hydrate || previousId) {
          textarea.value = '';
        }
        slot.dataset.selectedId = '';
        return;
      }

      textarea.disabled = false;

      if (hydrate) {
        if (String(textarea.value || '').trim() === '') {
          textarea.value = String(option?.descripcion || '').trim();
        }
      } else if (previousId !== currentId) {
        textarea.value = String(option?.descripcion || '').trim();
      }

      slot.dataset.selectedId = currentId;
    });
  };

  const syncDoctorActiveSummary = () => {
    const summary = formRoot.querySelector('[data-doctor-active-summary]');
    if (!summary) return;

    const activeDoctors = Array.from(formRoot.querySelectorAll('[data-doctor-row]')).filter((row) => {
      if (!rowChecked(row, '[is_active]', true)) {
        return false;
      }

      return Boolean(
        rowValue(row, '[name]') ||
          rowValue(row, '[specialty]') ||
          rowValue(row, '[experience_label]') ||
          rowValue(row, '[featured_label]') ||
          rowValue(row, '[attendance_label]') ||
          rowValue(row, '[availability_label]') ||
          rowValue(row, '[cta_text]') ||
          rowValue(row, '[pill_text]') ||
          rowHiddenValue(row, '[photo_path]') ||
          filePreviews.get(row.dataset.rowKey || ''),
      );
    }).length;

    const remaining = Math.max(0, 3 - activeDoctors);
    summary.textContent =
      activeDoctors > 3
        ? 'Hay más de 3 doctores marcados como visibles. Debes desmarcar "Mostrar doctor" en alguno antes de guardar.'
        : `Doctores visibles seleccionados: ${activeDoctors}/3. Puedes crear más registros; solo 3 pueden mostrarse en Welcome.`;
    summary.classList.toggle('border-rose-200', activeDoctors > 3);
    summary.classList.toggle('bg-rose-50', activeDoctors > 3);
    summary.classList.toggle('text-rose-700', activeDoctors > 3);
    summary.classList.toggle('border-gray-200', activeDoctors <= 3);
    summary.classList.toggle('bg-gray-50/80', activeDoctors <= 3);
    summary.classList.toggle('text-gray-600', activeDoctors <= 3);
    summary.dataset.remainingVisibleDoctors = String(remaining);
  };

  const buildState = () => {
    const navToggles = {
      home: getCheckboxValue('header_show_home', true),
      services: getCheckboxValue('header_show_services', true),
      contact: getCheckboxValue('header_show_contact', true),
    };

    const navOrder = Array.from(formRoot.querySelectorAll('[data-navigation-select]'))
      .map((select) => String(select.value || '').trim())
      .filter(Boolean);

    const navigation = [];
    navOrder.forEach((key) => {
      if (navToggles[key] && !navigation.includes(key)) {
        navigation.push(key);
      }
    });

    Object.keys(navigationLabels).forEach((key) => {
      if (navToggles[key] && !navigation.includes(key)) {
        navigation.push(key);
      }
    });

    const slides = sortByOrder(
      Array.from(formRoot.querySelectorAll('[data-slide-row]'))
        .map((row) => ({
          alt: rowValue(row, '[alt]', 'Imagen de bienvenida'),
          title: rowValue(row, '[title]'),
          subtitle: rowValue(row, '[subtitle]'),
          text: rowValue(row, '[text]'),
          imageUrl:
            filePreviews.get(row.dataset.rowKey || '') ||
            resolveImageUrl(rowHiddenValue(row, '[image_path]')),
          sortOrder: parseNumber(rowValue(row, '[sort_order]', '0')),
          isActive: rowChecked(row, '[is_active]', true),
        }))
        .filter((item) => item.isActive),
    );

    const services = Array.from(formRoot.querySelectorAll('[data-featured-specialty-slot]'))
      .map((slot) => {
        const select = slot.querySelector('[data-featured-specialty-select]');
        const textarea = slot.querySelector('[data-featured-specialty-description]');
        const id = String(select?.value || '').trim();
        if (!id) return null;

        const option = featuredOptionsById.get(id);
        const title = String(select?.options[select.selectedIndex]?.text || option?.nombre || '').trim();
        if (!title) return null;

        const key = normalizeKey(title);

        return {
          id,
          title,
          description: String(textarea?.value || option?.descripcion || '').trim(),
          icon: String(option?.icono || serviceIconMap[key] || 'ri-stethoscope-line').trim(),
        };
      })
      .filter((item, index, collection) => item && collection.findIndex((entry) => entry.id === item.id) === index);

    const prices = sortByOrder(
      Array.from(formRoot.querySelectorAll('[data-price-row]'))
        .map((row) => ({
          service: rowValue(row, '[service]'),
          price: rowValue(row, '[price]'),
          sortOrder: parseNumber(rowValue(row, '[sort_order]', '0')),
          isActive: rowChecked(row, '[is_active]', true),
        }))
        .filter((item) => item.isActive && (item.service || item.price)),
    );

    const visibleDoctors = sortByOrder(
      Array.from(formRoot.querySelectorAll('[data-doctor-row]'))
        .map((row) => {
          const specialty = rowValue(row, '[specialty]');
          return {
            name: rowValue(row, '[name]'),
            specialty,
            featuredLabel: rowValue(row, '[featured_label]', 'Destacado'),
            experienceLabel: rowValue(row, '[experience_label]', '5+ años de experiencia'),
            attendanceLabel: rowValue(row, '[attendance_label]', 'Atención presencial'),
            availabilityLabel: rowValue(row, '[availability_label]', 'Agenda disponible'),
            ctaText: rowValue(row, '[cta_text]', defaults.heroPrimaryText),
            pillText: rowValue(row, '[pill_text]', defaults.doctorsPill),
            imageUrl:
              filePreviews.get(row.dataset.rowKey || '') ||
              resolveImageUrl(rowHiddenValue(row, '[photo_path]')),
            icon:
              serviceIconMap[normalizeKey(specialty)] ||
              'ri-user-heart-line',
            sortOrder: parseNumber(rowValue(row, '[sort_order]', '0')),
            isActive: rowChecked(row, '[is_active]', true),
          };
        })
        .filter((item) => item.isActive && (item.name || item.specialty)),
    );

    return {
      year: currentYear,
      brandingName: getValue('branding_name', defaults.brandingName),
      navbarText: getValue('branding_navbar_text', defaults.navbarText),
      footerName: getValue('branding_institutional_name', getValue('branding_name', defaults.brandingName)),
      footerBadge: getValue('branding_institutional_badge', defaults.footerBadge),
      headerLoginText: getValue('header_login_text', defaults.heroPrimaryText),
      navigation: navigation.map((key) => navigationLabels[key]).filter(Boolean),
      introBadge: getValue('intro_badge', defaults.introBadge),
      heroTitle: getValue('hero_title', defaults.heroTitle),
      heroSubtitle: getValue('hero_subtitle', defaults.heroSubtitle),
      heroPrimaryText: getValue('hero_primary_text', defaults.heroPrimaryText),
      heroSecondaryText: getValue('hero_secondary_text', defaults.heroSecondaryText),
      heroShowPrimary: getCheckboxValue('hero_show_primary', true),
      heroShowSecondary: getCheckboxValue('hero_show_secondary', true),
      heroCardOneTitle: getValue('intro_feature_1_title', defaults.heroCardOneTitle),
      heroCardOneText: getValue('intro_feature_1_text', defaults.heroCardOneText),
      heroCardTwoTitle: getValue('intro_feature_4_title', defaults.heroCardTwoTitle),
      heroCardTwoText: getValue('intro_feature_4_text', defaults.heroCardTwoText),
      servicesBadge: getValue('services_badge', defaults.servicesBadge),
      servicesTitle: getValue('services_title', defaults.servicesTitle),
      servicesSubtitle: getValue('services_subtitle', defaults.servicesSubtitle),
      servicesButtonText: getValue('services_button_text', defaults.servicesButtonText),
      showServicesBlock: getCheckboxValue('show_services_block', true),
      pricesBadge: getValue('prices_badge', defaults.pricesBadge),
      pricesTitle: getValue('prices_title', defaults.pricesTitle),
      pricesSubtitle: getValue('prices_subtitle', defaults.pricesSubtitle),
      pricesHighlightTitle: getValue('prices_highlight_title', defaults.pricesHighlightTitle),
      pricesHighlightSubtitle: getValue(
        'prices_highlight_subtitle',
        defaults.pricesHighlightSubtitle,
      ),
      pricesVisitTitle: getValue('prices_visit_title', defaults.pricesVisitTitle),
      pricesVisitSubtitle: getValue('prices_visit_subtitle', defaults.pricesVisitSubtitle),
      doctorsBadge: getValue('doctors_badge', defaults.doctorsBadge),
      doctorsTitle: getValue('doctors_title', defaults.doctorsTitle),
      doctorsSubtitle: getValue('doctors_subtitle', defaults.doctorsSubtitle),
      doctorsPill: getValue('doctors_pill', defaults.doctorsPill),
      footerLegalText: getValue('footer_legal_text', defaults.footerLegalText).replace(
        /\{year\}/g,
        currentYear,
      ),
      footerInstitutionalText: getValue(
        'footer_institutional_text',
        defaults.footerInstitutionalText,
      ),
      footerAddress: getValue('footer_contact_address', defaults.footerAddress),
      footerPhone: getValue('footer_contact_phone', defaults.footerPhone),
      footerEmail: getValue('footer_contact_email', defaults.footerEmail),
      footerNavigationLinks: footerLinks
        .filter((item) => item.section === 'navigation' && getCheckboxValue(item.toggle, false))
        .map((item) => getValue(item.field, item.fallback))
        .filter(Boolean),
      footerLegalLinks: footerLinks
        .filter((item) => item.section === 'legal' && getCheckboxValue(item.toggle, false))
        .map((item) => getValue(item.field, item.fallback))
        .filter(Boolean),
      slides,
      services,
      prices,
      doctors: visibleDoctors.slice(0, 3),
      visibleDoctorsCount: visibleDoctors.length,
      colors: {
        branding_accent: getValue('branding_accent', defaults.colors.branding_accent),
        branding_accent_strong: getValue(
          'branding_accent_strong',
          defaults.colors.branding_accent_strong,
        ),
        branding_accent_soft: getValue('branding_accent_soft', defaults.colors.branding_accent_soft),
        visual_soft_primary: getValue('visual_soft_primary', defaults.colors.visual_soft_primary),
        visual_soft_secondary: getValue('visual_soft_secondary', defaults.colors.visual_soft_secondary),
        visual_gradient_start: getValue('visual_gradient_start', defaults.colors.visual_gradient_start),
        visual_gradient_end: getValue('visual_gradient_end', defaults.colors.visual_gradient_end),
        visual_badge_soft: getValue('visual_badge_soft', defaults.colors.visual_badge_soft),
      },
      images: {
        headerLogo:
          filePreviews.get('header_logo') || resolveImageUrl(getHiddenValue('header_logo_path')),
        favicon:
          filePreviews.get('branding_favicon') ||
          resolveImageUrl(getHiddenValue('branding_favicon_path')),
      },
    };
  };

  const renderLivePreview = () => {
    if (!livePreviewRoot) return;

    const state = buildState();

    const heroSlides = Array.from({ length: Math.max(state.slides.length, 3) }, (_, index) => {
      const slide = state.slides[index];
      const copy = heroSlideCopy[index % heroSlideCopy.length];

      return {
        imageUrl: slide?.imageUrl || '',
        alt: slide?.alt || `Imagen ${index + 1}`,
        title: slide?.title || copy.title,
        subtitle: slide?.subtitle || copy.subtitle || '',
        text: slide?.text || copy.text,
      };
    });

    const heroGalleryMarkup = heroSlides
      .map(
        (slide, index) => `
          <article class="welcome-live-preview__hero-gallery-item ${
            index === 0 ? 'welcome-live-preview__hero-gallery-item--primary' : ''
          }">
            <div class="welcome-live-preview__hero-gallery-media">
              ${mediaMarkup(
                slide.imageUrl,
                slide.alt || slide.title || `Imagen ${index + 1}`,
                'ri-image-line',
                `Imagen ${index + 1}`,
              )}
            </div>
            <div class="welcome-live-preview__hero-gallery-copy">
              <p class="welcome-live-preview__eyebrow">Slide ${index + 1}</p>
              <p class="welcome-live-preview__section-title">${escapeHtml(slide.title)}</p>
              ${
                slide.subtitle
                  ? `<p class="welcome-live-preview__microcopy" style="margin-top:0.35rem;font-weight:600;color:var(--preview-accent);">${escapeHtml(slide.subtitle)}</p>`
                  : ''
              }
              <p class="welcome-live-preview__microcopy">${escapeHtml(slide.text)}</p>
            </div>
          </article>
        `,
      )
      .join('');

    const quickCardsMarkup = quickActionCards
      .map(
        (card) => `
          <article class="welcome-live-preview__card">
            <span class="welcome-live-preview__card-icon"><i class="${escapeHtml(card.icon)}"></i></span>
            <p class="welcome-live-preview__section-title">${escapeHtml(card.title)}</p>
            <p class="welcome-live-preview__card-text">${escapeHtml(card.text)}</p>
          </article>
        `,
      )
      .join('');

    const servicesMarkup =
      state.showServicesBlock && state.services.length
        ? state.services
            .map(
              (service) => `
                <article class="welcome-live-preview__service-card">
                  <span class="welcome-live-preview__service-icon"><i class="${escapeHtml(service.icon)}"></i></span>
                  <div>
                    <p class="welcome-live-preview__section-title">${escapeHtml(service.title)}</p>
                    <p class="welcome-live-preview__card-text">${escapeHtml(
                      service.description || 'Descripción pendiente.',
                    )}</p>
                  </div>
                </article>
              `,
            )
            .join('')
        : `<div class="welcome-live-preview__empty">${
            state.showServicesBlock
              ? 'Selecciona especialidades para ver servicios destacados.'
              : 'El bloque de servicios está oculto.'
          }</div>`;

    const stepsMarkup = visitSteps
      .map(
        (step) => `
          <article class="welcome-live-preview__card">
            <p class="welcome-live-preview__eyebrow">Paso ${escapeHtml(step.number)}</p>
            <p class="welcome-live-preview__section-title">${escapeHtml(step.title)}</p>
            <p class="welcome-live-preview__card-text">${escapeHtml(step.text)}</p>
          </article>
        `,
      )
      .join('');

    const pricesMarkup = state.prices.length
      ? state.prices
          .map(
            (price) => `
              <div class="welcome-live-preview__price-row">
                <div class="welcome-live-preview__price-title">${escapeHtml(
                  price.service || 'Servicio',
                )}</div>
                <div class="welcome-live-preview__price-value">${escapeHtml(
                  price.price || 'Consultar',
                )}</div>
              </div>
            `,
          )
          .join('')
      : `<div class="welcome-live-preview__empty">Agrega filas de precios para ver el tarifario.</div>`;

    const doctorsMarkup = state.doctors.length
      ? state.doctors
          .map(
            (doctor) => `
              <article class="welcome-live-preview__doctor-card">
                <div class="welcome-live-preview__doctor-image">
                  ${mediaMarkup(
                    doctor.imageUrl,
                    doctor.name || 'Doctor',
                    'ri-user-3-line',
                    'Foto de doctor',
                  )}
                </div>
                <div class="welcome-live-preview__doctor-copy">
                  <span class="welcome-live-preview__doctor-pill">${escapeHtml(
                    doctor.featuredLabel || 'Destacado',
                  )}</span>
                  <div class="welcome-live-preview__doctor-icon"><i class="${escapeHtml(
                    doctor.icon,
                  )}"></i></div>
                  <div>
                    <p class="welcome-live-preview__doctor-name">${escapeHtml(
                      doctor.name || 'Doctor',
                    )}</p>
                    <p class="welcome-live-preview__hero-text">${escapeHtml(
                      doctor.specialty || 'Especialidad',
                    )}</p>
                  </div>
                  <div class="welcome-live-preview__doctor-meta">
                    <span>${escapeHtml(doctor.experienceLabel)}</span>
                    <span>${escapeHtml(doctor.attendanceLabel)}</span>
                    <span>${escapeHtml(doctor.availabilityLabel)}</span>
                  </div>
                  <span class="welcome-live-preview__cta welcome-live-preview__cta--primary">${escapeHtml(
                    doctor.ctaText || state.heroPrimaryText,
                  )}</span>
                  <p class="welcome-live-preview__microcopy">${escapeHtml(
                    doctor.pillText || state.doctorsPill,
                  )}</p>
                </div>
              </article>
            `,
          )
          .join('')
      : `<div class="welcome-live-preview__empty">Agrega doctores para ver el directorio en esta vista previa.</div>`;

    const footerLinksMarkup = state.footerNavigationLinks.length
      ? state.footerNavigationLinks
          .map((label) => `<span class="welcome-live-preview__footer-link">${escapeHtml(label)}</span>`)
          .join('')
      : '<span class="welcome-live-preview__microcopy">Activa enlaces del footer para verlos aquí.</span>';

    const footerLegalMarkup = state.footerLegalLinks.length
      ? state.footerLegalLinks
          .map((label) => `<span class="welcome-live-preview__footer-link">${escapeHtml(label)}</span>`)
          .join('')
      : '<span class="welcome-live-preview__microcopy">Activa textos legales para verlos aquí.</span>';

    livePreviewRoot.innerHTML = `
      <div
        class="welcome-live-preview"
        style="
          --preview-accent:${escapeHtml(state.colors.branding_accent)};
          --preview-accent-strong:${escapeHtml(state.colors.branding_accent_strong)};
          --preview-accent-soft:${escapeHtml(state.colors.branding_accent_soft)};
          --preview-soft-primary:${escapeHtml(state.colors.visual_soft_primary)};
          --preview-soft-secondary:${escapeHtml(state.colors.visual_soft_secondary)};
          --preview-gradient-start:${escapeHtml(state.colors.visual_gradient_start)};
          --preview-gradient-end:${escapeHtml(state.colors.visual_gradient_end)};
          --preview-badge-soft:${escapeHtml(state.colors.visual_badge_soft)};
        "
      >
        <div class="welcome-live-preview__inner">
          <section class="welcome-live-preview__section">
            <div class="welcome-live-preview__topbar">
              <div class="welcome-live-preview__brand">
                <div class="welcome-live-preview__brand-logo">
                  ${mediaMarkup(state.images.headerLogo, state.brandingName, 'ri-hospital-line')}
                </div>
                <div class="welcome-live-preview__brand-copy">
                  <p class="welcome-live-preview__brand-title">${escapeHtml(state.brandingName)}</p>
                  <p class="welcome-live-preview__brand-subtitle">${escapeHtml(state.navbarText)}</p>
                </div>
              </div>
              <div class="welcome-live-preview__nav">
                ${
                  state.navigation.length
                    ? state.navigation
                        .map(
                          (item) =>
                            `<span class="welcome-live-preview__nav-item">${escapeHtml(item)}</span>`,
                        )
                        .join('')
                    : '<span class="welcome-live-preview__microcopy">Sin navegación visible</span>'
                }
              </div>
              <span class="welcome-live-preview__ghost-button">${escapeHtml(
                state.headerLoginText,
              )}</span>
            </div>
            <div class="welcome-live-preview__hero">
              <div>
                <span class="welcome-live-preview__hero-badge">${escapeHtml(
                  state.introBadge,
                )}</span>
                <h2 class="welcome-live-preview__hero-title">${escapeHtml(state.heroTitle)}</h2>
                <p class="welcome-live-preview__hero-text">${escapeHtml(state.heroSubtitle)}</p>
                <div class="welcome-live-preview__support-grid">
                  <article class="welcome-live-preview__support-card">
                    <p class="welcome-live-preview__section-title">${escapeHtml(
                      state.heroCardOneTitle,
                    )}</p>
                    <p class="welcome-live-preview__card-text">${escapeHtml(
                      state.heroCardOneText,
                    )}</p>
                  </article>
                  <article class="welcome-live-preview__support-card">
                    <p class="welcome-live-preview__section-title">${escapeHtml(
                      state.heroCardTwoTitle,
                    )}</p>
                    <p class="welcome-live-preview__card-text">${escapeHtml(
                      state.heroCardTwoText,
                    )}</p>
                  </article>
                </div>
                <div class="welcome-live-preview__cta-row">
                  ${
                    state.heroShowPrimary
                      ? `<span class="welcome-live-preview__cta welcome-live-preview__cta--primary">${escapeHtml(
                          state.heroPrimaryText,
                        )}</span>`
                      : ''
                  }
                  ${
                    state.heroShowSecondary
                      ? `<span class="welcome-live-preview__cta welcome-live-preview__cta--secondary">${escapeHtml(
                          state.heroSecondaryText,
                        )}</span>`
                      : ''
                  }
                </div>
              </div>
              <div class="welcome-live-preview__hero-media">
                <div class="welcome-live-preview__hero-gallery">
                  ${heroGalleryMarkup}
                </div>
              </div>
            </div>
          </section>

          <section class="welcome-live-preview__section">
            <div class="welcome-live-preview__section-header">
              <span class="welcome-live-preview__section-kicker">Acciones rápidas</span>
              <p class="welcome-live-preview__section-title">Cards fijas del sistema actual</p>
              <p class="welcome-live-preview__section-text">Este bloque no se edita desde personalización y se mantiene alineado con Welcome.</p>
            </div>
            <div class="welcome-live-preview__cards">${quickCardsMarkup}</div>
          </section>

          <section class="welcome-live-preview__section">
            <div class="welcome-live-preview__section-header">
              <span class="welcome-live-preview__section-kicker">${escapeHtml(
                state.servicesBadge,
              )}</span>
              <p class="welcome-live-preview__section-title">${escapeHtml(state.servicesTitle)}</p>
              <p class="welcome-live-preview__section-text">${escapeHtml(
                state.servicesSubtitle,
              )}</p>
              <div class="welcome-live-preview__cta-row">
                <span class="welcome-live-preview__cta welcome-live-preview__cta--secondary">${escapeHtml(
                  state.servicesButtonText,
                )}</span>
              </div>
            </div>
            <div class="welcome-live-preview__services">${servicesMarkup}</div>
          </section>

          <section class="welcome-live-preview__section">
            <div class="welcome-live-preview__section-header">
              <span class="welcome-live-preview__section-kicker">Agenda paso a paso</span>
              <p class="welcome-live-preview__section-title">${escapeHtml(
                state.pricesVisitTitle,
              )}</p>
              <p class="welcome-live-preview__section-text">${escapeHtml(
                state.pricesVisitSubtitle,
              )}</p>
            </div>
            <div class="welcome-live-preview__cards">${stepsMarkup}</div>
          </section>

          <section class="welcome-live-preview__section">
            <div class="welcome-live-preview__prices-header">
              <span class="welcome-live-preview__section-kicker">${escapeHtml(
                state.pricesBadge,
              )}</span>
              <p class="welcome-live-preview__section-title">${escapeHtml(state.pricesTitle)}</p>
              <p class="welcome-live-preview__section-text">${escapeHtml(
                state.pricesSubtitle,
              )}</p>
            </div>
            <div class="welcome-live-preview__prices-grid">
              <div class="welcome-live-preview__prices-list">${pricesMarkup}</div>
              <div class="welcome-live-preview__prices-highlight">
                <div class="welcome-live-preview__prices-highlight-copy">
                  <p class="welcome-live-preview__section-title">${escapeHtml(
                    state.pricesHighlightTitle,
                  )}</p>
                  <p class="welcome-live-preview__card-text">${escapeHtml(
                    state.pricesHighlightSubtitle,
                  )}</p>
                  <div class="welcome-live-preview__footer-links" style="margin-top:1rem;">
                    <span class="welcome-live-preview__footer-link">Información segura y confidencial</span>
                    <span class="welcome-live-preview__footer-link">Proceso rápido y organizado</span>
                    <span class="welcome-live-preview__footer-link">Atención profesional y cercana</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="welcome-live-preview__section">
            <div class="welcome-live-preview__section-header">
              <span class="welcome-live-preview__section-kicker">${escapeHtml(
                state.doctorsBadge,
              )}</span>
              <p class="welcome-live-preview__section-title">${escapeHtml(state.doctorsTitle)}</p>
              <p class="welcome-live-preview__section-text">${escapeHtml(
                state.doctorsSubtitle,
              )}</p>
            </div>
            <div class="welcome-live-preview__doctors">${doctorsMarkup}</div>
          </section>

          <section class="welcome-live-preview__section welcome-live-preview__footer">
            <div class="welcome-live-preview__footer-grid">
              <article class="welcome-live-preview__footer-column">
                <p class="welcome-live-preview__footer-title">${escapeHtml(state.footerName)}</p>
                <p class="welcome-live-preview__footer-text">${escapeHtml(
                  state.footerLegalText,
                )}</p>
                <p class="welcome-live-preview__footer-text">${escapeHtml(
                  state.footerInstitutionalText,
                )}</p>
              </article>
              <article class="welcome-live-preview__footer-column">
                <p class="welcome-live-preview__section-title">Enlaces</p>
                <div class="welcome-live-preview__footer-links">${footerLinksMarkup}</div>
              </article>
              <article class="welcome-live-preview__footer-column">
                <p class="welcome-live-preview__section-title">Legales</p>
                <div class="welcome-live-preview__footer-links">${footerLegalMarkup}</div>
              </article>
              <article class="welcome-live-preview__footer-column">
                <p class="welcome-live-preview__section-title">Contacto</p>
                <p class="welcome-live-preview__contact-text">${escapeHtml(state.footerAddress)}</p>
                <p class="welcome-live-preview__contact-text">${escapeHtml(state.footerPhone)}</p>
                <p class="welcome-live-preview__contact-text">${escapeHtml(state.footerEmail)}</p>
              </article>
            </div>
          </section>
        </div>
      </div>
    `;

    syncPreviewScale();
  };

  initRepeater({
    listSelector: '[data-slide-list]',
    templateSelector: '[data-slide-template]',
    addSelector: '[data-add-slide]',
    rowSelector: '[data-slide-row]',
  });

  initRepeater({
    listSelector: '[data-price-list]',
    templateSelector: '[data-price-template]',
    addSelector: '[data-add-price]',
    rowSelector: '[data-price-row]',
  });

  initRepeater({
    listSelector: '[data-doctor-list]',
    templateSelector: '[data-doctor-template]',
    addSelector: '[data-add-doctor]',
    rowSelector: '[data-doctor-row]',
    afterAdd: () => syncDoctorActiveSummary(),
    afterRemove: () => syncDoctorActiveSummary(),
  });

  initTabs();
  initPreviewModal();
  syncAllColors();
  syncNavigationSelectOptions();
  syncFeaturedSpecialtyFields({ hydrate: true });
  syncDoctorActiveSummary();

  formRoot.addEventListener('input', (event) => {
    if (event.target.matches('[data-color-input]')) {
      syncColorPreview(event.target);
    }

    if (!event.target.matches('input[type="file"]')) {
      scheduleRender();
    }
  });

  formRoot.addEventListener('change', (event) => {
    if (event.target.matches('[data-color-input]')) {
      syncColorPreview(event.target);
    }

    if (event.target.matches('input[type="file"]')) {
      handleFileInput(event.target);
      return;
    }

    if (event.target.matches('[data-navigation-select]')) {
      syncNavigationSelectOptions();
    }

    if (event.target.matches('[data-featured-specialty-select]')) {
      syncFeaturedSpecialtyFields();
    }

    if (event.target.name?.includes('[is_active]')) {
      syncDoctorActiveSummary();
    }

    scheduleRender();
  });

  const faviconPath = getHiddenValue('branding_favicon_path');
  updateFaviconPreview(faviconPath ? resolveImageUrl(faviconPath) : '');
  renderLivePreview();
  syncPreviewScale();
});

function parseFeaturedOptions(rawJson) {
  try {
    return JSON.parse(rawJson);
  } catch (_error) {
    return [];
  }
}
