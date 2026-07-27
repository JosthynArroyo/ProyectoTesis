document.addEventListener('DOMContentLoaded', function () {
  var widget = document.getElementById('chatbot-widget');
  var panel  = document.getElementById('chatbot-panel');
  var toggle = document.getElementById('chatbot-toggle');
  if (!panel || !toggle || !widget) {
    return;
  }

  function focusInput() {
    var input = document.getElementById('chatbot-input');
    if (input) {
      input.focus();
    }
  }

  function togglePanel() {
    var isOpen = panel.classList.toggle('is-open');
    panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) {
      setTimeout(focusInput, 120);
    }
  }

  toggle.addEventListener('click', togglePanel);
  window.toggleChatbotWidget = togglePanel;

  const log   = document.getElementById('chat-log');
  const form  = document.getElementById('chatbot-form');
  const input = document.getElementById('chatbot-input');

  if (!log || !form || !input) return;

  const metaCsrf = document.querySelector('meta[name="csrf-token"]');
  const csrf    = widget.dataset.csrf || metaCsrf.getAttribute('content') || '';
  const slotHoldUrl = widget.dataset.slotHoldUrl || '';
  const baseUrl = (() => {
    const raw = widget.dataset.baseUrl || '';
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

  const initialBuffer = () => ({
    nombre: '',
    cedula: '',
    email: '',
    telefono: '',
    dni: '',
    direccion: '',
    fecha_nacimiento: '',
    sexo: '',
    parentesco: '',
    telefono_emergencia: '',
    notas: '',
    motivo: '',
    especialidad_id: null,
    doctor_id: null,
    doctor_nombre: '',
    doctor_tarifa: '',
    fecha: '',
    fecha_label: '',
    hora: '',
    especialidades: [],
    doctores: [],
    fechas: [],
    fechasReagendar: [],
    slots: [],
    slotsReagendar: [],
    cita_id: null,
    nueva_fecha: '',
    nueva_fecha_label: '',
    nueva_hora: '',
    dependiente_id: null,
  });

  const initialRegistro = () => ({
    cedula: '',
    email: '',
    nombre: '',
    telefono: '',
    crearUsuario: null,
  });

  const initialSession = () => ({
    type: 'anonymous',
    otpSent: false,
    emailVerified: false,
    profileFound: false,
  });

  let challengeAbortController = null;
  const actionLock = window.ActionLock || null;
  const runLocked = (copy, executor, options = {}) => {
    if (actionLock && typeof actionLock.run === 'function') {
      return actionLock.run(copy, executor, options);
    }

    return Promise.resolve().then(executor);
  };

  const state = {
    mode: 'identidad',
    step: 'espera_saludo',
    autenticado: false,
    identidad: { id:null, nombre:'', cedula:'', email:'', telefono:'' },
    session: initialSession(),
    holdToken: '',
    holdExpiresAt: null,
    registro: initialRegistro(),
    crearUsuarioPreferido: null,
    buffer: initialBuffer(),
    citasEncontradas: [],
    dependientes: [],
    retoHumano: { token: '', targetLabelEs: '', images: [], verifying: false },
  };

  const limpiarCedula = (value) => String(value || '').replace(/\s+/g, '');
  const normalizarCorreo = (value) => String(value || '').trim().toLowerCase();
  const esCedulaValida = (value) => /^\d{10}$/.test(limpiarCedula(value));
  const esTelefonoValido = (value) => /^\d{10}$/.test(value);
  const esCorreoValido = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizarCorreo(value));
  const esFechaValida = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value);
  const esEdadDependienteValida = (fecha) => {
    try {
      const birth = new Date(fecha);
      const today = new Date();
      let age = today.getFullYear() - birth.getFullYear();
      const m = today.getMonth() - birth.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        age--;
      }
      return age < 18 || age > 65;
    } catch(e) {
      return false;
    }
  };
  const MOTIVOS_NO_VALIDOS = ['no', 'ninguno', 'ninguna', 'n/a', 'na', 'sin motivo', 'omitir'];
  const normalizarMotivoConsulta = (value) => String(value || '').trim().replace(/\s+/g, ' ');
  const esMotivoConsultaValido = (value) => {
    const motivo = normalizarMotivoConsulta(value).toLowerCase();
    return motivo.length >= 3 && !MOTIVOS_NO_VALIDOS.includes(motivo);
  };
  const MENSAJE_MOTIVO_CONSULTA = 'Indica el motivo de la consulta en pocas palabras. Ejemplos: fiebre, dolor de cabeza o tos.';
  const normalizarSexo = (value) => {
    const v = (value || '').trim().toLowerCase();
    if (!v) return '';
    if (['masculino','m'].includes(v)) return 'Masculino';
    if (['femenino','f'].includes(v)) return 'Femenino';
    if (['otro','x'].includes(v)) return 'Otro';
    return null;
  };

  function scrollBottom() { log.scrollTop = log.scrollHeight; }

  function hasRegisteredSession() {
    return state.session.type === 'registered' && state.autenticado;
  }

  function hasGuestSession() {
    return state.session.type === 'guest';
  }

  function hasActiveSession() {
    return hasRegisteredSession() || hasGuestSession();
  }

  function clearVerificationState() {
    state.autenticado = false;
    state.identidad = { id:null, nombre:'', cedula:'', email:'', telefono:'' };
    state.session = initialSession();
    state.dependientes = [];
  }

  function issueHoldToken() {
    if (window.crypto?.randomUUID) {
      return window.crypto.randomUUID();
    }
    return `hold-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  function ensureHoldToken() {
    if (!state.holdToken) {
      state.holdToken = issueHoldToken();
    }
    return state.holdToken;
  }

  function buildSlotsUrl(doctorId, fecha) {
    const url = new URL(`${baseUrl}/api/doctor/${doctorId}/fecha/${fecha}/slots`, window.location.origin);
    if (state.holdToken) {
      url.searchParams.set('hold_token', state.holdToken);
    }
    return url.toString();
  }

  async function reservarHorarioTemporal(fecha, hora) {
    if (!slotHoldUrl || !state.buffer.doctor_id || !fecha || !hora) {
      return true;
    }

    addMessage('bot', 'Reservando temporalmente ese horario...');

    try {
      const res = await fetch(slotHoldUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          doctor_id: state.buffer.doctor_id,
          fecha,
          hora,
          token: ensureHoldToken(),
          paciente_id: state.identidad.id || null,
        }),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) {
        addMessage('bot', data.message || 'Ese horario ya no esta disponible. Elige otro.');
        await pedirHorarios(fecha);
        return false;
      }

      state.holdToken = data.hold_token || state.holdToken;
      state.holdExpiresAt = data.expires_at || null;

      return true;
    } catch (error) {
      console.error(error);
      addMessage('bot', 'No pudimos reservar ese horario. Intenta nuevamente.');
      await pedirHorarios(fecha);
      return false;
    }
  }

  function addMessage(role, text) {
    const wrap = document.createElement('div');
    wrap.className = 'chat-message';

    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${role === 'bot' ? 'chat-bubble--bot' : 'chat-bubble--user'}`;
    bubble.textContent = text;

    wrap.appendChild(bubble);
    log.appendChild(wrap);
    scrollBottom();
  }

  function addRetoHumano(reto) {
    const wrap = document.createElement('div');
    wrap.className = 'chat-message';

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble chat-bubble--bot';

    const title = document.createElement('div');
    title.className = 'chat-verificacion-text';
    title.textContent = `Para verificar que no eres un robot, haz clic o escribe el número (1-4) de: ${reto.targetLabelEs}.`;

    const grid = document.createElement('div');
    grid.className = 'chat-verificacion-grid';

    reto.images.forEach((image, idx) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'chat-verificacion-item';
      item.setAttribute('aria-label', `Seleccionar opción ${idx + 1}`);
      item.addEventListener('click', () => {
        procesarRespuestaRetoHumano(idx + 1);
      });

      const img = document.createElement('img');
      const resolvedUrl = /^https?:\/\//i.test(image.url)
        ? image.url
        : `${baseUrl}${String(image.url || '').startsWith('/') ? '' : '/'}${image.url}`;
      img.src = resolvedUrl;
      img.alt = `Opcion ${idx + 1}`;
      img.loading = 'lazy';
      img.decoding = 'async';

      const numero = document.createElement('div');
      numero.className = 'chat-verificacion-numero';
      numero.textContent = String(idx + 1);

      item.appendChild(img);
      item.appendChild(numero);
      grid.appendChild(item);
    });

    bubble.appendChild(title);
    bubble.appendChild(grid);
    wrap.appendChild(bubble);
    log.appendChild(wrap);
    scrollBottom();
  }

  function addNumberedVerticalList(title, options) {
    const safeOptions = Array.isArray(options) ? options : [];
    if (title) {
      addMessage('bot', title);
    }
    safeOptions.forEach((option, idx) => {
      addMessage('bot', `${idx + 1}) ${option}`);
    });
  }

  async function obtenerRetoHumano() {
    if (challengeAbortController) {
      challengeAbortController.abort();
    }
    challengeAbortController = new AbortController();

    const res = await fetch(`${baseUrl}/captcha/challenge`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      signal: challengeAbortController.signal,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.message || 'No se pudo generar el captcha.');
    }

    const images = Array.isArray(data.images) ? data.images : [];
    return {
      token: String(data.token || ''),
      targetLabelEs: String(data.target_label_es || ''),
      images: images.map((img) => ({
        position: Number(img.position),
        url: String(img.url || ''),
      })),
    };
  }

  async function verificarRetoHumano(token, position) {
    const res = await fetch(`${baseUrl}/captcha/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        token: token,
        position: position,
      }),
    });

    const data = await res.json().catch(() => ({}));
    return {
      ok: res.ok && data.ok !== false && data.verified === true,
      message: data.message || '',
    };
  }

  async function mostrarRetoHumano() {
    try {
      const reto = await obtenerRetoHumano();
      if (!reto.token || reto.images.length !== 4) {
        throw new Error('No se pudo preparar el captcha.');
      }
      state.retoHumano = reto;
      addRetoHumano(state.retoHumano);
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error(error);
        addMessage('bot', error.message || 'La verificación no está disponible temporalmente.');
      }
    }
  }

  async function procesarRespuestaRetoHumano(respuesta) {
    const seleccion = parseInt(respuesta, 10);

    if (!state.retoHumano.token || !Array.isArray(state.retoHumano.images) || state.retoHumano.images.length !== 4) {
      addMessage('bot', 'Generando un nuevo reto...');
      await mostrarRetoHumano();
      return;
    }

    if (!seleccion || seleccion < 1 || seleccion > state.retoHumano.images.length) {
      addMessage('bot', 'Escribe el número correspondiente al animal solicitado (1-4) o haz clic en la imagen.');
      return;
    }

    const selected = state.retoHumano.images[seleccion - 1];
    if (!selected || selected.position === undefined || selected.position === null) {
      addMessage('bot', 'No se pudo leer la opción seleccionada. Intentaremos de nuevo.');
      await mostrarRetoHumano();
      return;
    }

    if (state.retoHumano.verifying) {
      return;
    }

    state.retoHumano.verifying = true;

    try {
      addMessage('bot', 'Validando selección...');
      const resultado = await verificarRetoHumano(state.retoHumano.token, selected.position);
      if (!resultado.ok) {
        addMessage('bot', resultado.message || 'Selección incorrecta. Intenta nuevamente.');
        await mostrarRetoHumano();
        return;
      }

      state.step = 'cedula';
      addMessage('bot', 'Validación completada.\n\nIngresa tu número de cédula (10 dígitos, solo números):');
    } finally {
      state.retoHumano.verifying = false;
    }
  }

  function addButtons(buttons) {
    const wrap = document.createElement('div');
    wrap.className = 'chat-options';
    buttons.forEach(btn => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = btn.label;
      b.dataset.action = btn.action;
      b.dataset.payload = JSON.stringify(btn.payload || {});
      b.className = 'chat-option-btn';
      b.addEventListener('click', () => {
        if (wrap.dataset.locked === '1') {
          return;
        }
        wrap.dataset.locked = '1';
        wrap.querySelectorAll('button').forEach(x => {
          x.disabled = true;
          x.style.opacity = '0.55';
          x.style.cursor = 'default';
        });
        handleButton(btn);
      });
      wrap.appendChild(b);
    });
    log.appendChild(wrap);
    scrollBottom();
  }

  function handleButton(btn) {
    const a = btn.action;
    addMessage('user', btn.label);

    if (a === 'menu') return showMainMenu();
    if (a === 'agendar') return startAgendar();
    if (a === 'cancelar') return startCancelar();
    if (a === 'reagendar') return startReagendar();
    if (a === 'info_citas') return startInfoCitas();
    if (a === 'actualizar_perfil') return startActualizarPerfil();
    if (a === 'registro_iniciar') return startRegistro();
    if (a === 'finalizar') return finalizarChat();
    if (a === 'reenviar_codigo') return reenviarCodigo();
    
    // Family / patient selection button handlers
    if (a === 'agendar_para_titular') {
      state.buffer.dependiente_id = null;
      state.step = 'motivo';
      return addMessage('bot', MENSAJE_MOTIVO_CONSULTA);
    }
    if (a === 'agendar_para_dependiente') {
      state.buffer.dependiente_id = btn.payload.dependiente_id;
      state.step = 'motivo';
      return addMessage('bot', `Agendaremos para: ${btn.payload.nombre}.\n\n${MENSAJE_MOTIVO_CONSULTA}`);
    }
    
    if (a === 'info_citas_filter') {
      state.buffer.dependiente_id = btn.payload.dependiente_id;
      state.step = 'elige_estado';
      return mostrarEstadosCitas();
    }
    
    if (a === 'perfil_ver_titular') {
      return cargarPerfilDesdeServidor(null);
    }
    if (a === 'perfil_ver_dependiente') {
      return cargarPerfilDesdeServidor(btn.payload.dependiente_id);
    }

    if (a === 'estado_citas') return mostrarCitasPorEstado(btn.payload.estado, btn.payload.titulo);
    if (a === 'volver_estados') return mostrarEstadosCitas();
    if (a === 'cancelar_cita_id') return seleccionarCitaCancelar(btn.payload.cita_id);
    if (a === 'reagendar_cita_id') return prepararReagendarDesdeId(btn.payload.cita_id);
  }

  function resetBufferConIdentidad() {
    state.buffer = initialBuffer();
    state.buffer.nombre = state.identidad.nombre;
    state.buffer.cedula = state.identidad.cedula;
    state.buffer.dni = state.identidad.cedula;
    state.buffer.email = state.identidad.email;
    state.buffer.telefono = state.identidad.telefono;
  }

  function resetRegistro() {
    state.registro = initialRegistro();
    state.crearUsuarioPreferido = null;
  }

  function startIdentidad(reiniciar = false) {
    if (reiniciar) {
      clearVerificationState();
      state.holdToken = '';
      state.holdExpiresAt = null;
      state.citasEncontradas = [];
      state.buffer = initialBuffer();
      resetRegistro();
    }
    state.mode = 'identidad';
    state.step = 'espera_saludo';
    addMessage('bot','Hola, soy el asistente virtual de la clínica.\n\nEscribe "hola" para comenzar.');
  }

  async function resetChat() {
    return runLocked({
      title: 'Cerrando conversación...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      log.innerHTML = '';
      state.retoHumano = { token: '', targetLabelEs: '', images: [], verifying: false };

      // Explicitly call finalized on backend to purge session data
      try {
        await fetch(`${baseUrl}/chatbot/finalizar`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
          }
        });
      } catch(e) {}

      startIdentidad(true);
    });
  }

  function mostrarOpcionesRegistro(mensaje) {
    state.mode = 'registro';
    state.step = 'registro_opción';
    addMessage('bot', mensaje || 'No encontramos pacientes registrados con esta cédula.');
    addMessage('bot', 'Puedes registrarte para continuar o finalizar.');
    addButtons([
      { label:'Registrarse', action:'registro_iniciar' },
      { label:'Finalizar', action:'finalizar' },
    ]);
  }

  function startRegistro() {
    state.mode = 'registro';
    state.step = 'registro_cedula';
    resetRegistro();
    addMessage('bot','Para continuar, registraremos tus datos.');
    addMessage('bot','Ingresa tu número de cédula (10 dígitos):');
  }

  async function registrarUsuarioDesdeRegistro() {
    return runLocked({
      title: 'Registrando usuario...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      addMessage('bot', 'Registrando tu usuario...');

      try {
        const res = await fetch(`${baseUrl}/chatbot/registrar-usuario`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            nombre: state.registro.nombre,
            cedula: state.registro.cedula,
            email: state.registro.email,
            telefono: state.registro.telefono,
          }),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) {
          addMessage('bot', data.message || 'No pudimos registrar tu usuario en este momento.');
          return;
        }

        state.identidad = {
          id: data.paciente?.id || null,
          nombre: data.paciente?.nombre || state.registro.nombre,
          cedula: state.registro.cedula,
          email: data.paciente?.email || state.registro.email,
          telefono: data.paciente?.telefono || state.registro.telefono,
        };
        state.autenticado = true;
        state.session.type = 'registered';
        state.session.emailVerified = true;
        state.dependientes = [];
        resetBufferConIdentidad();

        let mensaje = 'Tu cuenta de paciente ha sido registrada correctamente. Tu cédula es tu contraseña temporal.';
        addMessage('bot', mensaje);
        startAgendar();
      } catch (error) {
        console.error(error);
        addMessage('bot', 'No pudimos registrar tu usuario en este momento.');
      }
    });
  }

  async function procesarCedula(cedula) {
    const cedulaNormalizada = limpiarCedula(cedula);
    if (!esCedulaValida(cedulaNormalizada)) {
      return addMessage('bot','El número de cédula debe tener exactamente 10 dígitos.');
    }

    state.identidad.cedula = cedulaNormalizada;
    state.buffer.cedula = cedulaNormalizada;
    addMessage('bot','Validando tu cédula, un momento...');

    try {
      const res = await fetch(`${baseUrl}/chatbot/verificar-paciente`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ cedula: cedulaNormalizada }),
      });
      const data = await res.json();

      if (!res.ok || data.ok === false) {
        if (res.status === 404 || data.existe === false) {
          return mostrarOpcionesRegistro(data.message);
        }
        return addMessage('bot', data.message || 'No pudimos validar esa cédula. Intenta nuevamente.');
      }

      state.step = 'email';
      addMessage('bot', 'Paciente identificado.\n\nEscribe tu correo electrónico para continuar:');
    } catch (error) {
      console.error(error);
      addMessage('bot','Hubo un problema al validar tu cédula. Intenta nuevamente.');
    }
  }

  async function procesarCorreo(email) {
    const emailNormalizado = normalizarCorreo(email);
    if (!esCorreoValido(emailNormalizado)) {
      return addMessage('bot','El correo debe incluir @ y no tener espacios.');
    }

    return runLocked({
      title: 'Enviando código de verificación...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      addMessage('bot','Enviando tu codigo de verificacion...');

      try {
        state.identidad.email = emailNormalizado;
        state.buffer.email = emailNormalizado;

        const envio = await enviarCodigoVerificacion(emailNormalizado);

        if (!envio.ok) {
          state.session.otpSent = false;
          state.step = 'email';

          if (envio.status === 404 || envio.data?.existe === false) {
            return mostrarOpcionesRegistro(envio.data?.message || 'No encontramos pacientes registrados con estos datos.');
          }

          if (envio.status === 429 && envio.data?.retry_after) {
            return addMessage('bot', envio.data.message || `Has solicitado varios codigos. Intenta nuevamente en ${envio.data.retry_after} segundos.`);
          }

          return addMessage('bot', envio.data?.message || 'No pudimos enviar el codigo de verificacion.');
        }

        state.session.otpSent = true;
        state.step = 'codigo';
        addMessage('bot', 'Te enviamos un codigo de 6 digitos a tu correo. Ingresa el codigo para continuar:');
      } catch (error) {
        console.error(error);
        addMessage('bot','No fue posible enviar el codigo en este momento. Intenta nuevamente.');
      }
    });
  }

  async function enviarCodigoVerificacion(email) {
    try {
      const res = await fetch(`${baseUrl}/chatbot/enviar-codigo`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          cedula: state.identidad.cedula,
          email,
        }),
      });

      const data = await res.json().catch(() => ({}));
      return {
        ok: res.ok && data.ok !== false,
        status: res.status,
        data,
      };
    } catch (error) {
      console.error(error);
      return {
        ok: false,
        status: 0,
        data: {
          message: 'No pudimos enviar el codigo de verificacion. Intenta mas tarde.',
        },
      };
    }
  }

  async function reenviarCodigo() {
    if (!state.identidad.cedula || !state.identidad.email) {
      state.step = 'cedula';
      return addMessage('bot','Necesito tu cédula y correo para enviarte un nuevo código.');
    }

    return runLocked({
      title: 'Enviando código de verificación...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      addMessage('bot','Enviando un nuevo código...');
      const envio = await enviarCodigoVerificacion(state.identidad.email);
      if (!envio.ok) {
        if (envio.status === 429 && envio.data?.retry_after) {
          addMessage('bot', envio.data.message || `Has solicitado varios codigos. Intenta nuevamente en ${envio.data.retry_after} segundos.`);
          return addButtons([{ label:'Enviar nuevo codigo', action:'reenviar_codigo' }]);
        }

        addMessage('bot', envio.data?.message || 'No pudimos enviar el codigo de verificacion.');
        return addButtons([{ label:'Enviar nuevo codigo', action:'reenviar_codigo' }]);
      }

      state.session.otpSent = true;
      state.step = 'codigo';
      addMessage('bot','Te enviamos un nuevo codigo de 6 digitos a tu correo. Ingresa el codigo para continuar:');
    });
  }

  async function procesarCodigo(codigo) {
    if (!/^\d{6}$/.test(codigo)) {
      return addMessage('bot','El código debe tener 6 dígitos.');
    }

    return runLocked({
      title: 'Verificando código...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      addMessage('bot','Validando código...');

      try {
        const res = await fetch(`${baseUrl}/chatbot/verificar-codigo`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            cedula: state.identidad.cedula,
            email: state.identidad.email,
            codigo,
          }),
        });

        const data = await res.json();
        if (!res.ok || data.ok === false) {
          if (data.error === 'otp_invalidated' || data.error === 'codigo_vencido' || data.allow_resend) {
            addMessage('bot', data.message || 'El código fue invalidado. Solicita uno nuevo.');
            return addButtons([{ label:'Enviar nuevo código', action:'reenviar_codigo' }]);
          }
          return addMessage('bot', data.message || 'Código incorrecto.');
        }

        if (data.paciente) {
          state.identidad.id = data.paciente.id || state.identidad.id;
          state.identidad.nombre = data.paciente.nombre || state.identidad.nombre;
          state.identidad.telefono = data.paciente.telefono || state.identidad.telefono;
          state.identidad.email = data.paciente.email || state.identidad.email;
        }

        state.dependientes = Array.isArray(data.dependientes) ? data.dependientes : [];
        state.session.type = 'registered';
        state.autenticado = true;
        resetBufferConIdentidad();
        addMessage('bot', `Código verificado. Bienvenido${state.identidad.nombre ? ', ' + state.identidad.nombre : ''}.`);
        showMainMenu(true);
      } catch (error) {
        console.error(error);
        addMessage('bot','No pudimos validar el código en este momento.');
      }
    });
  }

  function showMainMenu(esBienvenida = false) {
    if (!hasRegisteredSession()) {
      return startIdentidad(true);
    }

    state.mode = 'menu';
    state.step = null;
    resetBufferConIdentidad();
    state.citasEncontradas = [];

    const saludo = esBienvenida ? 'Identidad confirmada.' : 'Menú principal.';
    const nombre = state.identidad.nombre ? state.identidad.nombre : '';

    addMessage('bot', `${saludo}${nombre ? ' ' + nombre : ''}\n\nSelecciona la gestión que deseas realizar:`);
    addButtons([
      { label:'Agendar cita', action:'agendar' },
      { label:'Cancelar cita', action:'cancelar' },
      { label:'Reagendar cita', action:'reagendar' },
      { label:'Información de mis citas', action:'info_citas' },
      { label:'Actualizar mis datos', action:'actualizar_perfil' },
      { label:'Finalizar', action:'finalizar' },
    ]);
  }

  async function finalizarChat() {
    return runLocked({
      title: 'Cerrando conversación...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      state.mode = 'finalizado';
      state.step = null;
      state.citasEncontradas = [];
      addMessage('bot','Cerrando la sesión en el servidor...');
      try {
        await fetch(`${baseUrl}/chatbot/finalizar`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
          }
        });
      } catch(e) {}
      clearVerificationState();
      addMessage('bot','Se cerró esta gestión. Escribe "menu" para continuar sin reiniciar tu identidad o "reiniciar" para iniciar desde cero.');
    });
  }

  function startAgendar() {
    if (!hasActiveSession()) return startIdentidad(true);
    state.mode = 'agendar';
    state.buffer = initialBuffer();
    state.buffer.dependiente_id = null;

    addMessage('bot','Iniciaremos el agendamiento de tu cita.');

    if (state.dependientes && state.dependientes.length > 0) {
      state.step = 'seleccionar_paciente';
      addMessage('bot', '¿Para quién deseas agendar la cita?');
      const buttons = [
        { label: `Para mí (${state.identidad.nombre})`, action: 'agendar_para_titular' }
      ];
      state.dependientes.forEach(dep => {
        buttons.push({
          label: `Para ${dep.nombre} (${dep.parentesco})`,
          action: 'agendar_para_dependiente',
          payload: { dependiente_id: dep.id, nombre: dep.nombre }
        });
      });
      addButtons(buttons);
    } else {
      state.step = 'motivo';
      addMessage('bot', MENSAJE_MOTIVO_CONSULTA);
    }
  }

  async function pedirEspecialidades() {
    try {
      const res = await fetch(`${baseUrl}/chatbot/especialidades`);
      const data = await res.json();
      state.buffer.especialidades = data;

      addNumberedVerticalList(
        'Especialidades disponibles:',
        data.map((e) => e.nombre)
      );
      state.step = 'especialidad';
      addMessage('bot','Escribe el número de la especialidad que necesitas:');
    } catch (error) {
      console.error(error);
      addMessage('bot','No pude cargar las especialidades. Vuelve al menú.');
      showMainMenu();
    }
  }

  async function pedirDoctores() {
    addMessage('bot','Consultando médicos disponibles...');
    try {
      const res = await fetch(`${baseUrl}/chatbot/especialidades/${state.buffer.especialidad_id}/doctores`);
      const data = await res.json();

      if (!data.ok || !data.doctores.length) {
        addMessage('bot', data.message || 'No hay médicos activos para esta especialidad.');
        return showMainMenu();
      }

      state.buffer.doctores = data.doctores;
      addNumberedVerticalList(
        `Médicos disponibles (${data.especialidad}):`,
        data.doctores.map((d) => {
          const tarifa = d.precio_format ? ` - ${d.precio_format}` : '';
          return `${d.nombre}${tarifa}`;
        })
      );
      state.step = 'doctor';
      addMessage('bot','Escribe el número del médico que prefieres:');
    } catch (error) {
      console.error(error);
      addMessage('bot','Error cargando los médicos.');
      showMainMenu();
    }
  }

  async function pedirFechas(doctor, contexto = 'agendar') {
    addMessage('bot',`Consultando disponibilidad de ${doctor.nombre}...`);
    try {
      const res = await fetch(`${baseUrl}/chatbot/doctores/${doctor.id}/fechas`);
      const data = await res.json();

      if (!data.ok || !data.fechas.length) {
        addMessage('bot', data.message || 'No hay fechas disponibles con este médico.');
        if (contexto === 'agendar') {
          state.step = 'doctor';
          return addMessage('bot','Elige otro médico escribiendo su número:');
        }
        state.step = 'reagendar_cita';
        return addMessage('bot','Elige otra cita o intenta nuevamente en otro momento.');
      }

      if (contexto === 'agendar') {
        state.buffer.fechas = data.fechas;
      } else {
        state.buffer.fechasReagendar = data.fechas;
      }
      state.buffer.doctor_nombre = doctor.nombre;

      addNumberedVerticalList(
        `Fechas disponibles con ${doctor.nombre}:`,
        data.fechas.map((f) => f.label)
      );
      if (contexto === 'agendar') {
        state.step = 'fecha';
        addMessage('bot','Escribe el número de la fecha:');
      } else {
        state.step = 'reagendar_fecha';
        addMessage('bot','Elige la nueva fecha escribiendo su número:');
      }
    } catch (error) {
      console.error(error);
      addMessage('bot','No pude cargar las fechas.');
      showMainMenu();
    }
  }

  async function pedirHorarios(fecha, contexto = 'agendar') {
    addMessage('bot','Consultando horarios libres...');
    try {
      const res = await fetch(buildSlotsUrl(state.buffer.doctor_id, fecha));
      const data = await res.json();
      const slots = data.slots || [];
      const libres = slots.filter(slot => slot.estado === 'libre');

      if (!libres.length) {
        addMessage('bot','Ese día ya no tiene cupos. Escoge otra fecha.');
        state.step = contexto === 'agendar' ? 'fecha' : 'reagendar_fecha';
        return;
      }

      if (contexto === 'agendar') {
        state.buffer.slots = libres;
      } else {
        state.buffer.slotsReagendar = libres;
      }

      addNumberedVerticalList(
        `Horarios disponibles (${fecha}):`,
        libres.map((slot) => slot.hora)
      );

      if (contexto === 'agendar') {
        state.step = 'hora';
        addMessage('bot','Escribe el número del horario:');
      } else {
        state.step = 'reagendar_hora';
        addMessage('bot','Elige la nueva hora escribiendo su número:');
      }
    } catch (error) {
      console.error(error);
      addMessage('bot','No pude obtener los horarios.');
      showMainMenu();
    }
  }

  async function enviarAgendar() {
    const payload = {
      especialidad_id: state.buffer.especialidad_id,
      doctor_id:       state.buffer.doctor_id,
      fecha:           state.buffer.fecha,
      hora:            state.buffer.hora,
      hold_token:      state.holdToken || null,
      motivo_consulta: state.buffer.motivo,
      dependiente_id:  state.buffer.dependiente_id || null,
    };

    return runLocked({
      title: 'Registrando cita...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      addMessage('bot','Registrando tu cita...');

      try {
        const res = await fetch(`${baseUrl}/chatbot/agendar`, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':csrf,
            'Accept':'application/json',
          },
          body:JSON.stringify(payload)
        });

        const data = await res.json();

        if (!res.ok || !data.ok) {
          addMessage('bot', data.message || 'No se pudo agendar la cita.');
          return showMainMenu();
        }

        let msg = `Cita agendada con ${state.buffer.doctor_nombre} el ${state.buffer.fecha} a las ${state.buffer.hora} (Paciente: ${data.cita?.paciente || state.identidad.nombre}).`;
        if (state.buffer.doctor_tarifa) {
          msg += `\nTarifa: ${state.buffer.doctor_tarifa}`;
        }
        if (state.identidad.email) {
          msg += `\nHemos enviado la confirmación al correo ${state.identidad.email}.`;
        }

        addMessage('bot', msg);
        state.holdExpiresAt = null;
        addButtons([
          { label:'Volver al menú', action:'menu' },
          { label:'Finalizar', action:'finalizar' },
        ]);
        state.mode = 'menu';
        state.step = null;

      } catch (error) {
        console.error(error);
        addMessage('bot','Error interno al agendar.');
        showMainMenu();
      }
    });
  }

  function startCancelar() {
    if (!hasRegisteredSession()) return startIdentidad(true);
    state.mode = 'cancelar';
    state.step = 'cancelar_cita';
    addMessage('bot','Estas son las citas vigentes asociadas a tu cuenta. Selecciona cuál deseas cancelar:');
    buscarCitasConCredenciales().then(listar => {
      if (!listar) {
        addButtons([{label:'Volver al menú', action:'menu'}]);
        return;
      }
      state.citasEncontradas = listar;
      mostrarListadoCitas(listar, 'cancelar');
    });
  }

  function startReagendar() {
    if (!hasRegisteredSession()) return startIdentidad(true);
    state.mode = 'reagendar';
    state.step = 'reagendar_cita';
    addMessage('bot','Selecciona la cita que deseas reprogramar:');
    buscarCitasConCredenciales().then(listar => {
      if (!listar) {
        addButtons([{label:'Volver al menú', action:'menu'}]);
        return;
      }
      state.citasEncontradas = listar;
      mostrarListadoCitas(listar, 'reagendar');
    });
  }

  async function buscarCitasConCredenciales(opciones = {}) {
    const payload = {
      estado: opciones.estado || 'all',
      incluir_todas: opciones.incluir_todas || false,
      dependiente_id: 'all' // Query all so titular sees their own and their dependents' appointments
    };

    try {
      const res = await fetch(`${baseUrl}/chatbot/buscar-citas`, {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-TOKEN':csrf,
          'Accept':'application/json',
        },
        body:JSON.stringify(payload)
      });

      const data = await res.json();

      if (!res.ok || !data.ok || !data.citas.length) {
        addMessage('bot', data.message || 'No encontramos citas.');
        return null;
      }

      return data.citas;
    } catch (error) {
      console.error(error);
      addMessage('bot','Error buscando tus citas.');
      showMainMenu();
      return null;
    }
  }

  function mostrarListadoCitas(citas, contexto) {
    let msg = 'Citas encontradas:\n\n';
    citas.forEach((c,i)=> {
      msg += `${i+1}) ${c.fecha} ${c.hora} - ${c.especialidad} (${c.paciente_real}) | Estado: ${c.estado}\n`;
    });
    addMessage('bot', msg.trim());

    if (contexto === 'cancelar') {
      addMessage('bot','Selecciona la cita a cancelar (escribe el número o usa un botón):');
      addButtons(citas.map((c,i)=>({
        label:`${i+1}) ${c.fecha} ${c.hora} (${c.paciente_real})`,
        action:'cancelar_cita_id',
        payload:{ cita_id:c.id }
      })));
    }

    if (contexto === 'reagendar') {
      addMessage('bot','Selecciona la cita a reprogramar (escribe el número o usa un botón):');
      addButtons(citas.map((c,i)=>({
        label:`${i+1}) ${c.fecha} ${c.hora} (${c.paciente_real})`,
        action:'reagendar_cita_id',
        payload:{ cita_id:c.id }
      })));
    }
  }

  function seleccionarCitaCancelar(id) {
    state.buffer.cita_id = id;
    state.step = 'confirmar_cancelacion';
    addMessage('bot','¿Confirma que deseas cancelar esta cita (si/no)?');
  }

  function seleccionarCitaCancelarPorIndice(idx) {
    const cita = state.citasEncontradas[idx];
    if (!cita) {
      return addMessage('bot','Número inválido, intenta nuevamente.');
    }
    seleccionarCitaCancelar(cita.id);
  }

  async function ejecutarCancelacion() {
    return runLocked({
      title: 'Cancelando cita...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      try {
        const res = await fetch(`${baseUrl}/chatbot/cancelar`, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':csrf,
            'Accept':'application/json',
          },
          body:JSON.stringify({
            cita_id: state.buffer.cita_id
          })
        });

        const data = await res.json();
        addMessage('bot', data.message || 'Cita cancelada.');
        addButtons([
          {label:'Volver al menú', action:'menu'},
          {label:'Finalizar', action:'finalizar'},
        ]);
      } catch (error) {
        console.error(error);
        addMessage('bot','Error cancelando la cita.');
        showMainMenu();
      }
    });
  }

  function prepararReagendarDesdeId(id) {
    const cita = state.citasEncontradas.find(c => c.id === id);
    if (!cita) {
      return addMessage('bot','No pude identificar la cita seleccionada.');
    }
    prepararReagendarCita(cita);
  }

  function prepararReagendarPorIndice(idx) {
    const cita = state.citasEncontradas[idx];
    if (!cita) {
      return addMessage('bot','Número inválido, intenta nuevamente.');
    }
    prepararReagendarCita(cita);
  }

  function prepararReagendarCita(cita) {
    if (!cita.doctor_id) {
      addMessage('bot','Esta cita no puede reprogramarse porque no tiene un doctor asignado.');
      return showMainMenu();
    }
    state.buffer.cita_id = cita.id;
    state.buffer.doctor_id = cita.doctor_id;
    state.buffer.doctor_nombre = cita.doctor || 'tu médico';
    state.buffer.especialidad_id = cita.especialidad_id || null;
    state.buffer.nueva_fecha = '';
    state.buffer.nueva_hora = '';
    addMessage('bot',`Reprogramaremos tu cita con ${state.buffer.doctor_nombre}.`);
    pedirFechas({ id: state.buffer.doctor_id, nombre: state.buffer.doctor_nombre }, 'reagendar');
  }

  async function ejecutarReagendar() {
    return runLocked({
      title: 'Reagendando cita...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      try {
        const res = await fetch(`${baseUrl}/chatbot/reagendar`, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':csrf,
            'Accept':'application/json',
          },
          body:JSON.stringify({
            cita_id:state.buffer.cita_id,
            fecha:state.buffer.nueva_fecha,
            hora:state.buffer.nueva_hora,
          })
        });

        const data = await res.json();
        addMessage('bot', data.message || 'Cita reprogramada.');
        addButtons([
          {label:'Volver al menú', action:'menu'},
          {label:'Finalizar', action:'finalizar'},
        ]);

      } catch (error) {
        console.error(error);
        addMessage('bot','Error reprogramando la cita.');
        showMainMenu();
      }
    });
  }

  function startInfoCitas() {
    if (!hasRegisteredSession()) return startIdentidad(true);
    state.mode = 'info_citas';
    
    if (state.dependientes && state.dependientes.length > 0) {
      state.step = 'info_seleccionar_paciente';
      addMessage('bot', '¿De quién deseas consultar las citas?');
      const buttons = [
        { label: 'Todas las citas', action: 'info_citas_filter', payload: { dependiente_id: 'all' } },
        { label: 'Solo mis citas', action: 'info_citas_filter', payload: { dependiente_id: 'titular' } }
      ];
      state.dependientes.forEach(dep => {
        buttons.push({
          label: `Citas de ${dep.nombre}`,
          action: 'info_citas_filter',
          payload: { dependiente_id: dep.id }
        });
      });
      addButtons(buttons);
    } else {
      state.buffer.dependiente_id = 'all';
      state.step = 'elige_estado';
      mostrarEstadosCitas();
    }
  }

  function mostrarEstadosCitas() {
    addMessage('bot','Elige el estado de las citas que deseas consultar:');
    addButtons([
      { label:'Pendientes', action:'estado_citas', payload:{ estado:'pendiente', titulo:'Citas pendientes' } },
      { label:'Confirmadas', action:'estado_citas', payload:{ estado:'confirmada', titulo:'Citas confirmadas' } },
      { label:'Canceladas', action:'estado_citas', payload:{ estado:'cancelada', titulo:'Citas canceladas' } },
      { label:'Realizadas', action:'estado_citas', payload:{ estado:'realizada', titulo:'Citas realizadas' } },
    ]);
  }

  async function mostrarCitasPorEstado(estado, titulo) {
    const citas = await buscarCitasConCredenciales({ estado, incluir_todas:true });
    if (!citas) {
      const textoEstado = titulo ? titulo.toLowerCase() : 'en este estado';
      addMessage('bot', `No se encontraron citas ${textoEstado}. Puedes revisar otro estado o volver al menú.`);
      addButtons([
        { label:'Volver a estados', action:'volver_estados' },
        { label:'Volver al menú', action:'menu' },
      ]);
      return;
    }

    let msg = `${titulo}:\n\n`;
    citas.forEach((c,i)=> {
      msg += `${i+1}) ${c.fecha} ${c.hora} - ${c.especialidad} | Doctor: ${c.doctor || 'No asignado'} | Paciente: ${c.paciente_real} | Estado: ${c.estado}\n`;
    });
    addMessage('bot', msg.trim());
    addButtons([
      { label:'Volver a estados', action:'volver_estados' },
      { label:'Volver al menú', action:'menu' },
    ]);
  }

  function startActualizarPerfil() {
    if (!hasRegisteredSession()) return startIdentidad(true);
    state.mode = 'actualizar_perfil';
    state.buffer = initialBuffer();
    state.buffer.dependiente_id = null;

    if (state.dependientes && state.dependientes.length > 0) {
      state.step = 'perfil_seleccionar_paciente';
      addMessage('bot', '¿Los datos de quién deseas actualizar?');
      const buttons = [
        { label: 'Mis datos personales', action: 'perfil_ver_titular' }
      ];
      state.dependientes.forEach(dep => {
        buttons.push({
          label: `Datos de ${dep.nombre} (${dep.parentesco})`,
          action: 'perfil_ver_dependiente',
          payload: { dependiente_id: dep.id, nombre: dep.nombre }
        });
      });
      addButtons(buttons);
    } else {
      cargarPerfilDesdeServidor(null);
    }
  }

  async function cargarPerfilDesdeServidor(dependienteId = null) {
    state.buffer.dependiente_id = dependienteId;
    addMessage('bot','Obteniendo tus datos de perfil...');

    try {
      const res = await fetch(`${baseUrl}/chatbot/perfil`, {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-TOKEN':csrf,
          'Accept':'application/json',
        },
        body:JSON.stringify({
          cedula: state.identidad.cedula,
          email: state.identidad.email,
        })
      });

      const data = await res.json();

      if (!res.ok || !data.ok) {
        addMessage('bot', data.message || 'No pudimos obtener tu perfil.');
        return showMainMenu();
      }

      if (dependienteId) {
        const dep = (data.dependientes || []).find(d => d.id === dependienteId);
        if (!dep) {
          addMessage('bot', 'No se encontró el dependiente seleccionado.');
          return showMainMenu();
        }
        state.buffer.nombre = dep.nombre;
        state.buffer.dni = dep.dni;
        state.buffer.fecha_nacimiento = dep.fecha_nacimiento;
        state.buffer.sexo = dep.sexo || '';
        state.buffer.parentesco = dep.parentesco;
        state.buffer.telefono_emergencia = dep.telefono_emergencia || '';
        state.buffer.notes = dep.notas || '';

        addMessage('bot', `Datos de ${dep.nombre} (${dep.parentesco}):\n\nNombre: ${state.buffer.nombre}\nCédula: ${state.buffer.dni}\nFecha de nacimiento: ${state.buffer.fecha_nacimiento}\nSexo: ${state.buffer.sexo || 'Sin especificar'}\nTeléfono emergencia: ${state.buffer.telefono_emergencia || 'No registrado'}\nNotas: ${state.buffer.notes || 'Ninguna'}`);
        addMessage('bot','Responde con el nuevo valor o escribe "igual" para dejarlo como está.');
        state.step = 'perfil_dep_nombre';
        addMessage('bot',`Nombre actual: ${state.buffer.nombre}.`);
      } else {
        const perfil = data.perfil;
        state.buffer.nombre = perfil.nombre || state.identidad.nombre;
        state.buffer.email = perfil.email || state.identidad.email;
        state.buffer.telefono = perfil.telefono || state.identidad.telefono || '';
        state.buffer.cedula = state.identidad.cedula;
        state.buffer.dni = perfil.dni || state.identidad.cedula;
        state.buffer.direccion = perfil.direccion || '';
        state.buffer.fecha_nacimiento = perfil.fecha_nacimiento || '';
        state.buffer.sexo = perfil.sexo || '';

        addMessage('bot', `Tus datos registrados:\n\nNombre: ${state.buffer.nombre}\nCorreo: ${state.buffer.email} (No editable)\nTeléfono: ${state.buffer.telefono}\nCédula: ${state.buffer.dni} (No editable)\nDirección: ${state.buffer.direccion || 'Sin dirección'}\nFecha de nacimiento: ${state.buffer.fecha_nacimiento || 'No registrada'}\nSexo: ${state.buffer.sexo || 'Sin especificar'}`);
        addMessage('bot','Responde con el nuevo valor o escribe "igual" para dejarlo como está.');
        state.step = 'perfil_nombre';
        addMessage('bot',`Nombre actual: ${state.buffer.nombre}.`);
      }
    } catch (error) {
      console.error(error);
      addMessage('bot','No pudimos cargar tu perfil ahora mismo.');
      showMainMenu();
    }
  }

  function resumenPerfil() {
    let msg = 'Así quedarán tus datos:\n\n';
    if (state.buffer.dependiente_id) {
      msg += `Nombre: ${state.buffer.nombre}\n`;
      msg += `Cédula: ${state.buffer.dni}\n`;
      msg += `Fecha de nacimiento: ${state.buffer.fecha_nacimiento}\n`;
      msg += `Sexo: ${state.buffer.sexo || 'Sin especificar'}\n`;
      msg += `Parentesco: ${state.buffer.parentesco}\n`;
      msg += `Teléfono emergencia: ${state.buffer.telefono_emergencia || 'Sin teléfono'}\n`;
      msg += `Notas: ${state.buffer.notes || 'Ninguna'}\n`;
    } else {
      msg += `Nombre: ${state.buffer.nombre}\n`;
      msg += `Teléfono: ${state.buffer.telefono || 'Sin teléfono'}\n`;
      msg += `Dirección: ${state.buffer.direccion || 'Sin dirección'}\n`;
      msg += `Fecha de nacimiento: ${state.buffer.fecha_nacimiento}\n`;
      msg += `Sexo: ${state.buffer.sexo || 'Sin especificar'}\n`;
    }
    addMessage('bot', msg.trim());
    state.step = 'perfil_confirmar';
    addMessage('bot','¿Confirmas guardar estos cambios (si/no)?');
  }

  async function enviarActualizacionPerfil() {
    const payload = {
      nombre: state.buffer.nombre,
      telefono: state.buffer.telefono || null,
      direccion: state.buffer.direccion || null,
      fecha_nacimiento: state.buffer.fecha_nacimiento,
      sexo: state.buffer.sexo || null,
    };

    if (state.buffer.dependiente_id) {
      payload.dependiente_id = state.buffer.dependiente_id;
      payload.dni = state.buffer.dni;
      payload.parentesco = state.buffer.parentesco;
      payload.telefono_emergencia = state.buffer.telefono_emergencia || null;
      payload.notas = state.buffer.notes || null;
    }

    return runLocked({
      title: 'Actualizando perfil...',
      description: 'Por favor, espera. No cierres esta página.',
      mode: 'operation',
    }, async () => {
      addMessage('bot','Guardando tus datos...');

      try {
        const res = await fetch(`${baseUrl}/chatbot/perfil/actualizar`, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':csrf,
            'Accept':'application/json',
          },
          body:JSON.stringify(payload)
        });

        const data = await res.json();

        if (!res.ok || data.ok === false) {
          addMessage('bot', data.message || 'No pudimos actualizar los datos.');
          return showMainMenu();
        }

        if (!state.buffer.dependiente_id) {
          state.identidad.nombre = data.perfil.nombre || state.identidad.nombre;
          state.identidad.telefono = data.perfil.telefono || state.identidad.telefono;
        }

        // Reload local cache of profile & dependents
        const profileRes = await fetch(`${baseUrl}/chatbot/perfil`, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':csrf,
            'Accept':'application/json',
          },
          body:JSON.stringify({
            cedula: state.identidad.cedula,
            email: state.identidad.email,
          })
        });
        const profileData = await profileRes.json().catch(() => ({}));
        if (profileRes.ok && profileData.ok) {
          state.dependientes = Array.isArray(profileData.dependientes) ? profileData.dependientes : [];
        }

        addMessage('bot','Listo, los datos fueron actualizados correctamente.');
        addButtons([
          { label:'Volver al menú', action:'menu' },
          { label:'Finalizar', action:'finalizar' },
        ]);
        state.mode = 'menu';
        state.step = null;
      } catch (error) {
        console.error(error);
        addMessage('bot','No pudimos guardar los cambios en este momento.');
        showMainMenu();
      }
    });
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addMessage('user', text);
    input.value = '';

    const comando = text.toLowerCase();
    if (comando === 'menu') {
      return hasActiveSession() ? showMainMenu() : startIdentidad(true);
    }
    if (comando === 'reiniciar') {
      return resetChat();
    }

    if (state.mode === 'finalizado') {
      return addMessage('bot','La sesión está cerrada. Escribe "menu" para continuar o "reiniciar" para empezar de nuevo.');
    }

    if (state.mode === 'identidad') {
      if (state.step === 'espera_saludo') {
        if (comando !== 'hola') {
          return addMessage('bot','Por favor escribe "hola" para comenzar.');
        }
        state.step = 'verificacion_humano';
        await mostrarRetoHumano();
        return;
      }
      if (state.step === 'verificacion_humano') {
        return procesarRespuestaRetoHumano(text);
      }
      if (state.step === 'cedula') {
        return procesarCedula(text);
      }
      if (state.step === 'email') {
        return procesarCorreo(text);
      }
      if (state.step === 'codigo') {
        return procesarCodigo(text);
      }
      return;
    }

    if (state.mode === 'registro') {
      if (state.step === 'registro_opción') {
        if (comando.includes('registr')) return startRegistro();
        if (comando.includes('finalizar')) return finalizarChat();
        return addMessage('bot','Usa los botones para elegir una opción.');
      }

      if (state.step === 'registro_cedula') {
        const cedulaNormalizada = limpiarCedula(text);
        if (!esCedulaValida(cedulaNormalizada)) {
          return addMessage('bot','El número de cédula debe tener exactamente 10 dígitos.');
        }
        state.registro.cedula = cedulaNormalizada;
        state.step = 'registro_email';
        return addMessage('bot','Escribe tu correo electrónico:');
      }

      if (state.step === 'registro_email') {
        const correoNormalizado = normalizarCorreo(text);
        if (!esCorreoValido(correoNormalizado)) {
          return addMessage('bot','El correo debe incluir @ y no tener espacios.');
        }
        state.registro.email = correoNormalizado;
        state.step = 'registro_nombre';
        return addMessage('bot','Escribe tus nombres y apellidos:');
      }

      if (state.step === 'registro_nombre') {
        if (!text) {
          return addMessage('bot','Necesitamos tus nombres y apellidos.');
        }
        state.registro.nombre = text;
        state.step = 'registro_telefono';
        return addMessage('bot','Escribe tu número de teléfono (10 dígitos):');
      }

      if (state.step === 'registro_telefono') {
        if (!esTelefonoValido(text)) {
          return addMessage('bot','El teléfono debe tener 10 dígitos, solo números.');
        }
        state.registro.telefono = text;
        return registrarUsuarioDesdeRegistro();
      }

      return;
    }

    // Flujo AGENDAR
    if (state.mode === 'agendar') {
      if (state.step === 'motivo') {
        if (!esMotivoConsultaValido(text)) {
          return addMessage('bot','El motivo de la cita es obligatorio y debe ser breve. Ejemplos: fiebre, dolor de cabeza o tos.');
        }
        state.buffer.motivo = normalizarMotivoConsulta(text);
        return pedirEspecialidades();
      }
      if (state.step === 'especialidad') {
        const idx = parseInt(text, 10);
        const list = state.buffer.especialidades || [];
        if (!idx || idx < 1 || idx > list.length) {
          return addMessage('bot','Número inválido. Escribe el número de una especialidad de la lista.');
        }
        const esp = list[idx - 1];
        state.buffer.especialidad_id = esp.id;
        return pedirDoctores();
      }
      if (state.step === 'doctor') {
        const idx = parseInt(text, 10);
        const list = state.buffer.doctores || [];
        if (!idx || idx < 1 || idx > list.length) {
          return addMessage('bot','Número inválido. Escribe el número de un médico de la lista.');
        }
        const doctor = list[idx - 1];
        state.buffer.doctor_id = doctor.id;
        state.buffer.doctor_nombre = doctor.nombre;
        state.buffer.doctor_tarifa = doctor.precio_format || '';
        return pedirFechas(doctor);
      }
      if (state.step === 'fecha') {
        const idx = parseInt(text, 10);
        const list = state.buffer.fechas || [];
        if (!idx || idx < 1 || idx > list.length) {
          return addMessage('bot','Número inválido. Escribe el número de una fecha de la lista.');
        }
        const fechaObj = list[idx - 1];
        state.buffer.fecha = fechaObj.value || fechaObj.fecha || '';
        state.buffer.fecha_label = fechaObj.label || state.buffer.fecha;
        return pedirHorarios(state.buffer.fecha);
      }
      if (state.step === 'hora') {
        const idx = parseInt(text, 10);
        const list = state.buffer.slots || [];
        if (!idx || idx < 1 || idx > list.length) {
          return addMessage('bot','Número inválido. Escribe el número de un horario de la lista.');
        }
        const slot = list[idx - 1];
        const reservado = await reservarHorarioTemporal(state.buffer.fecha, slot.hora);
        if (!reservado) {
          return;
        }
        state.buffer.hora = slot.hora;
        state.step = 'confirmar';

        let resumen = `Vas a agendar una cita con ${state.buffer.doctor_nombre} el ${state.buffer.fecha} a las ${state.buffer.hora}.`;
        if (state.buffer.doctor_tarifa) {
          resumen += `\nTarifa: ${state.buffer.doctor_tarifa}`;
        }
        resumen += `\n\nConfirmas (si/no)`;
        return addMessage('bot', resumen);
      }
      if (state.step === 'confirmar') {
        if (text.toLowerCase().startsWith('s')) {
          return enviarAgendar();
        }
        addMessage('bot','Se canceló el proceso de agendamiento.');
        return showMainMenu();
      }
    }

    // Flujo CANCELAR / REAGENDAR
    if (['cancelar','reagendar'].includes(state.mode)) {
      if (state.mode === 'cancelar' && state.step === 'cancelar_cita') {
        const idx = parseInt(text, 10);
        if (!idx || idx < 1 || Number.isNaN(idx)) {
          return addMessage('bot','Ingresa un número válido de la lista.');
        }
        return seleccionarCitaCancelarPorIndice(idx - 1);
      }

      if (state.step === 'confirmar_cancelacion') {
        const answer = text.toLowerCase();
        if (answer.startsWith('s')) {
          return ejecutarCancelacion();
        }
        addMessage('bot','No cancelaremos la cita.');
        return showMainMenu();
      }

      if (state.mode === 'reagendar') {
        if (state.step === 'reagendar_cita') {
          const idx = parseInt(text, 10);
          if (!idx || idx < 1 || Number.isNaN(idx)) {
            return addMessage('bot','Ingresa un número válido de la lista.');
          }
          return prepararReagendarPorIndice(idx - 1);
        }

        if (state.step === 'reagendar_fecha') {
          const idx = parseInt(text, 10);
          const list = state.buffer.fechasReagendar || [];
          const item = list[idx - 1];
          if (!idx || idx < 1 || Number.isNaN(idx) || !item) {
            return addMessage('bot','Número inválido, elige una fecha de la lista.');
          }
          state.buffer.nueva_fecha = item.value;
          state.buffer.nueva_fecha_label = item.label;
          return pedirHorarios(state.buffer.nueva_fecha, 'reagendar');
        }

        if (state.step === 'reagendar_hora') {
          const idx = parseInt(text, 10);
          const list = state.buffer.slotsReagendar || [];
          const slot = list[idx - 1];
          if (!idx || idx < 1 || Number.isNaN(idx) || !slot) {
            return addMessage('bot','Número inválido, elige un horario de la lista.');
          }
          state.buffer.nueva_hora = slot.hora;
          state.step = 'confirmar_reagendar';
          return addMessage('bot',`Confirmas reprogramar para ${state.buffer.nueva_fecha_label || state.buffer.nueva_fecha} a las ${state.buffer.nueva_hora} (si/no)`);
        }

        if (state.step === 'confirmar_reagendar') {
          const answer = text.toLowerCase();
          if (answer.startsWith('s')) {
            return ejecutarReagendar();
          }
          addMessage('bot','No reprogramaremos la cita.');
          return showMainMenu();
        }
      }
      return;
    }

    // Flujo ACTUALIZAR PERFIL
    if (state.mode === 'actualizar_perfil') {
      const lower = text.toLowerCase();

      // Dependent specific update step machine
      if (state.buffer.dependiente_id) {
        if (state.step === 'perfil_dep_nombre') {
          if (lower !== 'igual') {
            state.buffer.nombre = text;
          }
          state.step = 'perfil_dep_dni';
          return addMessage('bot',`Cédula actual: ${state.buffer.dni}. Ingresa la nueva (10 dígitos) o escribe "igual":`);
        }

        if (state.step === 'perfil_dep_dni') {
          if (lower !== 'igual') {
            const ced = limpiarCedula(text);
            if (!esCedulaValida(ced)) {
              return addMessage('bot','La cédula debe tener 10 dígitos, solo números.');
            }
            state.buffer.dni = ced;
          }
          state.step = 'perfil_dep_fecha';
          return addMessage('bot',`Fecha de nacimiento actual: ${state.buffer.fecha_nacimiento}. Ingresa en formato AAAA-MM-DD o escribe "igual":`);
        }

        if (state.step === 'perfil_dep_fecha') {
          if (lower !== 'igual') {
            if (!esFechaValida(text)) {
              return addMessage('bot','Usa el formato AAAA-MM-DD.');
            }
            if (esEdadDependienteValida(text)) {
              return addMessage('bot','Aviso: El paciente ingresado es mayor de edad. Por políticas del sistema, las personas entre 18 y 65 años deben registrar y gestionar su propia cuenta principal.');
            }
            state.buffer.fecha_nacimiento = text;
          }
          state.step = 'perfil_dep_sexo';
          return addMessage('bot',`Sexo actual: ${state.buffer.sexo || 'Sin especificar'}. Opciones: Masculino, Femenino u Otro. Escribe uno o "igual":`);
        }

        if (state.step === 'perfil_dep_sexo') {
          if (lower !== 'igual') {
            const sex = normalizarSexo(text);
            if (!sex) {
              return addMessage('bot','Elige Masculino, Femenino u Otro.');
            }
            state.buffer.sexo = sex;
          }
          state.step = 'perfil_dep_parentesco';
          return addMessage('bot',`Parentesco actual: ${state.buffer.parentesco}. Opciones: Hijo, Hija, Conyuge, Padre, Madre, Otro. Escribe uno o "igual":`);
        }

        if (state.step === 'perfil_dep_parentesco') {
          if (lower !== 'igual') {
            const normalizedParentesco = text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
            const allowed = ['Hijo', 'Hija', 'Conyuge', 'Padre', 'Madre', 'Otro'];
            if (!allowed.includes(normalizedParentesco)) {
              return addMessage('bot','Elige un parentesco válido: Hijo, Hija, Conyuge, Padre, Madre, Otro.');
            }
            state.buffer.parentesco = normalizedParentesco;
          }
          state.step = 'perfil_dep_telefono_emergencia';
          return addMessage('bot',`Teléfono de emergencia actual: ${state.buffer.telefono_emergencia || 'Ninguno'}.\nIngresa uno nuevo de 10 dígitos o escribe "igual":`);
        }

        if (state.step === 'perfil_dep_telefono_emergencia') {
          if (lower !== 'igual') {
            if (text && !esTelefonoValido(text)) {
              return addMessage('bot','El teléfono de emergencia debe tener 10 dígitos.');
            }
            state.buffer.telefono_emergencia = text;
          }
          state.step = 'perfil_dep_notas';
          return addMessage('bot',`Notas actuales: ${state.buffer.notes || 'Ninguna'}.\nEscribe las nuevas notas o escribe "igual":`);
        }

        if (state.step === 'perfil_dep_notas') {
          if (lower !== 'igual') {
            state.buffer.notes = text;
          }
          return resumenPerfil();
        }
      }

      // Titular update step machine
      if (state.step === 'perfil_nombre') {
        if (lower !== 'igual') {
          state.buffer.nombre = text;
        } else if (!state.buffer.nombre) {
          return addMessage('bot','Necesitamos un nombre. Ingresa tu nombre completo.');
        }
        state.step = 'perfil_telefono';
        return addMessage('bot',`Teléfono actual: ${state.buffer.telefono || 'No registrado'}.\nIngresa uno nuevo de 10 dígitos o escribe "igual" para mantenerlo.`);
      }

      if (state.step === 'perfil_telefono') {
        if (lower === 'igual') {
          if (!state.buffer.telefono) {
            return addMessage('bot','Necesitamos un teléfono válido para tus notificaciones.');
          }
        } else {
          if (!esTelefonoValido(text)) {
            return addMessage('bot','El teléfono debe tener exactamente 10 dígitos.');
          }
          state.buffer.telefono = text;
        }
        state.step = 'perfil_direccion';
        return addMessage('bot',`Dirección actual: ${state.buffer.direccion || 'Sin dirección'}. Escribe la nueva dirección o "igual" para mantenerla.`);
      }

      if (state.step === 'perfil_direccion') {
        if (lower === 'igual') {
          if (!state.buffer.direccion) {
            return addMessage('bot','Necesitamos una dirección para completar tu perfil.');
          }
        } else {
          state.buffer.direccion = text;
        }
        state.step = 'perfil_fecha';
        return addMessage('bot',`Fecha de nacimiento actual: ${state.buffer.fecha_nacimiento || 'No registrada'}. Ingresa en formato AAAA-MM-DD o escribe "igual".`);
      }

      if (state.step === 'perfil_fecha') {
        if (lower !== 'igual') {
          if (!esFechaValida(text)) {
            return addMessage('bot','Usa el formato AAAA-MM-DD.');
          }
          state.buffer.fecha_nacimiento = text;
        } else if (!state.buffer.fecha_nacimiento) {
          return addMessage('bot','Aún no tenemos tu fecha de nacimiento. Ingresa en formato AAAA-MM-DD.');
        }
        const sexoActual = state.buffer.sexo || 'Sin especificar';
        state.step = 'perfil_sexo';
        return addMessage('bot',`Sexo actual: ${sexoActual}. Opciones: Masculino, Femenino u Otro. Escribe una opción o "igual" para mantenerlo.`);
      }

      if (state.step === 'perfil_sexo') {
        if (lower === 'igual') {
          if (!state.buffer.sexo) {
            return addMessage('bot','Necesitamos que selecciones tu sexo para completar el perfil.');
          }
        } else {
          const normalizado = normalizarSexo(text);
          if (!normalizado) {
            return addMessage('bot','Elige Masculino, Femenino u Otro.');
          }
          state.buffer.sexo = normalizado;
        }
        return resumenPerfil();
      }

      if (state.step === 'perfil_confirmar') {
        if (lower.startsWith('s')) {
          return enviarActualizacionPerfil();
        }
        addMessage('bot','No guardaremos cambios.');
        return showMainMenu();
      }
    }

    // Flujo INFO CITAS
    if (state.mode === 'info_citas') {
      return addMessage('bot','Usa los botones para elegir una opción. Escribe "menu" para volver.');
    }
  });

  // Mensaje inicial
  startIdentidad();
});
