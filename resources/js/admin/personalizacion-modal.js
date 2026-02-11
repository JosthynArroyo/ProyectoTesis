document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('personalizacionModal');
  if (!modal) return;

  const openers = document.querySelectorAll('[data-open-personalizacion]');
  const closers = modal.querySelectorAll('[data-close-personalizacion]');

  const open = () => {
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');
  };

  const close = () => {
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
  };

  openers.forEach((btn) => btn.addEventListener('click', open));
  closers.forEach((btn) => btn.addEventListener('click', close));

  if (modal.dataset.openOnload === '1') {
    open();
  }
});
