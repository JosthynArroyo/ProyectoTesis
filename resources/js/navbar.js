// resources/js/navbar.js
document.addEventListener('DOMContentLoaded', () => {
  const header = document.getElementById('cnav-header');
  const menu = document.getElementById('cnav-menu');
  const toggle = document.getElementById('cnav-toggle');
  const closeBtn = document.getElementById('cnav-close');

  // Mostrar sombra al hacer scroll
  const onScroll = () => {
    if (window.scrollY > 2) header.classList.add('cnav--scrolled');
    else header.classList.remove('cnav--scrolled');
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  // Abrir menú móvil
  const openMenu = () => {
    menu.classList.add('is-open');
    menu.setAttribute('aria-hidden', 'false');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('nav-open');
  };

  // Cerrar menú móvil
  const closeMenu = () => {
    menu.classList.remove('is-open');
    menu.setAttribute('aria-hidden', 'true');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('nav-open');
  };

  toggle?.addEventListener('click', openMenu);
  closeBtn?.addEventListener('click', closeMenu);

  // Cerrar con ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && menu.classList.contains('is-open')) closeMenu();
  });

  // Cerrar al hacer click en un enlace del menú en móvil
  menu?.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (a && window.matchMedia('(max-width:1150px)').matches) closeMenu();
  });
});
