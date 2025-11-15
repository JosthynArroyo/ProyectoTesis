document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-copy]').forEach(btn=>{
    const sel=btn.getAttribute('data-copy');
    const el=document.querySelector(sel);
    if(!el)return;
    btn.addEventListener('click',async()=>{
      const text=el.textContent.trim();
      try{
        await navigator.clipboard.writeText(text);
        btn.classList.add('copied');
        setTimeout(()=>btn.classList.remove('copied'),800);
      }catch(e){}
    });
  });
});
