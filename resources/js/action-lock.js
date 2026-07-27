const UNSAFE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const FORM_LOCK_ATTR = 'data-action-lock-active';
const SUBMITTER_NAME = 'data-action-lock-submitter';
const OVERLAY_ID = 'global-action-lock';
const OVERLAY_TITLE_ID = 'global-action-lock-title';
const OVERLAY_DESCRIPTION_ID = 'global-action-lock-description';

const inFlightFetches = new Map();

const nativeSubmit = HTMLFormElement.prototype.submit;
const nativeRequestSubmit = HTMLFormElement.prototype.requestSubmit;
const nativeFetch = window.fetch ? window.fetch.bind(window) : null;

const state = {
  active: false,
  token: 0,
  mode: 'operation',
  title: '',
  description: '',
  overlay: null,
  titleNode: null,
  descriptionNode: null,
  focusNode: null,
  previousFocus: null,
  previousBodyOverflow: '',
  previousBodyPaddingRight: '',
  lockedNodes: [],
  listenersReady: false,
  restoreScrollY: 0,
};

function normalizeMethod(method) {
  return String(method || 'GET').toUpperCase();
}

function effectiveFormMethod(form, submitter = null) {
  return normalizeMethod(submitter?.getAttribute?.('formmethod') || form.getAttribute('method') || 'GET');
}

function shouldLockForm(form, submitter = null) {
  if (!form || form.dataset.actionLock === 'off' || submitter?.dataset?.actionLock === 'off') {
    return false;
  }

  return UNSAFE_METHODS.has(effectiveFormMethod(form, submitter));
}

function normalizeText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function textFromElement(element) {
  if (!element) {
    return '';
  }

  if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement) {
    return normalizeText(element.value);
  }

  return normalizeText(element.textContent || element.getAttribute('aria-label') || element.getAttribute('title'));
}

function getElementText(element) {
  if (!element) {
    return '';
  }

  if (element instanceof HTMLInputElement) {
    return normalizeText(element.value || element.getAttribute('value'));
  }

  return normalizeText(element.textContent || element.getAttribute('aria-label') || element.getAttribute('title'));
}

function readCopy(element) {
  if (!element) {
    return {};
  }

  return {
    title: element.dataset.actionLockTitle || element.dataset.actionLockNavTitle || '',
    description: element.dataset.actionLockDescription || element.dataset.actionLockNavDescription || '',
    mode: element.dataset.actionLockMode || '',
  };
}

function mergeCopy(...parts) {
  return parts.reduce(
    (copy, part) => {
      if (!part) {
        return copy;
      }

      if (!copy.title && part.title) {
        copy.title = part.title;
      }
      if (!copy.description && part.description) {
        copy.description = part.description;
      }
      if (!copy.mode && part.mode) {
        copy.mode = part.mode;
      }

      return copy;
    },
    { title: '', description: '', mode: '' }
  );
}

function defaultDescription(mode) {
  return mode === 'navigation'
    ? 'Por favor, espera mientras cargamos esta sección.'
    : 'Por favor, espera. No cierres esta página.';
}

function defaultTitle(mode) {
  return mode === 'navigation' ? 'Cargando sección...' : 'Procesando solicitud...';
}

function buildOverlayMarkup() {
  const wrapper = document.createElement('div');
  wrapper.innerHTML = `
    <div
      id="${OVERLAY_ID}"
      class="fixed inset-0 z-[9999] hidden items-center justify-center px-4 py-6 sm:px-6"
      aria-hidden="true"
      data-global-action-lock
      tabindex="-1"
    >
      <div class="absolute inset-0 bg-white/55 backdrop-blur-[2px] dark:bg-slate-950/55" data-global-action-lock-backdrop></div>
      <div
        class="relative w-full max-w-md rounded-3xl border border-white/70 bg-white px-6 py-7 text-center shadow-[0_24px_60px_rgba(15,23,42,0.18)] dark:border-slate-700/80 dark:bg-slate-900 dark:shadow-[0_30px_70px_rgba(2,6,23,0.55)]"
        role="status"
        aria-live="polite"
        aria-atomic="true"
      >
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-teal-50 text-teal-600 dark:bg-teal-500/10 dark:text-teal-300">
          <svg class="h-10 w-10 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.2" stroke-width="3"></circle>
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
          </svg>
        </div>
        <h2 id="${OVERLAY_TITLE_ID}" class="mt-5 text-xl font-bold text-gray-900 dark:text-white">
          Procesando solicitud...
        </h2>
        <p id="${OVERLAY_DESCRIPTION_ID}" class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
          Por favor, espera. No cierres esta página.
        </p>
      </div>
    </div>
  `;

  return wrapper.firstElementChild;
}

