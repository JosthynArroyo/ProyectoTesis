document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-horario-edit');
  const hi = document.getElementById('hora_inicio');
  const hf = document.getElementById('hora_fin');
  const fecha = document.getElementById('fecha');
  const fechaTrigger = document.getElementById('admin-horario-edit-fecha-trigger');

  function openNativePicker(input) {
    if (!input) return;
    input.focus({ preventScroll: true });
    if (typeof input.showPicker === 'function') {
      try {
        input.showPicker();
        return;
      } catch (_err) {}
    }
    input.click();
  }

  function validRange() {
    if (!hi.value || !hf.value) {
      hi.setCustomValidity('');
      hf.setCustomValidity('');
      return true;
    }
    const a = hi.value;
    const b = hf.value;
    const ok = a < b;
    const msg = ok ? '' : 'La hora fin debe ser mayor a la hora inicio.';
    hi.setCustomValidity('');
    hf.setCustomValidity(msg);
    return ok;
  }

  function clampStep(el) {
    if (!el.value) return;
    const [h, m] = el.value.split(':').map(Number);
    const minutes = Math.round(m / 30) * 30;
    const safe = String(h).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
    if (el.value !== safe) el.value = safe;
  }

  [hi, hf].forEach(el => {
    el.addEventListener('change', () => {
      clampStep(el);
      validRange();
      el.reportValidity();
    });
    el.addEventListener('input', validRange);
  });

  if (fecha) {
    fecha.addEventListener('change', () => {
      fecha.setCustomValidity('');
      fecha.reportValidity();
    });
  }

  if (fechaTrigger && fecha) {
    fechaTrigger.addEventListener('click', (event) => {
      event.preventDefault();
      openNativePicker(fecha);
    });
  }

  form.addEventListener('submit', e => {
    clampStep(hi);
    clampStep(hf);
    if (!validRange()) {
      e.preventDefault();
      hf.reportValidity();
    }
  });
});
