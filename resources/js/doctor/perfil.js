import { setupFaceEnrollment } from '../face-enrollment.js';
import { setupProfileAvatarPicker } from '../profile-avatar-picker.js';

document.addEventListener('DOMContentLoaded', () => {
  setupAvatarPicker();
  initFaceEnrollment();
});

function setupAvatarPicker() {
  setupProfileAvatarPicker({
    triggerSelectors: ['#changePhoto', '#avatarPreview'],
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
