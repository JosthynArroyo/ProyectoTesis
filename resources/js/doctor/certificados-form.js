document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('[data-certificado-reposo-form]');
  if (!form) return;

  const diasReposo = form.querySelector('[name="dias_reposo"]');
  const fechasReposo = form.querySelectorAll('[data-reposo-date]');
  const indicadores = form.querySelectorAll('[data-reposo-required-indicator]');
  const mensaje = form.querySelector('[data-reposo-required-message]');

  function actualizarObligatoriedadReposo() {
    if (!diasReposo) return;
    const requiereFechas = Number(diasReposo.value) > 0;

    fechasReposo.forEach(function (campo) {
      campo.toggleAttribute('required', requiereFechas);
      campo.setAttribute('aria-required', requiereFechas ? 'true' : 'false');
    });
    indicadores.forEach(function (indicador) {
      indicador.classList.toggle('hidden', !requiereFechas);
    });
    if (mensaje) {
      mensaje.classList.toggle('hidden', !requiereFechas);
    }
  }

  if (diasReposo) {
    diasReposo.addEventListener('input', actualizarObligatoriedadReposo);
    diasReposo.addEventListener('change', actualizarObligatoriedadReposo);
    actualizarObligatoriedadReposo();
  }
});
