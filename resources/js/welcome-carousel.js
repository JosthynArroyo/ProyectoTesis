// resources/js/welcome-carousel.js
(function () {
  const root = document.querySelector('[data-carousel]');
  if (!root) return;

  const track = root.querySelector('[data-carousel-track]');
  const slides = Array.from(track.children);
  const prevBtn = root.querySelector('[data-carousel-prev]');
  const nextBtn = root.querySelector('[data-carousel-next]');
  const dots = Array.from(root.querySelectorAll('[data-carousel-dot]'));

  let index = 0;
  let timer = null;
  const DURATION = 2000;

  function setActive(i){
    index = (i + slides.length) % slides.length;
    track.style.transform = `translate3d(${-index*100}%,0,0)`;
    dots.forEach((d,k)=>d.classList.toggle('is-active',k===index));
  }
  function next(){ setActive(index+1); }
  function prev(){ setActive(index-1); }
  function start(){ stop(); timer = setInterval(next, DURATION); }
  function stop(){ if(timer) clearInterval(timer); }

  setActive(0); start();

  nextBtn?.addEventListener('click', ()=>{ next(); start(); });
  prevBtn?.addEventListener('click', ()=>{ prev(); start(); });
  dots.forEach((d,i)=> d.addEventListener('click', ()=>{ setActive(i); start(); }));

  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);

  window.addEventListener('resize', ()=> setActive(index));
})();
