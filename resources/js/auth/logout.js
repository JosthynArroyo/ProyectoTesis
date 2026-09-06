/**
 * Manejo declarativo y seguro para acciones de cierre de sesion
 * Activa logout en elementos con [data-logout-trigger].
 * [data-logout-form] es metadata opcional para resolver el ID del formulario objetivo.
 */

let isInitialized = false;

export function initLogoutTriggers() {
  if (isInitialized) return;
  isInitialized = true;

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-logout-trigger]');
    if (!trigger) return;

    event.preventDefault();

    const formId = trigger.getAttribute('data-logout-form') || 'logout-form';
    const form = document.getElementById(formId);

    if (form) {
      form.submit();
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initLogoutTriggers, { once: true });
} else {
  initLogoutTriggers();
}

export default initLogoutTriggers;
