function canOpenPicker(input) {
  return input instanceof HTMLInputElement
    && !input.disabled
    && !input.readOnly
    && ['date', 'datetime-local', 'month'].includes(input.type);
}

function openPicker(input, allowClickFallback = false) {
  if (!canOpenPicker(input)) {
    return;
  }

  input.focus({ preventScroll: true });

  if (typeof input.showPicker === 'function') {
    try {
      input.showPicker();
      return;
    } catch {
      // showPicker can be restricted to trusted user gestures.
    }
  }

  if (allowClickFallback) {
    input.click();
  }
}

function bindTrigger(trigger) {
  if (!(trigger instanceof HTMLElement) || trigger.dataset.nativeDateBound === '1') {
    return;
  }

  const selector = trigger.getAttribute('data-native-date-open');
  const input = selector ? document.querySelector(selector) : null;
  if (!canOpenPicker(input)) {
    return;
  }

  trigger.dataset.nativeDateBound = '1';
  trigger.addEventListener('click', (event) => {
    event.preventDefault();
    openPicker(input, true);
  });
}

function bindInput(input) {
  if (!canOpenPicker(input) || input.dataset.nativeDateInputBound === '1') {
    return;
  }

  input.dataset.nativeDateInputBound = '1';
  input.addEventListener('click', () => openPicker(input));
}

export function initNativeDatePickers(root = document) {
  if (!root || typeof root.querySelectorAll !== 'function') {
    return;
  }

  root.querySelectorAll('[data-native-date-open]').forEach(bindTrigger);
  root.querySelectorAll('input[type="date"], input[type="datetime-local"], input[type="month"]').forEach(bindInput);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initNativeDatePickers(), { once: true });
} else {
  initNativeDatePickers();
}
