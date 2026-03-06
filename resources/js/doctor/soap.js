document.addEventListener('DOMContentLoaded', () => {
  initSoapStepper();
  initDiagnosticos();
  initEvolucionSignos();
  initAgendarControl();
  initSoapAutosave();
});

function initSoapStepper() {
  const form = document.querySelector('#soap-page form');
  const stepItems = Array.from(document.querySelectorAll('[data-soap-step-item]'));
  const sections = Array.from(document.querySelectorAll('[data-soap-section]'));
  const sectionButtons = Array.from(document.querySelectorAll('[data-soap-section-toggle]'));
  const progressText = document.querySelector('[data-soap-progress-text]');
  const mobileQuery = window.matchMedia('(max-width: 768px)');
  if (!form || !stepItems.length || !sections.length) {
    return;
  }

  let selectedStep = null;

  const markStep = (step, completed, active) => {
    const node = stepItems.find((item) => Number(item.dataset.soapStepItem) === step);
    if (!node) {
      return;
    }
    node.classList.remove(
      'border-teal-200',
      'bg-teal-50',
      'text-teal-700',
      'border-emerald-200',
      'bg-emerald-50',
      'text-emerald-700',
      'border-slate-200',
      'bg-white',
      'text-slate-500'
    );
    if (completed) {
      node.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
      return;
    }
    if (active) {
      node.classList.add('border-teal-200', 'bg-teal-50', 'text-teal-700');
      return;
    }
    node.classList.add('border-slate-200', 'bg-white', 'text-slate-500');
  };

  const showSection = (step, scroll = false) => {
    selectedStep = step;
    sections.forEach((section) => {
      const sectionStep = Number(section.dataset.soapSection);
      const body = section.querySelector('[data-soap-section-body]');
      const toggle = section.querySelector('[data-soap-section-toggle]');
      const expanded = !mobileQuery.matches || sectionStep === step;

      if (body) {
        body.hidden = !expanded;
      }
      if (toggle) {
        toggle.textContent = expanded ? 'Seccion activa' : 'Ver seccion';
      }
      section.classList.toggle('is-active', expanded);
    });

    if (!scroll) {
      return;
    }

    const target = sections.find((section) => Number(section.dataset.soapSection) === step);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const sectionIsCompleted = (section) => {
    const required = Array.from(section.querySelectorAll('input[required], textarea[required], select[required]'));
    if (!required.length) {
      return true;
    }
    return required.every((field) => {
      if (field.disabled || field.readOnly) {
        return true;
      }
      if (field.type === 'checkbox' || field.type === 'radio') {
        return field.checked;
      }
      return String(field.value || '').trim() !== '';
    });
  };

  const updateStepper = () => {
    const completion = {};
    sections.forEach((section) => {
      const step = Number(section.dataset.soapSection);
      completion[step] = sectionIsCompleted(section);
    });

    let activeStep = 1;
    const sortedSteps = sections
      .map((section) => Number(section.dataset.soapSection))
      .sort((a, b) => a - b);

    for (const step of sortedSteps) {
      if (!completion[step]) {
        activeStep = step;
        break;
      }
      activeStep = step;
    }

    sortedSteps.forEach((step) => {
      markStep(step, completion[step], step === activeStep && !completion[step]);
    });

    if (progressText) {
      progressText.textContent = `Seccion ${activeStep} de ${sortedSteps.length}`;
    }

    const stepToShow = mobileQuery.matches ? (selectedStep ?? activeStep) : activeStep;
    showSection(stepToShow, false);
  };

  stepItems.forEach((item) => {
    const step = Number(item.dataset.soapStepItem);
    const activate = () => showSection(step, true);
    item.addEventListener('click', activate);
    item.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        activate();
      }
    });
  });

  sectionButtons.forEach((button) => {
    const step = Number(button.dataset.soapSectionToggle);
    button.addEventListener('click', () => {
      showSection(step, true);
    });
  });

  form.addEventListener('input', updateStepper);
  form.addEventListener('change', updateStepper);
  if (mobileQuery.addEventListener) {
    mobileQuery.addEventListener('change', updateStepper);
  } else {
    window.addEventListener('resize', updateStepper);
  }
  updateStepper();
}

function initSoapAutosave() {
  const page = document.getElementById('soap-page');
  const form = page ? page.querySelector('form') : null;
  const status = document.querySelector('[data-soap-autosave-status]');
  if (!page || !form) {
    return;
  }

  const autosaveUrl = page.dataset.autosaveUrl || '';
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrf = page.dataset.csrf || (csrfMeta ? csrfMeta.getAttribute('content') : '') || '';
  const canEdit = Boolean(form.querySelector('button[formaction*="firmar"]'));
  if (!autosaveUrl || !csrf || !canEdit) {
    if (status) {
      status.textContent = 'Autoguardado no disponible para esta consulta.';
    }
    return;
  }

  let submitting = false;
  form.addEventListener('submit', () => {
    submitting = true;
  });

  const setStatus = (message, tone = 'neutral') => {
    if (!status) {
      return;
    }
    status.textContent = message;
    status.classList.remove('text-slate-500', 'text-emerald-700', 'text-rose-600');
    if (tone === 'success') {
      status.classList.add('text-emerald-700');
      return;
    }
    if (tone === 'error') {
      status.classList.add('text-rose-600');
      return;
    }
    status.classList.add('text-slate-500');
  };

  const autosave = async () => {
    if (submitting || document.hidden) {
      return;
    }

    const formData = new FormData(form);
    try {
      const response = await fetch(autosaveUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
      });

      if (!response.ok) {
        setStatus('No se pudo autoguardar el borrador.', 'error');
        return;
      }

      const stamp = new Date().toLocaleTimeString();
      setStatus(`Borrador autoguardado ${stamp}.`, 'success');
    } catch (error) {
      setStatus('No se pudo autoguardar el borrador.', 'error');
    }
  };

  setInterval(autosave, 30000);
}

