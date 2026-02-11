from __future__ import annotations

import json
from pathlib import Path

import tensorflow as tf
from tensorflow.keras import layers


IMAGE_SIZE = (224, 224)
BATCH_SIZE = 32
INITIAL_EPOCHS = 10
FINE_TUNE_EPOCHS = 5

CLASSES = [
    "giraffe",
    "horse",
    "koala",
    "kangaroo",
    "rhinoceros",
    "dolphin",
    "blue_whale",
    "zebra",
]


def validate_dataset_dirs(dataset_root: Path) -> None:
    for split in ("train", "val"):
        for class_name in CLASSES:
            class_dir = dataset_root / split / class_name
            if not class_dir.exists():
                raise FileNotFoundError(f"Missing dataset directory: {class_dir}")


def load_datasets(dataset_root: Path) -> tuple[tf.data.Dataset, tf.data.Dataset]:
    train_dir = dataset_root / "train"
    val_dir = dataset_root / "val"

    train_ds = tf.keras.utils.image_dataset_from_directory(
        train_dir,
        labels="inferred",
        label_mode="int",
        class_names=CLASSES,
        image_size=IMAGE_SIZE,
        batch_size=BATCH_SIZE,
        shuffle=True,
        seed=42,
    )

    val_ds = tf.keras.utils.image_dataset_from_directory(
        val_dir,
        labels="inferred",
        label_mode="int",
        class_names=CLASSES,
        image_size=IMAGE_SIZE,
        batch_size=BATCH_SIZE,
        shuffle=False,
    )

    autotune = tf.data.AUTOTUNE
    return train_ds.prefetch(autotune), val_ds.prefetch(autotune)


def build_model(num_classes: int) -> tuple[tf.keras.Model, tf.keras.Model]:
    base_model = tf.keras.applications.MobileNetV2(
        input_shape=(*IMAGE_SIZE, 3),
        include_top=False,
        weights="imagenet",
    )
    base_model.trainable = False

    inputs = tf.keras.Input(shape=(*IMAGE_SIZE, 3))
    x = layers.Rescaling(1.0 / 127.5, offset=-1)(inputs)
    x = base_model(x, training=False)
    x = layers.GlobalAveragePooling2D()(x)
    x = layers.Dropout(0.2)(x)
    outputs = layers.Dense(num_classes, activation="softmax")(x)

    model = tf.keras.Model(inputs, outputs)
    return model, base_model


def main() -> None:
    project_root = Path(__file__).resolve().parents[2]
    dataset_root = project_root / "ai" / "dataset"
    model_root = Path(__file__).resolve().parent / "model"
    model_root.mkdir(parents=True, exist_ok=True)

    validate_dataset_dirs(dataset_root)
    train_ds, val_ds = load_datasets(dataset_root)

    model, base_model = build_model(len(CLASSES))

    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=1e-3),
        loss="sparse_categorical_crossentropy",
        metrics=["accuracy"],
    )

    print("Starting initial training (frozen base)...")
    history = model.fit(
        train_ds,
        validation_data=val_ds,
        epochs=INITIAL_EPOCHS,
    )

    print("Starting fine-tuning (last 30 layers)...")
    base_model.trainable = True
    for layer in base_model.layers[:-30]:
        layer.trainable = False

    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=1e-5),
        loss="sparse_categorical_crossentropy",
        metrics=["accuracy"],
    )

    model.fit(
        train_ds,
        validation_data=val_ds,
        epochs=INITIAL_EPOCHS + FINE_TUNE_EPOCHS,
        initial_epoch=history.epoch[-1] + 1,
    )

    model_path = model_root / "animal_model.keras"
    classes_path = model_root / "classes.json"

    model.save(model_path)
    with classes_path.open("w", encoding="utf-8") as fp:
        json.dump(CLASSES, fp, ensure_ascii=False, indent=2)

    val_loss, val_acc = model.evaluate(val_ds, verbose=0)
    print(f"Saved model to: {model_path}")
    print(f"Saved classes to: {classes_path}")
    print(f"Validation accuracy: {val_acc:.4f} | loss: {val_loss:.4f}")


if __name__ == "__main__":
    main()
