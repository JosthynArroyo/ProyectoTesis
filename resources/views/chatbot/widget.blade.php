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
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }

  #chatbot-toggle {
    position: relative;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg,#1d4ed8,#2563eb);
    color: #fff;
    box-shadow: 0 18px 38px rgba(15,23,42,.25);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-left: auto;
  }

  #chatbot-panel {
    position: absolute;
    bottom: 72px;
    right: 0;
    width: 360px;
    max-width: calc(100vw - 2rem);
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 18px 38px rgba(15,23,42,.18);
    overflow: hidden;
    transform: translateY(12px) scale(.98);
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
    padding: 16px 14px 10px;
    font-size: .9rem;
  }

  #chatbot-input-area {
    border-top: 1px solid #e5e7eb;
    padding: 10px 12px;
    background: #f9fafb;
  }

  #chatbot-form {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  #chatbot-input {
    flex: 1;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    padding: 8px 12px;
    font-size: .9rem;
    outline: none;
    background: #ffffff;
  }

  #chatbot-send-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg,#2563eb,#1d4ed8);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 14px rgba(37,99,235,.35);
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease;
    font-size: 1.05rem;
  }

  #chatbot-send-btn:active {
    transform: scale(.96);
    box-shadow: 0 4px 10px rgba(37,99,235,.45);
  }
</style>
@endonce