function ensureOverlay() {
  if (state.overlay && document.body.contains(state.overlay)) {
    return state.overlay;
  }

  let overlay = document.getElementById(OVERLAY_ID);

  if (!overlay && document.body) {
    overlay = buildOverlayMarkup();
    document.body.appendChild(overlay);
  }

  if (!overlay) {
    return null;
  }

  state.overlay = overlay;
  state.titleNode = overlay.querySelector(`#${OVERLAY_TITLE_ID}`);
  state.descriptionNode = overlay.querySelector(`#${OVERLAY_DESCRIPTION_ID}`);
  state.focusNode = overlay;

  return overlay;
}

function shouldBlockPointerTarget(target) {
  if (!state.active || !state.overlay) {
    return false;
  }

  return !state.overlay.contains(target);
}

function setBodyLock(active) {
  if (!document.body) {
    return;
  }

  if (active) {
    state.restoreScrollY = window.scrollY || 0;
    state.previousBodyOverflow = document.body.style.overflow;
    state.previousBodyPaddingRight = document.body.style.paddingRight;

    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = `${scrollbarWidth}px`;
    }

    document.body.style.overflow = 'hidden';
    document.body.setAttribute('aria-busy', 'true');
    document.body.classList.add('global-action-lock-active');
    return;
  }

  document.body.style.overflow = state.previousBodyOverflow;
  document.body.style.paddingRight = state.previousBodyPaddingRight;
  document.body.removeAttribute('aria-busy');
  document.body.classList.remove('global-action-lock-active');
  window.scrollTo(0, state.restoreScrollY || 0);
}

function setInert(active) {
  if (!document.body) {
    return;
  }

  const supportsInert = 'inert' in HTMLElement.prototype;
  state.lockedNodes = [];

  Array.from(document.body.children).forEach((node) => {
    if (node === state.overlay) {
      return;
    }

    const previous = {
      node,
      inert: supportsInert ? Boolean(node.inert) : null,
      ariaHidden: node.getAttribute('aria-hidden'),
    };

    if (active) {
      if (supportsInert) {
        node.inert = true;
      } else {
        node.setAttribute('aria-hidden', 'true');
      }

      state.lockedNodes.push(previous);
      return;
    }

    if (supportsInert && previous.inert !== null) {
      node.inert = previous.inert;
    }

    if (!supportsInert) {
      if (previous.ariaHidden === null) {
        node.removeAttribute('aria-hidden');
      } else {
        node.setAttribute('aria-hidden', previous.ariaHidden);
      }
    }
  });
}

function updateOverlayText(copy, mode = 'operation') {
  const overlay = ensureOverlay();
  if (!overlay) {
    return;
  }

  const normalized = mergeCopy(copy);
  const title = normalized.title || defaultTitle(mode);
  const description = normalized.description || defaultDescription(mode);

  state.mode = normalized.mode || mode;
  state.title = title;
  state.description = description;

  if (state.titleNode) {
    state.titleNode.textContent = title;
  }

  if (state.descriptionNode) {
    state.descriptionNode.textContent = description;
  }
}

function focusOverlay() {
  if (!state.focusNode || typeof state.focusNode.focus !== 'function') {
    return;
  }

  window.requestAnimationFrame(() => {
    try {
      state.focusNode.focus({ preventScroll: true });
    } catch {
      state.focusNode.focus();
    }
  });
}

function showOverlay(copy, mode = 'operation') {
  const overlay = ensureOverlay();
  if (!overlay) {
    return false;
  }

  if (state.active) {
    return false;
  }

  state.active = true;
  state.token += 1;
  updateOverlayText(copy, mode);
  overlay.classList.remove('hidden');
  overlay.classList.add('flex');
  overlay.setAttribute('aria-hidden', 'false');
  setBodyLock(true);
  setInert(true);
  focusOverlay();

  return true;
}

