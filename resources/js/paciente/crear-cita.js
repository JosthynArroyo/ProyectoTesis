(function () {
  const form = document.querySelector('form[data-endpoint-template]');
  if (!form) return;

  const espSel   = document.getElementById('especialidad_id');
  const docSel   = document.getElementById('doctor_id');
  const fechaInp = document.getElementById('fecha');
  const horaSel  = document.getElementById('hora');
  const horaHelp = document.getElementById('horaHelp');

  const tarifaPanel = document.getElementById('tarifaPanel');
  const tarifaLabel = document.getElementById('tarifaLabel');
  const labSection = document.getElementById('labSection');
  const labId = form.dataset.laboratorioId || '';
  const labRequired = labSection ? Array.from(labSection.querySelectorAll('[data-lab-required]')) : [];
  const examSel = document.getElementById('tipo_examen');
  const prepAyuno = document.querySelector('[data-lab-prep="ayuno"]');
  const prepAgua = document.querySelector('[data-lab-prep="agua"]');
  const prepHorario = document.querySelector('[data-lab-prep="horario"]');

  const doctorsUrlTpl = form.dataset.endpointTemplate;
  const tarifaUrlTpl  = form.dataset.tarifaTemplate;
  const slotsUrlTpl   = form.dataset.slotsTemplate; // debe contener DOC_ID y FECHA

  const oldEsp  = form.dataset.oldEsp || '';
  const oldDoc  = form.dataset.oldDoc || '';
  const oldHora = form.dataset.oldHora || '';

  const tarifaUrlFrom = (doctorId) => (tarifaUrlTpl || '').replace('DOC_ID', String(doctorId));
  const slotsUrlFrom  = (doctorId, fecha) =>
    (slotsUrlTpl || '').replace('DOC_ID', String(doctorId)).replace('FECHA', encodeURIComponent(fecha));

  function hideTarifa(){
    if (tarifaPanel) tarifaPanel.hidden = true;
    if (tarifaLabel) tarifaLabel.textContent = 'Tarifa: —';
  }

  function toggleLabFields(especialidadId){
    if (!labSection) return;
    const isLab = labId && String(especialidadId) === String(labId);
    labSection.hidden = !isLab;
    labRequired.forEach((el) => {
      if (isLab) {
        el.setAttribute('required', 'required');
      } else {
        el.removeAttribute('required');
      }
    });
    updateLabPrep();
  }

  function updateLabPrep(){
    if (!examSel || !prepAyuno || !prepAgua || !prepHorario) return;
    const option = examSel.selectedOptions && examSel.selectedOptions[0];
    const empty = examSel.dataset.prepEmpty || 'Selecciona un examen para ver la preparacion.';
    prepAyuno.textContent = option.dataset.prepAyuno || empty;
    prepAgua.textContent = option.dataset.prepAgua || empty;
    prepHorario.textContent = option.dataset.prepHorario || empty;
  }

  async function fetchTarifa(doctorId){
    if(!doctorId || !tarifaUrlTpl || !tarifaPanel || !tarifaLabel){ hideTarifa(); return; }
    try{
      tarifaPanel.hidden=false;
      tarifaPanel.classList.remove('is-warning');
      tarifaLabel.textContent='Consultando tarifa…';
      const res = await fetch(tarifaUrlFrom(doctorId), { headers:{'Accept':'application/json'} });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if(data.ok && data.definido && data.label){
        tarifaLabel.textContent = data.label;
      }else{
        tarifaLabel.textContent = 'Tarifa no configurada';
        tarifaPanel.classList.add('is-warning');
      }
    }catch{
      tarifaLabel.textContent='Error al consultar la tarifa';
      tarifaPanel.classList.add('is-warning');
      tarifaPanel.hidden=false;
    }
  }

  async function loadDoctors(especialidadId, preselectId){
    hideTarifa();
    clearSlots();
    docSel.innerHTML='<option value="">Cargando…</option>';
    docSel.disabled=true;
    if(!especialidadId){
      docSel.innerHTML='<option value="">Seleccione una especialidad primero</option>';
      return;
    }
    const url = doctorsUrlTpl.replace('ESP_ID', encodeURIComponent(especialidadId));
    try{
      const res = await fetch(url, { headers:{'Accept':'application/json'} });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if(!Array.isArray(data) || data.length===0){
        docSel.innerHTML='<option value="">No hay doctores activos en esta especialidad</option>';
      }else{
        let opts='<option value="">Seleccionar</option>';
        for(const d of data){
          const sel = String(preselectId || '') === String(d.id) ? ' selected' : '';
          opts += `<option value="${d.id}"${sel}>${d.name}</option>`;
        }
        docSel.innerHTML=opts;
      }
      docSel.disabled=false;
      if (preselectId) { await fetchTarifa(preselectId); await loadSlots(); }
    }catch{
      docSel.innerHTML='<option value="">Error cargando doctores</option>';
      docSel.disabled=false;
    }
  }

  function clearSlots(msg){
    horaSel.innerHTML = `<option value="">${msg || 'Seleccione doctor y fecha'}</option>`;
    horaSel.disabled = true;
  }

  // ---- helpers para slots ----

  // 120 -> "02:00", 540 -> "09:00"
  function minutesToHHMM(mins){
    const m = Number(mins);
    if (!Number.isFinite(m)) return null;
    const h = Math.floor(m/60);
    const mm = String(m%60).padStart(2,'0');
    return String(h).padStart(2,'0') + ':' + mm;
  }

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

  // Acepta: "08:30" | 510 | {value,label} | {hora} | {time} | {inicio,fin}
  function normalizeSlot(item){
    // string directo
    if (typeof item === 'string') {
      return { value: item, label: item, disabled: false };
    }
    // minutos numéricos
    if (typeof item === 'number') {
      const hhmm = minutesToHHMM(item);
      return hhmm ? { value: hhmm, label: hhmm, disabled: false } : null;
    }
    // objeto
    if (item && typeof item === 'object') {
      const value = item.value ?? item.hora ?? item.time ?? item.start ?? item.inicio ?? null;
      const label = item.label ?? item.hora ?? item.time ?? (item.inicio && item.fin ? `${item.inicio} - ${item.fin}` : value);
      const disabled = Boolean(item.disabled);
      if (!value && !label) return null;
      // Si llega {inicio,fin} sin hora concreta, usa inicio como value para crear la cita
      const val = value ?? (item.inicio ?? null);
      if (!val) return null;
      return { value: String(val), label: String(label ?? val), disabled };
    }
    return null;
  }

  async function loadSlots(){
    const doctorId = docSel.value;
    const fecha    = fechaInp.value; // type="date" -> YYYY-MM-DD
    if(!doctorId || !fecha){ clearSlots(); return; }

    horaHelp && (horaHelp.textContent = 'Buscando horarios…');
    clearSlots('Buscando…');

    try{
      const url = slotsUrlFrom(doctorId, fecha);
      const res = await fetch(url, { headers:{'Accept':'application/json'} });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();

      // Soporte: {slots:[...]} o directamente [...]
      const raw = Array.isArray(data) ? data : (Array.isArray(data.slots) ? data.slots : []);
      const norm = raw.map(normalizeSlot).filter(Boolean);

      const hoy = new Date();
      const hoyStr = hoy.toISOString().slice(0, 10);
      const minAdelantoHoy = hoy.getHours() * 60 + hoy.getMinutes() + 60;
      const filtrados = norm.filter((slot) => {
        if (fecha !== hoyStr) return true;
        const mins = hhmmToMinutes(slot.value);
        if (mins === null) return true; // si no se puede parsear, no lo descartamos
        return mins >= minAdelantoHoy;
      });

      if(filtrados.length===0){
        clearSlots('No hay horarios con 1 hora de anticipación');
        horaHelp && (horaHelp.textContent = 'No hay horarios disponibles con al menos 1 hora de anticipación.');
        return;
      }

      let opts = '<option value="">Seleccionar hora</option>';
      for(const s of filtrados){
        const sel = String(oldHora || '') === String(s.value) ? ' selected' : '';
        const dis = s.disabled ? ' disabled' : '';
        opts += `<option value="${s.value}"${sel}${dis}>${s.label}</option>`;
      }
      horaSel.innerHTML = opts;
      horaSel.disabled = false;
      horaHelp && (horaHelp.textContent = 'Formato 24h. Se listan solo los horarios disponibles.');
    }catch{
      clearSlots('Error al cargar horarios');
      horaHelp && (horaHelp.textContent = 'Error al cargar horarios.');
    }
  }

  // ---- listeners ----
  espSel && espSel.addEventListener('change', function(){
    toggleLabFields(this.value);
    loadDoctors(this.value, null);
  });
  docSel && docSel.addEventListener('change', function(){
    const id=this.value;
    if(!id){ hideTarifa(); clearSlots(); return; }
    fetchTarifa(id);
    loadSlots();
  });
  fechaInp && fechaInp.addEventListener('change', loadSlots);
  examSel && examSel.addEventListener('change', updateLabPrep);

  // ---- init ----
  (async function init(){
    if (oldEsp) {
      toggleLabFields(oldEsp);
      await loadDoctors(oldEsp, oldDoc || null);
      if (oldDoc && fechaInp && fechaInp.value) await loadSlots();
    }
    updateLabPrep();
  })();
})();
