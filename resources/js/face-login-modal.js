import { initFaceApi, captureDescriptor } from './face-auth.js';

const FACE_STATES = {
  initial: {
    title: 'Listo para escanear',
    message: 'Mantén tu rostro dentro del recuadro y presiona el botón para iniciar.',
    hint: 'Este acceso solo funciona si ya registraste tu rostro desde tu perfil.',
    overlay: 'Cámara lista.',
    icon: 'ri-scan-2-line',
    iconClass: 'bg-teal-50 text-teal-700',
    panelClass: 'border-slate-200 bg-slate-50',
    button: 'Escanear rostro',
    disabled: false,
    showPasswordButton: true,
  },
  scanning: {
    title: 'Escaneando rostro',
    message: 'Mira al frente y mantén buena iluminación mientras se captura tu rostro.',
    hint: '',
    overlay: 'Escaneando...',
    icon: 'ri-focus-3-line',
    iconClass: 'bg-sky-50 text-sky-700',
    panelClass: 'border-sky-200 bg-sky-50',
    button: 'Escaneando...',
    disabled: true,
    showPasswordButton: false,
  },
  processing: {
    title: 'Procesando reconocimiento',
    message: 'Estamos comparando la captura con los rostros registrados.',
    hint: '',
    overlay: 'Procesando...',
    icon: 'ri-loader-4-line animate-spin',
    iconClass: 'bg-amber-50 text-amber-700',
    panelClass: 'border-amber-200 bg-amber-50',
    button: 'Procesando...',
    disabled: true,
    showPasswordButton: false,
  },
  recognized: {
    title: 'Rostro reconocido',
    message: 'Acceso validado correctamente. Redirigiendo a tu panel.',
    hint: '',
    overlay: 'Rostro reconocido.',
    icon: 'ri-checkbox-circle-line',
    iconClass: 'bg-emerald-50 text-emerald-700',
    panelClass: 'border-emerald-200 bg-emerald-50',
    button: 'Redirigiendo...',
    disabled: true,
    showPasswordButton: false,
  },
  not_recognized: {
    title: 'Rostro no reconocido',
    message: 'No encontramos coincidencia con un rostro registrado.',
    hint: 'Puedes reintentar con mejor iluminación o ingresar con correo y contraseña.',
    overlay: 'No reconocido.',
    icon: 'ri-error-warning-line',
    iconClass: 'bg-rose-50 text-rose-700',
    panelClass: 'border-rose-200 bg-rose-50',
    button: 'Reintentar',
    disabled: false,
    showPasswordButton: true,
  },
  not_registered: {
    title: 'No hay rostro registrado',
    message: 'Para usar esta opción, primero inicia sesión con correo y contraseña y registra tu rostro desde tu perfil.',
    hint: '',
    overlay: 'Sin rostro registrado.',
    icon: 'ri-user-unfollow-line',
    iconClass: 'bg-amber-50 text-amber-700',
    panelClass: 'border-amber-200 bg-amber-50',
    button: 'Escanear rostro',
    disabled: true,
    showPasswordButton: true,
  },
  camera_error: {
    title: 'No se pudo usar la cámara',
    message: 'Permite el acceso a la cámara en tu navegador o revisa que no esté siendo usada por otra aplicación.',
    hint: 'También puedes ingresar con correo y contraseña.',
    overlay: 'Cámara no disponible.',
    icon: 'ri-camera-off-line',
    iconClass: 'bg-rose-50 text-rose-700',
    panelClass: 'border-rose-200 bg-rose-50',
    button: 'Reintentar cámara',
    disabled: false,
    showPasswordButton: true,
  },
};

const STATE_ALIASES = {
  recognized: 'recognized',
  face_recognized: 'recognized',
  not_recognized: 'not_recognized',
  face_not_recognized: 'not_recognized',
  not_registered: 'not_registered',
  face_not_registered: 'not_registered',
  no_registered_faces: 'not_registered',
  camera_error: 'camera_error',
};

const FACE_CAPTURE_TIMEOUT_MS = 5000;

