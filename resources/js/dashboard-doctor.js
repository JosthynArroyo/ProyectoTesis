const themeToggler = document.querySelector('.theme-toggler');

themeToggler?.addEventListener('click', () => {
  document.body.classList.toggle('dark-theme-variables');
  themeToggler.querySelector('span:nth-child(1)')?.classList.toggle('active');
  themeToggler.querySelector('span:nth-child(2)')?.classList.toggle('active');
});

// ---- Dashboard data ----
const meta = document.querySelector('meta[name="doctor-dashboard-data"]');
const ENDPOINT = meta ? meta.content : '';

const els = {
  hoy: document.getElementById('k-hoy'),
  realizadas: document.getElementById('k-realizadas'),
  pendientes: document.getElementById('k-pendientes'),
  conf2h: document.getElementById('k-conf-2h'),
  real2h: document.getElementById('k-real-2h'),
  canc2h: document.getElementById('k-canc-2h'),
  tbody: document.getElementById('tbody-citas'),
  circles: document.querySelectorAll('.insights .progress svg circle'),
  numbers: document.querySelectorAll('.insights .progress .number'),
};

function estadoClass(s){
  switch((s||'').toLowerCase()){
    case 'pendiente': return 'warning';
    case 'realizada': return 'success';
    case 'confirmada': return 'info';
    case 'cancelada': return 'danger';
    default: return '';
  }
}

function cap(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

// ====== FORMATEO DE FECHA Y HORA (corto) ======
const TZ = 'America/Guayaquil';

const fmtDate = (val) => {
  // Acepta ISO o 'YYYY-MM-DD' y devuelve 'dd/mm/yyyy'
  if (!val) return '';
  try {
    // Si ya viene corto, no tocar
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(val)) return val;
    // Si viene 'YYYY-MM-DD', conviértelo primero a Date seguro
    if (/^\d{4}-\d{2}-\d{2}$/.test(val)) {
      const [y,m,d] = val.split('-').map(Number);
      const date = new Date(Date.UTC(y, m-1, d));
      return new Intl.DateTimeFormat('es-EC', {
        day:'2-digit', month:'2-digit', year:'numeric', timeZone: TZ
      }).format(date);
    }
    const d = new Date(val);
    return new Intl.DateTimeFormat('es-EC', {
      day:'2-digit', month:'2-digit', year:'numeric', timeZone: TZ
    }).format(d);
  } catch { return val; }
};

const fmtTime = (val) => {
  // Devuelve HH:mm
  if (!val) return '';
  // Si ya viene 'HH:mm' o 'HH:mm:ss', recortar
  if (/^\d{2}:\d{2}(:\d{2})?$/.test(val)) return val.slice(0,5);
  try {
    const d = new Date(val);
    return new Intl.DateTimeFormat('es-EC', {
      hour:'2-digit', minute:'2-digit', hour12:false, timeZone: TZ
    }).format(d);
  } catch { return val; }
};
// ===============================================

// Dibuja el aro de progreso (SVG circle)
function setCircle(idx, percent){
  const c = els.circles?.[idx];
  if (!c) return;
  const r = Number(c.getAttribute('r') || 30);
  const circ = 2 * Math.PI * r;
  const p = Math.max(0, Math.min(percent ?? 0, 100));
  c.style.strokeDasharray = `${circ}`;
  c.style.strokeDashoffset = `${circ - (circ * p) / 100}`;
  if (els.numbers?.[idx]) els.numbers[idx].textContent = `${Math.round(p)}%`;
}

async function refreshDashboard(){
  if (!ENDPOINT) return;
  try{
    const r = await fetch(ENDPOINT, { headers: { 'Accept':'application/json','X-Requested-With':'XMLHttpRequest' }, cache:'no-store' });
    if(!r.ok) throw new Error('HTTP '+r.status);
    const data = await r.json();

    // --- KPIs ---
    const k = data.kpis || {};
    if (els.hoy) els.hoy.textContent = k.hoy ?? 0;
    if (els.realizadas) els.realizadas.textContent = k.realizadas ?? 0;
    if (els.pendientes) els.pendientes.textContent = k.pendientes ?? 0;
    if (els.conf2h) els.conf2h.textContent = k.confirmadas_2h ?? 0;
    if (els.real2h) els.real2h.textContent = k.realizadas_2h ?? 0;
    if (els.canc2h) els.canc2h.textContent = k.canceladas_2h ?? 0;

    // Aros: 0) hoy (solo decorativo), 1) % atendidas de hoy, 2) % pendientes de hoy
    const totalHoy = Number(k.hoy ?? 0);
    const pReal = totalHoy > 0 ? (Number(k.realizadas ?? 0) / totalHoy) * 100 : 0;
    const pPend = totalHoy > 0 ? (Number(k.pendientes ?? 0) / totalHoy) * 100 : 0;
    setCircle(0, totalHoy > 0 ? 100 : 0); // si hay citas, llena; si no, vacío
    setCircle(1, pReal);
    setCircle(2, pPend);

    // --- Tabla ---
    const rows = data.citas || [];
    if (els.tbody){
      if (rows.length === 0){
        els.tbody.innerHTML = `<tr><td colspan="4">Sin citas para hoy.</td></tr>`;
      } else {
        els.tbody.innerHTML = rows.map(c => {
          // Preferir campos ya formateados si existen; si no, helpers
          const fechaCorta = c.fecha_corta ?? fmtDate(c.fecha);
          const horaCorta  = c.hora ?? fmtTime(c.fecha);
          return `
            <tr>
              <td>${c.paciente ?? 'Paciente'}</td>
              <td class="${estadoClass(c.estado)}">${cap(c.estado || '')}</td>
              <td>${fechaCorta}</td>
              <td>${horaCorta}</td>
            </tr>
          `;
        }).join('');
      }
    }
  }catch(e){
    console.error('Error refrescando dashboard del doctor:', e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  refreshDashboard();
  setInterval(refreshDashboard, 15000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshDashboard(); });

  const USER_ID = document.querySelector('meta[name="user-id"]')?.content;
  if (window.Echo && USER_ID){
    window.Echo.private(`doctor.${USER_ID}`).listen('.cita.actualizada', () => {
      refreshDashboard();
    });
  }
});
