# Google Colab — copy/paste cells

Open [Google Colab](https://colab.research.google.com/), create a new notebook, set **Runtime → Change runtime type → T4 GPU**, then paste each cell below.

---

## Cell 1 — Install TensorFlow

```python
# CELL 1: Install TensorFlow + dependencies + check GPU
!pip install -q tensorflow scipy pillow numpy

import tensorflow as tf
import numpy as np
import json
import os
from pathlib import Path

print("TensorFlow:", tf.__version__)
gpus = tf.config.list_physical_devices("GPU")
if gpus:
    print("GPU:", gpus[0])
    from tensorflow.keras import mixed_precision
    mixed_precision.set_global_policy("mixed_float16")
    print("Mixed precision: ON")
else:
    print("WARNING: No GPU. Enable Runtime → Change runtime type → T4 GPU")
```

---

## Cell 2 — Upload & extract dataset

Download from Kaggle: https://www.kaggle.com/datasets/adikurniawan/color-dataset-for-color-recognition

```python
# CELL 2: Upload & extract dataset
import zipfile
from google.colab import files
from pathlib import Path

DATA_ROOT = Path("/content/training_dataset")
DATA_ROOT.mkdir(parents=True, exist_ok=True)

print("Upload your dataset ZIP...")
uploaded = files.upload()
zip_name = next(iter(uploaded.keys()))
print("Extracting:", zip_name)

with zipfile.ZipFile(zip_name, "r") as z:
    z.extractall("/content")

def find_dataset_root(base="/content"):
    base = Path(base)
    expected = {"black", "blue", "red", "white", "yellow"}
    for path in base.rglob("*"):
        if path.is_dir() and expected.issubset({p.name.lower() for p in path.iterdir() if p.is_dir()}):
            return path
    for name in ["training_dataset", "dataset", "data"]:
        p = base / name
        if p.is_dir():
            return p
    return base / "training_dataset"

DATA_ROOT = find_dataset_root()
print("Dataset root:", DATA_ROOT)

classes = sorted([d.name for d in DATA_ROOT.iterdir() if d.is_dir()])
print("Classes (", len(classes), "):", classes)
for c in classes:
    n = len(list((DATA_ROOT / c).glob("*")))
    print(f"  {c}: {n} files")

if "pink" not in [x.lower() for x in classes]:
    print("\nNOTE: No 'pink' folder. Add training_dataset/pink/ with pink images to detect pink.")
```

**For pink:** create folder `training_dataset/pink/` and add 20+ pink images before training.

---

## Cell 3 — Train (tuned, fast on GPU)

```python
# CELL 3: Train model
import tensorflow as tf
from tensorflow.keras.applications import MobileNetV2
from tensorflow.keras import layers, models, callbacks, optimizers
from tensorflow.keras.preprocessing.image import ImageDataGenerator

IMG_SIZE = (224, 224)
BATCH_SIZE = 64 if tf.config.list_physical_devices("GPU") else 32
SEED = 42
MODEL_PATH = "/content/color_classifier.h5"
INDICES_PATH = "/content/class_indices.json"

tf.keras.utils.set_random_seed(SEED)

train_gen = ImageDataGenerator(
    rescale=1.0 / 255.0,
    validation_split=0.2,
    rotation_range=20,
    width_shift_range=0.12,
    height_shift_range=0.12,
    shear_range=0.08,
    zoom_range=0.12,
    horizontal_flip=True,
    brightness_range=(0.75, 1.25),
    channel_shift_range=25.0,
    fill_mode="nearest",
)
val_gen = ImageDataGenerator(rescale=1.0 / 255.0, validation_split=0.2)

train_data = train_gen.flow_from_directory(
    str(DATA_ROOT), target_size=IMG_SIZE, batch_size=BATCH_SIZE,
    class_mode="categorical", subset="training", shuffle=True, seed=SEED,
)
val_data = val_gen.flow_from_directory(
    str(DATA_ROOT), target_size=IMG_SIZE, batch_size=BATCH_SIZE,
    class_mode="categorical", subset="validation", shuffle=False, seed=SEED,
)

num_classes = train_data.num_classes
class_indices = {int(v): k for k, v in train_data.class_indices.items()}
with open(INDICES_PATH, "w") as f:
    json.dump(class_indices, f, indent=2)
print("num_classes:", num_classes, class_indices)

def build_model(num_classes, trainable_base=False):
    base = MobileNetV2(weights="imagenet", include_top=False, input_shape=(224, 224, 3))
    base.trainable = trainable_base
    inputs = layers.Input(shape=(224, 224, 3))
    x = base(inputs, training=trainable_base)
    x = layers.GlobalAveragePooling2D()(x)
    x = layers.BatchNormalization()(x)
    x = layers.Dropout(0.4)(x)
    x = layers.Dense(256, activation="relu")(x)
    x = layers.Dropout(0.3)(x)
    outputs = layers.Dense(num_classes, activation="softmax", dtype="float32")(x)
    return models.Model(inputs, outputs)

def compile_model(model, lr):
    model.compile(
        optimizer=optimizers.Adam(learning_rate=lr),
        loss=tf.keras.losses.CategoricalCrossentropy(label_smoothing=0.05),
        metrics=["accuracy"],
    )

cb = [
    callbacks.ModelCheckpoint(MODEL_PATH, monitor="val_accuracy", save_best_only=True, verbose=1),
    callbacks.EarlyStopping(monitor="val_accuracy", patience=8, restore_best_weights=True, verbose=1),
    callbacks.ReduceLROnPlateau(monitor="val_loss", factor=0.35, patience=3, min_lr=1e-7, verbose=1),
]

print("--- Phase 1: frozen backbone ---")
model = build_model(num_classes, trainable_base=False)
compile_model(model, lr=3e-4)
model.fit(train_data, validation_data=val_data, epochs=25, callbacks=cb)

print("--- Phase 2: fine-tune ---")
base = model.layers[1]
base.trainable = True
for layer in base.layers[:-50]:
    layer.trainable = False
compile_model(model, lr=1e-5)
model.fit(train_data, validation_data=val_data, epochs=30, callbacks=cb)

model.save(MODEL_PATH)
loss, acc = model.evaluate(val_data, verbose=0)
print(f"val_accuracy: {acc:.4f}")
print("Download color_classifier.h5 and class_indices.json to your project folder.")
```

---

## Cell 4 (optional) — Download files

```python
from google.colab import files
files.download("/content/color_classifier.h5")
files.download("/content/class_indices.json")
```

---

## After training

1. Copy `color_classifier.h5` and `class_indices.json` into `Color Classifier/`
2. Update `CLASS_NAMES` in `predict.py` and `color_utils.py` if you added **pink** (match `class_indices.json` order)
3. Restart `start_model_server.bat`
