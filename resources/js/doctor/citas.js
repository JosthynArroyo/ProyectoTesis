document.addEventListener('DOMContentLoaded', () => {
  const page = document.querySelector('.appointments-page');
  if (!page) return;

  const csrf = page.dataset.csrf || '';
  const checkUrl = page.dataset.checkUrl || '';
  const planUrlTpl = page.dataset.planUrl || '';
  const slotsUrlTpl = page.dataset.slotsUrl || '';
  const loginUrl = page.dataset.loginUrl || '';

  const $ = (q, ctx = document) => ctx.querySelector(q);
  const $$ = (q, ctx = document) => Array.from(ctx.querySelectorAll(q));

  const state = { citaId: null, doctorId: null, slot: null };

  $('#btn-refresh').addEventListener('click', () => window.location.reload());

  $$('#tabla-citas [data-accion="planificador"]').forEach((button) => {
    button.addEventListener('click', async (event) => {
      event.preventDefault();
      state.citaId = button.dataset.cita || null;
      state.doctorId = button.dataset.doctor || null;
      state.slot = null;
      $('#tp-fecha').value = '';
      $('#tp-slots').innerHTML = '';
      $('#tp-help').textContent = '';
      $('#tp-crear').disabled = true;
      $('#toast-warn').classList.add('is-hidden');

      if (checkUrl) {
        const chk = await getJson(checkUrl);
        if (!chk.ok || (chk.json.ok === false && chk.json.reason === 'sin_horario')) {
          $('#toast-warn').classList.remove('is-hidden');
        }
      }
      openToast();
    });
  });

  // Toggle de acciones en móvil
  $$('.action-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const cell = btn.closest('.cell-actions');
      const panel = cell.querySelector('.actions-scroll');
      if (!panel) return;
      const isOpen = panel.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        // Cerrar otros abiertos
        $$('.actions-scroll.is-open').forEach((p) => {
          if (p !== panel) p.classList.remove('is-open');
        });
        $$('.action-toggle[aria-expanded="true"]').forEach((b) => {
          if (b !== btn) b.setAttribute('aria-expanded', 'false');
        });
      }
    });
  });

  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('.action-toggle');
    const menu = e.target.closest('.actions-scroll');
    if (toggle || menu) return;
    $$('.actions-scroll.is-open').forEach((p) => p.classList.remove('is-open'));
    $$('.action-toggle[aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  });

  $('#toast-close').addEventListener('click', closeToast);
  $('#tp-cancelar').addEventListener('click', closeToast);

  $('#tp-fecha').addEventListener('change', async () => {
    state.slot = null;
    $('#tp-crear').disabled = true;
    $('#tp-slots').innerHTML = '';
    $('#tp-help').textContent = 'Cargando…';

    const fecha = $('#tp-fecha').value;
    if (!fecha) {
      $('#tp-help').textContent = '';
      return;
    }

    const urlSlots = buildSlotsUrl(state.doctorId, fecha);
    if (!urlSlots) {
      $('#tp-help').textContent = 'No se configuró la ruta de horarios.';
      return;
    }

    const response = await getJson(urlSlots);
    if (!response.ok) {
      $('#tp-help').textContent = 'Error al cargar horarios.';
      return;
    }

    const slotsRaw = (response.json && (response.json.slots ?? response.json.data ?? response.json)) || [];
    const slots = normalizeSlots(slotsRaw);

    if (slots.length === 0) {
      $('#tp-help').textContent = 'Sin disponibilidad en esta fecha.';
      return;
    }

    $('#tp-help').textContent = 'Seleccione una hora.';
    const wrap = $('#tp-slots');
    wrap.innerHTML = '';

    slots.forEach(({ label, value }) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chip';
      chip.textContent = label;
      chip.addEventListener('click', () => {
        $$('#tp-slots .chip').forEach((c) => c.removeAttribute('aria-checked'));
        chip.setAttribute('aria-checked', 'true');
        state.slot = value;
        $('#tp-crear').disabled = false;
      });
      wrap.appendChild(chip);
    });
  });

  $('#tp-crear').addEventListener('click', async () => {
    if (!state.citaId || !$('#tp-fecha').value || !state.slot) return;
    setLoading($('#tp-crear'), true);

    const url = buildPlanUrl(state.citaId);
    const res = await postJson(url, { fecha: $('#tp-fecha').value, hora: state.slot });
    setLoading($('#tp-crear'), false);

    if (res.ok && res.json.ok) {
      toastSmall('Cita creada y notificada por email.');
      closeToast();
      window.location.reload();
      return;
    }
    if (res.status === 422) {
      toastWarn(res.json.msg || 'Validación rechazada.');
      return;
    }
    if (res.status === 419) {
      toastWarn('Sesión expirada.');
      if (loginUrl) window.location.href = loginUrl;
      return;
    }
    toastWarn('No se pudo crear la cita.');
  });

  function normalizeSlots(raw) {
    if (!Array.isArray(raw)) return [];
    return raw
      .filter((s) => {
        if (s && typeof s === 'object' && Object.prototype.hasOwnProperty.call(s, 'estado')) {
          return String(s.estado).toLowerCase() === 'libre';
        }
        return true;
      })
      .map((s) => {
        if (typeof s === 'string') return { label: s, value: s };
        if (typeof s === 'number') {
          const z = String(s).padStart(4, '0');
          const hhmm = `${z.slice(0, 2)}:${z.slice(2)}`;
          return { label: hhmm, value: hhmm };
        }
        if (s == null || typeof s !== 'object') return null;
        const cand = s.hhmm || s.hora || s.time || s.label || s.value;
        if (cand) return { label: String(cand), value: String(cand) };
        const first = Object.values(s)[0];
        return first ? { label: String(first), value: String(first) } : null;
      })
      .filter(Boolean);
  }

  function openToast() {
    $('#toast-planificador').setAttribute('open', '');
  }

  function closeToast() {
    $('#toast-planificador').removeAttribute('open');
  }

  function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
      btn.dataset._txt = btn.innerHTML;
      btn.innerHTML = '<i class="ri-time-line"></i> Procesando…';
      btn.setAttribute('disabled', 'disabled');
    } else {
      if (btn.dataset._txt) btn.innerHTML = btn.dataset._txt;
      btn.removeAttribute('disabled');
    }
  }

  async function getJson(url) {
    try {
      const resp = await fetch(url, { headers: { Accept: 'application/json' } });
      const json = await safeJson(resp);
      return { ok: resp.ok, status: resp.status, json };
    } catch (err) {
      return { ok: false, status: 0, json: {} };
    }
  }

  async function postJson(url, data) {
    try {
      const resp = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(data || {}),
      });
      const json = await safeJson(resp);
      return { ok: resp.ok, status: resp.status, json };
    } catch (err) {
      return { ok: false, status: 0, json: {} };
    }
  }

  async function safeJson(resp) {
    try {
      const text = await resp.text();
      return text ? JSON.parse(text) : {};
    } catch (err) {
      return {};
    }
  }

  function toastSmall(message) {
    const el = document.createElement('div');
    el.className = 'toast-float toast-float--success';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2200);
  }

  function toastWarn(message) {
    const box = document.createElement('div');
    box.className = 'toast-float toast-float--warn';
    const content = document.createElement('div');
    content.className = 'toast-float__content';
    content.innerHTML = '<i class="ri-error-warning-line"></i><div></div>';
    content.querySelector('div').textContent = message;
    box.appendChild(content);
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 4000);
  }

  function buildPlanUrl(id) {
    return planUrlTpl ? planUrlTpl.replace('__ID__', String(id ?? '')) : '';
  }

  function buildSlotsUrl(doctor, fecha) {
    if (!slotsUrlTpl) return '';
    return slotsUrlTpl.replace('__D__', String(doctor ?? '')).replace('__F__', String(fecha ?? ''));
  }
});
