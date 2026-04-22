import { initFaceApi, captureDescriptor } from '../face-auth.js';

const FACE_CAPTURE_TIMEOUT_MS = 5000;

const STATES = {
  initial: ['Listo para escanear', 'Mantén tu rostro dentro del recuadro y presiona el botón para iniciar.', 'Escanear rostro'],
  scanning: ['Escaneando rostro', 'Mira al frente mientras capturamos tu rostro.', 'Escaneando...'],
  processing: ['Procesando reconocimiento', 'Estamos comparando la captura con los rostros registrados.', 'Procesando...'],
  recognized: ['Rostro reconocido', 'Acceso validado correctamente. Redirigiendo a tu panel.', 'Redirigiendo...'],
  not_recognized: ['Rostro no reconocido', 'No encontramos coincidencia. Reintenta o ingresa con correo y contraseña.', 'Reintentar'],
  not_registered: ['No hay rostro registrado', 'Primero inicia sesión con correo y contraseña y registra tu rostro desde tu perfil.', 'Escanear rostro'],
  camera_error: ['No se pudo usar la cámara', 'Permite el acceso a la cámara en tu navegador o usa correo y contraseña.', 'Reintentar cámara'],
};

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('faceLoginForm');
  const video = document.getElementById('video');
  const loginBtn = document.getElementById('loginBtn');
  const title = document.querySelector('[data-face-state-title]');
  const message = document.querySelector('[data-face-state-message]');
  const overlay = document.querySelector('[data-face-overlay]');

  if (!form || !video || !loginBtn || !title || !message) return;

  const endpoint = form.dataset.endpoint || '';
  const csrf = form.dataset.csrf || '';
  const modelsUrl = form.dataset.modelsUrl || '/models';

  let stream = null;
  let modelsPromise = null;

  function setState(name, customMessage = null) {
    const state = STATES[name] || STATES.initial;
    form.dataset.faceState = name;
    title.textContent = state[0];
    message.textContent = customMessage || state[1];
    loginBtn.textContent = state[2];
    loginBtn.disabled = ['scanning', 'processing', 'recognized', 'not_registered'].includes(name);
    if (overlay) overlay.textContent = state[0];
  }

  function cameraMessage(error) {
    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
      return 'El navegador no tiene permiso para usar la cámara.';
    }

    if (error?.name === 'NotFoundError') {
      return 'No se encontró una cámara disponible.';
    }

    return error?.message || STATES.camera_error[1];
  }

  function loadModels() {
    if (!modelsPromise) {
      modelsPromise = initFaceApi(modelsUrl).catch((error) => {
        modelsPromise = null;
        throw error;
      });
    }

    return modelsPromise;
  }

  function preloadModels() {
    loadModels().catch(() => {});
  }

  function preloadModelsSoon() {
    window.setTimeout(preloadModels, 100);
  }

  async function ensureCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('Este navegador no permite acceder a la cámara desde esta página.');
    }

    if (!stream) {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
      video.srcObject = stream;
    }

    video.muted = true;
    video.playsInline = true;
    await video.play().catch(() => {});
    preloadModels();
  }

  async function readError(response) {
    const payload = await response.json().catch(() => ({}));
    const state = payload.state || (payload.code === 'face_not_registered' ? 'not_registered' : 'not_recognized');

    return {
      state: STATES[state] ? state : 'not_recognized',
      message: payload.message || payload.errors?.descriptor?.[0] || null,
    };
  }

  preloadModelsSoon();

  ensureCamera()
    .then(() => setState('initial'))
    .catch((error) => setState('camera_error', cameraMessage(error)));

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!endpoint || !csrf) {
      setState('not_recognized', 'No se pudo iniciar el reconocimiento facial en esta pantalla.');
      return;
    }

    setState('scanning');

    try {
      await ensureCamera();
      setState('scanning', 'Cámara activa. Mantén el rostro dentro del recuadro.');
      await loadModels();
      setState('scanning');
      const descriptor = await captureDescriptor(video, {
        timeout: FACE_CAPTURE_TIMEOUT_MS,
        retryInterval: 250,
      });

      setState('processing');

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
        const errorState = await readError(response);
        setState(errorState.state, errorState.message);
        return;
      }

      const data = await response.json();
      setState('recognized');
      window.location.href = data.redirect;
    } catch (error) {
      const isCameraError = ['NotAllowedError', 'SecurityError', 'NotFoundError', 'NotReadableError'].includes(error?.name);
      setState(isCameraError ? 'camera_error' : 'not_recognized', isCameraError ? cameraMessage(error) : error?.message);
    }
  });
});
