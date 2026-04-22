function initPanelChrome() {
  const main = document.querySelector('.dashboard-content main');
  if (!main) return;

  const anchor = document.querySelector('[data-panel-back-anchor]');
  initPanelBackButton(main, anchor);
}

function initPanelBackButton(main, anchor) {
  if (!anchor) return false;
  if (anchor.dataset.panelBackReady === '1') return true;
  anchor.dataset.panelBackReady = '1';

  const backButton = anchor.querySelector('[data-panel-back-button]');
  if (!backButton) return true;

  const legacyFallbackUrl = hideLegacyBackActions(main, anchor);
  const fallbackUrl = resolveFallbackUrl(legacyFallbackUrl, anchor.dataset.panelBackFallback);
  moveLeadActionBarIntoBackAnchor(main, anchor);
  bindBackButton(backButton, fallbackUrl);

  return true;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPanelChrome, { once: true });
} else {
  initPanelChrome();
}

function bindBackButton(backButton, fallbackUrl) {
  backButton.addEventListener('click', (event) => {
    event.preventDefault();

    if (shouldUseHistoryBack(fallbackUrl)) {
      window.history.back();
      return;
    }

    if (fallbackUrl && !isCurrentPage(fallbackUrl)) {
      window.location.assign(fallbackUrl);
    }
  });
}

function resolveFallbackUrl(...urls) {
  for (const url of urls) {
    if (url && !isCurrentPage(url)) {
      return url;
    }
  }

  return '';
}

function shouldUseHistoryBack(fallbackUrl) {
  if (window.history.length <= 1 || !document.referrer) {
    return false;
  }

  try {
    const referrer = new URL(document.referrer);
    if (referrer.origin !== window.location.origin) {
      return false;
    }

    const current = new URL(window.location.href);
    if (samePage(referrer, current)) {
      return false;
    }

    if (fallbackUrl) {
      const fallback = new URL(fallbackUrl, window.location.origin);
      if (samePage(fallback, current)) {
        return false;
      }
    }

    return true;
  } catch {
    return false;
  }
}

function samePage(left, right) {
  return left.origin === right.origin
    && left.pathname === right.pathname
    && left.search === right.search;
}

function isCurrentPage(url) {
  try {
    return samePage(new URL(url, window.location.origin), new URL(window.location.href));
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

  main.querySelectorAll('[data-form-actions], .page-header__actions, .panel-action-bar__actions').forEach((container) => {
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

  main.querySelectorAll('.panel-action-bar').forEach((container) => {
    if (!hasVisiblePanelContent(container)) {
      container.classList.add('legacy-panel-back-action');
    }
  });

  return fallbackUrl;
}

function moveLeadActionBarIntoBackAnchor(main, anchor) {
  const extras = anchor.querySelector('[data-panel-back-extras]');
  const actionBar = findLeadActionBar(main, anchor);

  if (!extras || !actionBar) {
    return;
  }

  actionBar.classList.add('panel-action-bar--in-back');
  extras.append(actionBar);
}

function findLeadActionBar(main, anchor) {
  const firstContent = Array.from(main.children).find((element) => (
    (!anchor || element !== anchor) && !element.classList.contains('legacy-panel-back-action')
  ));

  if (!firstContent) {
    return null;
  }

  if (firstContent.matches('.panel-action-bar') && hasVisiblePanelContent(firstContent)) {
    return firstContent;
  }

  const firstChild = Array.from(firstContent.children || []).find(
    (element) => !element.classList.contains('legacy-panel-back-action')
  );

  if (firstChild?.matches('.panel-action-bar') && hasVisiblePanelContent(firstChild)) {
    return firstChild;
  }

  return null;
}

function hasVisiblePanelContent(container) {
  return Array.from(container.children).some((child) => {
    if (child.classList.contains('legacy-panel-back-action')) {
      return false;
    }

    if (child.matches('.panel-action-bar__actions')) {
      return Array.from(child.querySelectorAll('a, button')).some(
        (element) => !element.classList.contains('legacy-panel-back-action')
      );
    }

    return (child.textContent || '').replace(/\s+/g, ' ').trim() !== ''
      || child.querySelector('a:not(.legacy-panel-back-action), button:not(.legacy-panel-back-action), input, select, textarea');
  });
}
