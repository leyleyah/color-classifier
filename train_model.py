"""
Retrain color_classifier.h5 on the Kaggle color dataset.

Dataset: https://www.kaggle.com/datasets/adikurniawan/color-dataset-for-color-recognition
Extract so you have:
  training_dataset/
    black/
    blue/
    brown/
    ...

Run from project folder:
  venv\\Scripts\\python.exe train_model.py
  venv\\Scripts\\python.exe train_model.py --data "C:\\path\\to\\training_dataset"
"""
from __future__ import annotations

import argparse
import json
import os

os.environ["TF_CPP_MIN_LOG_LEVEL"] = "2"

import tensorflow as tf
from tensorflow.keras.applications import MobileNetV2
from tensorflow.keras.callbacks import EarlyStopping, ModelCheckpoint, ReduceLROnPlateau
from tensorflow.keras.layers import Dense, Dropout, GlobalAveragePooling2D
from tensorflow.keras.models import Sequential
from tensorflow.keras.preprocessing.image import ImageDataGenerator

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DEFAULT_DATA = os.path.join(BASE_DIR, "training_dataset")
MODEL_OUT = os.path.join(BASE_DIR, "color_classifier.h5")
INDICES_OUT = os.path.join(BASE_DIR, "class_indices.json")

IMG_SIZE = (224, 224)
BATCH_SIZE = 32


def build_model(num_classes: int, trainable_base: bool = False) -> tf.keras.Model:
    base = MobileNetV2(
        weights="imagenet",
        include_top=False,
        input_shape=(224, 224, 3),
    )
    base.trainable = trainable_base

    model = Sequential(
        [
            base,
            GlobalAveragePooling2D(),
            Dropout(0.35),
            Dense(num_classes, activation="softmax"),
        ]
    )
    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=1e-3 if not trainable_base else 1e-5),
        loss="categorical_crossentropy",
        metrics=["accuracy"],
    )
    return model


def unfreeze_top_layers(model: tf.keras.Model, n_layers: int = 40) -> None:
    base = model.layers[0]
    base.trainable = True
    for layer in base.layers[:-n_layers]:
        layer.trainable = False


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", default=DEFAULT_DATA, help="Path to training_dataset folder")
    parser.add_argument("--epochs1", type=int, default=12, help="Epochs with frozen MobileNetV2")
    parser.add_argument("--epochs2", type=int, default=15, help="Fine-tune epochs")
    args = parser.parse_args()

    data_dir = os.path.abspath(args.data)
    if not os.path.isdir(data_dir):
        raise SystemExit(
            f"Dataset not found: {data_dir}\n"
            "Download from Kaggle and extract to training_dataset/ with one folder per color."
        )

    # Match your Colab setup: rescale 1/255, 80/20 split
    train_datagen = ImageDataGenerator(
        rescale=1.0 / 255.0,
        validation_split=0.2,
        rotation_range=12,
        width_shift_range=0.08,
        height_shift_range=0.08,
        shear_range=0.05,
        zoom_range=0.08,
        horizontal_flip=True,
        brightness_range=(0.85, 1.15),
        fill_mode="nearest",
    )
    val_datagen = ImageDataGenerator(rescale=1.0 / 255.0, validation_split=0.2)

    train_data = train_datagen.flow_from_directory(
        data_dir,
        target_size=IMG_SIZE,
        batch_size=BATCH_SIZE,
        class_mode="categorical",
        subset="training",
        shuffle=True,
    )
    val_data = val_datagen.flow_from_directory(
        data_dir,
        target_size=IMG_SIZE,
        batch_size=BATCH_SIZE,
        class_mode="categorical",
        subset="validation",
        shuffle=False,
    )

    num_classes = train_data.num_classes
    class_indices = {v: k for k, v in train_data.class_indices.items()}
    with open(INDICES_OUT, "w", encoding="utf-8") as f:
        json.dump(class_indices, f, indent=2)
    print("Class indices (index -> folder name):", class_indices)

    callbacks = [
        EarlyStopping(monitor="val_accuracy", patience=6, restore_best_weights=True, verbose=1),
        ReduceLROnPlateau(monitor="val_loss", factor=0.4, patience=3, min_lr=1e-6, verbose=1),
        ModelCheckpoint(MODEL_OUT, monitor="val_accuracy", save_best_only=True, verbose=1),
    ]

    print("\n--- Phase 1: train head (frozen MobileNetV2) ---")
    model = build_model(num_classes, trainable_base=False)
    model.fit(train_data, validation_data=val_data, epochs=args.epochs1, callbacks=callbacks)

    print("\n--- Phase 2: fine-tune top layers ---")
    unfreeze_top_layers(model, n_layers=40)
    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=1e-5),
        loss="categorical_crossentropy",
        metrics=["accuracy"],
    )
    model.fit(train_data, validation_data=val_data, epochs=args.epochs2, callbacks=callbacks)

    model.save(MODEL_OUT)
    print(f"\nSaved model: {MODEL_OUT}")
    print(f"Saved class map: {INDICES_OUT}")
    print("Restart start_model_server.bat (or model_server.py) to load the new weights.")


if __name__ == "__main__":
    main()
