const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'textarea:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

const LEGAL_MODAL_ALIASES = {
  privacy: 'privacy-policy-modal',
  'privacy-policy': 'privacy-policy-modal',
  terms: 'terms-service-modal',
  'terms-service': 'terms-service-modal',
};

if (!window.__legalModalsInitialized && typeof window.openLegalModal !== 'function') {
  window.__legalModalsInitialized = true;

  let activeModal = null;
  let previousFocus = null;

function resolveModalId(value) {
  if (!value) {
    return null;
  }

  const normalized = value.replace(/^#/, '').trim();

  return LEGAL_MODAL_ALIASES[normalized] || normalized;
}

function getModal(value) {
  const id = resolveModalId(value);

  return id ? document.getElementById(id) : null;
}

function setModalState(modal, open) {
  modal.classList.toggle('is-open', open);
  modal.setAttribute('aria-hidden', open ? 'false' : 'true');
}

function openLegalModal(id) {
  const modal = getModal(id);

  if (!modal || !modal.matches('[data-legal-modal]')) {
    return;
  }

  if (activeModal && activeModal !== modal) {
    closeLegalModal(false);
  }

  previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  activeModal = modal;
  setModalState(modal, true);
  document.body.classList.add('modal-open');

  const dialog = modal.querySelector('[role="document"]');
  window.requestAnimationFrame(() => {
    (dialog || modal).focus({ preventScroll: true });
  });
}

function closeLegalModal(restoreFocus = true) {
  if (!activeModal) {
    return;
  }

  const modal = activeModal;
  activeModal = null;
  setModalState(modal, false);
  document.body.classList.remove('modal-open');

  if (restoreFocus && previousFocus) {
    previousFocus.focus({ preventScroll: true });
  }

  previousFocus = null;
}

function trapFocus(event) {
  if (!activeModal || event.key !== 'Tab') {
    return;
  }

  const focusable = Array.from(activeModal.querySelectorAll(FOCUSABLE_SELECTOR))
    .filter((element) => element instanceof HTMLElement && element.offsetParent !== null);

  if (!focusable.length) {
    event.preventDefault();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

document.addEventListener('click', (event) => {
  const target = event.target instanceof Element ? event.target : null;

  if (!target) {
    return;
  }

  const opener = target.closest('[data-legal-open]');

  if (opener) {
    event.preventDefault();
    openLegalModal(opener.getAttribute('data-legal-open'));
    return;
  }

  const closer = target.closest('[data-legal-close]');

  if (closer && activeModal && activeModal.contains(closer)) {
    event.preventDefault();
    closeLegalModal();
  }
});

document.addEventListener('keydown', (event) => {
  if (!activeModal) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    closeLegalModal();
    return;
  }

  trapFocus(event);
});

  window.openLegalModal = openLegalModal;
  window.closeLegalModal = closeLegalModal;
}
