export function parseTimeToMinutes(timeStr) {
  if (!timeStr) return 0;
  const [h, m] = timeStr.split(':').map(Number);
  return h * 60 + m;
}

export function minutesToTimeString(minutes) {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

export function minutesToUserString(minutes) {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  const ampm = h >= 12 ? 'p. m.' : 'a. m.';
  let displayHour = h % 12;
  if (displayHour === 0) displayHour = 12;
  const displayMin = String(m).padStart(2, '0');
  const displayH = String(displayHour).padStart(2, '0');
  return `${displayH}:${displayMin} ${ampm}`;
}

export function getIsoDayFromDateString(dateStr) {
  if (!dateStr) return null;
  const parts = dateStr.split('-');
  if (parts.length !== 3) return null;
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10);
  const d = parseInt(parts[2], 10);
  const date = new Date(y, m - 1, d);
  const jsDay = date.getDay(); // 0 = Sunday, 1 = Monday ... 6 = Saturday
  return jsDay === 0 ? 7 : jsDay;
}

export function getClinicHoursConfig() {
  const configEl = document.getElementById('clinica-horarios-config');
  if (!configEl) return {};
  try {
    return JSON.parse(configEl.textContent || '{}');
  } catch (e) {
    console.error('Error parsing clinic hours config', e);
    return {};
  }
}

export function populateHoursSelects(dayOfWeek, startSelect, endSelect, infoTextElement, clinicConfig, oldStartVal, oldEndVal, intervalMinutes = 30) {
  if (!startSelect || !endSelect) return;

  const dayConfig = clinicConfig[dayOfWeek];
  if (!dayConfig || String(dayConfig.status) !== '1') {
    startSelect.innerHTML = '<option value="">Cerrado</option>';
    endSelect.innerHTML = '<option value="">Cerrado</option>';
    startSelect.disabled = true;
    endSelect.disabled = true;
    if (infoTextElement) {
      infoTextElement.textContent = `La clínica está cerrada el ${getDayNameSpanish(dayOfWeek)}.`;
    }
    return;
  }

  startSelect.disabled = false;
  endSelect.disabled = false;

  const openingMins = parseTimeToMinutes(dayConfig.opening);
  const closingMins = parseTimeToMinutes(dayConfig.closing);

  if (infoTextElement) {
    infoTextElement.textContent = `Horario de la clínica para el ${getDayNameSpanish(dayOfWeek)}: ${dayConfig.opening}–${dayConfig.closing}`;
  }

  // Populate start select (from opening to closing - interval)
  const currentStartVal = startSelect.value || oldStartVal;
  startSelect.innerHTML = '';
  for (let m = openingMins; m <= closingMins - intervalMinutes; m += intervalMinutes) {
    const val = minutesToTimeString(m);
    const text = minutesToUserString(m);
    const opt = document.createElement('option');
    opt.value = val;
    opt.textContent = text;
    if (val === currentStartVal) opt.selected = true;
    startSelect.appendChild(opt);
  }

  // Helper to populate end select based on selected start time
  const updateEndSelect = () => {
    const selectedStartMins = parseTimeToMinutes(startSelect.value) || openingMins;
    const currentEndVal = endSelect.value || oldEndVal;
    endSelect.innerHTML = '';

    for (let m = selectedStartMins + intervalMinutes; m <= closingMins; m += intervalMinutes) {
      const val = minutesToTimeString(m);
      const text = minutesToUserString(m);
      const opt = document.createElement('option');
      opt.value = val;
      opt.textContent = text;
      if (val === currentEndVal) opt.selected = true;
      endSelect.appendChild(opt);
    }
  };

  startSelect.onchange = updateEndSelect;
  updateEndSelect();
}

function getDayNameSpanish(day) {
  return ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'][day - 1] || 'desconocido';
}
