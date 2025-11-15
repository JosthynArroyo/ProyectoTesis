const changePhoto=document.getElementById('changePhoto');
const input=document.getElementById('avatarInput');
const preview=document.getElementById('avatarPreview');
if(changePhoto&&input&&preview){
  changePhoto.addEventListener('click',()=>input.click());
  input.addEventListener('change',e=>{
    const f=e.target.files&&e.target.files[0];
    if(!f)return;
    preview.src=URL.createObjectURL(f);
  });
}
document.querySelectorAll('.btn-eye').forEach(btn=>{
  const sel=btn.getAttribute('data-target');
  const target=document.querySelector(sel);
  if(!target)return;
  btn.addEventListener('click',()=>{
    const isPwd=target.type==='password';
    target.type=isPwd?'text':'password';
    const icon=btn.querySelector('.material-symbols-outlined');
    if(icon)icon.textContent=isPwd?'visibility_off':'visibility';
  });
});
const clamp10=el=>el&&el.addEventListener('input',()=>{el.value=el.value.replace(/\D+/g,'').slice(0,10);});
clamp10(document.querySelector('input[name="telefono"]'));
clamp10(document.querySelector('input[name="dni"]'));
