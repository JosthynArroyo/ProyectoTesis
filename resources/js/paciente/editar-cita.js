(function(){
  const form = document.querySelector('form[data-slots-url]');
  if (!form) return;

  const fechaInp = document.getElementById('fecha');
  const horaSel  = document.getElementById('hora');
  const horaHelp = document.getElementById('horaHelp');
  const slotsTpl = form.dataset.slotsUrl || '';
  const oldHora  = form.dataset.oldHora || '';

  function hhmmToMinutes(value){
    if (value == null) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    const str = String(value).trim();
    if (/^\d{3,4}$/.test(str)) {
      const h = Number(str.slice(0, -2));
      const m = Number(str.slice(-2));
      if (!Number.isFinite(h) || !Number.isFinite(m)) return null;
      return h * 60 + m;
    }
    const parts = str.split(':');
    if (parts.length !== 2) return null;
    const h = Number(parts[0]);
    const m = Number(parts[1]);
    if (!Number.isFinite(h) || !Number.isFinite(m)) return null;
    return h * 60 + m;
  }

  function minutesToHHMM(mins){
    const m = Number(mins);
    if (!Number.isFinite(m)) return null;
    const h = Math.floor(m/60);
    const mm = String(m%60).padStart(2,'0');
    return String(h).padStart(2,'0') + ':' + mm;
  }

  function urlFor(dateStr){
    if (!slotsTpl || !dateStr) return '';
    return slotsTpl.replace('__FECHA__', encodeURIComponent(dateStr));
  }

  function clearSlots(msg){
    if (!horaSel) return;
    horaSel.innerHTML = `<option value="">${msg || 'Seleccione una hora'}</option>`;
    horaSel.disabled = true;
  }

  async function loadSlots(){
    const fecha = fechaInp.value;
    if (!fecha){ clearSlots('Seleccione una fecha'); return; }
    horaHelp && (horaHelp.textContent = 'Buscando horarios disponibles…');
    clearSlots('Cargando…');

    try{
      const url = urlFor(fecha);
      const res = await fetch(url, { headers:{'Accept':'application/json'} });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      const raw = Array.isArray(data.slots) ? data.slots : Array.isArray(data) ? data : [];
      const libres = raw
        .map((s) => {
          if (s == null) return null;
          if (typeof s === 'string') return { hora:s, estado:'libre' };
          if (typeof s === 'object') return { hora: s.hora || s.time || s.value, estado: s.estado || 'libre' };
          return null;
        })
        .filter(Boolean)
        .filter((s) => (s.estado || '').toLowerCase() === 'libre');

      const hoy = new Date();
      const esHoy = fecha === hoy.toISOString().slice(0,10);
      const minHoy = hoy.getHours()*60 + hoy.getMinutes() + 60; // +1h

      const opciones = libres
        .map((s) => s.hora)
        .filter(Boolean)
        .map((h) => {
          const mins = hhmmToMinutes(h);
          if (esHoy && mins !== null && mins < minHoy) return null;
          return (mins !== null ? minutesToHHMM(mins) : h) || String(h);
        })
        .filter(Boolean);

      if (opciones.length === 0){
        clearSlots('No hay horarios disponibles');
        horaHelp && (horaHelp.textContent = 'No hay horarios con al menos 1h de anticipación.');
        return;
      }

      let opts = '<option value="">Seleccione una hora</option>';
      opciones.forEach((h) => {
        const sel = String(oldHora) === String(h) ? ' selected' : '';
        opts += `<option value="${h}"${sel}>${h}</option>`;
      });
      horaSel.innerHTML = opts;
      horaSel.disabled = false;
      horaHelp && (horaHelp.textContent = 'Formato 24h. Se muestran solo horarios disponibles.');
    }catch(err){
      clearSlots('Error al cargar horarios');
      horaHelp && (horaHelp.textContent = 'No pudimos cargar los horarios disponibles.');
    }
  }

  fechaInp.addEventListener('change', () => {
    horaSel.value = '';
    loadSlots();
  });

  if (fechaInp.value) loadSlots();
})();
