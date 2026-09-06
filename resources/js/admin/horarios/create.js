import { populateHoursSelects, getClinicHoursConfig } from '../../shared/horarios/schedule-common.js';

(function(){
  const form=document.getElementById('form-horario');
  const wrap=document.getElementById('dias-wrap');
  const same=document.getElementById('misma_franja');
  const boxGlobal=document.getElementById('franja-global');
  const boxPerDay=document.getElementById('franjas-por-dia');
  const kpi=document.getElementById('kpi');
  const f1=document.getElementById('fecha_inicio');
  const f2=document.getElementById('fecha_fin');
  const f1Trigger=document.getElementById('admin-horario-fecha-inicio-trigger');
  const f2Trigger=document.getElementById('admin-horario-fecha-fin-trigger');
  const today=f1?.min||'';

  const clinicConfig = getClinicHoursConfig();
  const getInterval = () => parseInt(document.getElementById('intervalo_minutos')?.value || '30', 10);

  function parseLocalDate(s){
    if(!s)return null;
    const [y,m,d]=s.split('-').map(Number);
    return new Date(y,m-1,d);
  }

  function openNativePicker(input){
    if(!input)return;
    input.focus({preventScroll:true});
    if(typeof input.showPicker==='function'){
      try{
        input.showPicker();
        return;
      }catch(_err){}
    }
    input.click();
  }

  function bindDateTrigger(trigger,input){
    if(!trigger||!input)return;
    trigger.addEventListener('click',event=>{
      event.preventDefault();
      openNativePicker(input);
    });
  }

  function syncDateRange(){
    if(!f1||!f2)return;
    f1.min=today;
    f1.max=f2.value||'';
    f2.min=f1.value||'';
  }

  function validDateRange(){
    if(!f1||!f2)return true;
    const startOk=!f1.value||!today||f1.value>=today;
    const rangeOk=!f1.value||!f2.value||f1.value<=f2.value;
    f1.setCustomValidity(startOk ? '' : 'La fecha inicial no puede ser anterior a hoy.');
    f2.setCustomValidity(rangeOk ? '' : 'La fecha final debe ser igual o posterior a la fecha inicial.');
    return startOk&&rangeOk;
  }

  if(wrap){
    wrap.addEventListener('change',e=>{
      if(e.target&&e.target.type==='checkbox'){
        e.target.closest('.day-chip').classList.toggle('active',e.target.checked);
        toggleRows();
        updateKPI();
        syncGlobalSelectors();
      }
    });
  }

  document.querySelectorAll('.btn-mini').forEach(b=>{
    b.addEventListener('click',()=>{
      const map={lv:[1,2,3,4,5],ld:[1,2,3,4,5,6,7],sd:[6,7],none:[]};
      const set=map[b.dataset.preset]||[];
      [...wrap.querySelectorAll('input[type="checkbox"]')].forEach(i=>{
        const on=set.includes(parseInt(i.value,10));
        i.checked=on;
        i.closest('.day-chip').classList.toggle('active',on);
      });
      toggleRows();
      updateKPI();
      syncGlobalSelectors();
    });
  });

  if(same)same.addEventListener('change',syncMode);
  [f1,f2].forEach(i=>i&&['change','input'].forEach(eventName=>{
    i.addEventListener(eventName,()=>{
      syncDateRange();
      validDateRange();
      updateKPI();
    });
  }));

  function syncMode(){
    const on=!!same.checked;
    boxGlobal.classList.toggle('hidden', !on);
    boxPerDay.classList.toggle('hidden', on);
    toggleRows();
    updateKPI();
    syncGlobalSelectors();
  }

  function toggleRows(){
    const checked=new Set([...wrap.querySelectorAll('input[type="checkbox"]:checked')].map(b=>parseInt(b.value,10)));
    boxPerDay.querySelectorAll('.row-dia').forEach(row=>{
      const d=parseInt(row.dataset.dia,10);
      const show=checked.has(d);
      row.style.opacity=show ? '1' : '.35';
      row.querySelectorAll('input, select').forEach(i=>i.disabled=!show);
    });
    if(same.checked)boxPerDay.querySelectorAll('input, select').forEach(i=>i.disabled=true);
  }

  function updateKPI(){
    const start=parseLocalDate(f1.value);
    const end=parseLocalDate(f2.value);
    const selDays=[...wrap.querySelectorAll('input[type="checkbox"]:checked')].map(b=>parseInt(b.value,10));
    let total=0;
    if(start&&end&&start<=end&&selDays.length){
      const d=new Date(start.getFullYear(),start.getMonth(),start.getDate());
      while(d<=end){
        const iso=((d.getDay()+6)%7)+1;
        if(selDays.includes(iso))total++;
        d.setDate(d.getDate()+1);
      }
    }
    kpi.textContent=selDays.length ? `Dias marcados: ${selDays.length}. Fechas afectadas en el rango: ${total}.` : `Selecciona al menos un dia.`;
  }

  function syncGlobalSelectors() {
    const startSelect = document.getElementById('hora_inicio_global');
    const endSelect = document.getElementById('hora_fin_global');
    const infoText = document.getElementById('clinic-hours-info-global');
    if (!startSelect || !endSelect) return;

    const checkboxes = wrap.querySelectorAll('input[type="checkbox"]');
    let maxOpening = null;
    let minClosing = null;
    let checkedCount = 0;
    let closedChecked = false;

    checkboxes.forEach(cb => {
      if (cb.checked) {
        checkedCount++;
        const dayNum = parseInt(cb.value, 10);
        const dayConfig = clinicConfig[dayNum];
        if (!dayConfig || String(dayConfig.status) !== '1') {
          closedChecked = true;
        } else {
          if (maxOpening === null || dayConfig.opening > maxOpening) {
            maxOpening = dayConfig.opening;
          }
          if (minClosing === null || dayConfig.closing < minClosing) {
            minClosing = dayConfig.closing;
          }
        }
      }
    });

    if (checkedCount === 0) {
      startSelect.innerHTML = '<option value="">Selecciona días</option>';
      endSelect.innerHTML = '<option value="">Selecciona días</option>';
      startSelect.disabled = true;
      endSelect.disabled = true;
      if (infoText) infoText.textContent = 'Selecciona al menos un día.';
      return;
    }

    if (closedChecked) {
      startSelect.innerHTML = '<option value="">Día cerrado seleccionado</option>';
      endSelect.innerHTML = '<option value="">Día cerrado seleccionado</option>';
      startSelect.disabled = true;
      endSelect.disabled = true;
      if (infoText) infoText.textContent = 'Has seleccionado un día en que la clínica está cerrada.';
      return;
    }

    const mockDayNum = 99;
    clinicConfig[mockDayNum] = {
      status: '1',
      opening: maxOpening,
      closing: minClosing
    };
    populateHoursSelects(mockDayNum, startSelect, endSelect, infoText, clinicConfig, startSelect.dataset.old, endSelect.dataset.old, getInterval());
  }

  // Populate static per-day selectors
  function initPerDaySelectors() {
    for (let W = 1; W <= 7; W++) {
      const startSelect = document.getElementById(`dias_${W}_hora_inicio`);
      const endSelect = document.getElementById(`dias_${W}_hora_fin`);
      if (startSelect && endSelect) {
        populateHoursSelects(W, startSelect, endSelect, null, clinicConfig, startSelect.dataset.old, endSelect.dataset.old, getInterval());
      }
    }
  }

  bindDateTrigger(f1Trigger,f1);
  bindDateTrigger(f2Trigger,f2);
  syncMode();
  syncDateRange();
  validDateRange();
  initPerDaySelectors();
  syncGlobalSelectors();

  form&&form.addEventListener('submit',e=>{
    syncDateRange();
    if(!validDateRange()){
      e.preventDefault();
      (f1.validationMessage ? f1 : f2).reportValidity();
    }
  });
})();
