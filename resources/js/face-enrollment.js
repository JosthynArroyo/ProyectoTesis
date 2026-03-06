import { initFaceApi, detectFaces } from './face-auth.js';

const DEFAULTS = {
  minSamples: 3,
  maxSamples: 5,
  sampleInterval: 240,
  maxDuration: 24000,
  minFaceRatio: 0.05,
  maxFaceRatio: 0.5,
  minBrightness: 30,
  minContrast: 8,
  minScore: 0.35,
  maxMovement: 0.08,
  maxSizeDelta: 0.35,
  maxLandmarkDrift: 0.08,
  minMicroMovement: 0.001,
  maxMicroMovement: 0.04,
  stableFramesTarget: 4,
  livenessTarget: 3,
};

const STATUS = {
  idle: 'Ajustando captura...',
  ready: 'Captura lista',
  noFace: 'No se detecta rostro',
  multiFace: 'Hay mas de un rostro',
  lowLight: 'Iluminacion insuficiente',
  tooFar: 'Rostro demasiado lejos',
  tooClose: 'Rostro demasiado cerca',
  tooMuchMovement: 'Movimiento excesivo',
  lowFocus: 'Camara sin foco',
  verifying: 'Verificando presencia...',
  saving: 'Guardando...',
  cameraStarting: 'Activando cámara...',
};

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function normalizeAnchor(anchor) {
  if (!anchor) return '';
  return anchor.startsWith('#') ? anchor : `#${anchor}`;
}

function buildRedirectUrl(baseUrl, anchor, result) {
  if (!baseUrl) return '';
  const url = new URL(baseUrl, window.location.origin);
  if (result) {
    url.searchParams.set('face', result);
  }
  const hash = normalizeAnchor(anchor);
  if (hash) {
    url.hash = hash;
  }
  return url.toString();
}

function setStatus(statusEl, nextStatus) {
  if (!statusEl) return;
  if (statusEl.textContent === nextStatus) return;
  statusEl.textContent = nextStatus;
}

function setProgress(progressEl, value) {
  if (!progressEl) return;
  const bar = progressEl.querySelector('.face-enroll-progress__bar');
  if (!bar) return;
  const clamped = Math.min(1, Math.max(0, value));
  bar.style.width = `${Math.round(clamped * 100)}%`;
  progressEl.dataset.progress = String(Math.round(clamped * 100));
}

async function waitForVideo(videoElement, timeout = 1500) {
  if (!videoElement) return false;
  if (videoElement.readyState >= 2 && videoElement.videoWidth > 0) return true;

  let resolved = false;
  await new Promise((resolve) => {
    const cleanup = () => {
      videoElement.removeEventListener('loadeddata', onLoad);
      videoElement.removeEventListener('loadedmetadata', onLoad);
    };

    const onLoad = () => {
      cleanup();
      resolved = true;
      resolve();
    };

    videoElement.addEventListener('loadeddata', onLoad);
    videoElement.addEventListener('loadedmetadata', onLoad);
    setTimeout(() => {
      cleanup();
      resolve();
    }, timeout);
  });

  return resolved && videoElement.videoWidth > 0;
}

function pushHistory(history, value, limit) {
  history.push(value);
  if (history.length > limit) {
    history.shift();
  }
}

function average(values) {
  if (!values.length) return 0;
  const total = values.reduce((sum, value) => sum + value, 0);
  return total / values.length;
}

