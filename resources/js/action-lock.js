const UNSAFE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const FORM_LOCK_ATTR = 'data-action-lock-active';
const SUBMITTER_NAME = 'data-action-lock-submitter';
const inFlightFetches = new Map();

const nativeSubmit = HTMLFormElement.prototype.submit;
const nativeRequestSubmit = HTMLFormElement.prototype.requestSubmit;
const nativeFetch = window.fetch ? window.fetch.bind(window) : null;

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

function loadingTextFor(button) {
  const configured = button?.dataset?.loadingText;
  if (configured) {
    return configured;
  }

  const text = (button?.textContent || '').trim().toLowerCase();
  if (text.includes('guardar') || text.includes('actualizar')) return 'Guardando...';
  if (text.includes('enviar') || text.includes('solicitar')) return 'Enviando...';
  if (text.includes('agendar') || text.includes('reagendar')) return 'Agendando...';
  if (text.includes('registrar') || text.includes('crear')) return 'Registrando...';
  if (text.includes('subir') || text.includes('comprobante')) return 'Subiendo...';
  if (text.includes('aprobar')) return 'Aprobando...';
  if (text.includes('rechazar')) return 'Rechazando...';
  if (text.includes('eliminar') || text.includes('cancelar') || text.includes('anular')) return 'Procesando...';
  if (text.includes('entrar') || text.includes('iniciar')) return 'Ingresando...';

  return 'Procesando...';
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

function findSubmitButtons(form) {
  return Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
}

function lockForm(form, submitter = null) {
  if (!shouldLockForm(form, submitter)) {
    return false;
  }

  if (form.getAttribute(FORM_LOCK_ATTR) === '1') {
    return false;
  }

  form.setAttribute(FORM_LOCK_ATTR, '1');
  preserveSubmitterValue(form, submitter);

  const buttons = findSubmitButtons(form);
  const buttonToLabel = submitter?.matches?.('button, input[type="submit"]')
    ? submitter
    : buttons[0] || null;

  buttons.forEach((button) => {
    lockButton(button, button === buttonToLabel ? null : button.textContent);
  });

  return true;
}

function unlockForm(form) {
  if (!form) return;

  form.removeAttribute(FORM_LOCK_ATTR);
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

function restorePageLocks() {
  document.querySelectorAll(`[${FORM_LOCK_ATTR}="1"]`).forEach(unlockForm);
  document.querySelectorAll('[data-action-lock-button="1"]').forEach(unlockButton);
}

document.addEventListener('click', clickedSubmitter, true);
document.addEventListener('submit', onFormSubmit);
window.addEventListener('pageshow', restorePageLocks);

installProgrammaticSubmitGuard();
installFetchGuard();

window.ActionLock = {
  lockButton,
  unlockButton,
  lockForm,
  unlockForm,
};
