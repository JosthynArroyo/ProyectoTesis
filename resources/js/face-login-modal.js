import { initFaceApi, captureDescriptor } from './face-auth.js';

(() => {
  const modal = document.getElementById('loginModal');
  if (!modal) return;

  const tabButtons = modal.querySelectorAll('[data-login-tab]');
  const panels = modal.querySelectorAll('[data-login-panel]');
  const facePanel = modal.querySelector('[data-login-panel="face"]');
  const faceForm = facePanel.querySelector('#faceLoginForm');
  const statusEl = faceForm.querySelector('[data-face-status]');
  const video = faceForm.querySelector('#faceLoginVideo');
  const submitButton = faceForm.querySelector('#faceLoginSubmit');
  const modelsUrl = faceForm.dataset.modelsUrl || '/models';

  let stream = null;

  function toggleTabs(active) {
    tabButtons.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.loginTab === active));
    panels.forEach((panel) => {
      const isTarget = panel.dataset.loginPanel === active;
      panel.hidden = !isTarget;
      panel.classList.toggle('is-active', isTarget);
    });
  }

  async function switchTab(target) {
    toggleTabs(target);
    if (target === 'face') {
      try {
        await startCamera();
      } catch (err) {
        statusEl.textContent = err.message || 'No se pudo activar la cámara.';
      }
    } else {
      stopCamera();
    }
  }

  async function startCamera() {
    if (stream || !video) return;
    await initFaceApi(modelsUrl);
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
    video.srcObject = stream;
  }

  function stopCamera() {
    if (!stream) return;
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
    if (video) video.srcObject = null;
  }

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => switchTab(btn.dataset.loginTab));
  });

  faceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!faceForm || !statusEl || !submitButton) return;

    const endpoint = faceForm.dataset.endpoint;
    const csrf = faceForm.dataset.csrf;
    if (!endpoint || !csrf) {
      statusEl.textContent = 'Configuración incompleta.';
      return;
    }

    submitButton.disabled = true;
    statusEl.textContent = 'Escaneando rostro...';

    try {
      await initFaceApi(modelsUrl);
      await startCamera();
      const descriptor = await captureDescriptor(video);

      statusEl.textContent = 'Verificando...';

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ descriptor }),
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || 'No se pudo validar el rostro.');
      }

      const data = await response.json();
      statusEl.textContent = data.name
        ? `Hola, ${data.name}. Rostro reconocido. Redirigiendo...`
        : 'Rostro reconocido. Redirigiendo...';
      window.location.href = data.redirect;
    } catch (err) {
      statusEl.textContent = err.message || 'No se detectó el rostro. Intenta de nuevo.';
      submitButton.disabled = false;
    }
  });

  const observer = new MutationObserver(() => {
    if (!modal.classList.contains('is-open')) {
      stopCamera();
      if (submitButton) submitButton.disabled = false;
      if (statusEl) statusEl.textContent = '';
      toggleTabs('password');
    }
  });

  observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
})();
