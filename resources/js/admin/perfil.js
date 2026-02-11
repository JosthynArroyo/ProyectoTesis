import { setupFaceEnrollment } from '../face-enrollment.js';

document.addEventListener('DOMContentLoaded', () => {
  setupAvatarPicker();
  initFaceEnrollment();
});

function setupAvatarPicker() {
  const changePhoto = document.getElementById('changePhoto');
  const input = document.getElementById('avatarInput');
  const preview = document.getElementById('avatarPreview');
  const avatarBox = changePhoto ? changePhoto.closest('.avatar') : null;

  if (!changePhoto || !input || !preview) return;

  changePhoto.addEventListener('click', () => input.click());
  if (avatarBox) avatarBox.addEventListener('click', () => input.click());
  preview.addEventListener('click', () => input.click());
  input.addEventListener('change', (event) => {
    const [file] = event.target.files || [];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
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
