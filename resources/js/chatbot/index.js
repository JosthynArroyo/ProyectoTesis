document.addEventListener('DOMContentLoaded', () => {
  const chatbot = document.getElementById('chatbot');
  const log = document.getElementById('chat-log');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  const metaCsrf = document.querySelector('meta[name="csrf-token"]');

  if (!chatbot || !log || !form || !input) return;

  const csrf = chatbot.dataset.csrf || metaCsrf.getAttribute('content') || '';
  const baseUrl = (() => {
    const raw = chatbot.dataset.baseUrl || '';
    if (!raw) return window.location.origin;
    try {
      const url = new URL(raw, window.location.origin);
      const path = url.pathname.replace(/\/$/, '');
      if (url.origin !== window.location.origin) {
        return `${window.location.origin}${path}`;
      }
      return `${url.origin}${path}`;
    } catch (error) {
      return window.location.origin;
    }
  })();

  const state = {
    mode: 'menu',
    step: null,
    buffer: {},
    citasEncontradas: [],
  };

  const scrollBottom = () => { log.scrollTop = log.scrollHeight; };

  function addMessage(role, text, optionsHtml) {
    const wrap = document.createElement('div');
    wrap.className = 'chat-message';

    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${role === 'bot' ? 'chat-bubble--bot' : 'chat-bubble--user'}`;
    bubble.textContent = text;
    wrap.appendChild(bubble);

    if (optionsHtml) {
      const opts = document.createElement('div');
      opts.className = 'chat-options';
      opts.innerHTML = optionsHtml;
      wrap.appendChild(opts);
    }

    log.appendChild(wrap);
    scrollBottom();
  }

  function addButtons(buttons) {
    const wrap = document.createElement('div');
    wrap.className = 'chat-options';
    buttons.forEach((btn) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = btn.label;
      b.dataset.action = btn.action;
      if (btn.payload) {
        b.dataset.payload = JSON.stringify(btn.payload);
      }
      b.className = 'chat-option-btn';
      b.addEventListener('click', () => handleButtonClick(btn.action, btn.payload || null, btn.label));
      wrap.appendChild(b);
    });
    log.appendChild(wrap);
    scrollBottom();
  }

  function handleButtonClick(action, payload, label) {
    addMessage('user', label);

    switch (action) {
      case 'menu':
        showMainMenu();
        break;
      case 'agendar':
        startAgendar();
        break;
      case 'cancelar':
        startCancelar();
        break;
      case 'reagendar':
        startReagendar();
        break;
      case 'ver_citas':
        startVerCitas();
        break;
      case 'soporte':
        state.mode = 'soporte';
        state.step = 'descripcion';
        addMessage('bot', 'Por favor describe brevemente tu consulta o problema.');
        break;
      case 'cancelar_cita_id':
        if (payload && payload.cita_id) {
          confirmarCancelacion(payload.cita_id);
        }
        break;
      case 'reagendar_cita_id':
        if (payload && payload.cita_id) {
          pedirNuevaFechaHora(payload.cita_id);
        }
        break;
    }
  }

  function showMainMenu() {
    state.mode = 'menu';
    state.step = null;
    state.buffer = {};
    state.citasEncontradas = [];

    addMessage('bot', '¿Qué necesitas hoy');
    addButtons([
      { label: 'Agendar una cita', action: 'agendar' },
      { label: 'Cancelar una cita', action: 'cancelar' },
      { label: 'Reagendar una cita', action: 'reagendar' },
      { label: 'Ver próximas citas', action: 'ver_citas' },
      { label: 'Hablar con soporte', action: 'soporte' },
    ]);
  }

  // Flujo AGENDAR
  function startAgendar() {
    state.mode = 'agendar';
    state.step = 'nombre';
    state.buffer = {};
    addMessage('bot', 'Perfecto, vamos a agendar una nueva cita.\n\n¿Cuál es tu nombre completo');
  }

  async function pedirEspecialidades() {
    try {
      const res = await fetch(baseUrl + '/chatbot/especialidades');
      const data = await res.json();
      state.buffer.especialidades = data;

      let text = 'Estas son las especialidades disponibles. Escribe el número de la especialidad que deseas:\n\n';
      data.forEach((esp, i) => {
        text += `${i + 1}) ${esp.nombre}\n`;
      });
      addMessage('bot', text.trim());
      state.step = 'especialidad';
    } catch (e) {
      addMessage('bot', 'No pude obtener la lista de especialidades. Intenta más tarde o elige otra opción.');
      showMainMenu();
    }
  }

  async function enviarAgendar() {
    addMessage('bot', 'Estoy registrando tu cita, un momento...');

    const payload = {
      nombre: state.buffer.nombre,
      cedula: state.buffer.cedula,
      email: state.buffer.email,
      telefono: state.buffer.telefono,
      especialidad_id: state.buffer.especialidad_id,
      fecha: state.buffer.fecha,
      hora: state.buffer.hora,
      motivo: state.buffer.motivo,
      crear_usuario: true,
    };

    try {
      const res = await fetch(baseUrl + '/chatbot/agendar', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (!res.ok || !data.ok) {
        addMessage('bot', data.message || 'No se pudo agendar la cita. Verifica los datos o intenta de nuevo.');
        showMainMenu();
        return;
      }

      let msg = 'Listo, tu cita ha sido agendada.\n\n';
      msg += `Fecha: ${data.cita.fecha}\n`;
      msg += `Hora: ${data.cita.hora}\n`;
      if (data.cita.doctor) {
        msg += `Doctor: ${data.cita.doctor}\n`;
      }
      msg += `Estado: ${data.cita.estado}\n`;

      if (data.usuario_creado) {
        msg += '\nSe creó una cuenta con tu correo. Revisa tu bandeja de entrada, te enviamos una contraseña temporal.';
      }

      addMessage('bot', msg.trim());
      addButtons([
        { label: 'Volver al menú principal', action: 'menu' },
      ]);

      state.mode = 'menu';
      state.step = null;
      state.buffer = {};
    } catch (e) {
      addMessage('bot', 'Ocurrió un error al agendar la cita. Intenta nuevamente más tarde.');
      showMainMenu();
    }
  }

  // Flujo CANCELAR / REAGENDAR / VER_CITAS comparten la búsqueda por cédula/correo
  function startCancelar() {
    state.mode = 'cancelar';
    state.step = 'cedula';
    state.buffer = {};
    addMessage('bot', 'Para cancelar una cita necesito identificarte.\n\nIngresa tu número de cédula (10 dígitos):');
  }

  function startReagendar() {
    state.mode = 'reagendar';
    state.step = 'cedula';
    state.buffer = {};
    addMessage('bot', 'Para reprogramar una cita necesito identificarte.\n\nIngresa tu número de cédula (10 dígitos):');
  }

  function startVerCitas() {
    state.mode = 'ver_citas';
    state.step = 'cedula';
    state.buffer = {};
    addMessage('bot', 'Para mostrar tus próximas citas necesito identificarte.\n\nIngresa tu número de cédula (10 dígitos):');
  }

  async function buscarCitasPorIdentificacion() {
    const payload = {
      email: state.buffer.email,
      cedula: state.buffer.cedula,
      estado: 'all',
      incluir_todas: true,
    };

    try {
      const res = await fetch(baseUrl + '/chatbot/buscar-citas', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok || !data.citas || data.citas.length === 0) {
        addMessage('bot', data.message || 'No encontré citas activas con esos datos.');
        addButtons([{ label: 'Volver al menú principal', action: 'menu' }]);
        state.mode = 'menu';
        state.step = null;
        return;
      }

      state.citasEncontradas = data.citas;

      let msg = `Paciente: ${data.paciente || ''}\n\n`;
      msg += 'Estas son tus próximas citas:\n\n';
      data.citas.forEach((c, i) => {
        msg += `${i + 1}) ${c.fecha} ${c.hora} - ${c.especialidad || 'Especialidad'} (${c.estado})\n`;
      });
      addMessage('bot', msg.trim());

      if (state.mode === 'cancelar') {
        addMessage('bot', 'Elige la cita que deseas cancelar pulsando uno de los botones:');
        const buttons = data.citas.map((c, i) => ({
          label: `${i + 1}) ${c.fecha} ${c.hora}`,
          action: 'cancelar_cita_id',
          payload: { cita_id: c.id },
        }));
        addButtons(buttons);
      } else if (state.mode === 'reagendar') {
        addMessage('bot', 'Elige la cita que deseas reprogramar pulsando uno de los botones:');
        const buttons = data.citas.map((c, i) => ({
          label: `${i + 1}) ${c.fecha} ${c.hora}`,
          action: 'reagendar_cita_id',
          payload: { cita_id: c.id },
        }));
        addButtons(buttons);
      } else if (state.mode === 'ver_citas') {
        addButtons([{ label: 'Volver al menú principal', action: 'menu' }]);
        state.mode = 'menu';
      }

      state.step = null;
    } catch (e) {
      addMessage('bot', 'Ocurrió un error al buscar tus citas. Intenta más tarde.');
      showMainMenu();
    }
  }

  function confirmarCancelacion(citaId) {
    state.buffer.cita_id = citaId;
    state.step = 'confirmar_cancelacion';
    addMessage('bot', '¿Confirmas que deseas cancelar esta cita Escribe "sí" para confirmar o "no" para cancelar la operación.');
  }

  async function ejecutarCancelacion() {
    const citaId = state.buffer.cita_id;
    try {
      const res = await fetch(baseUrl + '/chatbot/cancelar', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify({
          cita_id: citaId,
          cedula: state.buffer.cedula || null,
          email: state.buffer.email || null,
        }),
      });
      const data = await res.json();

      if (!data.ok) {
        addMessage('bot', data.message || 'No se pudo cancelar la cita.');
      } else {
        addMessage('bot', data.message || 'Cita cancelada correctamente.');
      }
      addButtons([{ label: 'Volver al menú principal', action: 'menu' }]);
      state.mode = 'menu';
      state.step = null;
      state.buffer = {};
    } catch (e) {
      addMessage('bot', 'Ocurrió un error al cancelar la cita.');
      showMainMenu();
    }
  }

  function pedirNuevaFechaHora(citaId) {
    state.buffer.cita_id = citaId;
    state.step = 'nueva_fecha';
    addMessage('bot', 'Indica la nueva fecha para la cita en formato AAAA-MM-DD (por ejemplo, 2025-11-20).');
  }

  async function ejecutarReagendar() {
    const payload = {
      cita_id: state.buffer.cita_id,
      fecha: state.buffer.nueva_fecha,
      hora: state.buffer.nueva_hora,
      cedula: state.buffer.cedula || null,
      email: state.buffer.email || null,
    };

    try {
      const res = await fetch(baseUrl + '/chatbot/reagendar', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) {
        addMessage('bot', data.message || 'No se pudo reprogramar la cita.');
      } else {
        addMessage('bot', data.message || 'La cita ha sido reprogramada.');
      }
      addButtons([{ label: 'Volver al menú principal', action: 'menu' }]);
      state.mode = 'menu';
      state.step = null;
      state.buffer = {};
    } catch (e) {
      addMessage('bot', 'Ocurrió un error al reprogramar la cita.');
      showMainMenu();
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    addMessage('user', text);

    if (state.mode === 'agendar') {
      if (state.step === 'nombre') {
        state.buffer.nombre = text;
        state.step = 'cedula';
        addMessage('bot', 'Anota tu número de cédula:');
      } else if (state.step === 'cedula') {
        state.buffer.cedula = text;
        state.step = 'email';
        addMessage('bot', 'Escribe tu correo electrónico:');
      } else if (state.step === 'email') {
        state.buffer.email = text;
        state.step = 'telefono';
        addMessage('bot', 'Escribe tu número de teléfono:');
      } else if (state.step === 'telefono') {
        state.buffer.telefono = text;
        state.step = 'motivo';
        addMessage('bot', 'Escribe el motivo de la consulta.');
      } else if (state.step === 'motivo') {
        state.buffer.motivo = text;
        pedirEspecialidades();
      } else if (state.step === 'especialidad') {
        const idx = parseInt(text, 10);
        const lista = state.buffer.especialidades || [];
        if (!idx || idx < 1 || idx > lista.length) {
          addMessage('bot', 'Por favor, escribe un número válido de la lista de especialidades.');
          return;
        }
        const esp = lista[idx - 1];
        state.buffer.especialidad_id = esp.id;
        state.step = 'fecha';
        addMessage('bot', 'Indica la fecha que deseas para la cita en formato AAAA-MM-DD (por ejemplo, 2025-11-20).');
      } else if (state.step === 'fecha') {
        state.buffer.fecha = text;
        state.step = 'hora';
        addMessage('bot', 'Indica la hora que deseas en formato HH:MM (por ejemplo, 09:30).');
      } else if (state.step === 'hora') {
        state.buffer.hora = text;
        state.step = 'confirmar';
        addMessage('bot', '¿Confirmas que deseas agendar la cita con esos datos Escribe "sí" para confirmar o "no" para cancelar.');
      } else if (state.step === 'confirmar') {
        const lower = text.toLowerCase();
        if (lower === 'si' || lower === 'sí') {
          enviarAgendar();
        } else {
          addMessage('bot', 'Se canceló el proceso de agendamiento.');
          showMainMenu();
        }
      }
    } else if (['cancelar', 'reagendar', 'ver_citas'].includes(state.mode)) {
      if (state.step === 'cedula') {
        state.buffer.cedula = text;
        state.step = 'email';
        addMessage('bot', 'Escribe tu correo electrónico:');
      } else if (state.step === 'email') {
        state.buffer.email = text;
        buscarCitasPorIdentificacion();
      } else if (state.step === 'confirmar_cancelacion') {
        const lower = text.toLowerCase();
        if (lower === 'si' || lower === 'sí') {
          ejecutarCancelacion();
        } else {
          addMessage('bot', 'No se canceló ninguna cita.');
          showMainMenu();
        }
      } else if (state.mode === 'reagendar') {
        if (state.step === 'nueva_fecha') {
          state.buffer.nueva_fecha = text;
          state.step = 'nueva_hora';
          addMessage('bot', 'Ahora indica la nueva hora en formato HH:MM (por ejemplo, 14:00).');
        } else if (state.step === 'nueva_hora') {
          state.buffer.nueva_hora = text;
          state.step = 'confirmar_reagendar';
          addMessage('bot', '¿Confirmas que deseas reprogramar la cita a esa nueva fecha y hora Escribe "sí" para confirmar o "no" para cancelar.');
        } else if (state.step === 'confirmar_reagendar') {
          const lower = text.toLowerCase();
          if (lower === 'si' || lower === 'sí') {
            ejecutarReagendar();
          } else {
            addMessage('bot', 'No se reprogramó ninguna cita.');
            showMainMenu();
          }
        }
      }
    } else if (state.mode === 'soporte') {
      if (state.step === 'descripcion') {
        addMessage('bot', 'Gracias. Hemos registrado tu mensaje. Un miembro del equipo de soporte revisará tu caso.');
        addButtons([{ label: 'Volver al menú principal', action: 'menu' }]);
        state.mode = 'menu';
        state.step = null;
      }
    } else if (state.mode === 'menu') {
      addMessage('bot', 'Para continuar elige una de las opciones del menú.');
    }
  });

  addMessage('bot', 'Hola, soy el asistente virtual de la Clínica Los Don Bosco.');
  showMainMenu();
});
