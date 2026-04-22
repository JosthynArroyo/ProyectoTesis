function initSidebarToggle() {
  const sidebar = document.querySelector('.dashboard-sidebar');
  if (!sidebar || sidebar.dataset.sidebarToggleReady === '1') return;
  sidebar.dataset.sidebarToggleReady = '1';
  const overlay = document.querySelector('[data-sidebar-overlay]');

  const openers = Array.from(new Set([
    ...document.querySelectorAll('#menu_bar'),
    ...document.querySelectorAll('[data-open-sidebar]')
  ]));
  const closeBtn = sidebar.querySelector('.top .close');
  const openClass = 'translate-x-0';
  const closedClass = '-translate-x-full';
  const desktopMq = window.matchMedia('(min-width: 1024px)');

  let lastScrollY = 0;
  let prevInline = {
    display: sidebar.style.display,
    left: sidebar.style.left,
    transform: sidebar.style.transform,
    translate: sidebar.style.translate,
    position: sidebar.style.position,
    top: sidebar.style.top,
    bottom: sidebar.style.bottom,
    height: sidebar.style.height,
    zIndex: sidebar.style.zIndex
  };
  let isOpen = false;
  function resetOffsets() {
    sidebar.style.position = prevInline.position || '';
    sidebar.style.top = prevInline.top || '';
    sidebar.style.bottom = prevInline.bottom || '';
    sidebar.style.height = prevInline.height || '';
    sidebar.style.zIndex = prevInline.zIndex || '';
    if (overlay) {
      overlay.style.top = '';
      overlay.style.bottom = '';
      overlay.style.left = '';
      overlay.style.right = '';
      overlay.style.height = '';
    }
  }

  function lockScroll() {
    lastScrollY = window.scrollY || 0;
    document.body.style.overflow = 'hidden';
  }

  function unlockScroll() {
    document.body.style.overflow = '';
    window.scrollTo(0, lastScrollY);
  }

  function openSidebar(e) {
    if (e) e.preventDefault();
    prevInline = {
      display: sidebar.style.display,
      left: sidebar.style.left,
      transform: sidebar.style.transform,
      translate: sidebar.style.translate,
      position: sidebar.style.position,
      top: sidebar.style.top,
      bottom: sidebar.style.bottom,
      height: sidebar.style.height,
      zIndex: sidebar.style.zIndex
    };

    sidebar.classList.remove(closedClass);
    sidebar.classList.add(openClass);
    sidebar.style.display = 'block';
    sidebar.style.left = '0';
    sidebar.style.setProperty('translate', '0 0');
    sidebar.style.transform = 'translateX(0)';
    sidebar.setAttribute('aria-hidden', 'false');

    if (getComputedStyle(sidebar).position !== 'fixed') {
      sidebar.style.position = 'fixed';
      sidebar.style.top = '0';
      sidebar.style.bottom = '0';
      sidebar.style.zIndex = '9999';
    }

    if (!sidebar.style.zIndex) {
      sidebar.style.zIndex = '9999';
    }

    sidebar.style.animation = 'none';
    sidebar.offsetHeight;
    sidebar.style.animation = 'menuAni .28s forwards';

    lockScroll();
    if (overlay) {
      overlay.classList.remove('hidden');
      overlay.classList.add('block');
    }
    isOpen = true;
  }

  function closeSidebar(e) {
    if (e) e.preventDefault();
    isOpen = false;
    sidebar.style.animation = '';
    sidebar.classList.add(closedClass);
    sidebar.classList.remove(openClass);
    sidebar.style.setProperty('translate', prevInline.translate || '');
    sidebar.style.transform = 'translateX(-110%)';
    setTimeout(() => {
      sidebar.style.display = prevInline.display || '';
      sidebar.style.left = prevInline.left || '';
      sidebar.style.transform = prevInline.transform || '';
      sidebar.style.translate = prevInline.translate || '';
      sidebar.setAttribute('aria-hidden', 'true');
      if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('block');
      }
      resetOffsets();
      unlockScroll();
    }, 180);
  }

  function toggleSidebar(e) {
    if (isOpen || sidebar.getAttribute('aria-hidden') === 'false') {
      closeSidebar(e);
    } else {
      openSidebar(e);
    }
  }

  openers.forEach(btn => btn.addEventListener('click', toggleSidebar));
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape' && isOpen) {
      closeSidebar(ev);
    }
  });

  if (!desktopMq.matches) {
    sidebar.setAttribute('aria-hidden', 'true');
  }

  window.addEventListener('resize', () => {
    if (desktopMq.matches) {
      sidebar.style.display = '';
      sidebar.style.transform = '';
      sidebar.style.animation = '';
      sidebar.removeAttribute('aria-hidden');
      isOpen = false;
      if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('block');
      }
      resetOffsets();
      unlockScroll();
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSidebarToggle, { once: true });
} else {
  initSidebarToggle();
}