(() => {
  const modal = document.getElementById('loginModal');
  if (!modal) return;

  const tabButtons = modal.querySelectorAll('[data-login-tab]');
  const panels = modal.querySelectorAll('[data-login-panel]');
  const faceTabDisabled = modal.dataset.faceTabDisabled === '1';
  const facePanel = modal.querySelector('[data-login-panel="face"]');
  if (!facePanel) return;

  const faceForm = facePanel.querySelector('#faceLoginForm');
  if (!faceForm) return;

  const statusBox = faceForm.querySelector('[data-face-status]');
  const stateTitle = faceForm.querySelector('[data-face-state-title]');
  const stateMessage = faceForm.querySelector('[data-face-state-message]');
  const stateHint = faceForm.querySelector('[data-face-state-hint]');
  const stateIcon = faceForm.querySelector('[data-face-state-icon]');
  const overlay = faceForm.querySelector('[data-face-overlay]');
  const video = faceForm.querySelector('#faceLoginVideo');
  const submitButton = faceForm.querySelector('#faceLoginSubmit');
  const passwordButton = faceForm.querySelector('[data-face-password-tab]');
  const modelsUrl = faceForm.dataset.modelsUrl || '/models';

  let stream = null;
  let submitting = false;
  let modelsPromise = null;

  function resolveState(name) {
    return STATE_ALIASES[name] || name || 'initial';
  }

  function setState(name, overrides = {}) {
    const stateName = resolveState(name);
    const baseState = FACE_STATES[stateName] || FACE_STATES.initial;
    const state = { ...baseState, ...overrides };

    if (!state || !statusBox) return;

    faceForm.dataset.faceState = stateName;
    statusBox.dataset.faceState = stateName;
    statusBox.className = `rounded-lg border p-4 ${state.panelClass}`;

    if (stateTitle) stateTitle.textContent = state.title;
    if (stateMessage) stateMessage.textContent = state.message;
    if (overlay) overlay.textContent = state.overlay;

    if (stateHint) {
      stateHint.textContent = state.hint || '';
      stateHint.hidden = !state.hint;
    }

    if (stateIcon) {
      stateIcon.className = `inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${state.iconClass}`;
      stateIcon.innerHTML = `<i class="${state.icon} text-lg" aria-hidden="true"></i>`;
    }

    if (submitButton) {
      submitButton.textContent = state.button;
      submitButton.disabled = state.disabled;
    }

    if (passwordButton) {
      passwordButton.hidden = !state.showPasswordButton;
    }
  }

  function toggleTabs(active) {
    tabButtons.forEach((btn) => {
      const isActive = btn.dataset.loginTab === active;
      btn.classList.toggle('is-active', isActive);
      btn.setAttribute('aria-selected', String(isActive));
    });

    panels.forEach((panel) => {
      const isTarget = panel.dataset.loginPanel === active;
      panel.hidden = !isTarget;
      panel.classList.toggle('is-active', isTarget);
    });
  }

  function normalizeCameraError(error) {
    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
      return 'El navegador no tiene permiso para usar la cámara. Activa el permiso o ingresa con correo y contraseña.';
    }

    if (error?.name === 'NotFoundError' || error?.name === 'DevicesNotFoundError') {
      return 'No se encontró una cámara disponible en este dispositivo.';
    }

    if (error?.name === 'NotReadableError' || error?.name === 'TrackStartError') {
      return 'La cámara está ocupada por otra aplicación. Ciérrala e intenta de nuevo.';
    }

    return error?.message || FACE_STATES.camera_error.message;
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

  async function startCamera() {
    if (!video) {
      throw new Error('No hay un visor de cámara disponible.');
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('Este navegador no permite acceder a la cámara desde esta página.');
    }

    if (!stream) {
      stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: 'user',
          width: { ideal: 640 },
          height: { ideal: 480 },
        },
        audio: false,
      });
      video.srcObject = stream;
    }

    video.muted = true;
    video.playsInline = true;
    await video.play().catch(() => {});
    preloadModels();

    return stream;
  }

  function stopCamera() {
    if (!stream) return;
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
    if (video) video.srcObject = null;
  }

  async function prepareFacePanel() {
    setState('initial', { overlay: 'Activando cámara...' });

    try {
      await startCamera();
      setState('initial', {
        overlay: 'Cámara lista.',
        message: 'La cámara ya está activa. Presiona el botón para escanear tu rostro.',
      });
    } catch (error) {
      setState('camera_error', { message: normalizeCameraError(error) });
    }
  }

  async function switchTab(target) {
    if (target === 'face' && faceTabDisabled) {
      toggleTabs('password');
      stopCamera();
      return;
    }

    toggleTabs(target);

    if (target === 'face') {
      await prepareFacePanel();
      return;
    }

    stopCamera();
    submitting = false;
    setState('initial');
  }

  function messageFromPayload(payload, fallback) {
    return payload?.message
      || payload?.errors?.descriptor?.[0]
      || fallback;
  }

  async function readErrorState(response) {
    const payload = await response.json().catch(() => ({}));
    const state = resolveState(payload.state || payload.code);

    if (FACE_STATES[state]) {
      return {
        state,
        message: messageFromPayload(payload, FACE_STATES[state].message),
      };
    }

    return {
      state: 'not_recognized',
      message: messageFromPayload(payload, FACE_STATES.not_recognized.message),
    };
  }

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => switchTab(btn.dataset.loginTab));
  });

  passwordButton?.addEventListener('click', () => switchTab('password'));

  faceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;

    const endpoint = faceForm.dataset.endpoint;
    const csrf = faceForm.dataset.csrf;

    if (!endpoint || !csrf) {
      setState('not_recognized', {
        title: 'Configuración incompleta',
        message: 'No se pudo iniciar el reconocimiento facial en esta pantalla.',
      });
      return;
    }

    submitting = true;
    setState('scanning', {
      overlay: 'Activando cámara...',
      message: 'Estamos abriendo la cámara. Si aparece un permiso del navegador, acéptalo para continuar.',
    });

    try {
      await startCamera();
      setState('scanning', {
        overlay: 'Cámara activa.',
        message: 'Cámara activa. Mantén el rostro dentro del recuadro.',
      });
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
        const errorState = await readErrorState(response);
        setState(errorState.state, { message: errorState.message });
        submitting = false;
        return;
      }

      const data = await response.json();
      setState('recognized', {
        message: data.name
          ? `Hola, ${data.name}. Acceso validado correctamente. Redirigiendo a tu panel.`
          : FACE_STATES.recognized.message,
      });
      stopCamera();
      window.location.href = data.redirect;
    } catch (error) {
      const cameraNames = ['NotAllowedError', 'SecurityError', 'NotFoundError', 'DevicesNotFoundError', 'NotReadableError', 'TrackStartError'];
      const isCameraError = cameraNames.includes(error?.name);
      setState(isCameraError ? 'camera_error' : 'not_recognized', {
        message: isCameraError
          ? normalizeCameraError(error)
          : error?.message || FACE_STATES.not_recognized.message,
      });
      submitting = false;
    }
  });

  const observer = new MutationObserver(() => {
    if (modal.classList.contains('is-open')) {
      preloadModels();
      return;
    }

    if (!modal.classList.contains('is-open')) {
      stopCamera();
      submitting = false;
      setState('initial');
      toggleTabs('password');
    }
  });

  observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
  setState('initial');
  preloadModelsSoon();
})();
