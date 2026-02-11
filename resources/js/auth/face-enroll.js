import { setupFaceEnrollment } from '../face-enrollment.js';

document.addEventListener('DOMContentLoaded', () => {
  const video = document.getElementById('video');
  const captureBtn = document.getElementById('capture');
  const status = document.getElementById('faceEnrollStatus');
  const progress = document.getElementById('faceEnrollProgress');

  if (!video || !captureBtn) return;

  setupFaceEnrollment({
    video,
    button: captureBtn,
    status,
    progress,
    enrollUrl: captureBtn.dataset.enrollUrl || '',
    csrfToken: captureBtn.dataset.csrf || '',
    modelsUrl: captureBtn.dataset.modelsUrl || '/models',
    hasFace: captureBtn.dataset.hasFace === '1',
    savedStatus: captureBtn.dataset.savedStatus || '',
    returnUrl: captureBtn.dataset.returnUrl || '',
    returnAnchor: captureBtn.dataset.returnAnchor || '',
  });
});
