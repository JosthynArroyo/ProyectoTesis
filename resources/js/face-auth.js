import * as faceapi from 'face-api.js';

let loaded = false;

export async function initFaceApi() {
  if (loaded) return;
  await Promise.all([
    faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
    faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
    faceapi.nets.faceRecognitionNet.loadFromUri('/models'),
  ]);
  loaded = true;
}

export async function captureDescriptor(videoElement) {
  const detection = await faceapi
    .detectSingleFace(videoElement, new faceapi.TinyFaceDetectorOptions())
    .withFaceLandmarks()
    .withFaceDescriptor();

  if (!detection) {
    throw new Error('No se detectó ningún rostro.');
  }

  return Array.from(detection.descriptor);
}
