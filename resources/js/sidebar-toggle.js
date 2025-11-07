document.addEventListener('DOMContentLoaded', () => {
  const aside = document.querySelector('aside');
  if (!aside) return;

  const openers = [
    ...document.querySelectorAll('#menu_bar'),
    ...document.querySelectorAll('[data-open-sidebar]')
  ];
  const closeBtn = aside.querySelector('.top .close');

  let lastScrollY = 0;
  let prevInline = {
    display: aside.style.display,
    left: aside.style.left,
    transform: aside.style.transform
  };

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
      transform: aside.style.transform
    };

    aside.style.display = 'block';
    aside.style.left = '0';
    aside.style.transform = 'translateX(0)';
    aside.setAttribute('aria-hidden', 'false');

    if (getComputedStyle(aside).position !== 'fixed') {
      aside.style.position = 'fixed';
      aside.style.top = '0';
      aside.style.bottom = '0';
      aside.style.zIndex = '9999';
    }

    aside.style.animation = 'none';
    // reflow
    // eslint-disable-next-line no-unused-expressions
    aside.offsetHeight;
    aside.style.animation = 'menuAni .28s forwards';

    lockScroll();
  }

  function closeSidebar(e) {
    if (e) e.preventDefault();
    aside.style.animation = '';
    aside.style.transform = 'translateX(-110%)';
    setTimeout(() => {
      aside.style.display = prevInline.display || '';
      aside.style.left = prevInline.left || '';
      aside.style.transform = prevInline.transform || '';
      aside.setAttribute('aria-hidden', 'true');
      unlockScroll();
    }, 180);
  }

  openers.forEach(btn => btn.addEventListener('click', openSidebar));
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape' && getComputedStyle(aside).display !== 'none') {
      closeSidebar(ev);
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      aside.style.display = '';
      aside.style.transform = '';
      aside.style.animation = '';
      aside.removeAttribute('aria-hidden');
      unlockScroll();
    }
  });
});
