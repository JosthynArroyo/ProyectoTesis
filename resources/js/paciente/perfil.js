const changePhoto = document.getElementById('changePhoto');
const input = document.getElementById('avatarInput');
const preview = document.getElementById('avatarPreview');
const avatarBox = document.getElementById('avatarBox');
const changeBtn = document.getElementById('changePhotoBtn');

if (changePhoto && input && preview && avatarBox) {
  avatarBox.addEventListener('mouseenter', () => {
    if (window.innerWidth > 768) changePhoto.style.opacity = 1;
  });
  avatarBox.addEventListener('mouseleave', () => {
    if (window.innerWidth > 768) changePhoto.style.opacity = 0;
  });
  const openPicker = () => input.click();
  changePhoto.addEventListener('click', openPicker);
  avatarBox.addEventListener('click', openPicker);
  if (changeBtn) changeBtn.addEventListener('click', openPicker);
  avatarBox.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openPicker();
    }
  });
  input.addEventListener('change', (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const url = URL.createObjectURL(f);
    preview.src = url;
  });
}

document.querySelectorAll('.btn-eye').forEach(btn => {
  const sel = btn.getAttribute('data-target');
  const target = document.querySelector(sel);
  if (!target) return;
  btn.addEventListener('click', () => {
    const isPwd = target.type === 'password';
    target.type = isPwd ? 'text' : 'password';
    const icon = btn.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = isPwd ? 'visibility_off' : 'visibility';
  });
});

const clamp10 = el => el && el.addEventListener('input', () => {
  el.value = el.value.replace(/\D+/g,'').slice(0,10);
});
clamp10(document.querySelector('input[name="telefono"]'));
clamp10(document.querySelector('input[name="dni"]'));