function computeFrameStats(video, ctx, canvas) {
  const width = video.videoWidth;
  const height = video.videoHeight;
  if (!width || !height || !ctx) {
    return { brightness: 0, contrast: 0 };
  }

  const targetWidth = 160;
  const targetHeight = Math.round((height / width) * targetWidth);
  canvas.width = targetWidth;
  canvas.height = targetHeight;
  ctx.drawImage(video, 0, 0, targetWidth, targetHeight);

  const data = ctx.getImageData(0, 0, targetWidth, targetHeight).data;
  let sum = 0;
  let sumSq = 0;
  let count = 0;
  const step = 4 * 4;

  for (let i = 0; i < data.length; i += step) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    sum += luminance;
    sumSq += luminance * luminance;
    count += 1;
  }

  const brightness = count ? sum / count : 0;
  const variance = count ? sumSq / count - brightness * brightness : 0;
  const contrast = Math.sqrt(Math.max(0, variance));

  return { brightness, contrast };
}

function averageLandmarkDelta(current, previous) {
  if (!current || !previous) return 0;
  const currentPositions = current.positions || [];
  const previousPositions = previous.positions || [];
  const total = Math.min(currentPositions.length, previousPositions.length);
  if (!total) return 0;

  let sum = 0;
  for (let i = 0; i < total; i += 1) {
    const dx = currentPositions[i].x - previousPositions[i].x;
    const dy = currentPositions[i].y - previousPositions[i].y;
    sum += Math.hypot(dx, dy);
  }

  return sum / total;
}

function boxMovement(currentBox, previousBox) {
  if (!currentBox || !previousBox) return 0;
  const currentCenterX = currentBox.x + currentBox.width / 2;
  const currentCenterY = currentBox.y + currentBox.height / 2;
  const previousCenterX = previousBox.x + previousBox.width / 2;
  const previousCenterY = previousBox.y + previousBox.height / 2;
  const distance = Math.hypot(currentCenterX - previousCenterX, currentCenterY - previousCenterY);
  const normalizer = Math.max(previousBox.width, previousBox.height, 1);
  return distance / normalizer;
}

function boxSizeDelta(currentBox, previousBox) {
  if (!currentBox || !previousBox) return 0;
  const widthDelta = Math.abs(currentBox.width - previousBox.width) / Math.max(previousBox.width, 1);
  const heightDelta = Math.abs(currentBox.height - previousBox.height) / Math.max(previousBox.height, 1);
  return Math.max(widthDelta, heightDelta);
}

