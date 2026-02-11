from __future__ import annotations

import json
from io import BytesIO
from pathlib import Path
from typing import Any

import numpy as np
import tensorflow as tf
from fastapi import FastAPI, File, HTTPException, UploadFile
from PIL import Image


IMAGE_SIZE = (224, 224)
MODEL_PATH = Path(__file__).resolve().parent / "model" / "animal_model.keras"
CLASSES_PATH = Path(__file__).resolve().parent / "model" / "classes.json"


def load_model_and_classes() -> tuple[tf.keras.Model, list[str]]:
    if not MODEL_PATH.exists():
        raise RuntimeError(f"Model not found: {MODEL_PATH}")
    if not CLASSES_PATH.exists():
        raise RuntimeError(f"Classes file not found: {CLASSES_PATH}")

    model = tf.keras.models.load_model(MODEL_PATH)
    with CLASSES_PATH.open("r", encoding="utf-8") as fp:
        classes = json.load(fp)

    if not isinstance(classes, list) or not classes:
        raise RuntimeError("classes.json must contain a non-empty list of class names.")

    return model, [str(item) for item in classes]


app = FastAPI(title="Vision CAPTCHA API")
model, classes = load_model_and_classes()


@app.post("/classify")
async def classify(image: UploadFile = File(...)) -> dict[str, Any]:
    if image.content_type and not image.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Uploaded file must be an image.")

    payload = await image.read()
    if not payload:
        raise HTTPException(status_code=400, detail="Empty image payload.")

    try:
        img = Image.open(BytesIO(payload)).convert("RGB").resize(IMAGE_SIZE)
    except Exception as exc:  # pragma: no cover
        raise HTTPException(status_code=400, detail="Invalid image file.") from exc

    # The trained model already contains a Rescaling layer.
    arr = np.asarray(img, dtype=np.float32)
    arr = np.expand_dims(arr, axis=0)

    predictions = model.predict(arr, verbose=0)[0]
    idx = int(np.argmax(predictions))

    return {
        "label": classes[idx],
        "confidence": float(predictions[idx]),
    }
