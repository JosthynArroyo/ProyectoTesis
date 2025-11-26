import * as faceapi from 'face-api.js';

let loaded = false;
let currentPath = '';
const tinyFaceOptions = new faceapi.TinyFaceDetectorOptions();

function normalizeModelsPath(path = '/models') {
  if (!path.startsWith('http') && !path.startsWith('/')) {
    path = `/${path}`;
  }
  if (!path.endsWith('/')) {
    path = `${path}/`;
  }
  return path;
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function ensureVideoReady(videoElement) {
  if (!videoElement) {
    throw new Error('No hay camara activa.');
  }

  if (videoElement.readyState >= 2) return;

  try {
    await videoElement.play();
  } catch (err) {
    // Ignore autoplay errors; we still wait for data below.
  }

  if (videoElement.readyState >= 2) return;

  await new Promise((resolve) => {
    const cleanup = () => {
      videoElement.removeEventListener('loadeddata', onLoad);
      videoElement.removeEventListener('error', onError);
    };

    const onLoad = () => {
      cleanup();
      resolve();
    };

    const onError = () => {
      cleanup();
      resolve();
    };

    videoElement.addEventListener('loadeddata', onLoad);
    videoElement.addEventListener('error', onError);
    setTimeout(() => {
      cleanup();
      resolve();
    }, 500);
  });
}

export async function initFaceApi(modelsPath = '/models') {
  const normalized = normalizeModelsPath(modelsPath);

  if (loaded && currentPath === normalized) return;

  currentPath = normalized;
  await Promise.all([
    faceapi.nets.tinyFaceDetector.loadFromUri(normalized),
    faceapi.nets.faceLandmark68Net.loadFromUri(normalized),
    faceapi.nets.faceRecognitionNet.loadFromUri(normalized),
  ]);

  loaded = true;
}

export async function captureDescriptor(videoElement, options = {}) {
  const { timeout = 10000, retryInterval = 300 } = options;

  await ensureVideoReady(videoElement);

  const start = Date.now();

  while (Date.now() - start < timeout) {
    const detection = await faceapi
      .detectSingleFace(videoElement, tinyFaceOptions)
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (detection) {
      return Array.from(detection.descriptor);
    }

    await wait(retryInterval);
  }

  throw new Error('No se detecto ningun rostro. Acercate a la camara y verifica la iluminacion.');
}