function initDiagnosticos() {
  const addBtn = document.getElementById('add-diagnostico');
  const wrap = document.getElementById('diagnosticos-wrap');

  if (!addBtn || !wrap) {
    return;
  }

  addBtn.addEventListener('click', () => {
    const index = wrap.querySelectorAll('[data-diag-row]').length;
    const row = document.createElement('div');
    row.className = 'grid gap-2 md:grid-cols-[140px_1fr_140px]';
    row.setAttribute('data-diag-row', '1');
    row.innerHTML = `
      <select name="diagnosticos[${index}][tipo]" class="form-select">
        <option value="principal">Principal</option>
        <option value="secundario">Secundario</option>
        <option value="diferencial">Diferencial</option>
      </select>
      <input name="diagnosticos[${index}][texto]" class="form-input" placeholder="Diagnostico">
      <input name="diagnosticos[${index}][cie10]" class="form-input" placeholder="CIE-10 (opcional)">
    `;
    wrap.appendChild(row);
  });
}

function initEvolucionSignos() {
  const cards = Array.from(document.querySelectorAll('[data-sv-card]'));
  if (!cards.length) {
    return;
  }

  cards.forEach((card) => {
    const inputId = card.dataset.svInput || '';
    const input = document.getElementById(inputId);
    const deltaNode = card.querySelector('[data-sv-delta]');
    const sparkline = card.querySelector('[data-sv-sparkline]');
    if (!input || !deltaNode) {
      return;
    }

    const prevRaw = card.dataset.svPrevious || '';
    const unit = card.dataset.svUnit || '';

    const update = () => {
      const previous = parseSigno(prevRaw, inputId);
      const current = parseSigno(input.value, inputId);
      if (previous === null) {
        deltaNode.textContent = 'No existe valor previo comparable.';
        drawSparkline(sparkline, null, current);
        return;
      }
      if (current === null) {
        deltaNode.textContent = 'Ingresa un valor actual para comparar.';
        drawSparkline(sparkline, previous, null);
        return;
      }
      const delta = current - previous;
      if (Math.abs(delta) < 0.0001) {
        deltaNode.textContent = `Sin cambios (${formatNumber(current)} ${unit})`;
        drawSparkline(sparkline, previous, current);
        return;
      }
      const sign = delta > 0 ? '+' : '';
      deltaNode.textContent = `${sign}${formatNumber(delta)} ${unit}`;
      drawSparkline(sparkline, previous, current);
    };

    input.addEventListener('input', update);
    input.addEventListener('change', update);
    update();
  });
}

function drawSparkline(canvas, previous, current) {
  if (!canvas || !canvas.getContext) {
    return;
  }

  const ctx = canvas.getContext('2d');
  const width = canvas.width;
  const height = canvas.height;
  ctx.clearRect(0, 0, width, height);

  const values = [previous, current].filter((value) => Number.isFinite(value));
  if (!values.length) {
    return;
  }

  const min = Math.min(...values);
  const max = Math.max(...values);
  const span = Math.max(max - min, 1);
  const toY = (value) => {
    const paddedTop = 8;
    const paddedBottom = height - 8;
    const normalized = (value - min) / span;
    return paddedBottom - normalized * (paddedBottom - paddedTop);
  };

  const x1 = 16;
  const x2 = width - 16;
  const yPrev = Number.isFinite(previous) ? toY(previous) : null;
  const yCurrent = Number.isFinite(current) ? toY(current) : yPrev;

  ctx.strokeStyle = '#94a3b8';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(x1, yPrev ?? height / 2);
  ctx.lineTo(x2, yCurrent ?? height / 2);
  ctx.stroke();

  if (Number.isFinite(previous)) {
    ctx.fillStyle = '#64748b';
    ctx.beginPath();
    ctx.arc(x1, yPrev, 3, 0, Math.PI * 2);
    ctx.fill();
  }
  if (Number.isFinite(current)) {
    ctx.fillStyle = '#0d9488';
    ctx.beginPath();
    ctx.arc(x2, yCurrent, 3, 0, Math.PI * 2);
    ctx.fill();
  }
}

