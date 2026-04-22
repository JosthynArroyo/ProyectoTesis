// resources/js/navbar.js
function initNavbar() {
  const header = document.getElementById('cnav-header');
  const menu = document.getElementById('cnav-menu');
  const toggle = document.getElementById('cnav-toggle');
  const closeBtn = document.getElementById('cnav-close');
  const mobileMq = window.matchMedia('(max-width: 1023.98px)');

  if (!header || !menu || !toggle || !closeBtn || header.dataset.navbarReady === '1') return;
  header.dataset.navbarReady = '1';

  // Mostrar sombra al hacer scroll
  const onScroll = () => {
    if (window.scrollY > 2) header.classList.add('cnav--scrolled');
    else header.classList.remove('cnav--scrolled');
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  // Abrir menu movil
  const openMenu = () => {
    menu.classList.remove('hidden');
    menu.classList.add('is-open');
    menu.setAttribute('aria-hidden', 'false');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('nav-open');
  };

  // Cerrar menu movil
  const closeMenu = () => {
    menu.classList.remove('is-open');
    menu.classList.add('hidden');
    menu.setAttribute('aria-hidden', 'true');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('nav-open');
  };

  toggle.addEventListener('click', openMenu);
  closeBtn.addEventListener('click', closeMenu);

  // Cerrar con ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && menu.classList.contains('is-open')) closeMenu();
  });

  // Cerrar al hacer click en un enlace del menu en movil
  menu.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (!a || !mobileMq.matches || !menu.classList.contains('is-open')) return;
    requestAnimationFrame(closeMenu);
  });

}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNavbar, { once: true });
} else {
  initNavbar();
}
