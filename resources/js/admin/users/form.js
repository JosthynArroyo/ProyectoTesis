function selectedRoleText(){
  const sel=document.getElementById('role_id');
  if(!sel)return'';
  return sel.options[sel.selectedIndex].text.toLowerCase()||'';
}
function toggleDoctorFields(){
  const sel=document.getElementById('role_id');
  if(!sel)return;
  const preset=sel.getAttribute('data-preset-role')||'';
  const roleText=selectedRoleText();
  const isDoctor=preset==='doctor'||roleText.includes('doctor');
  const isLab=preset==='laboratorio'||roleText.includes('laboratorio');
  const esp=document.getElementById('doctor-only-esp');
  const precio=document.getElementById('doctor-only-precio');
  if(esp)esp.style.display=isDoctor ? '' : 'none';
  if(precio)precio.style.display=(isDoctor||isLab) ? '' : 'none';

  const espInput=document.getElementById('especialidad_id');
  const precioInput=document.getElementById('precio_consulta');
  if(espInput){
    if(isDoctor) espInput.setAttribute('required','required');
    else espInput.removeAttribute('required');
  }
  if(precioInput){
    if(isDoctor||isLab) precioInput.setAttribute('required','required');
    else precioInput.removeAttribute('required');
  }
}
function togglePatientFlags(){
  const sel=document.getElementById('role_id');
  if(!sel)return;
  const preset=sel.getAttribute('data-preset-role')||'';
  const roleText=selectedRoleText();
  const isPaciente=preset==='paciente'||roleText.includes('paciente');
  const flags=document.getElementById('patient-flags-section');
  if(flags)flags.style.display=isPaciente ? '' : 'none';
}
document.addEventListener('DOMContentLoaded',()=>{
  const sel=document.getElementById('role_id');
  if(sel)sel.addEventListener('change',()=>{
    toggleDoctorFields();
    togglePatientFlags();
  });
  toggleDoctorFields();
  togglePatientFlags();
});
