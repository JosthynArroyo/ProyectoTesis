let kebabMenusInitialized = false;

function getClippingAncestor(element) {
  let current = element?.parentElement ?? null;

  while (current && current !== document.body) {
    const styles = window.getComputedStyle(current);
    const overflowValues = [styles.overflow, styles.overflowX, styles.overflowY];
    const clipsChildren = overflowValues.some((value) => ['hidden', 'auto', 'scroll', 'clip'].includes(value));

    if (clipsChildren) {
      return current;
    }

    current = current.parentElement;
  }

  return null;
}

function setMenuState(menu, open) {
  menu.setAttribute('data-open', open ? '1' : '0');
  menu.setAttribute('aria-hidden', open ? 'false' : 'true');

  if (!open) {
    menu.setAttribute('data-placement', 'bottom');
  }
}

function setTriggerState(trigger, open) {
  trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function setMenuPlacement(trigger, menu) {
  const forcedPlacement = trigger.dataset.kebabPlacement;
  if (forcedPlacement === 'top' || forcedPlacement === 'bottom') {
    menu.setAttribute('data-placement', forcedPlacement);
    return;
  }

  menu.setAttribute('data-placement', 'bottom');

  const triggerRect = trigger.getBoundingClientRect();
  const menuRect = menu.getBoundingClientRect();
  const viewportPadding = 16;
  const viewportBelow = window.innerHeight - triggerRect.bottom - viewportPadding;
  const viewportAbove = triggerRect.top - viewportPadding;
  const clippingAncestor = getClippingAncestor(trigger);

  let containerBelow = viewportBelow;
  let containerAbove = viewportAbove;

  if (clippingAncestor) {
    const clippingRect = clippingAncestor.getBoundingClientRect();
    containerBelow = clippingRect.bottom - triggerRect.bottom - viewportPadding;
    containerAbove = triggerRect.top - clippingRect.top - viewportPadding;
  }

  const spaceBelow = Math.min(viewportBelow, containerBelow);
  const spaceAbove = Math.min(viewportAbove, containerAbove);
  const shouldOpenUp =
    (menuRect.height > spaceBelow && spaceAbove > spaceBelow) ||
    (spaceBelow < 180 && spaceAbove > spaceBelow);

  menu.setAttribute('data-placement', shouldOpenUp ? 'top' : 'bottom');
}

function closeKebabs(exceptId = '') {
  document.querySelectorAll('[data-kebab]').forEach((trigger) => {
    const menuId = trigger.dataset.kebab || '';
    const menu = menuId ? document.getElementById(menuId) : null;
    const shouldStayOpen = Boolean(exceptId && menuId === exceptId);

    setTriggerState(trigger, shouldStayOpen);

    if (menu) {
      setMenuState(menu, shouldStayOpen);
    }
  });
}

export function initKebabMenus() {
  if (kebabMenusInitialized) {
    return;
  }

  document.querySelectorAll('[data-kebab]').forEach((trigger) => {
    const menu = document.getElementById(trigger.dataset.kebab || '');
    setTriggerState(trigger, menu?.getAttribute('data-open') === '1');

    if (menu && !menu.hasAttribute('aria-hidden')) {
      menu.setAttribute('aria-hidden', menu.getAttribute('data-open') === '1' ? 'false' : 'true');
    }
  });

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-kebab]');
    if (!trigger) {
      closeKebabs();
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const menuId = trigger.dataset.kebab || '';
    const menu = menuId ? document.getElementById(menuId) : null;
    if (!menu) {
      return;
    }

    const isOpen = menu.getAttribute('data-open') === '1';
    closeKebabs();

    if (!isOpen) {
      setTriggerState(trigger, true);
      setMenuState(menu, true);
      setMenuPlacement(trigger, menu);

      requestAnimationFrame(() => {
        if (menu.getAttribute('data-open') === '1') {
          setMenuPlacement(trigger, menu);
        }
      });
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeKebabs();
    }
  });

  kebabMenusInitialized = true;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initKebabMenus, { once: true });
} else {
  initKebabMenus();
}
