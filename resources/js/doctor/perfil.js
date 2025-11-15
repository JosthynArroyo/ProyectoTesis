// resources/js/doctor/perfil.js
document.addEventListener('DOMContentLoaded', () => {
  const changePhoto = document.getElementById('changePhoto');
  const input = document.getElementById('avatarInput');
  const preview = document.getElementById('avatarPreview');
  if (changePhoto && input && preview) {
    changePhoto.addEventListener('click', () => input.click());
    input.addEventListener('change', (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      preview.src = URL.createObjectURL(file);
    });
  }

  const eyes = document.querySelectorAll('.btn-eye');
  eyes.forEach(btn => {
    const targetSel = btn.getAttribute('data-target');
    const target = document.querySelector(targetSel);
    if (!target) return;
    btn.addEventListener('click', () => {
      const isPwd = target.type === 'password';
      target.type = isPwd ? 'text' : 'password';
      const icon = btn.querySelector('.material-symbols-outlined');
      if (icon) icon.textContent = isPwd ? 'visibility_off' : 'visibility';
    });
  });

  // bloquea >10 dígitos en tiempo real para teléfono y dni
  const only10 = (el) => {
    el.addEventListener('input', () => {
      el.value = el.value.replace(/\D+/g,'').slice(0,10);
    });
  };
  const tel = document.querySelector('input[name="telefono"]');
  const dni = document.querySelector('input[name="dni"]');
  if (tel) only10(tel);
  if (dni) only10(dni);
});
