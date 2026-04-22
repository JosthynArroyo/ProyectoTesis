(function () {
  const form = document.querySelector('form[data-slots-template]');
  if (!form) return;

  const doctorField = document.getElementById('doctor_id');
  const fechaField = document.getElementById('fecha');
  const fechaTrigger = document.getElementById('doctor-lab-fecha-trigger');
  const horaField = document.getElementById('hora');
  const horaHelp = document.getElementById('horaHelp');
  const slotsTemplate = form.dataset.slotsTemplate || '';
  let preferredHour = form.dataset.oldHora || '';

  function openNativePicker(input) {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    input.focus({ preventScroll: true });

    if (typeof input.showPicker === 'function') {
      try {
        input.showPicker();
        return;
      } catch (_error) {}
    }

    input.click();
  }

  function selectedDoctorId() {
    return doctorField ? String(doctorField.value || '') : '';
  }

  function clearSlots(message) {
    horaField.innerHTML = `<option value="">${message || 'Seleccione laboratorio y fecha'}</option>`;
    horaField.disabled = true;
  }

  function normalizeSlot(item) {
    if (typeof item === 'string') {
      return { value: item, label: item, disabled: false };
    }

    if (item && typeof item === 'object') {
      const availability = String(item.estado ?? item.status ?? '').toLowerCase();
      const value = item.value ?? item.hora ?? item.time ?? item.start ?? item.inicio ?? null;
      const label = item.label ?? item.hora ?? item.time ?? value;

      if (!value) return null;

      return {
        value: String(value),
        label: String(label ?? value),
        disabled: Boolean(item.disabled) || availability === 'ocupado',
      };
    }

    return null;
  }

  async function loadSlots() {
    const doctorId = selectedDoctorId();
    const fecha = fechaField?.value || '';

    if (!doctorId || !fecha) {
      clearSlots('Seleccione laboratorio y fecha');
      return;
    }

    horaHelp && (horaHelp.textContent = 'Buscando horarios...');
    clearSlots('Cargando...');

    try {
      const url = slotsTemplate.replace('DOC_ID', encodeURIComponent(doctorId)).replace('FECHA', encodeURIComponent(fecha));
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const payload = await response.json();
      const rawSlots = Array.isArray(payload) ? payload : (Array.isArray(payload.slots) ? payload.slots : []);
      const slots = rawSlots.map(normalizeSlot).filter(Boolean).filter((slot) => !slot.disabled);

      if (slots.length === 0) {
        clearSlots('Sin horarios disponibles');
        horaHelp && (horaHelp.textContent = 'No hay horarios disponibles para esa fecha.');
        return;
      }

      let options = '<option value="">Seleccione una hora</option>';
      for (const slot of slots) {
        const selected = String(preferredHour || '') === String(slot.value) ? ' selected' : '';
        options += `<option value="${slot.value}"${selected}>${slot.label}</option>`;
      }

      horaField.innerHTML = options;
      horaField.disabled = false;
      preferredHour = '';
      horaHelp && (horaHelp.textContent = 'Se muestran solo horarios configurados y disponibles.');
    } catch {
      clearSlots('Error al cargar horarios');
      horaHelp && (horaHelp.textContent = 'No se pudieron cargar los horarios disponibles.');
    }
  }

  doctorField?.addEventListener('change', () => {
    preferredHour = '';
    loadSlots();
  });

  fechaTrigger?.addEventListener('click', (event) => {
    event.preventDefault();
    openNativePicker(fechaField);
  });

  const handleFechaChange = () => {
    preferredHour = '';
    loadSlots();
  };

  fechaField?.addEventListener('change', handleFechaChange);
  fechaField?.addEventListener('enhanced-date:change', handleFechaChange);

  horaField?.addEventListener('change', () => {
    preferredHour = horaField.value || '';
  });

  if (selectedDoctorId() && fechaField?.value) {
    loadSlots();
  }
})();
