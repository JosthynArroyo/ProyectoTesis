document.addEventListener('DOMContentLoaded', () => {
  const date = document.querySelector('input[name="fecha_nacimiento"]');
  const badge = document.getElementById('age-badge');

  function age(){
    if (!badge || !date || !date.value){ if(badge) badge.textContent='—'; return; }
    const b = new Date(date.value), t = new Date();
    if (Number.isNaN(b.getTime())) { badge.textContent='—'; return; }
    let a = t.getFullYear() - b.getFullYear();
    const m = t.getMonth() - b.getMonth();
    if (m < 0 || (m === 0 && t.getDate() < b.getDate())) a--;
    badge.textContent = `${a} años`;
  }
  if (date){ age(); date.addEventListener('change', age); }
});
