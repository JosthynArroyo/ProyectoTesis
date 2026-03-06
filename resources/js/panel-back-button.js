document.addEventListener('DOMContentLoaded', () => {
  const anchor = document.querySelector('[data-panel-back-anchor]');
  if (!anchor) return;

  const main = document.querySelector('.dashboard-content main');
  const backButton = anchor.querySelector('[data-panel-back-button]');
  if (!main || !backButton) return;

  const contentRoot = Array.from(main.children).find((child) => child !== anchor) || main;
  const target = resolveTarget(contentRoot) || main;

  if (target && target !== anchor) {
    target.prepend(anchor);
    if (target.classList?.contains('card') || target.classList?.contains('medical-toolbar')) {
      target.classList.add('panel-card--with-back');
    }
  }

  const fallbackUrl = hideLegacyBackActions(main, anchor) || anchor.dataset.panelBackFallback || '/';

  backButton.addEventListener('click', (event) => {
    event.preventDefault();

    if (shouldUseHistoryBack()) {
      window.history.back();
      return;
    }

    window.location.assign(fallbackUrl);
  });
});

function resolveTarget(root) {
  if (!root) return null;

  if (root.matches?.('.card, header.card, section.card, div.card, .medical-toolbar')) {
    return root;
  }

  const selectors = [
    ':scope > .card',
    ':scope > header.card',
    ':scope > section.card',
    ':scope > div.card',
    ':scope > .medical-toolbar',
  ];

  for (const selector of selectors) {
    const match = root.querySelector?.(selector);
    if (match) return match;
  }

  return root.firstElementChild || null;
}

function shouldUseHistoryBack() {
  if (window.history.length <= 1 || !document.referrer) {
    return false;
  }

  try {
    const referrer = new URL(document.referrer);
    return referrer.origin === window.location.origin;
  } catch {
    return false;
  }
}

function hideLegacyBackActions(main, anchor) {
  let fallbackUrl = '';

  const candidates = Array.from(main.querySelectorAll('a, button')).filter((element) => {
    if (anchor.contains(element)) return false;
    const text = (element.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    return text.startsWith('volver');
  });

  candidates.forEach((candidate) => {
    if (!fallbackUrl && candidate.tagName === 'A' && candidate.getAttribute('href')) {
      fallbackUrl = candidate.href;
    }

    candidate.classList.add('legacy-panel-back-action');
    candidate.setAttribute('aria-hidden', 'true');
    candidate.setAttribute('tabindex', '-1');

    if ('disabled' in candidate) {
      candidate.disabled = true;
    }
  });

  main.querySelectorAll('[data-form-actions], .page-header__actions').forEach((container) => {
    const hasVisibleActions = Array.from(container.querySelectorAll('a, button')).some(
      (element) => !element.classList.contains('legacy-panel-back-action')
    );

    if (!hasVisibleActions) {
      container.classList.add('legacy-panel-back-action');

      const card = container.closest('.card');
      if (card && card.children.length === 1 && card.firstElementChild === container) {
        card.classList.add('legacy-panel-back-action');
      }
    }
  });

  return fallbackUrl;
}
