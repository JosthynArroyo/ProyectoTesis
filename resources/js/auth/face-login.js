import { initFaceApi, captureDescriptor } from '../face-auth.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('faceLoginForm');
  const video = document.getElementById('video');
  const loginBtn = document.getElementById('loginBtn');
  const errorBox = document.getElementById('error');

  if (!form || !video || !loginBtn || !errorBox) return;

  const endpoint = form.dataset.endpoint || '';
  const csrf = form.dataset.csrf || '';
  const modelsUrl = form.dataset.modelsUrl || '/models';

  if (!endpoint || !csrf) return;

  let stream = null;

  async function ensureCamera() {
    if (stream) return stream;
    await initFaceApi(modelsUrl);
    stream = await navigator.mediaDevices.getUserMedia({ video: true });
    video.srcObject = stream;
    return stream;
  }

  ensureCamera().catch(() => {});

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginBtn.disabled = true;
    errorBox.textContent = '';

    try {
      await ensureCamera();
      const descriptor = await captureDescriptor(video);
      const formData = new FormData(form);
      formData.append('descriptor', JSON.stringify(descriptor));
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: formData,
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || 'No fue posible validar el rostro.');
      }

      const data = await response.json();
      window.location.href = data.redirect;
    } catch (err) {
      errorBox.textContent = err.message || 'No fue posible validar el rostro.';
      loginBtn.disabled = false;
    }
  });
});