async function collectSamples(video, statusEl, progressEl, options) {
  const config = { ...DEFAULTS, ...(options || {}) };
  const samples = [];
  const start = Date.now();
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const historyLimit = 6;
  const movementHistory = [];
  const sizeHistory = [];
  const landmarkHistory = [];
  const scoreHistory = [];

  let lastBox = null;
  let lastLandmarks = null;
  let stableFrames = 0;
  let livenessHits = 0;
  let lastSampleAt = 0;
  let noFaceFrames = 0;
  let multiFaceFrames = 0;
  let lowLightFrames = 0;
  let lowFocusFrames = 0;
  let sizeFrames = 0;
  let movementFrames = 0;
  let scoreFrames = 0;

  const resetStability = () => {
    stableFrames = 0;
    livenessHits = 0;
    movementHistory.length = 0;
    sizeHistory.length = 0;
    landmarkHistory.length = 0;
    scoreHistory.length = 0;
    setProgress(progressEl, 0);
  };

  setProgress(progressEl, 0);
  setStatus(statusEl, STATUS.idle);

  while (Date.now() - start < config.maxDuration) {
    let detections = [];
    try {
      detections = await detectFaces(video);
    } catch (err) {
      await wait(config.sampleInterval);
      continue;
    }

    if (!detections.length) {
      noFaceFrames += 1;
      if (noFaceFrames >= 2) {
        setStatus(statusEl, STATUS.noFace);
      } else {
        setStatus(statusEl, STATUS.idle);
      }
      resetStability();
      await wait(config.sampleInterval);
      continue;
    }

    if (detections.length > 1) {
      multiFaceFrames += 1;
      if (multiFaceFrames >= 2) {
        setStatus(statusEl, STATUS.multiFace);
      } else {
        setStatus(statusEl, STATUS.idle);
      }
      resetStability();
      await wait(config.sampleInterval);
      continue;
    }

    noFaceFrames = 0;
    multiFaceFrames = 0;

    const detection = detections[0];
    const box = detection.detection.box;
    const score = detection.detection.score ?? 0;
    const faceRatio = (box.width * box.height) / Math.max(video.videoWidth * video.videoHeight, 1);
    const { brightness, contrast } = computeFrameStats(video, ctx, canvas);
    const sizeDelta = boxSizeDelta(box, lastBox);
    const movement = boxMovement(box, lastBox);
    const landmarkDelta = averageLandmarkDelta(detection.landmarks, lastLandmarks);
    const landmarkNormalized = landmarkDelta / Math.max(box.width, box.height, 1);

    pushHistory(movementHistory, movement, historyLimit);
    pushHistory(sizeHistory, sizeDelta, historyLimit);
    pushHistory(landmarkHistory, landmarkNormalized, historyLimit);
    pushHistory(scoreHistory, score, historyLimit);

    const movementAvg = average(movementHistory);
    const sizeAvg = average(sizeHistory);
    const landmarkAvg = average(landmarkHistory);
    const scoreAvg = average(scoreHistory);

    const sizeOk = faceRatio >= config.minFaceRatio && faceRatio <= config.maxFaceRatio;
    const brightnessOk = brightness >= config.minBrightness;
    const contrastOk = contrast >= config.minContrast;
    const scoreOk = scoreAvg >= config.minScore;
    const stableMotion = movementAvg <= config.maxMovement && sizeAvg <= config.maxSizeDelta && landmarkAvg <= config.maxLandmarkDrift;
    const qualityOk = sizeOk && brightnessOk && contrastOk && scoreOk;

    if (!brightnessOk) {
      lowLightFrames += 1;
      lowFocusFrames = 0;
      sizeFrames = 0;
      movementFrames = 0;
      scoreFrames = 0;
      resetStability();
      setStatus(statusEl, lowLightFrames >= 2 ? STATUS.lowLight : STATUS.idle);
    } else if (!contrastOk) {
      lowLightFrames = 0;
      lowFocusFrames += 1;
      sizeFrames = 0;
      movementFrames = 0;
      scoreFrames = 0;
      resetStability();
      setStatus(statusEl, lowFocusFrames >= 2 ? STATUS.lowFocus : STATUS.idle);
    } else if (!sizeOk) {
      lowLightFrames = 0;
      lowFocusFrames = 0;
      sizeFrames += 1;
      movementFrames = 0;
      scoreFrames = 0;
      resetStability();
      const sizeStatus = faceRatio < config.minFaceRatio ? STATUS.tooFar : STATUS.tooClose;
      setStatus(statusEl, sizeFrames >= 2 ? sizeStatus : STATUS.idle);
    } else if (!scoreOk) {
      lowLightFrames = 0;
      lowFocusFrames = 0;
      sizeFrames = 0;
      scoreFrames += 1;
      movementFrames = 0;
      resetStability();
      setStatus(statusEl, STATUS.idle);
    } else if (!stableMotion) {
      lowLightFrames = 0;
      lowFocusFrames = 0;
      sizeFrames = 0;
      scoreFrames = 0;
      movementFrames += 1;
      resetStability();
      setStatus(statusEl, movementFrames >= 2 ? STATUS.tooMuchMovement : STATUS.idle);
    } else {
      lowLightFrames = 0;
      lowFocusFrames = 0;
      sizeFrames = 0;
      movementFrames = 0;
      scoreFrames = 0;

      stableFrames += 1;
      if (stableFrames < config.stableFramesTarget || !qualityOk) {
        setStatus(statusEl, STATUS.idle);
      } else {
        const microMovementOk = landmarkAvg >= config.minMicroMovement && landmarkAvg <= config.maxMicroMovement;
        if (microMovementOk) {
          livenessHits += 1;
        } else if (landmarkAvg >= config.minMicroMovement / 2) {
          livenessHits += 0.25;
        } else {
          livenessHits = Math.max(0, livenessHits - 0.2);
        }

        const livenessProgress = Math.min(1, livenessHits / config.livenessTarget);
        setProgress(progressEl, livenessProgress);

        if (livenessProgress < 1) {
          setStatus(statusEl, STATUS.verifying);
        } else {
          setStatus(statusEl, STATUS.ready);
          const now = Date.now();
          if (now - lastSampleAt >= config.sampleInterval && samples.length < config.maxSamples) {
            samples.push(Array.from(detection.descriptor));
            lastSampleAt = now;
          }

          if (samples.length >= config.minSamples) {
            return samples;
          }
        }
      }
    }

    lastBox = box;
    lastLandmarks = detection.landmarks;

    await wait(config.sampleInterval);
  }

  throw new Error('No se pudo completar la captura.');
}

