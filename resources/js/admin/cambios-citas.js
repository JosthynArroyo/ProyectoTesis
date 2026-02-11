document.addEventListener('DOMContentLoaded', ()=>{
  const toggle = document.querySelector('[data-filter-toggle]');
  const panel = document.querySelector('[data-filter-panel]');

  if(toggle && panel){
    const setLabel = (open)=>{ toggle.innerHTML = `<i class="ri-filter-3-line" aria-hidden="true"></i>${open ? 'Ocultar filtros' : 'Mostrar filtros'}`; };
    setLabel(panel.classList.contains('is-open'));
    toggle.addEventListener('click', ()=>{
      const open = panel.classList.toggle('is-open');
      setLabel(open);
    });
  }
});
