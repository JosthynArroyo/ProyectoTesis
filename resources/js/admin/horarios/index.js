document.addEventListener('DOMContentLoaded',()=>{
  // Mobile day switcher (diaria)
  const dayButtons = document.querySelectorAll('[data-day-btn]');
  const dayPanels = document.querySelectorAll('[data-day-panel]');
  if(dayButtons.length && dayPanels.length){
    dayButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const day = btn.getAttribute('data-day-btn');
        dayButtons.forEach(b=>b.classList.toggle('active', b===btn));
        dayPanels.forEach(p=>p.classList.toggle('is-active', p.getAttribute('data-day-panel')===day));
      });
    });
  }

  // Slider nav for tablet (4 días visibles)
  const dayTrack = document.querySelector('[data-day-track]');
  const sliderButtons = document.querySelectorAll('[data-day-scroll]');
  if(dayTrack && sliderButtons.length){
    const getStep = ()=>{
      const firstCard = dayTrack.querySelector('.day');
      const gap = parseFloat(getComputedStyle(dayTrack).columnGap || getComputedStyle(dayTrack).gap || 0);
      return (firstCard ? firstCard.getBoundingClientRect().width : dayTrack.clientWidth/2) + gap;
    };
    sliderButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const direction = btn.dataset.dayScroll === 'next' ? 1 : -1;
        dayTrack.scrollBy({left: direction * getStep(), behavior:'smooth'});
      });
    });
  }

  // Mobile: mostrar acciones desde un menú de tres puntos
  document.querySelectorAll('.slot-more').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const slot = btn.closest('.slot');
      if(slot) slot.classList.toggle('is-open');
    });
  });
});
