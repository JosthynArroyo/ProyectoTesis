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
  pacientes: document.getElementById('k-pacientes'),
  tbody: document.getElementById('tbody-citas'),
};

function estadoClass(s){
  switch((s||'').toLowerCase()){
    case 'pendiente': return 'warning';
    case 'realizada': return 'success';
    case 'confirmada': return 'info';
    case 'cancelada': return 'danger';
    case 'no_se_presento': return 'danger';
    default: return '';
  }
}

function cap(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
function estadoLabel(s){ return (s || '').toLowerCase() === 'no_se_presento' ? 'No se presento' : cap(s); }
function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);
}

function prioridadClass(nivel){
  switch ((nivel || '').toUpperCase()) {
    case 'ALTA': return 'danger';
    case 'MEDIA': return 'warning';
    default: return 'neutral';
  }
}

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
  if (/^\d{2}:\d{2}(:\d{2})$/.test(val)) return val.slice(0,5);
  try {
    const d = new Date(val);
    return new Intl.DateTimeFormat('es-EC', {
      hour:'2-digit', minute:'2-digit', hour12:false, timeZone: TZ
    }).format(d);
  } catch { return val; }
};
// ===============================================

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
    if (els.pacientes) els.pacientes.textContent = k.pacientes ?? 0;

    // --- Tabla ---
    const rows = data.citas || [];
    if (els.tbody){
      if (rows.length === 0){
        els.tbody.innerHTML = `<tr><td colspan="5">Sin citas para hoy.</td></tr>`;
      } else {
        els.tbody.innerHTML = rows.map(c => {
          // Preferir campos ya formateados si existen; si no, helpers
          const fechaCorta = c.fecha_corta || fmtDate(c.fecha);
          const horaCorta  = c.hora_corta || (c.hora ? fmtTime(c.hora) : fmtTime(c.fecha));
          const prioridad = String(c.prioridad || 'BAJA').toUpperCase();
          const prioridadTone = prioridadClass(prioridad);
          const redFlag = c.red_flag ? `<span class="badge danger">Red flag</span>` : '';
          const estadoTone = estadoClass(c.estado);
          return `
            <tr>
              <td data-label="Paciente">${escapeHtml(c.paciente || 'Paciente')}</td>
              <td data-label="Estado"><span class="badge ${estadoTone}">${escapeHtml(estadoLabel(c.estado || ''))}</span></td>
              <td data-label="Prioridad"><span class="badge ${prioridadTone}">${escapeHtml(prioridad)}</span> ${redFlag}</td>
              <td data-label="Fecha">${escapeHtml(fechaCorta)}</td>
              <td data-label="Hora">${escapeHtml(horaCorta)}</td>
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

  const USER_ID = document.querySelector('meta[name="user-id"]').content;
  if (window.Echo && USER_ID){
    window.Echo.private(`doctor.${USER_ID}`).listen('.cita.actualizada', () => {
      refreshDashboard();
    });
  }
});
