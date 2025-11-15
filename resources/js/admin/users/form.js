function selectedRoleText(){
  const sel=document.getElementById('role_id');
  if(!sel)return'';
  return sel.options[sel.selectedIndex]?.text?.toLowerCase()||'';
}
function toggleDoctorFields(){
  const sel=document.getElementById('role_id');
  if(!sel)return;
  const preset=sel.getAttribute('data-preset-role')||'';
  const isDoctor=preset==='doctor'||selectedRoleText().includes('doctor');
  const esp=document.getElementById('doctor-only-esp');
  const precio=document.getElementById('doctor-only-precio');
  if(esp)esp.style.display=isDoctor?'':'none';
  if(precio)precio.style.display=isDoctor?'':'none';
}
document.addEventListener('DOMContentLoaded',()=>{
  const sel=document.getElementById('role_id');
  if(sel)sel.addEventListener('change',toggleDoctorFields);
  toggleDoctorFields();
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
});