function hideOverlay() {
  if (!state.overlay) {
    return;
  }

  state.overlay.classList.add('hidden');
  state.overlay.classList.remove('flex');
  state.overlay.setAttribute('aria-hidden', 'true');
}

function resetOverlay() {
  const overlay = ensureOverlay();
  if (!overlay) {
    return;
  }

  state.active = false;
  state.mode = 'operation';
  state.title = '';
  state.description = '';
  hideOverlay();
  setInert(false);
  setBodyLock(false);

  if (state.previousFocus && typeof state.previousFocus.focus === 'function' && document.contains(state.previousFocus)) {
    try {
      state.previousFocus.focus({ preventScroll: true });
    } catch {
      state.previousFocus.focus();
    }
  }

  state.previousFocus = null;
}

function lock(copy, options = {}) {
  const mode = options.mode || 'operation';
  if (!document.body || !ensureOverlay()) {
    return false;
  }

  if (state.active) {
    return false;
  }

  state.previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  return showOverlay(copy, mode);
}

function unlock() {
  if (!state.active) {
    return false;
  }

  resetOverlay();
  return true;
}

function run(copy, executor, options = {}) {
  if (typeof executor !== 'function') {
    return Promise.reject(new TypeError('ActionLock.run expects a function.'));
  }

  const mode = options.mode || 'operation';
  const started = lock(copy, { mode });

  return Promise.resolve()
    .then(() => executor())
    .finally(() => {
      if (started) {
        unlock();
      }
    });
}

function startOperation(copy = null) {
  return lock(copy, { mode: 'operation' });
}

function startNavigation(copy = null) {
  return lock(copy, { mode: 'navigation' });
}

function resolveNavigationCopy(anchor) {
  const explicit = mergeCopy(readCopy(anchor), readCopy(anchor.closest('[data-action-lock-nav-title], [data-action-lock-nav-description]')));
  if (explicit.title || explicit.description) {
    return mergeCopy(explicit, {
      title: explicit.title || defaultTitle('navigation'),
      description: explicit.description || defaultDescription('navigation'),
      mode: 'navigation',
    });
  }

  const label = getElementText(anchor);
  if (!label) {
    return {
      title: defaultTitle('navigation'),
      description: defaultDescription('navigation'),
      mode: 'navigation',
    };
  }

  const lowered = label.toLowerCase();
  if (lowered.includes('cerrar sesión') || lowered.includes('salir')) {
    return {
      title: 'Cerrando sesión...',
      description: defaultDescription('navigation'),
      mode: 'navigation',
    };
  }

  if (lowered.includes('agendar cita')) {
    return {
      title: 'Abriendo Agendar cita...',
      description: defaultDescription('navigation'),
      mode: 'navigation',
    };
  }

  if (lowered.includes('mis citas')) {
    return {
      title: 'Abriendo Mis citas...',
      description: defaultDescription('navigation'),
      mode: 'navigation',
    };
  }

  if (lowered.includes('perfil')) {
    return {
      title: `Abriendo ${label}...`,
      description: defaultDescription('navigation'),
      mode: 'navigation',
    };
  }

  return {
    title: `Abriendo ${label}...`,
    description: defaultDescription('navigation'),
    mode: 'navigation',
  };
}

