import { populateHoursSelects, getClinicHoursConfig } from '../../shared/horarios/schedule-common.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-horario-edit');
  const hi = document.getElementById('hora_inicio');
  const hf = document.getElementById('hora_fin');
  const fecha = document.getElementById('fecha');
  const fechaTrigger = document.getElementById('admin-horario-edit-fecha-trigger');
  const infoText = document.getElementById('clinic-hours-info');

  const clinicConfig = getClinicHoursConfig();
  const getInterval = () => parseInt(document.getElementById('intervalo_minutos')?.value || '30', 10);

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

  function getIsoDayFromDateString(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    if (parts.length !== 3) return null;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    const d = parseInt(parts[2], 10);
    const date = new Date(y, m - 1, d);
    const jsDay = date.getDay();
    return jsDay === 0 ? 7 : jsDay;
  }

  function updateForDate() {
    if (!fecha || !hi || !hf) return;
    const day = getIsoDayFromDateString(fecha.value);
    if (day) {
      populateHoursSelects(day, hi, hf, infoText, clinicConfig, hi.dataset.old, hf.dataset.old, getInterval());
    }
  }

  if (fecha) {
    fecha.addEventListener('change', updateForDate);
  }

  if (fechaTrigger && fecha) {
    fechaTrigger.addEventListener('click', (event) => {
      event.preventDefault();
      openNativePicker(fecha);
    });
  }

  const intervalSelect = document.getElementById('intervalo_minutos');
  if (intervalSelect) {
      intervalSelect.addEventListener('change', updateForDate);
  }

  updateForDate();
});
