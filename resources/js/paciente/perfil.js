import { setupFaceEnrollment } from '../face-enrollment.js';
import { setupProfileAvatarPicker } from '../profile-avatar-picker.js';

document.addEventListener('DOMContentLoaded', () => {
  setupAvatarPicker();
  initFaceEnrollment();
});

function setupAvatarPicker() {
  const changePhoto = document.getElementById('changePhoto');
  const avatarBox = document.getElementById('avatarBox');

  if (!changePhoto || !avatarBox) return;

  avatarBox.addEventListener('mouseenter', () => {
    if (window.innerWidth > 768) changePhoto.style.opacity = 1;
  });
  avatarBox.addEventListener('mouseleave', () => {
    if (window.innerWidth > 768) changePhoto.style.opacity = 0;
  });

  setupProfileAvatarPicker({
    triggerSelectors: ['#avatarBox', '#changePhotoBtn'],
    keyboardTriggerSelectors: ['#avatarBox'],
  });
}

function initFaceEnrollment() {
  const video = document.getElementById('faceEnrollVideo');
  const overlay = document.getElementById('faceEnrollOverlay');
  const button = document.getElementById('faceEnrollButton');
  const status = document.getElementById('faceEnrollStatus');
  const progress = document.getElementById('faceEnrollProgress');

  if (!video || !button) return;

  setupFaceEnrollment({
    video,
    overlay,
    button,
    status,
    progress,
    enrollUrl: button.dataset.enrollUrl || '',
    csrfToken: button.dataset.csrf || '',
    modelsUrl: button.dataset.modelsUrl || '/models',
    hasFace: button.dataset.hasFace === '1',
    savedStatus: button.dataset.savedStatus || '',
    returnUrl: button.dataset.returnUrl || '',
    returnAnchor: button.dataset.returnAnchor || '',
  });
}
