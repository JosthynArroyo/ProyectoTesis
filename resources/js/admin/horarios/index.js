document.addEventListener('DOMContentLoaded',()=>{
  const tabs=document.getElementById('doctor-tabs');
  if(!tabs)return;
  tabs.addEventListener('click',e=>{
    const btn=e.target.closest('.chip');
    if(!btn)return;
    const doctorId=btn.dataset.doctor;
    const url=new URL(window.location.href);
    url.searchParams.set('doctor_id',doctorId);
    if(!url.searchParams.get('week')){
      const inp=document.querySelector('input[name="week"]');
      if(inp&&inp.value)url.searchParams.set('week',inp.value);
    }
    window.location.href=url.toString();
  });
});
