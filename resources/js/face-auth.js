let loaded = false;
let currentPath = '';
let loadingPromise = null;
let faceApi = null;
let faceApiPromise = null;
let tinyFaceOptions = null;

async function loadFaceApi() {
  if (faceApi) return faceApi;

  if (!faceApiPromise) {
    faceApiPromise = import('face-api.js')
      .then((module) => {
        faceApi = module;
        return module;
      })
      .catch((error) => {
        faceApiPromise = null;
        throw error;
      });
  }

  return faceApiPromise;
}

async function getTinyFaceOptions() {
  const api = await loadFaceApi();

  if (!tinyFaceOptions) {
    tinyFaceOptions = new api.TinyFaceDetectorOptions({
      inputSize: 160,
      scoreThreshold: 0.35,
    });
  }

  return tinyFaceOptions;
}

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
    throw new Error('No hay cámara activa.');
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
      videoElement.removeEventListener('loadedmetadata', onLoad);
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

    videoElement.addEventListener('loadedmetadata', onLoad);
    videoElement.addEventListener('loadeddata', onLoad);
    videoElement.addEventListener('error', onError);
    setTimeout(() => {
      cleanup();
      resolve();
    }, 2500);
  });
}

export async function initFaceApi(modelsPath = '/models') {
  const normalized = normalizeModelsPath(modelsPath);

  if (loaded && currentPath === normalized) return;
  if (loadingPromise && currentPath === normalized) return loadingPromise;

  currentPath = normalized;
  loaded = false;
  loadingPromise = loadFaceApi()
    .then((api) => Promise.all([
      api.nets.tinyFaceDetector.loadFromUri(normalized),
      api.nets.faceLandmark68TinyNet.loadFromUri(normalized),
      api.nets.faceRecognitionNet.loadFromUri(normalized),
    ]))
    .then(() => {
      loaded = true;
    })
    .finally(() => {
      loadingPromise = null;
    });

  return loadingPromise;
}

export async function detectFaces(videoElement) {
  await ensureVideoReady(videoElement);
  const api = await loadFaceApi();
  const options = await getTinyFaceOptions();

  return api.detectAllFaces(videoElement, options).withFaceLandmarks(true).withFaceDescriptors();
}

export async function captureDescriptor(videoElement, options = {}) {
  const { timeout = 10000, retryInterval = 300 } = options;

  await ensureVideoReady(videoElement);

  const start = Date.now();
  const api = await loadFaceApi();
  const detectorOptions = await getTinyFaceOptions();

  while (Date.now() - start < timeout) {
    const detection = await api
      .detectSingleFace(videoElement, detectorOptions)
      .withFaceLandmarks(true)
      .withFaceDescriptor();

    if (detection) {
      return Array.from(detection.descriptor);
    }

    await wait(retryInterval);
  }

  throw new Error('No se detectó un rostro claro.');
}
