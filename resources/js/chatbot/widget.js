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
    password_actual: '',
    password_nuevo: '',
    password_confirmacion: '',
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
  });
  const initialRegistro = () => ({
    cedula: '',
    email: '',
    nombre: '',
    crearUsuario: null,
  });

  const state = {
    mode: 'identidad',
    step: 'espera_saludo',
    autenticado: false,
    identidad: { id:null, nombre:'', cedula:'', email:'', telefono:'' },
    registro: initialRegistro(),
    crearUsuarioPreferido: null,
    buffer: initialBuffer(),
    citasEncontradas: [],
    retoHumano: { challengeId: null, targetKey: '', targetLabelEs: '', images: [] },
  };

  const limpiarCedula = (value) => String(value || '').replace(/\s+/g, '');
  const normalizarCorreo = (value) => String(value || '').trim().toLowerCase();
  const esCedulaValida = (value) => /^\d{10}$/.test(limpiarCedula(value));
  const esTelefonoValido = (value) => /^\d{10}$/.test(value);
  const esCorreoValido = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizarCorreo(value));
  const esFechaValida = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value);
  const normalizarSexo = (value) => {
    const v = (value || '').trim().toLowerCase();
    if (!v) return '';
    if (['masculino','m'].includes(v)) return 'Masculino';
    if (['femenino','f'].includes(v)) return 'Femenino';
    if (['otro','x'].includes(v)) return 'Otro';
    return null;
  };

  function scrollBottom() { log.scrollTop = log.scrollHeight; }

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
    title.textContent = `Para verificar que no eres un robot, escribe el número de: ${reto.targetLabelEs}.`;

    const grid = document.createElement('div');
    grid.className = 'chat-verificacion-grid';

    reto.images.forEach((image, idx) => {
      const item = document.createElement('div');
      item.className = 'chat-verificacion-item';

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
    const res = await fetch(`${baseUrl}/captcha/challenge`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.message || 'No se pudo generar el captcha.');
    }

    const images = Array.isArray(data.images) ? data.images : [];
    return {
      challengeId: Number(data.challenge_id) || null,
      targetKey: String(data.target_key || ''),
      targetLabelEs: String(data.target_label_es || data.target_key || ''),
      images: images.map((img) => ({
        id: Number(img.id) || null,
        url: String(img.url || ''),
      })).filter((img) => img.id && img.url),
    };
  }

  async function verificarRetoHumano(challengeId, imageId) {
    const res = await fetch(`${baseUrl}/captcha/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        challenge_id: challengeId,
        selected_image_id: imageId,
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
      if (!reto.challengeId || reto.images.length !== 4) {
        throw new Error('No se pudo preparar el captcha.');
      }
      state.retoHumano = reto;
      addRetoHumano(state.retoHumano);
    } catch (error) {
      console.error(error);
      addMessage('bot', 'No pudimos cargar el captcha. Intenta nuevamente.');
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
    if (a === 'registro_crear_si') return finalizarRegistro(true);
    if (a === 'registro_crear_no') return finalizarRegistro(false);
    if (a === 'finalizar') return finalizarChat();
    if (a === 'reenviar_codigo') return reenviarCodigo();
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
    state.buffer.direccion = '';
    state.buffer.fecha_nacimiento = '';
    state.buffer.sexo = '';
    state.buffer.password_actual = '';
    state.buffer.password_nuevo = '';
    state.buffer.password_confirmacion = '';
  }

  function resetRegistro() {
    state.registro = initialRegistro();
    state.crearUsuarioPreferido = null;
  }

  // ======================
  // IDENTIDAD Y ACCESO
  // ======================
  function startIdentidad(reiniciar = false) {
    if (reiniciar) {
      state.autenticado = false;
      state.identidad = { id:null, nombre:'', cedula:'', email:'', telefono:'' };
      state.citasEncontradas = [];
      state.buffer = initialBuffer();
      resetRegistro();
    }
    state.mode = 'identidad';
    state.step = 'espera_saludo';
    addMessage('bot','Hola, soy el asistente virtual de la clinica.\n\nEscribe "hola" para comenzar.');
  }

  function resetChat() {
    log.innerHTML = '';
    state.retoHumano = { challengeId: null, targetKey: '', targetLabelEs: '', images: [] };
    startIdentidad(true);
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

  function finalizarRegistro(crearUsuario) {
    state.registro.crearUsuario = crearUsuario;
    state.crearUsuarioPreferido = crearUsuario;
    state.identidad = {
      id: null,
      nombre: state.registro.nombre,
      cedula: state.registro.cedula,
      email: state.registro.email,
      telefono: '',
    };
    state.autenticado = true;
    resetBufferConIdentidad();
    addMessage(
      'bot',
      crearUsuario
        ?
         'Crearemos tu usuario y enviaremos una contraseña temporal al correo.'
        : 'Continuaremos sin crear usuario.'
    );
    startAgendar();
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

      if (data.existe && data.paciente) {
        state.identidad.id = data.paciente.id || state.identidad.id;
        state.identidad.nombre = data.paciente.nombre || state.identidad.nombre;
        state.identidad.telefono = data.paciente.telefono || state.identidad.telefono;
        state.buffer.telefono = state.identidad.telefono;
      }

      state.step = 'email';
      const saludoNombre = state.identidad.nombre ? `Hola, ${state.identidad.nombre}.` : 'Hola.';
      const notaCorreo = data.existe && data.paciente && data.paciente.email
        ? 'Usaremos el correo registrado para verificarte.'
        : 'Necesito tu correo para enviarte un código de verificación.';
      addMessage('bot', `${saludoNombre} ${notaCorreo}\n\nEscribe tu correo:`);
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

    addMessage('bot','Verificando tu correo...');

    try {
      const res = await fetch(`${baseUrl}/chatbot/verificar-paciente`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ cedula: state.identidad.cedula, email: emailNormalizado }),
      });
      const data = await res.json();

      if (!res.ok || data.ok === false) {
        if (res.status === 404 || data.existe === false) {
          return mostrarOpcionesRegistro(data.message);
        }
        return addMessage('bot', data.message || 'El correo no coincide con el paciente registrado.');
      }

      if (data.existe && data.paciente) {
        state.identidad.id = data.paciente.id || state.identidad.id;
        state.identidad.nombre = data.paciente.nombre || state.identidad.nombre;
        state.identidad.telefono = data.paciente.telefono || state.identidad.telefono;
      }

      state.identidad.email = emailNormalizado;
      state.buffer.email = emailNormalizado;
      const enviado = await enviarCodigoVerificacion(emailNormalizado);
      if (!enviado) {
        state.step = 'email';
        return addMessage('bot','Escribe tu correo nuevamente para intentar enviar el código:');
      }
      state.step = 'codigo';
      addMessage('bot', 'Te enviamos un código de 6 dígitos a tu correo. Ingresa el código para continuar:');
    } catch (error) {
      console.error(error);
      addMessage('bot','No fue posible verificar tu correo en este momento. Intenta nuevamente.');
    }
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

      const data = await res.json();
      if (!res.ok || data.ok === false) {
        addMessage('bot', data.message || 'No se pudo enviar el código de verificación.');
        return false;
      }
      return true;
    } catch (error) {
      console.error(error);
      addMessage('bot','No pudimos enviar el código de verificación. Intenta más tarde.');
      return false;
    }
  }

  async function reenviarCodigo() {
    if (!state.identidad.cedula || !state.identidad.email) {
      state.step = 'cedula';
      return addMessage('bot','Necesito tu cédula y correo para enviarte un nuevo código.');
    }

    addMessage('bot','Enviando un nuevo código...');
    const enviado = await enviarCodigoVerificacion(state.identidad.email);
    if (!enviado) {
      return addButtons([{ label:'Enviar nuevo código', action:'reenviar_codigo' }]);
    }

    state.step = 'codigo';
    addMessage('bot','Te enviamos un nuevo código de 6 dígitos a tu correo. Ingresa el código para continuar:');
  }

  async function procesarCodigo(codigo) {
    if (!/^\d{6}$/.test(codigo)) {
      return addMessage('bot','El código debe tener 6 dígitos.');
    }

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
        if (data.error === 'codigo_vencido' || data.allow_resend) {
          addMessage('bot', data.message || 'El código ya venció. Solicita uno nuevo.');
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

      state.autenticado = true;
      resetBufferConIdentidad();
      addMessage('bot', `Código verificado. Bienvenido${state.identidad.nombre ? ', ' + state.identidad.nombre : ''}.`);
      showMainMenu(true);
    } catch (error) {
      console.error(error);
      addMessage('bot','No pudimos validar el código en este momento.');
    }
  }

  // ======================
  // MENUS
  // ======================
  function showMainMenu(esBienvenida = false) {
    if (!state.autenticado) {
      return startIdentidad(true);
    }

    state.mode = 'menu';
    state.step = null;
    resetBufferConIdentidad();
    state.citasEncontradas = [];

    const saludo = esBienvenida
      ? 'Identidad confirmada.' 
      : 'Menú principal.';
    const nombre = state.identidad.nombre ? state.identidad.nombre : '';

    addMessage('bot', `${saludo}${nombre ? ' ' + nombre : ''}\n\nSelecciona la gestion que deseas realizar:`);
    addButtons([
      { label:'Agendar cita', action:'agendar' },
      { label:'Cancelar cita', action:'cancelar' },
      { label:'Reagendar cita', action:'reagendar' },
      { label:'Informacion de mis citas', action:'info_citas' },
       { label:'Actualizar mis datos', action:'actualizar_perfil' },
      { label:'Finalizar', action:'finalizar' },
    ]);
  }

  function finalizarChat() {
    state.mode = 'finalizado';
    state.step = null;
    state.citasEncontradas = [];
    addMessage('bot','Se cerró esta gestión. Escribe "menu" para continuar sin reiniciar tu identidad o "reiniciar" para iniciar desde cero.');
  }

  // ======================
  // AGENDAR
  // ======================
  function startAgendar() {
    if (!state.autenticado) return startIdentidad(true);
    state.mode = 'agendar';
    resetBufferConIdentidad();
    addMessage('bot','Iniciaremos el agendamiento de tu cita.');

    if (!state.buffer.nombre) {
      state.step = 'nombre';
      return addMessage('bot','Escribe tu nombre completo:');
    }

    if (!state.buffer.telefono) {
      state.step = 'telefono';
      return addMessage('bot','Ingresa tu número de teléfono (10 dígitos):');
    }

    state.step = 'motivo';
    addMessage('bot','Indica el motivo de la consulta.');
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
      const res = await fetch(`${baseUrl}/api/doctor/${state.buffer.doctor_id}/fecha/${fecha}/slots`);
      const data = await res.json();
      const libres = (data.slots || []).filter(slot => slot.estado === 'libre');

      if (!libres.length) {
        addMessage('bot','Ese dia ya no tiene cupos. Escoge otra fecha.');
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
    addMessage('bot','Registrando tu cita...');

    const payload = {
      nombre:          state.buffer.nombre,
      cedula:          state.buffer.cedula,
      email:           state.buffer.email,
      telefono:        state.buffer.telefono,
      especialidad_id: state.buffer.especialidad_id,
      doctor_id:       state.buffer.doctor_id,
      fecha:           state.buffer.fecha,
      hora:            state.buffer.hora,
      motivo:          state.buffer.motivo,
      crear_usuario:   state.crearUsuarioPreferido !== null ? state.crearUsuarioPreferido : true,
    };

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

      if (!data.ok) {
        addMessage('bot', data.message || 'No se pudo agendar la cita.');
        return showMainMenu();
      }

      let msg = `Cita agendada con ${state.buffer.doctor_nombre} el ${state.buffer.fecha} a las ${state.buffer.hora}.`;
      if (state.buffer.doctor_tarifa) {
        msg += `\nTarifa: ${state.buffer.doctor_tarifa}`;
      }
      if (data.credenciales_enviadas) {
        msg += '\nSe creó una cuenta y te enviamos una contraseña temporal a tu correo.';
      }
      if (state.buffer.email) {
        msg += `\nHemos enviado la confirmación al correo ${state.buffer.email}.`;
      }
      if (state.buffer.telefono) {
        msg += `\nEn caso necesario te contactaremos al teléfono ${state.buffer.telefono}.`;
      }

      addMessage('bot', msg);
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
  }

  // ======================
  // CANCELAR / REAGENDAR
  // ======================
  function startCancelar() {
    if (!state.autenticado) return startIdentidad(true);
    state.mode = 'cancelar';
    state.step = 'cancelar_cita';
    addMessage('bot','Estas son tus citas vigentes. Selecciona cuál deseas cancelar:');
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
    if (!state.autenticado) return startIdentidad(true);
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
      email: state.identidad.email || null,
      cedula: state.identidad.cedula || null,
      estado: opciones.estado || 'all',
      incluir_todas: opciones.incluir_todas || false,
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

      if (!data.ok || !data.citas.length) {
        addMessage('bot', data.message || 'No encontramos citas con esos datos.');
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
      msg += `${i+1}) ${c.fecha} ${c.hora} - ${c.especialidad} (${c.estado})\n`;
    });
    addMessage('bot', msg.trim());

    if (contexto === 'cancelar') {
      addMessage('bot','Selecciona la cita a cancelar (escribe el número o usa un botón):');
      addButtons(citas.map((c,i)=>({
        label:`${i+1}) ${c.fecha} ${c.hora}`,
        action:'cancelar_cita_id',
        payload:{ cita_id:c.id }
      })));
    }

    if (contexto === 'reagendar') {
      addMessage('bot','Selecciona la cita a reprogramar (escribe el número o usa un botón):');
      addButtons(citas.map((c,i)=>({
        label:`${i+1}) ${c.fecha} ${c.hora}`,
        action:'reagendar_cita_id',
        payload:{ cita_id:c.id }
      })));
    }
  }

  function seleccionarCitaCancelar(id) {
    state.buffer.cita_id = id;
    state.step = 'confirmar_cancelacion';
    addMessage('bot','Confirma que deseas cancelar esta cita (si/no):');
  }

  function seleccionarCitaCancelarPorIndice(idx) {
    const cita = state.citasEncontradas[idx];
    if (!cita) {
      return addMessage('bot','Número inválido, intenta nuevamente.');
    }
    seleccionarCitaCancelar(cita.id);
  }

  async function ejecutarCancelacion() {
    try {
      const res = await fetch(`${baseUrl}/chatbot/cancelar`, {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-TOKEN':csrf,
          'Accept':'application/json',
        },
        body:JSON.stringify({
          cita_id: state.buffer.cita_id,
          cedula: state.identidad.cedula || null,
          email: state.identidad.email || null,
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
          cedula: state.identidad.cedula || null,
          email: state.identidad.email || null,
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
  }

  // ======================
  // INFORMACION DE CITAS
  // ======================
  function startInfoCitas() {
    if (!state.autenticado) return startIdentidad(true);
    state.mode = 'info_citas';
    state.step = 'elige_estado';
    mostrarEstadosCitas();
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
      msg += `${i+1}) ${c.fecha} ${c.hora} - ${c.especialidad} | Doctor: ${c.doctor || 'No asignado'} | Estado: ${c.estado}\n`;
    });
    addMessage('bot', msg.trim());
    addButtons([
      { label:'Volver a estados', action:'volver_estados' },
      { label:'Volver al menú', action:'menu' },
    ]);
  }

  // ======================
  // ACTUALIZAR PERFIL
  // ======================
  function startActualizarPerfil() {
    if (!state.autenticado) return startIdentidad(true);
    state.mode = 'actualizar_perfil';
    state.step = 'perfil_cargando';
    resetBufferConIdentidad();
    addMessage('bot','Obteniendo tus datos de perfil...');
    cargarPerfilDesdeServidor();
  }

  async function cargarPerfilDesdeServidor() {
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
          user_id: state.identidad.id || null,
        })
      });

      const data = await res.json();

      if (!data.ok || !data.perfil) {
        addMessage('bot', data.message || 'No pudimos obtener tu perfil.');
        return showMainMenu();
      }

      const perfil = data.perfil;
      state.identidad.id = perfil.id || state.identidad.id;

      state.buffer.nombre = perfil.nombre || state.identidad.nombre;
      state.buffer.email = perfil.email || state.identidad.email;
      state.buffer.telefono = perfil.telefono || state.identidad.telefono || '';
      state.buffer.cedula = state.identidad.cedula;
      state.buffer.dni = perfil.dni || state.identidad.cedula;
      state.buffer.direccion = perfil.direccion || '';
      state.buffer.fecha_nacimiento = perfil.fecha_nacimiento || '';
      state.buffer.sexo = perfil.sexo || '';
      state.buffer.password_actual = '';
      state.buffer.password_nuevo = '';
      state.buffer.password_confirmacion = '';

      addMessage('bot', `Estos son tus datos registrados:\n\nNombre: ${state.buffer.nombre || 'No registrado'}\nCorreo: ${state.buffer.email || 'No registrado'}\nTeléfono: ${state.buffer.telefono || 'No registrado'}\nCédula: ${state.buffer.dni || 'No registrado'}\nDirección: ${state.buffer.direccion || 'Sin dirección'}\nFecha de nacimiento: ${state.buffer.fecha_nacimiento || 'No registrada'}\nSexo: ${state.buffer.sexo || 'Sin especificar'}`);
      addMessage('bot','Responde con el nuevo valor o escribe "igual" para dejarlo como esta.');
      state.step = 'perfil_nombre';
      addMessage('bot',`Nombre actual: ${state.buffer.nombre || 'No registrado'}.`);
    } catch (error) {
      console.error(error);
      addMessage('bot','No pudimos cargar tu perfil ahora mismo.');
      showMainMenu();
    }
  }

  function resumenPerfil() {
    let msg = 'Asi quedaran tus datos:\n\n';
    msg += `Nombre: ${state.buffer.nombre || 'No registrado'}\n`;
    msg += `Correo: ${state.buffer.email || 'No registrado'}\n`;
    msg += `Teléfono: ${state.buffer.telefono || 'Sin teléfono'}\n`;
    msg += `Cédula: ${state.buffer.dni || 'No registrada'}\n`;
    msg += `Dirección: ${state.buffer.direccion || 'Sin dirección'}\n`;
    msg += `Fecha de nacimiento: ${state.buffer.fecha_nacimiento || 'No registrada'}\n`;
    msg += `Sexo: ${state.buffer.sexo || 'Sin especificar'}\n`;
    msg += `Contraseña: ${state.buffer.password_nuevo ? 'Se actualizará' : 'Sin cambios'}`;
    addMessage('bot', msg.trim());
    state.step = 'perfil_confirmar';
    addMessage('bot','¿Confirmas guardar estos cambios (si/no)');
  }

  async function enviarActualizacionPerfil() {
    addMessage('bot','Guardando tus datos...');
    const payload = {
      user_id: state.identidad.id || null,
      cedula: state.identidad.cedula,
      email_identidad: state.identidad.email,
      nombre: state.buffer.nombre,
      email: state.buffer.email,
      telefono: state.buffer.telefono || null,
      dni: state.buffer.dni,
      direccion: state.buffer.direccion || null,
      fecha_nacimiento: state.buffer.fecha_nacimiento,
      sexo: state.buffer.sexo || null,
      current_password: state.buffer.password_actual || null,
      password: state.buffer.password_nuevo || null,
      password_confirmation: state.buffer.password_confirmacion || null,
    };

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
        const msg = data.message || 'No pudimos actualizar tus datos.';
        addMessage('bot', msg);
        if (data.field === 'current_password') {
          state.step = 'perfil_password_actual';
          return addMessage('bot','Ingresa de nuevo tu contraseña actual:');
        }
        return showMainMenu();
      }

      state.identidad.id = data.perfil.id || state.identidad.id;
      state.identidad.nombre = data.perfil.nombre || state.identidad.nombre;
      state.identidad.email = data.perfil.email || state.identidad.email;
      state.identidad.telefono = data.perfil.telefono || state.identidad.telefono;
      state.identidad.cedula = data.perfil.dni || state.identidad.cedula;
      resetBufferConIdentidad();

      addMessage('bot','Listo, tus datos fueron actualizados y se reflejarán en tu panel.');
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
  }

  // ======================
  // MANEJO DE INPUT
  // ======================
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addMessage('user', text);
    input.value = '';

    const comando = text.toLowerCase();
    if (comando === 'menu') {
      return state.autenticado ? showMainMenu() : startIdentidad(true);
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
          return addMessage('bot','Por favor escribe "hola" para continuar.');
        }
        state.step = 'verificacion_humano';
        await mostrarRetoHumano();
        return;
      }
      if (state.step === 'verificacion_humano') {
        const respuesta = parseInt(text, 10);
        if (!state.retoHumano.challengeId || !Array.isArray(state.retoHumano.images) || state.retoHumano.images.length !== 4) {
          addMessage('bot','Generando un nuevo reto...');
          await mostrarRetoHumano();
          return;
        }
        if (!respuesta || respuesta < 1 || respuesta > state.retoHumano.images.length) {
          addMessage('bot','Escribe el número correspondiente al animal solicitado (1-4).');
          return;
        }

        const selected = state.retoHumano.images[respuesta - 1];
        if (!selected || !selected.id) {
          addMessage('bot','No se pudo leer la opcion seleccionada. Intentaremos de nuevo.');
          await mostrarRetoHumano();
          return;
        }

        addMessage('bot','Validando seleccion...');
        const resultado = await verificarRetoHumano(state.retoHumano.challengeId, selected.id);
        if (!resultado.ok) {
          addMessage('bot', resultado.message || 'Seleccion incorrecta. Intenta nuevamente.');
          await mostrarRetoHumano();
          return;
        }
        state.step = 'cedula';
        return addMessage('bot','Validación completada.\n\nIngresa tu número de cédula (10 dígitos, solo números):');
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
        state.step = 'registro_crear';
        addMessage('bot','¿Deseas crear un usuario con ese correo y recibir una contraseña temporal?');
        return addButtons([
          { label:'Sí, crear usuario', action:'registro_crear_si' },
          { label:'No, solo agendar', action:'registro_crear_no' },
        ]);
      }

      if (state.step === 'registro_crear') {
        if (comando.startsWith('s')) {
          return finalizarRegistro(true);
        }
        if (comando.startsWith('n')) {
          return finalizarRegistro(false);
        }
        return addMessage('bot','Responde si/no o usa los botones.');
      }

      return;
    }

    // Flujo AGENDAR
    if (state.mode === 'agendar') {
      if (state.step === 'nombre') {
        state.buffer.nombre = text;
        state.identidad.nombre = text;
        state.step = 'telefono';
        return addMessage('bot','Ingresa tu número de teléfono (10 dígitos):');
      }
      if (state.step === 'telefono') {
        if (!esTelefonoValido(text)) {
          return addMessage('bot','El teléfono debe tener 10 dígitos, solo números.');
        }
        state.buffer.telefono = text;
        state.identidad.telefono = text;
        state.step = 'motivo';
        return addMessage('bot','Motivo de consulta (o escribe "no" para omitir):');
      }
      if (state.step === 'motivo') {
        state.buffer.motivo = text;
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

      if (state.step === 'perfil_nombre') {
        if (lower !== 'igual') {
          state.buffer.nombre = text;
        } else if (!state.buffer.nombre) {
          return addMessage('bot','Necesitamos un nombre. Ingresa tu nombre completo.');
        }
        state.step = 'perfil_email';
        return addMessage('bot',`Correo actual: ${state.buffer.email || 'No registrado'}.\nIngresa el nuevo correo o escribe "igual" para mantenerlo.`);
      }

      if (state.step === 'perfil_email') {
        if (lower !== 'igual') {
          if (!esCorreoValido(text)) {
            return addMessage('bot','El correo debe incluir @ y no tener espacios.');
          }
          state.buffer.email = text;
        } else if (!state.buffer.email) {
          return addMessage('bot','Necesitamos un correo válido para tus notificaciones.');
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
        state.step = 'perfil_dni';
        return addMessage('bot',`Cédula actual: ${state.buffer.dni || state.identidad.cedula || 'No registrada'}. Ingresa la nueva (10 dígitos) o escribe "igual".`);
      }

      if (state.step === 'perfil_dni') {
        if (lower !== 'igual') {
          const cedulaNormalizada = limpiarCedula(text);
          if (!esCedulaValida(cedulaNormalizada)) {
            return addMessage('bot','La cédula debe tener 10 dígitos.');
          }
          state.buffer.dni = cedulaNormalizada;
        } else if (!state.buffer.dni) {
          return addMessage('bot','Necesitamos tu número de cédula de 10 dígitos.');
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
        return addMessage('bot',`Fecha de nacimiento actual: ${state.buffer.fecha_nacimiento || 'No registrada'}. Ingresa en formato AAAA-MM-DD.`);
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
        state.step = 'perfil_pregunta_password';
        return addMessage('bot','¿Quieres cambiar tu contraseña (si/no)');
      }

      if (state.step === 'perfil_pregunta_password') {
        if (lower.startsWith('s')) {
          state.buffer.password_actual = '';
          state.buffer.password_nuevo = '';
          state.buffer.password_confirmacion = '';
          state.step = 'perfil_password_actual';
          return addMessage('bot','Escribe tu contraseña actual:');
        }
        state.buffer.password_actual = '';
        state.buffer.password_nuevo = '';
        state.buffer.password_confirmacion = '';
        return resumenPerfil();
      }

      if (state.step === 'perfil_password_actual') {
        if (!text) {
          return addMessage('bot','La contraseña actual no puede estar vacía.');
        }
        state.buffer.password_actual = text;
        state.step = 'perfil_password_nueva';
        return addMessage('bot','Ingresa la nueva contraseña (mínimo 8 caracteres, con letras, números y un carácter especial):');
      }

      if (state.step === 'perfil_password_nueva') {
        if (text.length < 8 || !/^(=.*[A-Za-z])(=.*\d)(=.*[^A-Za-z0-9]).{8,}$/.test(text)) {
          return addMessage('bot','La nueva contraseña debe tener mínimo 8 caracteres e incluir letras, números y un carácter especial.');
        }
        if (state.buffer.password_actual && text === state.buffer.password_actual) {
          return addMessage('bot','La nueva contraseña debe ser diferente a la actual.');
        }
        state.buffer.password_nuevo = text;
        state.step = 'perfil_password_confirmacion';
        return addMessage('bot','Confirma la nueva contraseña:');
      }

      if (state.step === 'perfil_password_confirmacion') {
        if (text !== state.buffer.password_nuevo) {
          return addMessage('bot','La confirmación no coincide. Intenta de nuevo.');
        }
        state.buffer.password_confirmacion = text;
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

    // Flujo INFO CITAS: respuestas solo con botones
    if (state.mode === 'info_citas') {
      return addMessage('bot','Usa los botones para elegir una opción. Escribe "menu" para volver.');
    }
  });

  // Mensaje inicial
  startIdentidad();
});