function initAgendarControl() {
  const page = document.getElementById('soap-page');
  const fechaInput = document.getElementById('control-fecha');
  const horaSelect = document.getElementById('control-hora');
  const agendarBtn = document.getElementById('btn-agendar-control');
  const help = document.getElementById('control-help');

  if (!page || !fechaInput || !horaSelect || !agendarBtn || !help) {
    return;
  }

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrf = page.dataset.csrf || (csrfMeta ? csrfMeta.getAttribute('content') : '') || '';
  const planUrl = page.dataset.planUrl || '';
  const slotsUrlTemplate = page.dataset.slotsUrlTemplate || '';
  const doctorId = page.dataset.doctorId || '';

  if (!planUrl || !slotsUrlTemplate || !doctorId) {
    help.textContent = 'No fue posible cargar la configuracion de agenda para esta consulta.';
    return;
  }

  fechaInput.addEventListener('change', async () => {
    const fecha = (fechaInput.value || '').trim();
    clearHorarios(horaSelect);
    agendarBtn.disabled = true;

    if (!fecha) {
      help.textContent = 'Selecciona una fecha para consultar horarios disponibles.';
      return;
    }

    help.textContent = 'Consultando horarios disponibles...';
    const url = slotsUrlTemplate
      .replace('__DOCTOR__', encodeURIComponent(String(doctorId)))
      .replace('__FECHA__', encodeURIComponent(fecha));

    try {
      const res = await fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json' },
      });
      const data = await safeJson(res);
      if (!res.ok) {
        help.textContent = 'No se pudo cargar la disponibilidad para esta fecha.';
        return;
      }

      const slots = normalizeSlots(data);
      if (!slots.length) {
        help.textContent = 'No hay horarios libres para la fecha seleccionada.';
        return;
      }

      slots.forEach((slot) => {
        const option = document.createElement('option');
        option.value = slot.value;
        option.textContent = slot.label;
        horaSelect.appendChild(option);
      });
      horaSelect.disabled = false;
      help.textContent = 'Selecciona el horario para confirmar el control.';
    } catch (error) {
      help.textContent = 'No se pudo cargar la disponibilidad para esta fecha.';
    }
  });

  horaSelect.addEventListener('change', () => {
    agendarBtn.disabled = !horaSelect.value;
  });

  agendarBtn.addEventListener('click', async () => {
    const fecha = (fechaInput.value || '').trim();
    const hora = (horaSelect.value || '').trim();
    if (!fecha || !hora) {
      help.textContent = 'Selecciona fecha y horario antes de agendar.';
      return;
    }

    const original = agendarBtn.innerHTML;
    agendarBtn.disabled = true;
    agendarBtn.innerHTML = '<i class="ri-loader-4-line"></i> Agendando...';

    try {
      const res = await fetch(planUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify({ fecha, hora }),
      });
      const data = await safeJson(res);

      if (res.ok && data.ok) {
        help.textContent = 'Control agendado correctamente. Se registro la nueva cita del paciente.';
        agendarBtn.innerHTML = original;
        clearHorarios(horaSelect);
        fechaInput.value = '';
        return;
      }

      if (res.status === 422) {
        help.textContent = data.msg || 'No se pudo agendar el control con los datos seleccionados.';
      } else if (res.status === 419) {
        help.textContent = 'La sesión expiró. Recarga la página para continuar.';
      } else {
        help.textContent = 'No se pudo agendar el control en este momento.';
      }
    } catch (error) {
      help.textContent = 'No se pudo agendar el control en este momento.';
    } finally {
      agendarBtn.innerHTML = original;
      agendarBtn.disabled = !horaSelect.value;
    }
  });
}

function clearHorarios(selectNode) {
  selectNode.innerHTML = '<option value="">Selecciona fecha primero</option>';
  selectNode.disabled = true;
}

function normalizeSlots(payload) {
  const raw = Array.isArray(payload?.slots)
    ? payload.slots
    : (Array.isArray(payload) ? payload : []);

  return raw
    .filter((slot) => {
      if (slot && typeof slot === 'object' && Object.prototype.hasOwnProperty.call(slot, 'estado')) {
        return String(slot.estado).toLowerCase() === 'libre';
      }
      return true;
    })
    .map((slot) => {
      if (typeof slot === 'string') {
        return { value: slot, label: slot };
      }
      if (!slot || typeof slot !== 'object') {
        return null;
      }
      const value = String(slot.hhmm || slot.hora || slot.time || slot.value || '').trim();
      if (!value) {
        return null;
      }
      return { value, label: value };
    })
    .filter(Boolean);
}

function parseSigno(value, inputId) {
  const raw = String(value || '').trim();
  if (!raw) {
    return null;
  }

  if (inputId === 'sv_ta') {
    const match = raw.match(/(\d{2,3})\s*\/\s*\d{2,3}/);
    if (match) {
      return Number(match[1]);
    }
  }

  const normalized = raw.replace(',', '.');
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : null;
}

function formatNumber(value) {
  const abs = Math.abs(value);
  if (abs >= 100 || Number.isInteger(value)) {
    return String(Math.round(value));
  }
  return value.toFixed(1);
}

async function safeJson(response) {
  try {
    const text = await response.text();
    return text ? JSON.parse(text) : {};
  } catch (error) {
    return {};
  }
}
