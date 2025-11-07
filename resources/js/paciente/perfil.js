const changePhoto = document.getElementById('changePhoto');
const input = document.getElementById('avatarInput');
const preview = document.getElementById('avatarPreview');
const avatarBox = document.getElementById('avatarBox');
const changeBtn = document.getElementById('changePhotoBtn');

if (changePhoto && input && preview && avatarBox) {
  // Desktop (hover)
  avatarBox.addEventListener('mouseenter', () => {
    if (window.innerWidth > 768) changePhoto.style.opacity = 1;
  });
  avatarBox.addEventListener('mouseleave', () => {
    if (window.innerWidth > 768) changePhoto.style.opacity = 0;
  });

  // Abrir selector desde overlay, avatar completo o botón
  const openPicker = () => input.click();
  changePhoto.addEventListener('click', openPicker);
  avatarBox.addEventListener('click', openPicker);
  if (changeBtn) changeBtn.addEventListener('click', openPicker);

  // Accesible con teclado
  avatarBox.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openPicker();
    }
  });

  // Previsualización
  input.addEventListener('change', (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const url = URL.createObjectURL(f);
    preview.src = url;
  });
}