function resolveOperationCopy(form, submitter = null) {
  const explicit = mergeCopy(readCopy(form), readCopy(submitter), readCopy(form?.closest('[data-action-lock-context]')));
  if (explicit.title || explicit.description) {
    return {
      title: explicit.title || defaultTitle('operation'),
      description: explicit.description || defaultDescription('operation'),
      mode: explicit.mode || 'operation',
    };
  }

  const actionUrl = normalizeText(submitter?.getAttribute?.('formaction') || form?.getAttribute('action') || '').toLowerCase();
  const formMethod = normalizeMethod(submitter?.getAttribute?.('formmethod') || form?.getAttribute('method') || 'GET');
  const submitText = getElementText(submitter).toLowerCase();
  const formText = normalizeText(form?.textContent || '').toLowerCase();
  const text = `${submitText} ${formText} ${actionUrl} ${window.location.pathname.toLowerCase()}`;

  if (text.includes('/salir') || text.includes('cerrar sesión') || text.includes('logout')) {
    return {
      title: 'Cerrando sesión...',
      description: defaultDescription('operation'),
      mode: 'navigation',
    };
  }

  if (text.includes('aprobar') && text.includes('pago')) {
    return {
      title: 'Aprobando pago...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('rechazar') && text.includes('pago')) {
    return {
      title: 'Rechazando pago...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('anular') && text.includes('pago')) {
    return {
      title: 'Anulando pago...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('publicar') && text.includes('resultado')) {
    return {
      title: 'Publicando resultados...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if ((text.includes('generar') || text.includes('descargar')) && (text.includes('document') || text.includes('pdf') || text.includes('receta') || text.includes('comprobante') || text.includes('pedido'))) {
    return {
      title: 'Generando documento...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('guardar borrador') || (text.includes('soap') && text.includes('guardar'))) {
    return {
      title: 'Guardando nota clínica...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('firmar') && text.includes('nota')) {
    return {
      title: 'Firmando nota clínica...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('cancelar') && text.includes('cita')) {
    return {
      title: 'Cancelando cita...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('cancelar') && text.includes('control')) {
    return {
      title: 'Cancelando control...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('reagendar') && text.includes('control')) {
    return {
      title: 'Reagendando control...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('reagendar') || text.includes('reprogramar') || actionUrl.includes('editar-cita') || actionUrl.includes('reagendar')) {
    return {
      title: 'Reagendando cita...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('agendar') && text.includes('control')) {
    return {
      title: 'Agendando control...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('registrar') && text.includes('cita')) {
    return {
      title: 'Registrando cita...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('/crear-cita') || actionUrl.includes('crear-cita')) {
    return {
      title: 'Registrando cita...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('/editar-cita') || actionUrl.includes('editar-cita')) {
    return {
      title: 'Reagendando cita...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('registrar') && text.includes('usuario')) {
    return {
      title: 'Registrando usuario...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('actualizar') && text.includes('usuario')) {
    return {
      title: 'Actualizando usuario...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('/perfil') && (text.includes('guardar') || text.includes('actualizar'))) {
    return {
      title: 'Guardando perfil...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('contacto') || text.includes('mensaje')) {
    return {
      title: 'Enviando información...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('login') || text.includes('ingresar') || text.includes('iniciar sesión')) {
    return {
      title: 'Iniciando sesión...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('register') || text.includes('registrarse')) {
    return {
      title: 'Registrando usuario...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('password') || text.includes('restablecer')) {
    return {
      title: 'Restableciendo contraseña...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('horario') && (text.includes('guardar') || formMethod !== 'GET')) {
    return {
      title: 'Guardando horario...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (actionUrl.includes('personalizacion') && (text.includes('solicitar') || text.includes('guardar'))) {
    return {
      title: text.includes('solicitar') ? 'Solicitando acceso...' : 'Guardando cambios...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('guardar') && actionUrl.includes('dependiente')) {
    return {
      title: 'Guardando familiar...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('guardar') && actionUrl.includes('pago')) {
    return {
      title: 'Guardando pago...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('publicar') && text.includes('resultado')) {
    return {
      title: 'Publicando resultados...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('reenviar') && (text.includes('resultado') || text.includes('pedido') || text.includes('receta'))) {
    return {
      title: 'Reenviando documento...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('firmar y cerrar')) {
    return {
      title: 'Firmando nota clínica...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('guardar cambios') && actionUrl.includes('cita')) {
    return {
      title: 'Reagendando cita...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('guardar') || text.includes('actualizar')) {
    return {
      title: 'Guardando cambios...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('enviar') || text.includes('solicitar')) {
    return {
      title: 'Enviando información...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  if (text.includes('cancelar') || text.includes('anular')) {
    return {
      title: 'Procesando solicitud...',
      description: defaultDescription('operation'),
      mode: 'operation',
    };
  }

  return {
    title: defaultTitle('operation'),
    description: defaultDescription('operation'),
    mode: 'operation',
  };
}

function lockButton(button, text = null) {
  if (!button || button.dataset.actionLockButton === '1') {
    return;
  }

  const rect = button.getBoundingClientRect();
  button.dataset.actionLockButton = '1';
  button.dataset.actionLockHtml = button.innerHTML;
  button.dataset.actionLockDisabled = button.disabled ? '1' : '0';

  if (rect.width > 0 && !button.style.minWidth) {
    button.style.minWidth = `${Math.ceil(rect.width)}px`;
    button.dataset.actionLockMinWidth = '1';
  }

  button.disabled = true;
  button.setAttribute('aria-busy', 'true');
  button.classList.add('is-processing');

  const nextText = text || loadingTextFor(button);
  if (nextText) {
    button.textContent = nextText;
  }
}

function unlockButton(button) {
  if (!button || button.dataset.actionLockButton !== '1') {
    return;
  }

  button.innerHTML = button.dataset.actionLockHtml || button.innerHTML;
  button.disabled = button.dataset.actionLockDisabled === '1';
  button.removeAttribute('aria-busy');
  button.classList.remove('is-processing');

  if (button.dataset.actionLockMinWidth === '1') {
    button.style.minWidth = '';
  }

  delete button.dataset.actionLockButton;
  delete button.dataset.actionLockHtml;
  delete button.dataset.actionLockDisabled;
  delete button.dataset.actionLockMinWidth;
}

function loadingTextFor(button) {
  const configured = button?.dataset?.loadingText;
  if (configured) {
    return configured;
  }

  const text = getElementText(button).toLowerCase();
  if (text.includes('guardar borrador')) return 'Guardando...';
  if (text.includes('guardar') || text.includes('actualizar')) return 'Guardando...';
  if (text.includes('enviar') || text.includes('solicitar')) return 'Enviando...';
  if (text.includes('agendar') || text.includes('reagendar')) return 'Agendando...';
  if (text.includes('registrar') || text.includes('crear')) return 'Registrando...';
  if (text.includes('subir') || text.includes('comprobante')) return 'Subiendo...';
  if (text.includes('aprobar')) return 'Aprobando...';
  if (text.includes('rechazar')) return 'Rechazando...';
  if (text.includes('anular') || text.includes('cancelar')) return 'Procesando...';
  if (text.includes('firmar')) return 'Firmando...';
  if (text.includes('restablecer')) return 'Restableciendo...';
  if (text.includes('cambiar')) return 'Actualizando...';
  if (text.includes('entrar') || text.includes('iniciar')) return 'Ingresando...';

  return 'Procesando...';
}

function findSubmitButtons(form) {
  return Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
}

function preserveSubmitterValue(form, submitter) {
  if (!submitter?.name || submitter.disabled) {
    return;
  }

  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = submitter.name;
  hidden.value = submitter.value;
  hidden.setAttribute(SUBMITTER_NAME, '1');
  form.appendChild(hidden);
}

function lockForm(form, submitter = null) {
  if (!shouldLockForm(form, submitter)) {
    return false;
  }

  if (form.getAttribute(FORM_LOCK_ATTR) === '1') {
    return false;
  }

  const copy = resolveOperationCopy(form, submitter);
  if (!lock(copy, { mode: copy.mode || 'operation' })) {
    return false;
  }

  form.setAttribute(FORM_LOCK_ATTR, '1');
  form.setAttribute('aria-busy', 'true');
  preserveSubmitterValue(form, submitter);

  const buttons = findSubmitButtons(form);
  const buttonToLabel = submitter?.matches?.('button, input[type="submit"]')
    ? submitter
    : buttons[0] || null;

  buttons.forEach((button) => {
    lockButton(button, button === buttonToLabel ? null : getElementText(button));
  });

  return true;
}

function unlockForm(form) {
  if (!form) {
    return;
  }

  form.removeAttribute(FORM_LOCK_ATTR);
  form.removeAttribute('aria-busy');
  form.querySelectorAll(`[${SUBMITTER_NAME}]`).forEach((node) => node.remove());
  findSubmitButtons(form).forEach(unlockButton);
}

function clickedSubmitter(event) {
  const target = event.target?.closest?.('button, input[type="submit"]');
  if (!target || target.type !== 'submit') {
    return;
  }

  const form = target.form;
  if (form) {
    form.__actionLockSubmitter = target;
  }
}

function onFormSubmit(event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
    return;
  }

  const submitter = event.submitter || form.__actionLockSubmitter || null;
  delete form.__actionLockSubmitter;

  if (!shouldLockForm(form, submitter)) {
    return;
  }

  if (form.getAttribute(FORM_LOCK_ATTR) === '1') {
    event.preventDefault();
    event.stopImmediatePropagation();
    return;
  }

  lockForm(form, submitter);
}

function installProgrammaticSubmitGuard() {
  HTMLFormElement.prototype.submit = function guardedSubmit() {
    if (!shouldLockForm(this)) {
      return nativeSubmit.call(this);
    }

    if (this.getAttribute(FORM_LOCK_ATTR) === '1') {
      return undefined;
    }

    lockForm(this);
    return nativeSubmit.call(this);
  };

  if (nativeRequestSubmit) {
    HTMLFormElement.prototype.requestSubmit = function guardedRequestSubmit(submitter) {
      if (!shouldLockForm(this, submitter)) {
        return nativeRequestSubmit.call(this, submitter);
      }

      if (this.getAttribute(FORM_LOCK_ATTR) === '1') {
        return undefined;
      }

      return nativeRequestSubmit.call(this, submitter);
    };
  }
}

function formDataSignature(formData) {
  const parts = [];
  formData.forEach((value, key) => {
    if (value instanceof File) {
      parts.push(`${key}=file:${value.name}:${value.size}:${value.lastModified}`);
      return;
    }

    if (value instanceof Blob) {
      parts.push(`${key}=blob:${value.size}:${value.type}`);
      return;
    }

    parts.push(`${key}=${String(value)}`);
  });

  return parts.join('&');
}

function bodySignature(body) {
  if (!body) return '';
  if (typeof body === 'string') return body;
  if (body instanceof URLSearchParams) return body.toString();
  if (body instanceof FormData) return formDataSignature(body);
  if (body instanceof Blob) return `blob:${body.size}:${body.type}`;
  if (body instanceof ArrayBuffer) return `arraybuffer:${body.byteLength}`;
  return '[body]';
}

function fetchKey(resource, init = {}) {
  const url = typeof resource === 'string'
    ? new URL(resource, window.location.href).toString()
    : new URL(resource?.url || '', window.location.href).toString();
  const method = normalizeMethod(init.method || resource?.method || 'GET');

  return `${method} ${url} ${bodySignature(init.body)}`;
}

function installFetchGuard() {
  if (!nativeFetch) {
    return;
  }

  window.fetch = function guardedFetch(resource, init = {}) {
    const method = normalizeMethod(init?.method || resource?.method || 'GET');
    if (!UNSAFE_METHODS.has(method) || init?.actionLock === false) {
      return nativeFetch(resource, init);
    }

    const key = fetchKey(resource, init);
    const existing = inFlightFetches.get(key);
    if (existing) {
      return existing.promise.then(() => existing.response?.clone() || nativeFetch(resource, init));
    }

    const entry = { response: null, promise: null };
    entry.promise = nativeFetch(resource, init)
      .then((response) => {
        entry.response = response.clone();
        return response;
      })
      .finally(() => {
        window.setTimeout(() => inFlightFetches.delete(key), 1200);
      });

    inFlightFetches.set(key, entry);
    return entry.promise;
  };
}

function openNavigation(event) {
  const link = event.target?.closest?.('a[href]');
  if (!link || event.defaultPrevented) {
    return;
  }

  if (
    link.hasAttribute('download') ||
    link.hasAttribute('target') ||
    link.hasAttribute('data-action-lock-ignore') ||
    link.hasAttribute('data-legal-open') ||
    link.hasAttribute('data-modal-toggle') ||
    link.hasAttribute('data-dropdown-toggle') ||
    link.hasAttribute('data-collapse-toggle') ||
    link.hasAttribute('data-tooltip-target') ||
    link.hasAttribute('data-sidebar-toggle') ||
    link.hasAttribute('data-sidebar-close') ||
    link.hasAttribute('data-open-sidebar') ||
    link.hasAttribute('data-theme-toggle') ||
    link.hasAttribute('data-role-switcher-link') ||
    link.hasAttribute('data-login-trigger') ||
    link.hasAttribute('data-demo-allow') ||
    link.hasAttribute('data-confirm-form') ||
    link.hasAttribute('data-confirm-trigger') ||
    link.hasAttribute('data-confirm-title') ||
    link.hasAttribute('data-confirm-message') ||
    link.hasAttribute('onclick')
  ) {
    return;
  }

  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return;
  }

  const rawHref = link.getAttribute('href') || '';
  if (
    rawHref === '' ||
    rawHref === '#' ||
    rawHref.startsWith('#') ||
    rawHref.startsWith('javascript:') ||
    rawHref.startsWith('mailto:') ||
    rawHref.startsWith('tel:')
  ) {
    return;
  }

  let url;
  try {
    url = new URL(rawHref, window.location.href);
  } catch {
    return;
  }

  if (url.origin !== window.location.origin) {
    return;
  }

  const current = new URL(window.location.href);
  if (url.pathname === current.pathname && url.search === current.search && url.hash && url.hash !== current.hash) {
    return;
  }

  event.preventDefault();
  event.stopImmediatePropagation();

  const copy = resolveNavigationCopy(link);
  const started = startNavigation(copy);
  if (!started) {
    return;
  }

  window.requestAnimationFrame(() => {
    window.location.assign(url.toString());
  });
}

function onFocusIn(event) {
  if (!state.active || !state.overlay || !event.target) {
    return;
  }

  if (state.overlay.contains(event.target)) {
    return;
  }

  event.stopPropagation();
  focusOverlay();
}

function onKeyDown(event) {
  if (!state.active || !state.overlay) {
    return;
  }

  if (event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (event.key === 'Tab' || event.key === 'Escape' || event.key === 'Enter' || event.key === ' ' || event.key.startsWith('Arrow')) {
    if (!state.overlay.contains(event.target)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      focusOverlay();
    }
  }
}

function onPointerDown(event) {
  if (!shouldBlockPointerTarget(event.target)) {
    return;
  }

  event.preventDefault();
  event.stopImmediatePropagation();
}

function onClickCapture(event) {
  if (!state.active) {
    openNavigation(event);
    return;
  }

  if (shouldBlockPointerTarget(event.target)) {
    event.preventDefault();
    event.stopImmediatePropagation();
  }
}

function restorePageLocks() {
  document.querySelectorAll(`[${FORM_LOCK_ATTR}="1"]`).forEach(unlockForm);
  document.querySelectorAll('[data-action-lock-button="1"]').forEach(unlockButton);
  unlock();
}

function installGlobalListeners() {
  if (state.listenersReady) {
    return;
  }

  state.listenersReady = true;
  document.addEventListener('click', onClickCapture, true);
  document.addEventListener('pointerdown', onPointerDown, true);
  document.addEventListener('mousedown', onPointerDown, true);
  document.addEventListener('touchstart', onPointerDown, true);
  document.addEventListener('keydown', onKeyDown, true);
  document.addEventListener('focusin', onFocusIn, true);
  document.addEventListener('click', clickedSubmitter, true);
  document.addEventListener('submit', onFormSubmit);
  window.addEventListener('pageshow', restorePageLocks);
}

function fetchWithActionLock(resource, init = {}, copy = null, options = {}) {
  return run(copy, () => window.fetch(resource, init), options);
}

function resolveCopyFromButton(button) {
  const copy = readCopy(button);
  const text = getElementText(button);

  return {
    title: copy.title || text || defaultTitle('operation'),
    description: copy.description || defaultDescription('operation'),
    mode: copy.mode || 'operation',
  };
}

installProgrammaticSubmitGuard();
installFetchGuard();
installGlobalListeners();

window.ActionLock = {
  lockButton,
  unlockButton,
  lockForm,
  unlockForm,
  startOperation,
  startNavigation,
  lock,
  unlock,
  run,
  fetch: fetchWithActionLock,
  resolveOperationCopy,
  resolveNavigationCopy,
  resolveCopyFromButton,
  isActive: () => state.active,
};
