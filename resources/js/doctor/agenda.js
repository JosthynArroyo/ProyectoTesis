const host  = document.getElementById('agendaHost');
const wrap  = document.querySelector('.agenda-wrap');
const label = document.getElementById('weekLabel');
const prev  = document.getElementById('prevWeek');
const next  = document.getElementById('nextWeek');
const gotoI = document.getElementById('gotoDate');
const urlTpl = wrap.dataset.slotsUrl; // …/YYYY-MM-DD

const dayNames = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];

/* ======== Utils sin UTC shift ======== */
const pad = n => String(n).padStart(2,'0');
const mkLocal = (y,m,d) => new Date(y, m-1, d);                   // local
const fmtDate = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const parseYMD = s => { const [y,m,d] = s.split('-').map(Number); return mkLocal(y,m,d); };
const addDays = (d,n)=> mkLocal(d.getFullYear(), d.getMonth()+1, d.getDate()+n);
const startOfWeek = d => { const x = mkLocal(d.getFullYear(), d.getMonth()+1, d.getDate()); const dow = (x.getDay()+6)%7; return addDays(x, -dow); }; // lunes

let monday = startOfWeek(new Date());

prev.addEventListener('click', ()=>{ monday = addDays(monday,-7); render(); });
next.addEventListener('click', ()=>{ monday = addDays(monday, 7); render(); });

/* Al elegir una fecha cualquiera, ir al LUNES de esa semana
   y dejar el input mostrando ese lunes para que sea consistente */
gotoI.addEventListener('change', e=>{
  if(!e.target.value) return;
  const chosen = parseYMD(e.target.value);   // local
  monday = startOfWeek(chosen);
  gotoI.value = fmtDate(monday);             // sincroniza el input al lunes
  render();
});

async function fetchDay(dateStr){
  const url = new URL(urlTpl.replace('YYYY-MM-DD', dateStr), window.location.origin);
  try{
    const r = await fetch(url.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!r.ok) return {slots:[]};
    return await r.json();
  }catch{ return {slots:[]}; }
}

function collectTimes(week) {
  const s = new Set();
  week.forEach(d => d.slots.forEach(x => s.add(x.hora)));
  return Array.from(s).sort();
}
function cellFor(state){
  if(state === 'libre')  return `<span class="badge free">Libre</span>`;
  if(state === 'ocupado')return `<span class="badge busy">Ocupado</span>`;
  return `<span class="na">—</span>`;
}

async function render(){
  // Mantén el input siempre sincronizado al lunes actual
  if (document.activeElement !== gotoI) {
    gotoI.value = fmtDate(monday);
  }

  const days = Array.from({length:7}, (_,i)=> addDays(monday,i));
  label.textContent = `Semana ${fmtDate(days[0])} → ${fmtDate(days[6])}`;

  const data = await Promise.all(days.map(d => fetchDay(fmtDate(d))));
  const allTimes = collectTimes(data);

  if(allTimes.length === 0){
    host.innerHTML = `<div class="empty-state">Sin horarios configurados esta semana.</div>`;
    return;
  }

  const maps = data.map(d => new Map(d.slots.map(s => [s.hora, s.estado])));

  let html = `<table class="agenda-table"><thead><tr><th>Hora</th>`;
  days.forEach((d,i)=> html += `<th>${dayNames[i]}<br><small>${fmtDate(d)}</small></th>`);
  html += `</tr></thead><tbody>`;

  allTimes.forEach(h => {
    html += `<tr><th>${h}</th>`;
    maps.forEach(mp => { html += `<td>${cellFor(mp.get(h) ?? null)}</td>`; });
    html += `</tr>`;
  });

  html += `</tbody></table>`;
  host.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', render);
