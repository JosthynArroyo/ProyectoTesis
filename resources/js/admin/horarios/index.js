document.addEventListener('DOMContentLoaded',()=>{
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

  const dayTrack = document.querySelector('[data-day-track]');
  const sliderButtons = document.querySelectorAll('[data-day-scroll]');

  if(dayTrack && sliderButtons.length){
    const getStep = ()=>{
      const firstCard = dayTrack.querySelector('.day');
      const gap = parseFloat(getComputedStyle(dayTrack).columnGap || getComputedStyle(dayTrack).gap || 0);
      return (firstCard ? firstCard.getBoundingClientRect().width : dayTrack.clientWidth / 2) + gap;
    };

    sliderButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const direction = btn.dataset.dayScroll === 'next' ? 1 : -1;
        dayTrack.scrollBy({left: direction * getStep(), behavior:'smooth'});
      });
    });
  }
});
