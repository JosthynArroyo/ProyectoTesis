/**
 * Auto-submit declarativo de formularios al cambiar un control.
 *
 * Contrato: añadir el atributo [data-auto-submit] al control (<select>, <input>, etc.).
 * Al cambiar, se hace submit del formulario padre (element.form).
 *
 * No intercepta controles sin el atributo.
 * No busca formularios heurísticamente.
 * Falla de forma segura si element.form no existe.
 */
document.addEventListener('change', (event) => {
  const control = event.target.closest('[data-auto-submit]');
  if (!control) return;

  const form = control.form;
  if (!form) return;

  form.submit();
});
