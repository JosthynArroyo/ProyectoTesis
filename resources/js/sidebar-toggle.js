document.addEventListener('DOMContentLoaded', () => {
  const aside = document.querySelector('aside');
  if (!aside) return;
  const overlay = document.querySelector('[data-sidebar-overlay]');

  const openers = [
    ...document.querySelectorAll('#menu_bar'),
    ...document.querySelectorAll('[data-open-sidebar]')
  ];
  const closeBtn = aside.querySelector('.top .close');
  const openClass = 'translate-x-0';
  const closedClass = '-translate-x-full';
  const desktopMq = window.matchMedia('(min-width: 1024px)');

  let lastScrollY = 0;
  let prevInline = {
    display: aside.style.display,
    left: aside.style.left,
    transform: aside.style.transform,
    translate: aside.style.translate,
    position: aside.style.position,
    top: aside.style.top,
    bottom: aside.style.bottom,
    height: aside.style.height,
    zIndex: aside.style.zIndex
  };
  let isOpen = false;
  function resetOffsets() {
    aside.style.position = prevInline.position || '';
    aside.style.top = prevInline.top || '';
    aside.style.bottom = prevInline.bottom || '';
    aside.style.height = prevInline.height || '';
    aside.style.zIndex = prevInline.zIndex || '';
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
      display: aside.style.display,
      left: aside.style.left,
      transform: aside.style.transform,
      translate: aside.style.translate,
      position: aside.style.position,
      top: aside.style.top,
      bottom: aside.style.bottom,
      height: aside.style.height,
      zIndex: aside.style.zIndex
    };

    aside.classList.remove(closedClass);
    aside.classList.add(openClass);
    aside.style.display = 'block';
    aside.style.left = '0';
    aside.style.setProperty('translate', '0 0');
    aside.style.transform = 'translateX(0)';
    aside.setAttribute('aria-hidden', 'false');

    if (getComputedStyle(aside).position !== 'fixed') {
      aside.style.position = 'fixed';
      aside.style.top = '0';
      aside.style.bottom = '0';
      aside.style.zIndex = '9999';
    }

    if (!aside.style.zIndex) {
      aside.style.zIndex = '9999';
    }

    aside.style.animation = 'none';
    // reflow
    // eslint-disable-next-line no-unused-expressions
    aside.offsetHeight;
    aside.style.animation = 'menuAni .28s forwards';

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
    aside.style.animation = '';
    aside.classList.add(closedClass);
    aside.classList.remove(openClass);
    aside.style.setProperty('translate', prevInline.translate || '');
    aside.style.transform = 'translateX(-110%)';
    setTimeout(() => {
      aside.style.display = prevInline.display || '';
      aside.style.left = prevInline.left || '';
      aside.style.transform = prevInline.transform || '';
      aside.style.translate = prevInline.translate || '';
      aside.setAttribute('aria-hidden', 'true');
      if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('block');
      }
      resetOffsets();
      unlockScroll();
    }, 180);
  }

  function toggleSidebar(e) {
    if (isOpen || aside.getAttribute('aria-hidden') === 'false') {
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
    aside.setAttribute('aria-hidden', 'true');
  }

  window.addEventListener('resize', () => {
    if (desktopMq.matches) {
      aside.style.display = '';
      aside.style.transform = '';
      aside.style.animation = '';
      aside.removeAttribute('aria-hidden');
      isOpen = false;
      if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('block');
      }
      resetOffsets();
      unlockScroll();
    }
  });
});
