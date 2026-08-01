export const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

export const normalizeText = (value) =>
  String(value ?? '')
    .trim();

export const normalizeKey = (value) =>
  String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

export const escapeSelector = (value) => {
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(value);
  }

  return String(value).replace(/["\\]/g, '\\$&');
};

export const resolveImageUrl = (formRoot, path) => {
  if (!path) return '';
  if (/^(https?:|data:|blob:)/i.test(path)) return path;

  const assetBase = formRoot.dataset.assetBase || '';
  const storageBase = formRoot.dataset.storageBase || '';
  const r2Url = formRoot.dataset.r2Url || '';
  const normalizedPath = String(path).replace(/^\//, '');

  if (r2Url && normalizedPath.startsWith('images/')) {
    return `${r2Url}/${normalizedPath}`;
  }

  if (
    normalizedPath.startsWith('img/') ||
    normalizedPath.startsWith('storage/')
  ) {
    return `${assetBase}${normalizedPath}`;
  }

  return `${storageBase}/${normalizedPath}`;
};

export const readFileAsDataUrl = (file) =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(new Error('No se pudo leer la imagen seleccionada.'));
    reader.readAsDataURL(file);
  });

export const isImageFile = (file) =>
  Boolean(file) &&
  (String(file.type || '').startsWith('image/') || /\.(png|jpe?g|webp|svg)$/i.test(file.name || ''));

export const getField = (scope, name) =>
  scope.querySelector(
    `input:not([type="hidden"])[name="${escapeSelector(name)}"], textarea[name="${escapeSelector(
      name,
    )}"], select[name="${escapeSelector(name)}"]`,
  );

export const getHiddenField = (scope, name) =>
  scope.querySelector(`input[type="hidden"][name="${escapeSelector(name)}"]`);

export const getValue = (scope, name, fallback = '') => {
  const field = getField(scope, name);
  const value = normalizeText(field?.value);

  return value !== '' ? value : fallback;
};

export const getHiddenValue = (scope, name, fallback = '') => {
  const field = getHiddenField(scope, name);
  const value = normalizeText(field?.value);

  return value !== '' ? value : fallback;
};

export const createPreviewModalController = (formRoot, { desktopWidth = 1180 } = {}) => {
  const previewModal = formRoot.querySelector('[data-public-preview-modal]');
  const previewStage = formRoot.querySelector('[data-public-preview-stage]');
  const previewSurface = formRoot.querySelector('[data-public-preview-surface]');
  const previewRoot = formRoot.querySelector('[data-public-preview-root]');
  const previewScroll = previewModal?.querySelector('.personalizacion-public-preview-scroll');
  const openers = Array.from(formRoot.querySelectorAll('[data-public-preview-open]'));
  const closers = Array.from(formRoot.querySelectorAll('[data-public-preview-close]'));

  if (!previewModal || !previewStage || !previewSurface || !previewRoot) {
    return null;
  }

  let previewResizeObserver = null;
  let previewSyncToken = null;

  const syncScale = () => {
    const availableWidth = previewStage.clientWidth;
    if (!availableWidth) return;

    const scale = Math.min(1, availableWidth / desktopWidth);
    const frameHeight = Math.max(previewSurface.scrollHeight, previewSurface.offsetHeight, 720);

    previewStage.style.setProperty('--public-preview-width', `${desktopWidth}px`);
    previewStage.style.setProperty('--public-preview-scale', `${scale}`);
    previewStage.style.setProperty('--public-preview-height', `${frameHeight}px`);

    if (previewScroll) {
      previewScroll.scrollLeft = 0;
    }
  };

  const scheduleSyncScale = () => {
    if (previewSyncToken !== null) return;

    previewSyncToken = window.requestAnimationFrame(() => {
      previewSyncToken = null;
      syncScale();
    });
  };

  const ensureResizeObserver = () => {
    if (previewResizeObserver || !('ResizeObserver' in window)) {
      return;
    }

    previewResizeObserver = new ResizeObserver(() => {
      scheduleSyncScale();
    });

    previewResizeObserver.observe(previewStage);
    previewResizeObserver.observe(previewSurface);
  };

  const openModal = (onOpen) => {
    previewModal.hidden = false;
    document.body.classList.add('overflow-hidden');
    if (typeof onOpen === 'function') {
      onOpen();
    }
    ensureResizeObserver();
    if (previewScroll) {
      previewScroll.scrollTo({ left: 0, top: 0, behavior: 'auto' });
    }
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        scheduleSyncScale();
        if (previewScroll) {
          previewScroll.scrollTo({ left: 0, top: 0, behavior: 'auto' });
        }
      });
    });
  };

  const closeModal = () => {
    previewModal.hidden = true;
    document.body.classList.remove('overflow-hidden');
  };

  openers.forEach((button) => {
    button.addEventListener('click', () => openModal());
  });

  closers.forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !previewModal.hidden) {
      closeModal();
    }
  });

  window.addEventListener('resize', () => {
    if (!previewModal.hidden) {
      scheduleSyncScale();
    }
  });

  return {
    open(onOpen) {
      openModal(onOpen);
    },
    close: closeModal,
    syncScale,
    previewRoot,
    previewSurface,
    previewModal,
  };
};