export function setupFaceEnrollment({
  video,
  overlay,
  button,
  status,
  progress,
  enrollUrl,
  csrfToken,
  modelsUrl,
  hasFace = false,
  savedStatus = '',
  returnUrl = '',
  returnAnchor = '',
  config,
} = {}) {
  if (!video || !button) return;

  const initialOverlay = hasFace
    ? 'Rostro registrado.'
    : 'Camara lista para captura.';

  if (overlay) {
    overlay.textContent = initialOverlay;
  }

  if (hasFace && status && !status.textContent.trim()) {
    status.textContent = savedStatus || 'Rostro registrado.';
  }

  if (!enrollUrl) {
    setStatus(status, 'No se configuro la ruta para guardar el rostro.');
    button.disabled = true;
    return;
  }

  let stream = null;
  let loading = false;

  async function requestCamera() {
    if (stream) return stream;

    try {
      await initFaceApi(modelsUrl || '/models');
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
      video.srcObject = stream;
      try {
        await video.play();
      } catch (err) {
        // Ignore autoplay errors; detection will still wait for frames.
      }
      const ready = await waitForVideo(video, 2000);
      if (!ready) {
        throw new Error('No se pudo iniciar la cámara.');
      }
      video.classList.add('is-active');
      overlay.classList.add('is-hidden');
      return stream;
    } catch (err) {
      const message =
        err.name === 'NotAllowedError'
          ? 'Permiso de cámara requerido.'
          : err.name === 'NotFoundError'
          ? 'No se detecta cámara disponible.'
          : err.message || 'No se pudo activar la cámara.';
      throw new Error(message);
    }
  }

  function stopCamera() {
    if (!stream) return;
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
    video.classList.remove('is-active');
    if (overlay) overlay.classList.remove('is-hidden');
  }

  async function handleEnroll() {
    if (loading) return;

    loading = true;
    button.disabled = true;
    setStatus(status, STATUS.cameraStarting);

    try {
      await requestCamera();
      const descriptors = await collectSamples(video, status, progress, config);
      setStatus(status, STATUS.saving);

      const response = await fetch(enrollUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ descriptors, descriptor: descriptors[0] || null }),
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || 'No se pudo guardar el rostro.');
      }

      if (overlay) overlay.textContent = 'Rostro registrado.';
      const redirect = buildRedirectUrl(returnUrl, returnAnchor, 'ok');
      if (redirect) {
        stopCamera();
        window.location.href = redirect;
        return;
      }

      setStatus(status, 'Rostro registrado.');
    } catch (err) {
      const redirect = buildRedirectUrl(returnUrl, returnAnchor, 'error');
      if (redirect) {
        stopCamera();
        window.location.href = redirect;
        return;
      }

      setStatus(status, err.message || 'No se pudo completar la captura.');
    } finally {
      button.disabled = false;
      loading = false;
    }
  }

  button.addEventListener('click', handleEnroll);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopCamera();
  });
  window.addEventListener('beforeunload', stopCamera);
}
