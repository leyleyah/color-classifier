# Retrain `color_classifier.h5`

Dataset: [Color dataset for color recognition (Kaggle)](https://www.kaggle.com/datasets/adikurniawan/color-dataset-for-color-recognition/data)

## Setup

1. Download and extract the dataset into this folder:

```
Color Classifier/
  training_dataset/
    black/
    blue/
    brown/
    green/
    grey/
    orange/
    red/
    violet/
    white/
    yellow/
```

2. Train (uses the same `rescale=1./255` and MobileNetV2 setup as your Colab notebook, plus fine-tuning):

```bat
venv\Scripts\python.exe train_model.py
```

Or with a custom path:

```bat
venv\Scripts\python.exe train_model.py --data "D:\datasets\training_dataset"
```

3. Restart the model server so it loads the new `.h5`:

```bat
start_model_server.bat
```

## Inference

`predict.py` loads `color_classifier.h5` and blends CNN output with **pixel dominant-color** analysis so rainbows and multi-hue images are judged by color, not object shape.