<div id="chatbot-widget">
  {{-- Botón flotante --}}
  <button id="chatbot-toggle"
          type="button"
          aria-controls="chatbot-panel"
          aria-expanded="false">
    💬
  </button>

  {{-- Panel del chatbot --}}
  <div id="chatbot-panel" aria-live="polite" aria-hidden="true">
    <div id="chat-log"></div>

    <div id="chatbot-input-area">
      <form id="chatbot-form" autocomplete="off">
        <input id="chatbot-input" type="text" placeholder="Escribe aquí...">
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

  // Estado del chatbot
  const initialBuffer = () => ({
    nombre: '',
    cedula: '',
    email: '',
    telefono: '',
    motivo: '',
    especialidad_id: null,
    doctor_id: null,
    doctor_nombre: '',
    doctor_tarifa: '',
    fecha: '',
    fecha_label: '',
    hora: '',
    paciente_existente: false,
    paciente_email_registrado: '',
    paciente_email_confirmado: false,
    // colecciones
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
    mode: 'menu',
    step: null,
    buffer: initialBuffer(),
    citasEncontradas: [],
  };

  const esCedulaValida = (value) => /^\d{10}$/.test(value);
  const esTelefonoValido = (value) => /^\d{10}$/.test(value);
  const esCorreoValido = (value) => value.includes('@');

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
      b.addEventListener('click', () => handleButton(btn));
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
      if (a === 'ver_citas') return startVerCitas();
      if (a === 'otra_cedula') return solicitarCedula(true);
    if (a === 'soporte') {
      state.mode = 'soporte';
      state.step = 'descripcion';
      return addMessage('bot', 'Describe tu problema:');
    }

    if (a === 'cancelar_cita_id') return seleccionarCitaCancelar(btn.payload.cita_id);
    if (a === 'reagendar_cita_id') return prepararReagendarDesdeId(btn.payload.cita_id);
  }

  // ===================
  //    FLUJO MENÚ
  // ===================
  function showMainMenu() {
    state.mode = 'menu';
    state.step = null;
    state.buffer = initialBuffer();
    state.citasEncontradas = [];

    addMessage('bot', '¿Qué necesitas hoy?');
    addButtons([
      { label:'Agendar cita', action:'agendar' },
      { label:'Cancelar cita', action:'cancelar' },
      { label:'Reagendar cita', action:'reagendar' },
      { label:'Ver mis citas', action:'ver_citas' },
      { label:'Hablar con soporte', action:'soporte' },
    ]);
  }

  // ===================
  //    FLUJO AGENDAR
  // ===================
  function solicitarCedula(mostrarMensaje = true) {
    state.step = 'cedula';
    state.buffer.cedula = '';
    state.buffer.email = '';
    state.buffer.telefono = '';
    state.buffer.motivo = '';
    state.buffer.paciente_existente = false;
    state.buffer.paciente_email_registrado = '';
    state.buffer.paciente_email_confirmado = false;
    if (mostrarMensaje) {
      addMessage('bot','Ingresa tu numero de cedula (10 digitos, solo numeros):');
    }
  }

  async function verificarPacientePorCedula(cedula) {
    addMessage('bot','Validando tu numero de cedula...');
    state.buffer.cedula = cedula;

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
        addMessage('bot', data.message || 'Este numero de cedula no coincide con los datos registrados.');
        addButtons([
          { label:'Ingresar otra cedula', action:'otra_cedula' },
          { label:'Volver al menu', action:'menu' },
        ]);
        state.step = 'cedula';
        return;
      }

      if (data.existe) {
        const paciente = data.paciente || {};
        const nombre = paciente.nombre || state.buffer.nombre || '';

        state.buffer.nombre = nombre || state.buffer.nombre;
        state.buffer.telefono = esTelefonoValido(paciente.telefono || '') ? paciente.telefono : '';
        state.buffer.paciente_existente = true;
        state.buffer.paciente_email_registrado = paciente.email || '';
        state.buffer.paciente_email_confirmado = false;

        if (paciente.email) {
          state.step = 'confirmar_email';
          return addMessage('bot', `Esta cedula pertenece a ${nombre || 'un paciente registrado'}. Ingresa el correo con el que te registraste para confirmar tu identidad:`);
        }

        addMessage('bot','Esta cedula es de un paciente registrado, pero necesitamos tu correo electronico.');
        state.step = 'email';
        return addMessage('bot','Escribe tu correo (debe incluir @):');
      }

      state.buffer.paciente_existente = false;
      addMessage('bot','No encontramos pacientes con esa cedula. Ingresa tu correo (debe incluir @):');
      state.step = 'email';

    } catch (error) {
      console.error(error);
      addMessage('bot','No pude verificar tu cedula en este momento. Intenta nuevamente o vuelve al menu.');
      addButtons([
        { label:'Ingresar otra cedula', action:'otra_cedula' },
        { label:'Volver al menu', action:'menu' },
      ]);
      state.step = 'cedula';
    }
  }

  async function confirmarCorreoRegistrado(email) {
    if (!esCorreoValido(email)) {
      return addMessage('bot','El correo debe incluir @ y no contener espacios.');
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
        body: JSON.stringify({
          cedula: state.buffer.cedula,
          email,
        }),
      });

      const data = await res.json();

      if (!res.ok || data.ok === false) {
        addMessage('bot', data.message || 'El correo no coincide con el paciente registrado.');
        return;
      }

      state.buffer.email = email;
      state.buffer.paciente_email_confirmado = true;

      if (!state.buffer.telefono) {
        state.step = 'telefono';
        return addMessage('bot','Necesitamos un numero de telefono de 10 digitos para continuar:');
      }

      addMessage('bot', `${state.buffer.nombre || 'Paciente'}, para que especialidad quieres agendar?`);
      return pedirEspecialidades();
    } catch (error) {
      console.error(error);
      addMessage('bot','No pude verificar tu correo en este momento. Intenta nuevamente.');
    }
  }

  function startAgendar() {
    state.mode = 'agendar';
    state.step = 'nombre';
    state.buffer = initialBuffer();
    addMessage('bot','Vamos a agendar tu cita.\n\nEscribe tu nombre completo:');
  }

  // Especialidades
  async function pedirEspecialidades() {
    try {
      const res = await fetch(`${baseUrl}/chatbot/especialidades`);
      const data = await res.json();
      state.buffer.especialidades = data;

      let txt = 'Especialidades disponibles:\n\n';
      data.forEach((e,i)=> txt += `${i+1}) ${e.nombre}\n`);
      addMessage('bot', txt.trim());
      state.step = 'especialidad';
      addMessage('bot','Escribe el número de la especialidad:');
    } catch (error) {
      addMessage('bot','No pude cargar las especialidades.');
      showMainMenu();
    }
  }

  // Doctores por especialidad
  async function pedirDoctores() {
    addMessage('bot','Buscando médicos disponibles...');
    try {
      const res = await fetch(`${baseUrl}/chatbot/especialidades/${state.buffer.especialidad_id}/doctores`);
      const data = await res.json();

      if (!data.ok || !data.doctores.length) {
        addMessage('bot', data.message || 'No hay médicos activos para esta especialidad.');
        return showMainMenu();
      }

      state.buffer.doctores = data.doctores;
      let txt = `Médicos disponibles (${data.especialidad}):\n\n`;
      data.doctores.forEach((d,i)=> {
        const tarifa = d.precio_format ? ` - ${d.precio_format}` : '';
        txt += `${i+1}) ${d.nombre}${tarifa}\n`;
      });
      addMessage('bot', txt.trim());
      state.step = 'doctor';
      addMessage('bot','Escribe el número del médico:');
    } catch (error) {
      addMessage('bot','Error cargando los médicos.');
      showMainMenu();
    }
  }

  // Fechas disponibles para un doctor
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

      let txt = `Fechas disponibles con ${doctor.nombre}:\n\n`;
      data.fechas.forEach((f,i)=> txt += `${i+1}) ${f.label}\n`);
      addMessage('bot', txt.trim());
      if (contexto === 'agendar') {
        state.step = 'fecha';
        addMessage('bot','Escribe el número de la fecha:');
      } else {
        state.step = 'reagendar_fecha';
        addMessage('bot','Elige la nueva fecha escribiendo su número:');
      }
    } catch (error) {
      addMessage('bot','No pude cargar las fechas.');
      showMainMenu();
    }
  }

  // Horarios libres en una fecha
  async function pedirHorarios(fecha, contexto = 'agendar') {
    addMessage('bot','Consultando horarios libres...');
    try {
      const res = await fetch(`${baseUrl}/api/doctor/${state.buffer.doctor_id}/fecha/${fecha}/slots`);
      const data = await res.json();
      const libres = (data.slots || []).filter(slot => slot.estado === 'libre');

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

      let txt = `Horarios disponibles (${fecha}):\n\n`;
      libres.forEach((slot,i)=> txt += `${i+1}) ${slot.hora}\n`);
      addMessage('bot', txt.trim());

      if (contexto === 'agendar') {
        state.step = 'hora';
        addMessage('bot','Escribe el número del horario:');
      } else {
        state.step = 'reagendar_hora';
        addMessage('bot','Elige la nueva hora escribiendo su número:');
      }
    } catch (error) {
      addMessage('bot','No pude obtener los horarios.');
      showMainMenu();
    }
  }

  // Agendar cita al backend
  async function enviarAgendar() {
    addMessage('bot','Guardando tu cita...');

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
        msg += '\nSe creó una cuenta y te enviamos una contraseña temporal a tu correo.';
      }

      addMessage('bot', msg);
      addButtons([{ label:'Volver al menú', action:'menu' }]);
      state.mode = 'menu';
      state.step = null;

    } catch (error) {
      addMessage('bot','Error interno al agendar.');
      showMainMenu();
    }
  }

  // =========================
  //  CANCELAR / REAGENDAR / VER
  // =========================
  function startCancelar() {
    state.mode = 'cancelar';
    state.step = 'identificacion';
    addMessage('bot','Para cancelar una cita, escribe tu cédula o tu correo:');
  }

  function startReagendar() {
    state.mode = 'reagendar';
    state.step = 'identificacion';
    addMessage('bot','Para reprogramar, escribe tu cédula o tu correo:');
  }

  function startVerCitas() {
    state.mode = 'ver_citas';
    state.step = 'identificacion';
    addMessage('bot','Para ver tus próximas citas, escribe tu cédula o tu correo:');
  }

  async function buscarCitasPorIdentificacion(text) {
    const isEmail = text.includes('@');
    if (!isEmail && !esCedulaValida(text)) {
      addMessage('bot','El numero de cedula debe tener 10 digitos.');
      return;
    }

    const payload = {
      email:  isEmail ? text : null,
      cedula: isEmail ? null : text,
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
        addMessage('bot', data.message || 'No encontré citas activas.');
        return addButtons([{label:'Volver al menú', action:'menu'}]);
      }

      state.citasEncontradas = data.citas;

      let msg = `Paciente: ${data.paciente}\n\nPróximas citas:\n\n`;
      data.citas.forEach((c,i)=>{
        msg += `${i+1}) ${c.fecha} ${c.hora} - ${c.especialidad} (${c.estado})\n`;
      });

      addMessage('bot', msg.trim());

      if (state.mode==='cancelar') {
        state.step = 'cancelar_cita';
        addMessage('bot','Selecciona la cita a cancelar (puedes escribir el número o tocar un botón):');
        addButtons(data.citas.map((c,i)=>({
          label:`${i+1}) ${c.fecha} ${c.hora}`,
          action:'cancelar_cita_id',
          payload:{ cita_id:c.id }
        })));
      }

      if (state.mode==='reagendar') {
        state.step = 'reagendar_cita';
        addMessage('bot','Selecciona la cita a reprogramar (escribe el número o toca un botón):');
        addButtons(data.citas.map((c,i)=>({
          label:`${i+1}) ${c.fecha} ${c.hora}`,
          action:'reagendar_cita_id',
          payload:{ cita_id:c.id }
        })));
      }

      if (state.mode==='ver_citas') {
        addButtons([{label:'Volver al menú', action:'menu'}]);
      }

    } catch (error) {
      addMessage('bot','Error buscando tus citas.');
      showMainMenu();
    }
  }

  // Cancelar
  function seleccionarCitaCancelar(id) {
    confirmarCancelacion(id);
  }

  function seleccionarCitaCancelarPorIndice(idx) {
    const cita = state.citasEncontradas[idx];
    if (!cita) {
      return addMessage('bot','Número inválido, intenta nuevamente.');
    }
    seleccionarCitaCancelar(cita.id);
  }

  function confirmarCancelacion(id) {
    state.buffer.cita_id = id;
    state.step = 'confirmar_cancelacion';
    addMessage('bot','¿Confirmas cancelar esta cita? (sí/no)');
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
      addButtons([{label:'Volver al menú', action:'menu'}]);
    } catch (error) {
      addMessage('bot','Error cancelando la cita.');
      showMainMenu();
    }
  }

  // Reagendar
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
          hora:state.buffer.nueva_hora
        })
      });

      const data = await res.json();
      addMessage('bot', data.message || 'Cita reprogramada.');
      addButtons([{label:'Volver al menú', action:'menu'}]);

    } catch (error) {
      addMessage('bot','Error reprogramando la cita.');
      showMainMenu();
    }
  }

  // =========================
  //  SOPORTE
  // =========================
  // (solo guarda mensaje por ahora)

  // =========================
  //  MANEJO DEL INPUT
  // =========================
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addMessage('user', text);
    input.value = '';

    // Flujo AGENDAR
    if (state.mode === 'agendar') {
      if (state.step === 'nombre') {
        state.buffer.nombre = text;
        return solicitarCedula();
      }
      if (state.step === 'cedula') {
        if (!esCedulaValida(text)) {
          return addMessage('bot','El numero de cedula debe tener exactamente 10 digitos.');
        }
        return verificarPacientePorCedula(text);
      }
      if (state.step === 'confirmar_email') {
        return confirmarCorreoRegistrado(text);
      }
      if (state.step === 'email') {
        if (!esCorreoValido(text)) {
          return addMessage('bot','El correo debe incluir un @ y no tener espacios.');
        }
        state.buffer.email = text;
        state.buffer.paciente_email_confirmado = state.buffer.paciente_existente;

        if (state.buffer.paciente_existente) {
          if (!state.buffer.telefono) {
            state.step = 'telefono';
            return addMessage('bot','Necesitamos un numero de telefono de 10 digitos para continuar:');
          }
          addMessage('bot', `${state.buffer.nombre || 'Paciente'}, para que especialidad quieres agendar?`);
          return pedirEspecialidades();
        }

        state.step = 'telefono';
        return addMessage('bot','Escribe tu numero de telefono de 10 digitos:');
      }
      if (state.step === 'telefono') {
        if (!esTelefonoValido(text)) {
          return addMessage('bot','El telefono debe tener 10 digitos, solo numeros.');
        }
        state.buffer.telefono = text;
        if (state.buffer.paciente_existente) {
          return pedirEspecialidades();
        }
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
          resumen += `
Tarifa: ${state.buffer.doctor_tarifa}`;
        }
        resumen += `

Confirmas? (si/no)`;
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
    // Flujo CANCELAR / REAGENDAR / VER
    if (['cancelar','reagendar','ver_citas'].includes(state.mode)) {
      if (state.step === 'identificacion') {
        return buscarCitasPorIdentificacion(text);
      }

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
          return addMessage('bot',`¿Confirmas reprogramar para ${state.buffer.nueva_fecha_label || state.buffer.nueva_fecha} a las ${state.buffer.nueva_hora}? (sí/no)`);
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

      if (state.mode === 'ver_citas') {
        // solo se listan, no hay pasos extra
        return;
      }
    }

    // Flujo SOPORTE
    if (state.mode === 'soporte') {
      if (state.step === 'descripcion') {
        addMessage('bot','Gracias, tu mensaje fue registrado. Un miembro del equipo lo revisará.');
        addButtons([{label:'Volver al menú', action:'menu'}]);
        state.mode = 'menu';
        state.step = null;
      }
    }
  });

  // Mensaje inicial
  addMessage('bot','Hola, soy el asistente virtual.');
  showMainMenu();
});
</script>
@endpush
