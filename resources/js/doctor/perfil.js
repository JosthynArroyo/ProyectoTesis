import { initFaceApi, captureDescriptor } from '../face-auth.js';

document.addEventListener('DOMContentLoaded', () => {
  setupAvatarPicker();
  setupPasswordToggles();
  clampNumericInput('input[name="telefono"]');
  clampNumericInput('input[name="dni"]');
  setupFaceEnrollment();
});

function setupAvatarPicker() {
  const changePhoto = document.getElementById('changePhoto');
  const input = document.getElementById('avatarInput');
  const preview = document.getElementById('avatarPreview');

  if (!changePhoto || !input || !preview) return;

  changePhoto.addEventListener('click', () => input.click());
  input.addEventListener('change', (event) => {
    const [file] = event.target.files ?? [];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
  });
}

function setupPasswordToggles() {
  document.querySelectorAll('.btn-eye').forEach((btn) => {
    const selector = btn.getAttribute('data-target');
    const target = selector ? document.querySelector(selector) : null;
    if (!target) return;

    btn.addEventListener('click', () => {
      const isPassword = target.type === 'password';
      target.type = isPassword ? 'text' : 'password';

      const icon = btn.querySelector('.material-symbols-outlined');
      if (icon) icon.textContent = isPassword ? 'visibility_off' : 'visibility';
    });
  });
}

function clampNumericInput(selector) {
  const field = document.querySelector(selector);
  if (!field) return;

  field.addEventListener('input', () => {
    field.value = field.value.replace(/\D+/g, '').slice(0, 10);
  });
}

function setupFaceEnrollment() {
  const video = document.getElementById('faceEnrollVideo');
  const overlay = document.getElementById('faceEnrollOverlay');
  const button = document.getElementById('faceEnrollButton');
  const status = document.getElementById('faceEnrollStatus');

  if (!video || !button) return;

  const enrollUrl = button.dataset.enrollUrl || '';
  const csrfToken = button.dataset.csrf || '';
  const modelsUrl = button.dataset.modelsUrl || '/models';
  const hasFace = button.dataset.hasFace === '1';
  const savedStatus = button.dataset.savedStatus || '';

  if (!enrollUrl) {
    status.textContent = 'No se configuró la ruta para guardar el rostro.';
    button.disabled = true;
    return;
  }

  if (hasFace && status) {
    status.textContent = savedStatus || 'Rostro registrado.';
    if (overlay) overlay.textContent = 'Rostro registrado. Si quieres actualizarlo, haz clic en "Actualizar rostro".';
  }

  let stream = null;
  let loading = false;

  async function requestCamera() {
    if (stream) return stream;

    try {
      await initFaceApi(modelsUrl);
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
      video.srcObject = stream;
      video.classList.add('is-active');
      overlay?.classList.add('is-hidden');
      return stream;
    } catch (err) {
      const message =
        err.name === 'NotAllowedError'
          ? 'Debes permitir el uso de la cámara para registrar tu rostro.'
          : err.name === 'NotFoundError'
          ? 'No se encontró una cámara disponible.'
          : err.message || 'No se pudo activar la cámara.';
      throw new Error(message);
    }
  }

  async function handleEnroll() {
    if (loading) return;

    loading = true;
    button.disabled = true;
    status.textContent = 'Activando cámara...';

    try {
      await requestCamera();
      status.textContent = 'Escaneando rostro...';
      const descriptor = await captureDescriptor(video, { timeout: 12000, retryInterval: 400 });

      status.textContent = 'Guardando...';
      const response = await fetch(enrollUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ descriptor }),
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || 'No se pudo guardar el rostro.');
      }

      status.textContent = 'Rostro guardado correctamente.';
      button.textContent = 'Actualizar rostro';
      button.dataset.hasFace = '1';
      if (overlay) overlay.textContent = 'Rostro registrado. Si quieres actualizarlo, haz clic en "Actualizar rostro".';
    } catch (err) {
      status.textContent = err.message || 'No se detectó el rostro. Intenta de nuevo.';
    } finally {
      button.disabled = false;
      loading = false;
    }
  }

  function stopCamera() {
    if (!stream) return;
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
    video.classList.remove('is-active');
    if (overlay) overlay.classList.remove('is-hidden');
  }

  button.addEventListener('click', handleEnroll);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopCamera();
  });
  window.addEventListener('beforeunload', stopCamera);
}
