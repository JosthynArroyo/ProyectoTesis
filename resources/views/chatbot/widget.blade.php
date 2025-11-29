{{-- resources/views/chatbot/widget.blade.php --}}
@once
<style>
  #chatbot-widget {
    position: fixed;
    bottom: 1.6rem;
    right: 1.6rem;
    width: 360px;
    max-width: calc(100vw - 2rem);
    z-index: 9999;
    font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
  }

  #chatbot-toggle {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #1f59ff, #2563eb);
    color: #fff;
    box-shadow: 0 16px 32px rgba(37,99,235,.3), 0 4px 12px rgba(0,0,0,.25);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    transition: transform .25s ease, box-shadow .25s ease, opacity .25s ease;
    margin-left: auto;
  }

  #chatbot-toggle:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 20px 36px rgba(37,99,235,.4), 0 6px 18px rgba(0,0,0,.28);
  }

  #chatbot-toggle svg {
    width: 30px;
    height: 30px;
  }

  #chatbot-panel {
    position: absolute;
    bottom: 72px;
    right: 0;
    width: 360px;
    max-width: calc(100vw - 2rem);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 18px;
    border: 1px solid #e4e7ec;
    box-shadow: 0 20px 44px rgba(15,23,42,.24);
    overflow: hidden;
    transform: translateY(14px) scale(.97);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: transform .25s ease, opacity .25s ease, visibility 0s linear .25s;
  }

  #chatbot-panel.is-open {
    transform: translateY(0) scale(1);
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transition-delay: 0s;
  }

  #chat-log {
    height: 380px;
    overflow-y: auto;
    padding: 18px 14px 12px;
    font-size: .9rem;
    background: radial-gradient(110% 60% at 30% 10%, rgba(37,99,235,.08), transparent 40%),
                radial-gradient(90% 50% at 80% 0%, rgba(99,102,241,.06), transparent 35%),
                #ffffff;
  }

  #chatbot-input-area {
    border-top: 1px solid #e5e7eb;
    padding: 10px 12px;
    background: #f1f5f9;
  }

  #chatbot-form {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  #chatbot-input {
    flex: 1;
    border-radius: 12px;
    border: 1px solid #d1d5db;
    padding: 10px 12px;
    font-size: .9rem;
    outline: none;
    background: #ffffff;
    transition: border-color .2s ease, box-shadow .2s ease;
  }

  #chatbot-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.15);
  }

  #chatbot-send-btn {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg,#2563eb,#1d4ed8);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 18px rgba(37,99,235,.35);
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    font-size: 1.05rem;
  }

  #chatbot-send-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(37,99,235,.4);
  }

  #chatbot-send-btn:active {
    transform: translateY(0);
    box-shadow: 0 6px 14px rgba(37,99,235,.45);
    opacity: .95;
  }
