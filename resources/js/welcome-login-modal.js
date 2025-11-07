(() => {
  const modal = document.getElementById('loginModal');
  if (!modal) return;

  const closeBtn  = document.getElementById('closeLoginModal');
  const backdrop  = modal.querySelector('.modal-backdrop');
  const triggers  = document.querySelectorAll('[data-login-trigger]');
  const emailInput = modal.querySelector('input[name="email"]');
  const menuToggle = document.getElementById('menu');

  let savedY = 0;

  function setAria(open) {
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  function openModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (menuToggle && menuToggle.checked) menuToggle.checked = false;
    savedY = window.scrollY || 0;
    document.body.classList.add('modal-open');
    modal.classList.add('is-open');
    setAria(true);
    if (emailInput) {
      try { emailInput.focus({ preventScroll: true }); } catch {}
    }
  }

  function closeModal(e) {
    if (e) e.preventDefault();
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
    setAria(false);
    window.scrollTo(0, savedY);
  }

  triggers.forEach(el => el.addEventListener('click', openModal));
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape' && modal.classList.contains('is-open')) closeModal(ev);
  });

  // Abre si el servidor dejó la marca por errores de validación
  if (modal.querySelector('[data-open-login-onload]')) {
    openModal();
  }

  // Abre si viene ?login=1 en la URL (enlaces "Iniciar Sesión" o "Agendar Cita")
  const params = new URLSearchParams(window.location.search);
  if (params.get('login') === '1') {
    openModal();
    params.delete('login');
    const cleanUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
    window.history.replaceState({}, '', cleanUrl);
  }

  // Soporte para abrir desde otros scripts
  window.addEventListener('open-login-modal', openModal);
})();
