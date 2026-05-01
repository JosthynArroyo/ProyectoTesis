(function () {
  const form = document.querySelector('form[data-endpoint-template]');
  if (!form) return;

  const espSel = document.getElementById('especialidad_id');
  const docSel = document.getElementById('doctor_id');
  const fechaInp = document.getElementById('fecha');
  const horaSel = document.getElementById('hora');
  const horaHelp = document.getElementById('horaHelp');

  const tarifaPanel = document.getElementById('tarifaPanel');
  const tarifaLabel = document.getElementById('tarifaLabel');
  const holdInput = form.querySelector('input[name="hold_token"]');
  const labSection = document.getElementById('labSection');
  const labId = form.dataset.laboratorioId || '';
  const labRequired = labSection ? Array.from(labSection.querySelectorAll('[data-lab-required]')) : [];
  const examSel = document.getElementById('tipo_examen');
  const stepItems = Array.from(document.querySelectorAll('[data-step-item]'));
  const stepSummary = document.querySelector('[data-step-summary]');
  const prepAyuno = document.querySelector('[data-lab-prep="ayuno"]');
  const prepAgua = document.querySelector('[data-lab-prep="agua"]');
  const prepHorario = document.querySelector('[data-lab-prep="horario"]');
  const doctorProfileButton = document.querySelector('[data-doctor-profile-open]');
  const doctorProfileModal = document.querySelector('[data-doctor-profile-modal]');
  const doctorProfileDialog = doctorProfileModal?.querySelector('.modal-dialog');
  const doctorProfileAvatar = doctorProfileModal?.querySelector('[data-doctor-profile-avatar]');
  const doctorProfileName = doctorProfileModal?.querySelector('[data-doctor-profile-name]');
  const doctorProfileRole = doctorProfileModal?.querySelector('[data-doctor-profile-role]');
  const doctorProfileSpecialties = doctorProfileModal?.querySelector('[data-doctor-profile-specialties]');
  const doctorProfilePrice = doctorProfileModal?.querySelector('[data-doctor-profile-price]');
  const doctorProfilePhone = doctorProfileModal?.querySelector('[data-doctor-profile-phone]');
  const doctorProfileAddress = doctorProfileModal?.querySelector('[data-doctor-profile-address]');

  const doctorsUrlTpl = form.dataset.endpointTemplate;
  const tarifaUrlTpl = form.dataset.tarifaTemplate;
  const slotsUrlTpl = form.dataset.slotsTemplate;
  const slotHoldUrl = form.dataset.slotHoldUrl || '';

  const oldEsp = form.dataset.oldEsp || '';
  const oldDoc = form.dataset.oldDoc || '';
  const oldHoldToken = form.dataset.oldHoldToken || '';
  let preferredHour = form.dataset.oldHora || '';
  let holdToken = holdInput?.value || oldHoldToken || '';
  let slotsRequestId = 0;
  let holdRequestId = 0;
  let slotsAbortController = null;

  const tarifaUrlFrom = (doctorId) => (tarifaUrlTpl || '').replace('DOC_ID', String(doctorId));
  const slotsUrlFrom = (doctorId, fecha) => {
    const raw = (slotsUrlTpl || '').replace('DOC_ID', String(doctorId)).replace('FECHA', encodeURIComponent(fecha));
    const resolved = new URL(raw, window.location.origin);
    const token = holdInput?.value || holdToken;
    if (token) {
      resolved.searchParams.set('hold_token', token);
    }
    return resolved.toString();
  };
  let doctorOptions = [];
  let lastFocusedBeforeDoctorModal = null;

  function issueHoldToken() {
    if (window.crypto?.randomUUID) {
      return window.crypto.randomUUID();
    }

    return `hold-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  function ensureHoldToken() {
    if (!holdToken) {
      holdToken = issueHoldToken();
    }
    if (holdInput && !holdInput.value) {
      holdInput.value = holdToken;
    }
    return holdToken;
  }

  async function acquireHold() {
    const doctorId = docSel?.value || '';
    const fecha = fechaInp?.value || '';
    const hora = horaSel?.value || '';

    if (!slotHoldUrl || !doctorId || !fecha || !hora) {
      return true;
    }

    const requestId = ++holdRequestId;
    const token = ensureHoldToken();
    horaSel.disabled = true;
    if (horaHelp) {
      horaHelp.textContent = 'Reservando horario por 10 minutos...';
    }

    try {
      const response = await fetch(slotHoldUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          doctor_id: Number(doctorId),
          fecha,
          hora,
          token,
        }),
      });

      const data = await response.json().catch(() => ({}));
      if (requestId !== holdRequestId) {
        return false;
      }

      if (!response.ok || data.ok === false) {
        preferredHour = '';
        horaSel.value = '';
        if (horaHelp) {
          horaHelp.textContent = data.message || 'Ese horario ya no esta disponible. Elige otro.';
        }
        await loadSlots();
        return false;
      }

      if (data.hold_token) {
        holdToken = data.hold_token;
        if (holdInput) {
          holdInput.value = data.hold_token;
        }
      }

      if (horaHelp) {
        horaHelp.textContent = 'Horario reservado temporalmente por 10 minutos mientras completas la cita.';
      }

      return true;
    } catch {
      if (requestId === holdRequestId) {
        preferredHour = '';
        horaSel.value = '';
        if (horaHelp) {
          horaHelp.textContent = 'No pudimos reservar el horario. Intenta nuevamente.';
        }
        await loadSlots();
      }

      return false;
    } finally {
      if (requestId === holdRequestId && horaSel.value) {
        horaSel.disabled = false;
      }
    }
  }

  function replaceSelectMessage(select, message) {
    if (!select) return;
    select.replaceChildren(new Option(message, ''));
  }

  function getSelectedDoctor() {
    const selectedId = docSel?.value || '';
    if (!selectedId) return null;

    return doctorOptions.find((doctor) => String(doctor.id) === String(selectedId)) || null;
  }

  function updateDoctorProfileButton() {
    if (!doctorProfileButton) return;

    const hasDoctor = Boolean(getSelectedDoctor());
    doctorProfileButton.disabled = !hasDoctor;
    doctorProfileButton.setAttribute('aria-disabled', hasDoctor ? 'false' : 'true');
  }

  function setText(node, value, fallback) {
    if (!node) return;
    const text = String(value || '').trim();
    node.textContent = text || fallback;
  }

  function renderDoctorSpecialties(specialties) {
    if (!doctorProfileSpecialties) return;

    doctorProfileSpecialties.replaceChildren();
    const values = Array.isArray(specialties) ? specialties.filter(Boolean) : [];

    if (values.length === 0) {
      const empty = document.createElement('span');
      empty.className = 'text-sm text-gray-500';
      empty.textContent = 'Sin especialidades registradas';
      doctorProfileSpecialties.append(empty);
      return;
    }

    values.forEach((specialty) => {
      const tag = document.createElement('span');
      tag.className = 'badge neutral';
      tag.textContent = specialty;
      doctorProfileSpecialties.append(tag);
    });
  }

  function fillDoctorProfile(doctor) {
    if (!doctor) return;

    const primarySpecialty = Array.isArray(doctor.especialidades) && doctor.especialidades.length
      ? doctor.especialidades[0]
      : 'Profesional de salud';

    setText(doctorProfileName, doctor.name, 'Doctor');
    setText(doctorProfileRole, primarySpecialty, 'Profesional de salud');
    setText(doctorProfilePrice, doctor.precio_format, 'Tarifa no configurada');
    setText(doctorProfilePhone, doctor.telefono, 'No registrado');
    setText(doctorProfileAddress, doctor.direccion, 'No registrada');
    renderDoctorSpecialties(doctor.especialidades);

    if (doctorProfileAvatar) {
      const avatarUrl = doctor.avatar_url || doctor.avatar_thumb || doctorProfileAvatar.dataset.fallbackSrc || '';
      doctorProfileAvatar.src = avatarUrl;
      doctorProfileAvatar.alt = doctor.name ? `Foto de ${doctor.name}` : 'Foto del doctor';
      if (doctor.avatar_srcset) {
        doctorProfileAvatar.srcset = doctor.avatar_srcset;
        doctorProfileAvatar.sizes = '(max-width: 640px) 80px, 96px';
      } else {
        doctorProfileAvatar.removeAttribute('srcset');
        doctorProfileAvatar.removeAttribute('sizes');
      }
    }
  }

  function openDoctorProfileModal() {
    const doctor = getSelectedDoctor();
    if (!doctor || !doctorProfileModal) return;

    fillDoctorProfile(doctor);
    lastFocusedBeforeDoctorModal = document.activeElement;
    doctorProfileModal.classList.add('is-open');
    doctorProfileModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    setTimeout(() => {
      doctorProfileDialog?.focus({ preventScroll: true });
    }, 0);
  }

  function closeDoctorProfileModal() {
    if (!doctorProfileModal) return;

    doctorProfileModal.classList.remove('is-open');
    doctorProfileModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');

    if (lastFocusedBeforeDoctorModal && typeof lastFocusedBeforeDoctorModal.focus === 'function') {
      lastFocusedBeforeDoctorModal.focus({ preventScroll: true });
    }
  }

  function hideTarifa() {
    if (tarifaPanel) tarifaPanel.hidden = true;
    if (tarifaLabel) tarifaLabel.textContent = 'Tarifa: -';
  }

  function toggleLabFields(especialidadId) {
    if (!labSection) return;

    const isLab = labId && String(especialidadId) === String(labId);
    labSection.hidden = !isLab;
    labRequired.forEach((element) => {
      if (isLab) {
        element.setAttribute('required', 'required');
      } else {
        element.removeAttribute('required');
      }
    });
    updateLabPrep();
  }

  function updateLabPrep() {
    if (!examSel || !prepAyuno || !prepAgua || !prepHorario) return;

    const option = examSel.selectedOptions && examSel.selectedOptions[0];
    const empty = examSel.dataset.prepEmpty || 'Selecciona un examen para ver la preparación.';
    prepAyuno.textContent = option?.dataset?.prepAyuno || empty;
    prepAgua.textContent = option?.dataset?.prepAgua || empty;
    prepHorario.textContent = option?.dataset?.prepHorario || empty;
  }

  function markStepState(step, completed, active) {
    const node = stepItems.find((item) => Number(item.dataset.stepItem) === step);
    if (!node) return;

    node.classList.remove(
      'border-gray-200',
      'bg-gray-100',
      'text-teal-700',
      'border-gray-200',
      'bg-gray-100',
      'text-emerald-700',
      'border-gray-200',
      'bg-white',
      'text-gray-500'
    );

    if (completed) {
      node.classList.add('border-gray-200', 'bg-gray-100', 'text-emerald-700');
      return;
    }

    if (active) {
      node.classList.add('border-gray-200', 'bg-gray-100', 'text-teal-700');
      return;
    }

    node.classList.add('border-gray-200', 'bg-white', 'text-gray-500');
  }

  function updateStepper() {
    if (!stepItems.length) return;

    const hasEspecialidad = Boolean(espSel?.value);
    const hasDoctor = Boolean(docSel?.value);
    const hasFechaHora = Boolean(fechaInp?.value && horaSel?.value);
    const hasMotivo = Boolean(document.getElementById('motivo_consulta')?.value?.trim());

    const completed = {
      1: hasEspecialidad,
      2: hasDoctor,
      3: hasFechaHora,
      4: hasMotivo,
    };

    let activeStep = 1;
    if (completed[1] && !completed[2]) activeStep = 2;
    if (completed[2] && !completed[3]) activeStep = 3;
    if (completed[3] && !completed[4]) activeStep = 4;
    if (completed[4]) activeStep = 4;

    [1, 2, 3, 4].forEach((step) => {
      markStepState(step, completed[step], step === activeStep && !completed[step]);
    });

    if (stepSummary) {
      stepSummary.textContent = `Paso ${completed[4] ? 4 : activeStep} de 4`;
    }
  }

  async function fetchTarifa(doctorId) {
    if (!doctorId || !tarifaUrlTpl || !tarifaPanel || !tarifaLabel) {
      hideTarifa();
      return;
    }

    try {
      tarifaPanel.hidden = false;
      tarifaPanel.classList.remove('is-warning');
      tarifaLabel.textContent = 'Consultando tarifa...';
      const response = await fetch(tarifaUrlFrom(doctorId), { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      if (data.ok && data.definido && data.label) {
        tarifaLabel.textContent = data.label;
      } else {
        tarifaLabel.textContent = 'Tarifa no configurada';
        tarifaPanel.classList.add('is-warning');
      }
    } catch {
      tarifaLabel.textContent = 'Error al consultar la tarifa';
      tarifaPanel.classList.add('is-warning');
      tarifaPanel.hidden = false;
    }
  }

  async function loadDoctors(especialidadId, preselectId) {
    hideTarifa();
    clearSlots();
    doctorOptions = [];
    updateDoctorProfileButton();
    replaceSelectMessage(docSel, 'Cargando...');
    docSel.disabled = true;

    if (!especialidadId) {
      replaceSelectMessage(docSel, 'Seleccione una especialidad primero');
      updateDoctorProfileButton();
      return;
    }

    const url = doctorsUrlTpl.replace('ESP_ID', encodeURIComponent(especialidadId));

    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      doctorOptions = Array.isArray(data) ? data : [];

      if (!Array.isArray(data) || data.length === 0) {
        replaceSelectMessage(docSel, 'No hay profesionales activos en esta especialidad');
      } else {
        const options = [new Option('Seleccionar', '')];
        for (const doctor of data) {
          const option = new Option(doctor.name || 'Profesional sin nombre', doctor.id);
          option.selected = String(preselectId || '') === String(doctor.id);
          options.push(option);
        }
        docSel.replaceChildren(...options);
      }

      docSel.disabled = false;
      updateDoctorProfileButton();
      if (preselectId) {
        await fetchTarifa(preselectId);
      }
    } catch {
      doctorOptions = [];
      replaceSelectMessage(docSel, 'Error cargando profesionales');
      docSel.disabled = false;
      updateDoctorProfileButton();
    }
  }

  function clearSlots(message) {
    horaSel.innerHTML = `<option value="">${message || 'Seleccione doctor y fecha'}</option>`;
    horaSel.disabled = true;
  }

  function abortPendingSlotsRequest() {
    if (!slotsAbortController) return;
    slotsAbortController.abort();
    slotsAbortController = null;
  }

  function minutesToHHMM(minutes) {
    const total = Number(minutes);
    if (!Number.isFinite(total)) return null;

    const hours = Math.floor(total / 60);
    const mins = String(total % 60).padStart(2, '0');
    return `${String(hours).padStart(2, '0')}:${mins}`;
  }

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

  function normalizeSlot(item) {
    if (typeof item === 'string') {
      return { value: item, label: item, disabled: false };
    }

    if (typeof item === 'number') {
      const hhmm = minutesToHHMM(item);
      return hhmm ? { value: hhmm, label: hhmm, disabled: false } : null;
    }

    if (item && typeof item === 'object') {
      const availability = String(item.estado ?? item.status ?? '').toLowerCase();
      const value = item.value ?? item.hora ?? item.time ?? item.start ?? item.inicio ?? null;
      const label = item.label ?? item.hora ?? item.time ?? (item.inicio && item.fin ? `${item.inicio} - ${item.fin}` : value);
      const disabled = Boolean(item.disabled) || availability === 'ocupado';
      const resolvedValue = value ?? item.inicio ?? null;

      if (!resolvedValue && !label) return null;
      if (!resolvedValue) return null;

      return {
        value: String(resolvedValue),
        label: String(label ?? resolvedValue),
        disabled,
      };
    }

    return null;
  }

  async function loadSlots() {
    const doctorId = docSel.value;
    const fecha = fechaInp.value;

    if (!doctorId || !fecha) {
      abortPendingSlotsRequest();
      clearSlots();
      return;
    }

    const requestId = ++slotsRequestId;
    abortPendingSlotsRequest();
    slotsAbortController = new AbortController();

    horaHelp && (horaHelp.textContent = 'Buscando horarios...');
    clearSlots('Buscando...');

    try {
      const response = await fetch(slotsUrlFrom(doctorId, fecha), {
        headers: { Accept: 'application/json' },
        signal: slotsAbortController.signal,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      if (requestId !== slotsRequestId) {
        return;
      }

      const raw = Array.isArray(data) ? data : (Array.isArray(data.slots) ? data.slots : []);
      const normalized = raw.map(normalizeSlot).filter(Boolean);

      const available = normalized.filter((slot) => !slot.disabled);

      if (available.length === 0) {
        clearSlots('No hay horarios disponibles');
        horaHelp && (horaHelp.textContent = 'No hay horarios disponibles para esta fecha.');
        return;
      }

      let options = '<option value="">Seleccionar hora</option>';
      for (const slot of available) {
        const selected = String(preferredHour || '') === String(slot.value) ? ' selected' : '';
        options += `<option value="${slot.value}"${selected}>${slot.label}</option>`;
      }

      horaSel.innerHTML = options;
      horaSel.disabled = false;
      preferredHour = '';
      horaHelp && (horaHelp.textContent = 'Formato 24h. Se listan solo los horarios disponibles.');
    } catch (error) {
      if (error?.name === 'AbortError' || requestId !== slotsRequestId) {
        return;
      }

      clearSlots('Error al cargar horarios');
      horaHelp && (horaHelp.textContent = 'Error al cargar horarios.');
    } finally {
      if (requestId === slotsRequestId) {
        slotsAbortController = null;
      }
    }
  }

  function handleFechaChange() {
    preferredHour = '';
    loadSlots();
    updateStepper();
  }

  espSel?.addEventListener('change', function () {
    preferredHour = '';
    abortPendingSlotsRequest();
    toggleLabFields(this.value);
    loadDoctors(this.value, null);
    updateStepper();
  });

  docSel?.addEventListener('change', function () {
    const doctorId = this.value;
    preferredHour = '';
    updateDoctorProfileButton();

    if (!doctorId) {
      abortPendingSlotsRequest();
      hideTarifa();
      clearSlots();
      updateStepper();
      return;
    }

    fetchTarifa(doctorId);
    loadSlots();
    updateStepper();
  });

  fechaInp?.addEventListener('change', handleFechaChange);
  fechaInp?.addEventListener('enhanced-date:change', handleFechaChange);
  horaSel?.addEventListener('change', async () => {
    preferredHour = horaSel.value || '';
    if (horaSel.value) {
      const reserved = await acquireHold();
      if (!reserved) {
        preferredHour = '';
      }
    }
    updateStepper();
  });
  document.getElementById('motivo_consulta')?.addEventListener('input', updateStepper);
  examSel?.addEventListener('change', updateLabPrep);
  doctorProfileButton?.addEventListener('click', openDoctorProfileModal);
  doctorProfileModal?.querySelectorAll('[data-doctor-profile-close]').forEach((trigger) => {
    trigger.addEventListener('click', closeDoctorProfileModal);
  });
  doctorProfileModal?.addEventListener('click', (event) => {
    if (event.target === doctorProfileModal) {
      closeDoctorProfileModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && doctorProfileModal?.classList.contains('is-open')) {
      closeDoctorProfileModal();
    }
  });

  (async function init() {
    ensureHoldToken();
    if (oldEsp) {
      toggleLabFields(oldEsp);
      await loadDoctors(oldEsp, oldDoc || null);
      if (oldDoc && fechaInp?.value) {
        await loadSlots();
      }
    }

    updateLabPrep();
    updateDoctorProfileButton();
    updateStepper();
  })();
})();