</style>
@endonce
<div id="chatbot-widget">
  <button id="chatbot-toggle"
          type="button"
          aria-controls="chatbot-panel"
          aria-expanded="false"
          aria-label="Abrir asistente de la clinica">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="white"/>
      <path d="M12 13H16V11H12V13ZM8 13H10V11H8V13ZM8 9H16V7H8V9Z" fill="#2563EB"/>
    </svg>
  </button>

  <div id="chatbot-panel" aria-live="polite" aria-hidden="true">
    <div id="chat-log"></div>

    <div id="chatbot-input-area">
      <form id="chatbot-form" autocomplete="off">
        <input id="chatbot-input" type="text" placeholder="Escribe aqui...">
        <button id="chatbot-send-btn" type="submit" aria-label="Enviar mensaje">
          &#10148;
        </button>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var panel  = document.getElementById('chatbot-panel');
  var toggle = document.getElementById('chatbot-toggle');
  if (!panel || !toggle) {
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

  const csrf    = '{{ csrf_token() }}';
  const baseUrl = '{{ url('/') }}';

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

  const state = {
    mode: 'identidad',
    step: 'espera_saludo',
    autenticado: false,
    identidad: { id:null, nombre:'', cedula:'', email:'', telefono:'' },
    buffer: initialBuffer(),
    citasEncontradas: [],
    retoHumano: { pregunta:'', respuesta:'' },
  };

  const esCedulaValida = (value) => /^\d{10}$/.test(value);
  const esTelefonoValido = (value) => /^\d{10}$/.test(value);
  const esCorreoValido = (value) => value.includes('@') && !value.includes(' ');
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
    wrap.style.marginBottom = '10px';

    const bubble = document.createElement('div');
    bubble.style.maxWidth = '90%';
    bubble.style.padding = '8px 10px';
    bubble.style.borderRadius = '12px';
    bubble.style.whiteSpace = 'pre-wrap';

    if (role === 'bot') {
      bubble.style.background = '#f3f4f6';
      bubble.style.color = '#111827';
      bubble.style.borderTopLeftRadius = '2px';
    } else {
      bubble.style.marginLeft = 'auto';
      bubble.style.background = '#1d4ed8';
      bubble.style.color = '#ffffff';
      bubble.style.borderTopRightRadius = '2px';
    }

    bubble.textContent = text;
    wrap.appendChild(bubble);
    log.appendChild(wrap);
    scrollBottom();
  }

  function addButtons(buttons) {
    const wrap = document.createElement('div');
    wrap.style.margin = '6px 0 10px';
    buttons.forEach(btn => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = btn.label;
      b.dataset.action = btn.action;
      b.dataset.payload = JSON.stringify(btn.payload || {});
      b.style.marginRight = '6px';
      b.style.marginBottom = '6px';
      b.style.borderRadius = '999px';
      b.style.padding = '6px 10px';
      b.style.fontSize = '.8rem';
      b.style.border = '1px solid #d1d5db';
      b.style.background = '#ffffff';
      b.style.cursor = 'pointer';
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
    if (a === 'finalizar') return finalizarChat();
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

  // ======================
  // IDENTIDAD Y ACCESO
  // ======================
  function startIdentidad(reiniciar = false) {
    if (reiniciar) {
      state.autenticado = false;
      state.identidad = { id:null, nombre:'', cedula:'', email:'', telefono:'' };
      state.citasEncontradas = [];
    }
    state.mode = 'identidad';
    state.step = 'espera_saludo';
    addMessage('bot','Hola, soy el asistente virtual de la clinica.\n\nEscribe "hola" para comenzar.');
  }

  async function procesarCedula(cedula) {
    if (!esCedulaValida(cedula)) {
      return addMessage('bot','El numero de cedula debe tener exactamente 10 digitos.');
    }

    state.identidad.cedula = cedula;
    state.buffer.cedula = cedula;
    addMessage('bot','Validando tu cedula, un momento...');

    try {
      const res = await fetch(`${baseUrl}/chatbot/verificar-paciente`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ cedula }),
      });
      const data = await res.json();

      if (!res.ok || data.ok === false) {
        return addMessage('bot', data.message || 'No pudimos validar esa cedula. Intenta nuevamente.');
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
        : 'Necesito tu correo para enviarte un codigo de verificacion.';
      addMessage('bot', `${saludoNombre} ${notaCorreo}\n\nEscribe tu correo:`);
    } catch (error) {
      console.error(error);
      addMessage('bot','Hubo un problema al validar tu cedula. Intenta nuevamente.');
    }
  }

  async function procesarCorreo(email) {
    if (!esCorreoValido(email)) {
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
        body: JSON.stringify({ cedula: state.identidad.cedula, email }),
      });
      const data = await res.json();

      if (!res.ok || data.ok === false) {
        return addMessage('bot', data.message || 'El correo no coincide con el paciente registrado.');
      }

      if (data.existe && data.paciente) {
        state.identidad.id = data.paciente.id || state.identidad.id;
        state.identidad.nombre = data.paciente.nombre || state.identidad.nombre;
        state.identidad.telefono = data.paciente.telefono || state.identidad.telefono;
      }

      state.identidad.email = email;
      state.buffer.email = email;
      await enviarCodigoVerificacion(email);
      state.step = 'codigo';
      addMessage('bot', 'Te enviamos un codigo de 6 digitos a tu correo. Ingresa el codigo para continuar:');
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
        addMessage('bot', data.message || 'No se pudo enviar el codigo de verificacion.');
      }
    } catch (error) {
      console.error(error);
      addMessage('bot','No pudimos enviar el codigo de verificacion. Intenta mas tarde.');
    }
  }

  async function procesarCodigo(codigo) {
    if (!/^\d{6}$/.test(codigo)) {
      return addMessage('bot','El codigo debe tener 6 digitos.');
    }

    addMessage('bot','Validando codigo...');

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
        return addMessage('bot', data.message || 'Codigo incorrecto o expirado.');
      }

      if (data.paciente) {
        state.identidad.id = data.paciente.id || state.identidad.id;
        state.identidad.nombre = data.paciente.nombre || state.identidad.nombre;
        state.identidad.telefono = data.paciente.telefono || state.identidad.telefono;
        state.identidad.email = data.paciente.email || state.identidad.email;
      }

      state.autenticado = true;
      resetBufferConIdentidad();
      addMessage('bot', `Codigo verificado. Bienvenido${state.identidad.nombre ? ', ' + state.identidad.nombre : ''}.`);
      showMainMenu(true);
    } catch (error) {
      console.error(error);
      addMessage('bot','No pudimos validar el codigo en este momento.');
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
      : 'Menu principal.';
    const nombre = state.identidad.nombre ? ` ${state.identidad.nombre}` : '';

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
    addMessage('bot','He finalizado la sesion. Si deseas hacer otra gestion escribe "menu" para volver a empezar.');
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
      return addMessage('bot','Ingresa tu numero de telefono (10 digitos):');
    }

    state.step = 'motivo';
    addMessage('bot','Indica el motivo de la consulta (o escribe "no" para omitir):');
  }

  async function pedirEspecialidades() {
    try {
      const res = await fetch(`${baseUrl}/chatbot/especialidades`);
      const data = await res.json();
      state.buffer.especialidades = data;

      let txt = 'Especialidades disponibles:\n\n';
      data.forEach((e,i)=> txt += `${i+1}) ${e.nombre}\n`);
      addMessage('bot', txt.trim());
      state.step = 'especialidad';
      addMessage('bot','Escribe el numero de la especialidad que necesitas:');
    } catch (error) {
      console.error(error);
      addMessage('bot','No pude cargar las especialidades. Vuelve al menu.');
      showMainMenu();
    }
  }

  async function pedirDoctores() {
      addMessage('bot','Consultando medicos disponibles...');
    try {
      const res = await fetch(`${baseUrl}/chatbot/especialidades/${state.buffer.especialidad_id}/doctores`);
      const data = await res.json();

      if (!data.ok || !data.doctores.length) {
        addMessage('bot', data.message || 'No hay medicos activos para esta especialidad.');
        return showMainMenu();
      }

      state.buffer.doctores = data.doctores;
      let txt = `Medicos disponibles (${data.especialidad}):\n\n`;
      data.doctores.forEach((d,i)=> {
        const tarifa = d.precio_format ? ` - ${d.precio_format}` : '';
        txt += `${i+1}) ${d.nombre}${tarifa}\n`;
      });
      addMessage('bot', txt.trim());
      state.step = 'doctor';
      addMessage('bot','Escribe el numero del medico que prefieres:');
    } catch (error) {
      console.error(error);
      addMessage('bot','Error cargando los medicos.');
      showMainMenu();
    }
  }

  async function pedirFechas(doctor, contexto = 'agendar') {
    addMessage('bot',`Consultando disponibilidad de ${doctor.nombre}...`);
    try {
      const res = await fetch(`${baseUrl}/chatbot/doctores/${doctor.id}/fechas`);
      const data = await res.json();

      if (!data.ok || !data.fechas.length) {
        addMessage('bot', data.message || 'No hay fechas disponibles con este medico.');
        if (contexto === 'agendar') {
          state.step = 'doctor';
          return addMessage('bot','Elige otro medico escribiendo su numero:');
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

      let txt = `Fechas disponibles con ${doctor.nombre}:\n\n`;
      data.fechas.forEach((f,i)=> txt += `${i+1}) ${f.label}\n`);
      addMessage('bot', txt.trim());
      if (contexto === 'agendar') {
        state.step = 'fecha';
        addMessage('bot','Escribe el numero de la fecha:');
      } else {
        state.step = 'reagendar_fecha';
        addMessage('bot','Elige la nueva fecha escribiendo su numero:');
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

      let txt = `Horarios disponibles (${fecha}):\n\n`;
      libres.forEach((slot,i)=> txt += `${i+1}) ${slot.hora}\n`);
      addMessage('bot', txt.trim());

      if (contexto === 'agendar') {
        state.step = 'hora';
        addMessage('bot','Escribe el numero del horario:');
      } else {
        state.step = 'reagendar_hora';
        addMessage('bot','Elige la nueva hora escribiendo su numero:');
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
      motivo:          state.buffer.motivo || null,
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
      if (data.usuario_creado) {
        msg += '\nSe creo una cuenta y te enviamos una contraseña temporal a tu correo.';
      }
      if (state.buffer.email) {
        msg += `\nHemos enviado la confirmacion al correo ${state.buffer.email}.`;
      }
      if (state.buffer.telefono) {
        msg += `\nEn caso necesario te contactaremos al telefono ${state.buffer.telefono}.`;
      }

      addMessage('bot', msg);
      addButtons([
        { label:'Volver al menu', action:'menu' },
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
    addMessage('bot','Estas son tus citas vigentes. Selecciona cual deseas cancelar:');
    buscarCitasConCredenciales().then(listar => {
      if (!listar) {
        addButtons([{label:'Volver al menu', action:'menu'}]);
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
        addButtons([{label:'Volver al menu', action:'menu'}]);
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
      estado: opciones.estado || null,
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
      addMessage('bot','Selecciona la cita a cancelar (escribe el numero o usa un boton):');
      addButtons(citas.map((c,i)=>({
        label:`${i+1}) ${c.fecha} ${c.hora}`,
        action:'cancelar_cita_id',
        payload:{ cita_id:c.id }
      })));
    }

    if (contexto === 'reagendar') {
      addMessage('bot','Selecciona la cita a reprogramar (escribe el numero o usa un boton):');
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
      return addMessage('bot','Numero invalido, intenta nuevamente.');
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
        body:JSON.stringify({ cita_id:state.buffer.cita_id })
      });

      const data = await res.json();
      addMessage('bot', data.message || 'Cita cancelada.');
      addButtons([
        {label:'Volver al menu', action:'menu'},
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
      return addMessage('bot','Numero invalido, intenta nuevamente.');
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
    state.buffer.doctor_nombre = cita.doctor || 'tu medico';
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
          hora:state.buffer.nueva_hora
        })
      });

      const data = await res.json();
      addMessage('bot', data.message || 'Cita reprogramada.');
      addButtons([
        {label:'Volver al menu', action:'menu'},
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
      addMessage('bot', `No se encontraron citas ${textoEstado}. Puedes revisar otro estado o volver al menu.`);
      addButtons([
        { label:'Volver a estados', action:'volver_estados' },
        { label:'Volver al menu', action:'menu' },
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
      { label:'Volver al menu', action:'menu' },
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

      addMessage('bot', `Estos son tus datos registrados:\n\nNombre: ${state.buffer.nombre || 'No registrado'}\nCorreo: ${state.buffer.email || 'No registrado'}\nTelefono: ${state.buffer.telefono || 'No registrado'}\nCedula: ${state.buffer.dni || 'No registrado'}\nDireccion: ${state.buffer.direccion || 'Sin direccion'}\nFecha de nacimiento: ${state.buffer.fecha_nacimiento || 'No registrada'}\nSexo: ${state.buffer.sexo || 'Sin especificar'}`);
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
    msg += `Telefono: ${state.buffer.telefono || 'Sin telefono'}\n`;
    msg += `Cedula: ${state.buffer.dni || 'No registrada'}\n`;
    msg += `Direccion: ${state.buffer.direccion || 'Sin direccion'}\n`;
    msg += `Fecha de nacimiento: ${state.buffer.fecha_nacimiento || 'No registrada'}\n`;
    msg += `Sexo: ${state.buffer.sexo || 'Sin especificar'}\n`;
    msg += `Contrasena: ${state.buffer.password_nuevo ? 'Se actualizara' : 'Sin cambios'}`;
    addMessage('bot', msg.trim());
    state.step = 'perfil_confirmar';
    addMessage('bot','¿Confirmas guardar estos cambios? (si/no)');
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

      state.identidad.id = data.perfil?.id || state.identidad.id;
      state.identidad.nombre = data.perfil?.nombre || state.identidad.nombre;
      state.identidad.email = data.perfil?.email || state.identidad.email;
      state.identidad.telefono = data.perfil?.telefono || state.identidad.telefono;
      state.identidad.cedula = data.perfil?.dni || state.identidad.cedula;
      resetBufferConIdentidad();

      addMessage('bot','Listo, tus datos fueron actualizados y se reflejaran en tu panel.');
      addButtons([
        { label:'Volver al menu', action:'menu' },
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

    if (state.mode === 'finalizado') {
      return addMessage('bot','La sesion esta cerrada. Escribe "menu" para iniciar nuevamente.');
    }

    if (state.mode === 'identidad') {
      if (state.step === 'espera_saludo') {
        if (comando !== 'hola') {
          return addMessage('bot','Por favor escribe "hola" para continuar.');
        }
        const a = Math.floor(Math.random() * 9) + 1;
        const b = Math.floor(Math.random() * 9) + 1;
        state.retoHumano = { pregunta: `${a} + ${b} = ?`, respuesta: String(a + b) };
        state.step = 'verificacion_humano';
        return addMessage('bot',`Para verificar que no eres un robot, responde: ${state.retoHumano.pregunta}`);
      }
      if (state.step === 'verificacion_humano') {
        if (text.trim() !== state.retoHumano.respuesta) {
          return addMessage('bot','Respuesta incorrecta. Intenta de nuevo.');
        }
        state.step = 'cedula';
        return addMessage('bot','Validacion completada.\n\nIngresa tu numero de cedula (10 digitos, solo numeros):');
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

    // Flujo AGENDAR
    if (state.mode === 'agendar') {
      if (state.step === 'nombre') {
        state.buffer.nombre = text;
        state.identidad.nombre = text;
        state.step = 'telefono';
        return addMessage('bot','Ingresa tu numero de telefono (10 digitos):');
      }
      if (state.step === 'telefono') {
        if (!esTelefonoValido(text)) {
          return addMessage('bot','El telefono debe tener 10 digitos, solo numeros.');
        }
        state.buffer.telefono = text;
        state.identidad.telefono = text;
        state.step = 'motivo';
        return addMessage('bot','Motivo de consulta (o escribe "no" para omitir):');
      }
      if (state.step === 'motivo') {
        if (text.toLowerCase() !== 'no') {
          state.buffer.motivo = text;
        }
        return pedirEspecialidades();
      }
      if (state.step === 'especialidad') {
        const idx = parseInt(text, 10);
        const list = state.buffer.especialidades || [];
        if (!idx || idx < 1 || idx > list.length) {
          return addMessage('bot','Numero invalido. Escribe el numero de una especialidad de la lista.');
        }
        const esp = list[idx - 1];
        state.buffer.especialidad_id = esp.id;
        return pedirDoctores();
      }
      if (state.step === 'doctor') {
        const idx = parseInt(text, 10);
        const list = state.buffer.doctores || [];
        if (!idx || idx < 1 || idx > list.length) {
          return addMessage('bot','Numero invalido. Escribe el numero de un medico de la lista.');
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
          return addMessage('bot','Numero invalido. Escribe el numero de una fecha de la lista.');
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
          return addMessage('bot','Numero invalido. Escribe el numero de un horario de la lista.');
        }
        const slot = list[idx - 1];
        state.buffer.hora = slot.hora;
        state.step = 'confirmar';

        let resumen = `Vas a agendar una cita con ${state.buffer.doctor_nombre} el ${state.buffer.fecha} a las ${state.buffer.hora}.`;
        if (state.buffer.doctor_tarifa) {
          resumen += `\nTarifa: ${state.buffer.doctor_tarifa}`;
        }
        resumen += `\n\nConfirmas? (si/no)`;
        return addMessage('bot', resumen);
      }
      if (state.step === 'confirmar') {
        if (text.toLowerCase().startsWith('s')) {
          return enviarAgendar();
        }
        addMessage('bot','Se cancelo el proceso de agendamiento.');
        return showMainMenu();
      }
    }

    // Flujo CANCELAR / REAGENDAR
    if (['cancelar','reagendar'].includes(state.mode)) {
      if (state.mode === 'cancelar' && state.step === 'cancelar_cita') {
        const idx = parseInt(text, 10);
        if (!idx || idx < 1 || Number.isNaN(idx)) {
          return addMessage('bot','Ingresa un numero valido de la lista.');
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
            return addMessage('bot','Ingresa un numero valido de la lista.');
          }
          return prepararReagendarPorIndice(idx - 1);
        }

        if (state.step === 'reagendar_fecha') {
          const idx = parseInt(text, 10);
          const list = state.buffer.fechasReagendar || [];
          const item = list[idx - 1];
          if (!idx || idx < 1 || Number.isNaN(idx) || !item) {
            return addMessage('bot','Numero invalido, elige una fecha de la lista.');
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
            return addMessage('bot','Numero invalido, elige un horario de la lista.');
          }
          state.buffer.nueva_hora = slot.hora;
          state.step = 'confirmar_reagendar';
          return addMessage('bot',`Confirmas reprogramar para ${state.buffer.nueva_fecha_label || state.buffer.nueva_fecha} a las ${state.buffer.nueva_hora}? (si/no)`);
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
          return addMessage('bot','Necesitamos un correo valido para tus notificaciones.');
        }
        state.step = 'perfil_telefono';
        return addMessage('bot',`Telefono actual: ${state.buffer.telefono || 'No registrado'}.\nIngresa uno nuevo de 10 digitos, escribe "ninguno" para dejarlo vacio o "igual" para mantenerlo.`);
      }

      if (state.step === 'perfil_telefono') {
        if (lower === 'igual') {
          // mantener
        } else if (['ninguno','ninguna','vacio','vacío'].includes(lower)) {
          state.buffer.telefono = '';
        } else {
          if (!esTelefonoValido(text)) {
            return addMessage('bot','El telefono debe tener exactamente 10 digitos.');
          }
          state.buffer.telefono = text;
        }
        state.step = 'perfil_dni';
        return addMessage('bot',`Cedula actual: ${state.buffer.dni || state.identidad.cedula || 'No registrada'}. Ingresa la nueva (10 digitos) o escribe "igual".`);
      }

      if (state.step === 'perfil_dni') {
        if (lower !== 'igual') {
          if (!esCedulaValida(text)) {
            return addMessage('bot','La cedula debe tener 10 digitos.');
          }
          state.buffer.dni = text;
        } else if (!state.buffer.dni) {
          return addMessage('bot','Necesitamos tu numero de cedula de 10 digitos.');
        }
        state.step = 'perfil_direccion';
        return addMessage('bot',`Direccion actual: ${state.buffer.direccion || 'Sin direccion'}. Escribe la nueva direccion, "igual" o "ninguna" para dejarla vacia.`);
      }

      if (state.step === 'perfil_direccion') {
        if (lower === 'igual') {
          // mantener
        } else if (['ninguna','ninguno','vacio','vacío','ninguna direccion','sin direccion'].includes(lower)) {
          state.buffer.direccion = '';
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
          return addMessage('bot','Aun no tenemos tu fecha de nacimiento. Ingresa en formato AAAA-MM-DD.');
        }
        const sexoActual = state.buffer.sexo || 'Sin especificar';
        state.step = 'perfil_sexo';
        return addMessage('bot',`Sexo actual: ${sexoActual}. Opciones: Masculino, Femenino u Otro. Escribe una opcion, "igual" o "ninguno" si prefieres no indicarlo.`);
      }

      if (state.step === 'perfil_sexo') {
        if (lower === 'igual') {
          // mantener
        } else if (['ninguno','ninguna','no',''].includes(lower)) {
          state.buffer.sexo = '';
        } else {
          const normalizado = normalizarSexo(text);
          if (!normalizado) {
            return addMessage('bot','Elige Masculino, Femenino u Otro, o escribe "ninguno".');
          }
          state.buffer.sexo = normalizado;
        }
        state.step = 'perfil_pregunta_password';
        return addMessage('bot','¿Quieres cambiar tu contraseña? (si/no)');
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
          return addMessage('bot','La contraseña actual no puede estar vacia.');
        }
        state.buffer.password_actual = text;
        state.step = 'perfil_password_nueva';
        return addMessage('bot','Ingresa la nueva contraseña (minimo 8 caracteres, con letras, numeros y un caracter especial):');
      }

      if (state.step === 'perfil_password_nueva') {
        if (text.length < 8 || !/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(text)) {
          return addMessage('bot','La nueva contraseña debe tener minimo 8 caracteres e incluir letras, numeros y un caracter especial.');
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
          return addMessage('bot','La confirmacion no coincide. Intenta de nuevo.');
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
      return addMessage('bot','Usa los botones para elegir una opcion. Escribe "menu" para volver.');
    }
  });

  // Mensaje inicial
  startIdentidad();
});
</script>
@endpush
