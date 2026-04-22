(function () {
  const form = document.querySelector('form[data-slots-url]');
  if (!form) return;

  const fechaInp = document.getElementById('fecha');
  const horaSel = document.getElementById('hora');
  const horaHelp = document.getElementById('horaHelp');
  const slotsTpl = form.dataset.slotsUrl || '';
  const oldHora = form.dataset.oldHora || '';
  let slotsRequestId = 0;
  let slotsAbortController = null;

  function hhmmToMinutes(value) {
    if (value == null) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;

    const normalized = String(value).trim();
    if (/^\d{3,4}$/.test(normalized)) {
      const hours = Number(normalized.slice(0, -2));
      const mins = Number(normalized.slice(-2));
      if (!Number.isFinite(hours) || !Number.isFinite(mins)) return null;
      return (hours * 60) + mins;
    }

    const parts = normalized.split(':');
    if (parts.length !== 2) return null;

    const hours = Number(parts[0]);
    const mins = Number(parts[1]);
    if (!Number.isFinite(hours) || !Number.isFinite(mins)) return null;
    return (hours * 60) + mins;
  }

  function minutesToHHMM(minutes) {
    const total = Number(minutes);
    if (!Number.isFinite(total)) return null;

    const hours = Math.floor(total / 60);
    const mins = String(total % 60).padStart(2, '0');
    return `${String(hours).padStart(2, '0')}:${mins}`;
  }

  function urlFor(dateStr) {
    if (!slotsTpl || !dateStr) return '';
    return slotsTpl.replace('__FECHA__', encodeURIComponent(dateStr));
  }

  function clearSlots(message) {
    if (!horaSel) return;
    horaSel.innerHTML = `<option value="">${message || 'Seleccione una hora'}</option>`;
    horaSel.disabled = true;
  }

  function abortPendingSlotsRequest() {
    if (!slotsAbortController) return;
    slotsAbortController.abort();
    slotsAbortController = null;
  }

  async function loadSlots() {
    const fecha = fechaInp.value;
    if (!fecha) {
      abortPendingSlotsRequest();
      clearSlots('Seleccione una fecha');
      return;
    }

    const requestId = ++slotsRequestId;
    abortPendingSlotsRequest();
    slotsAbortController = new AbortController();

    horaHelp && (horaHelp.textContent = 'Buscando horarios disponibles...');
    clearSlots('Cargando...');

    try {
      const response = await fetch(urlFor(fecha), {
        headers: { Accept: 'application/json' },
        signal: slotsAbortController.signal,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      if (requestId !== slotsRequestId) {
        return;
      }

      const raw = Array.isArray(data.slots) ? data.slots : Array.isArray(data) ? data : [];
      const available = raw
        .map((slot) => {
          if (slot == null) return null;
          if (typeof slot === 'string') return { hora: slot, estado: 'libre' };
          if (typeof slot === 'object') return { hora: slot.hora || slot.time || slot.value, estado: slot.estado || 'libre' };
          return null;
        })
        .filter(Boolean)
        .filter((slot) => String(slot.estado || '').toLowerCase() === 'libre');

      const options = available
        .map((slot) => slot.hora)
        .filter(Boolean)
        .map((hora) => {
          const minutes = hhmmToMinutes(hora);
          return (minutes !== null ? minutesToHHMM(minutes) : hora) || String(hora);
        })
        .filter(Boolean);

      if (options.length === 0) {
        clearSlots('No hay horarios disponibles');
        horaHelp && (horaHelp.textContent = 'No hay horarios disponibles para esta fecha.');
        return;
      }

      let html = '<option value="">Seleccione una hora</option>';
      options.forEach((hora) => {
        const selected = String(oldHora) === String(hora) ? ' selected' : '';
        html += `<option value="${hora}"${selected}>${hora}</option>`;
      });

      horaSel.innerHTML = html;
      horaSel.disabled = false;
      horaHelp && (horaHelp.textContent = 'Formato 24h. Se muestran solo horarios disponibles.');
    } catch (error) {
      if (error?.name === 'AbortError' || requestId !== slotsRequestId) {
        return;
      }

      clearSlots('Error al cargar horarios');
      horaHelp && (horaHelp.textContent = 'No pudimos cargar los horarios disponibles.');
    } finally {
      if (requestId === slotsRequestId) {
        slotsAbortController = null;
      }
    }
  }

  const handleFechaChange = () => {
    horaSel.value = '';
    loadSlots();
  };

  fechaInp.addEventListener('change', handleFechaChange);
  fechaInp.addEventListener('enhanced-date:change', handleFechaChange);

  if (fechaInp.value) loadSlots();
})();
