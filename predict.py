"""
Color classifier inference.
Uses color_classifier.h5 (same training as Colab: rescale 1/255, MobileNetV2)
plus pixel-based dominant color fusion for accuracy on multicolor / rainbow images.
"""
import json
import os
import sys

os.environ["TF_CPP_MIN_LOG_LEVEL"] = "3"
os.environ["TF_ENABLE_ONEDNN_OPTS"] = "0"

if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")
_devnull = open(os.devnull, "w", encoding="utf-8")
sys.stderr = _devnull

import numpy as np
import tensorflow as tf
from PIL import Image

from color_utils import (
    CLASS_NAMES,
    COLOR_HEX,
    _pink_score,
    analyze_image_colors,
    build_ranked_list,
    filter_chromatic_pixels,
    fuse_probabilities,
    sample_pixels,
)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "color_classifier.h5")

_model = None


def load_model():
    if not os.path.isfile(MODEL_PATH):
        raise FileNotFoundError(f"Model not found: {MODEL_PATH}")
    return tf.keras.models.load_model(MODEL_PATH, compile=False)


def get_model():
    global _model
    if _model is None:
        _model = load_model()
    return _model


def preprocess_image(image_path: str) -> np.ndarray:
    """Same as training: RGB 224x224, rescale 1/255."""
    img = Image.open(image_path).convert("RGB")
    img = img.resize((224, 224), Image.Resampling.LANCZOS)
    arr = np.asarray(img, dtype=np.float32) / 255.0
    return np.expand_dims(arr, axis=0)


def predict(image_path: str) -> dict:
    if not os.path.isfile(image_path):
        raise FileNotFoundError(f"Image not found: {image_path}")

    dom_probs, multicolor_score, is_multicolor = analyze_image_colors(image_path)

    model = get_model()
    batch = preprocess_image(image_path)
    model_probs = model.predict(batch, verbose=0)[0]
    model_probs = np.asarray(model_probs, dtype=np.float64)
    model_probs = model_probs / model_probs.sum() if model_probs.sum() > 0 else model_probs

    fused = fuse_probabilities(
        model_probs,
        dom_probs,
        is_multicolor=is_multicolor,
        model_top_confidence=float(model_probs.max()),
    )

    ranked = build_ranked_list(fused)
    top = ranked[0]

    result = {
        "success": True,
        "color": top["color"],
        "confidence": top["confidence"],
        "hex": top["hex"],
        "top_predictions": ranked[:3],
        "all_predictions": ranked,
        "detection_mode": "multicolor" if is_multicolor else "color",
        "multicolor_score": round(multicolor_score, 3),
    }

    if is_multicolor:
        result["note"] = (
            "Image has many hues (e.g. rainbow). Showing the dominant color by pixel analysis, "
            "not a single object label."
        )
        dom_ranked = build_ranked_list(dom_probs)
        result["dominant_colors"] = dom_ranked[:3]

    img = Image.open(image_path).convert("RGB")
    px = filter_chromatic_pixels(sample_pixels(img))
    if _pink_score(px) > 0.4:
        dom_ranked = build_ranked_list(dom_probs)
        result["dominant_colors"] = dom_ranked[:3]
        if top["color"] in ("grey", "white") and dom_ranked[0]["color"] in ("red", "violet"):
            result["color"] = dom_ranked[0]["color"]
            result["confidence"] = dom_ranked[0]["confidence"]
            result["hex"] = dom_ranked[0]["hex"]
            ranked[0] = dom_ranked[0]
            result["top_predictions"] = ranked[:3]
        result["note"] = (
            "Pink is not in the default Kaggle classes. Showing closest trained color (red/violet). "
            "For true pink labels, add training_dataset/pink/ in Colab and retrain."
        )

    return result


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No image path provided."}))
        sys.exit(1)

    image_path = sys.argv[1]
    if not os.path.isabs(image_path):
        image_path = os.path.join(BASE_DIR, image_path)

    try:
        result = predict(image_path)
        print(json.dumps(result))
    except Exception as exc:
        print(json.dumps({"success": False, "error": str(exc)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
