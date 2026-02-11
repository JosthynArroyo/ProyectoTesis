document.addEventListener('DOMContentLoaded', () => {
  initDiagnosticos();
  initEvolucionSignos();
  initAgendarControl();
});

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
        return;
      }
      if (current === null) {
        deltaNode.textContent = 'Ingresa un valor actual para comparar.';
        return;
      }
      const delta = current - previous;
      if (Math.abs(delta) < 0.0001) {
        deltaNode.textContent = `Sin cambios (${formatNumber(current)} ${unit})`;
        return;
      }
      const sign = delta > 0 ? '+' : '';
      deltaNode.textContent = `${sign}${formatNumber(delta)} ${unit}`;
    };

    input.addEventListener('input', update);
    input.addEventListener('change', update);
    update();
  });
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
        help.textContent = 'La sesion expiro. Recarga la pagina para continuar.';
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
