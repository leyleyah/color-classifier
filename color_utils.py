"""
Shared color labels and pixel-based color analysis (dominant hue, multicolor).
Used by predict.py so inference focuses on color, not object shape.
"""
from __future__ import annotations

import numpy as np
from PIL import Image

# Must match alphabetical order from ImageDataGenerator.flow_from_directory
CLASS_NAMES = [
    "black",
    "blue",
    "brown",
    "green",
    "grey",
    "orange",
    "red",
    "violet",
    "white",
    "yellow",
]

COLOR_HEX = {
    "black": "#000000",
    "blue": "#0000ff",
    "brown": "#8b4513",
    "green": "#008000",
    "grey": "#808080",
    "orange": "#ffa500",
    "red": "#ff0000",
    "violet": "#8a2be2",
    "white": "#ffffff",
    "yellow": "#ffff00",
}

# Representative RGB centroids (dataset color folders)
COLOR_CENTROIDS_RGB = np.array(
    [
        [25, 25, 25],
        [30, 30, 200],
        [120, 70, 40],
        [30, 160, 60],
        [128, 128, 128],
        [240, 140, 30],
        [220, 40, 40],
        [140, 50, 180],
        [245, 245, 245],
        [240, 230, 50],
    ],
    dtype=np.float64,
)


def rgb_to_hsv(rgb: np.ndarray) -> np.ndarray:
    """rgb: (N, 3) in 0-255 -> hsv with h in [0,1], s,v in [0,1]."""
    r, g, b = rgb[:, 0] / 255.0, rgb[:, 1] / 255.0, rgb[:, 2] / 255.0
    mx = np.maximum(np.maximum(r, g), b)
    mn = np.minimum(np.minimum(r, g), b)
    diff = mx - mn
    h = np.zeros_like(mx)
    s = np.where(mx > 1e-6, diff / mx, 0.0)
    v = mx

    mask = diff > 1e-6
    idx = np.argmax(np.stack([r, g, b]), axis=0)
    h[mask & (idx == 0)] = ((g - b) / diff)[mask & (idx == 0)] % 6.0
    h[mask & (idx == 1)] = ((b - r) / diff + 2.0)[mask & (idx == 1)]
    h[mask & (idx == 2)] = ((r - g) / diff + 4.0)[mask & (idx == 2)]
    h = (h / 6.0) % 1.0
    return np.stack([h, s, v], axis=1)


def sample_pixels(img: Image.Image, center_fraction: float = 0.72) -> np.ndarray:
    """Sample pixels from center region to reduce background/object edge bias."""
    w, h = img.size
    margin_x = int(w * (1 - center_fraction) / 2)
    margin_y = int(h * (1 - center_fraction) / 2)
    cropped = img.crop((margin_x, margin_y, w - margin_x, h - margin_y))
    small = cropped.resize((128, 128), Image.Resampling.LANCZOS)
    pixels = np.asarray(small, dtype=np.float64).reshape(-1, 3)
    return pixels


def filter_chromatic_pixels(pixels: np.ndarray) -> np.ndarray:
    """Keep pixels with enough saturation/value to represent a color (not grey noise)."""
    hsv = rgb_to_hsv(pixels)
    s, v = hsv[:, 1], hsv[:, 2]
    chromatic = (s > 0.12) & (v > 0.08) & (v < 0.98)
    if chromatic.sum() < 50:
        return pixels
    return pixels[chromatic]


def _pink_score(pixels: np.ndarray) -> float:
    """Detect pink/magenta (not in default Kaggle 10-class set). High R, medium B, G lower than R."""
    r, g, b = pixels[:, 0], pixels[:, 1], pixels[:, 2]
    pink_mask = (r > 140) & (b > 80) & (g < r * 0.92) & (r > g + 25)
    if pink_mask.sum() < 20:
        return 0.0
    return float(pink_mask.mean())


def dominant_color_probs(pixels: np.ndarray) -> np.ndarray:
    """Map pixels to nearest class centroid; return normalized class distribution."""
    dist = np.linalg.norm(
        pixels[:, None, :] - COLOR_CENTROIDS_RGB[None, :, :],
        axis=2,
    )
    labels = np.argmin(dist, axis=1)
    counts = np.bincount(labels, minlength=len(CLASS_NAMES)).astype(np.float64)

    # Pink is not a Kaggle class — boost red + violet when pixels look pink
    pink = _pink_score(pixels)
    if pink > 0.35:
        red_i = CLASS_NAMES.index("red")
        violet_i = CLASS_NAMES.index("violet")
        counts[red_i] += pink * counts.sum() * 1.2
        counts[violet_i] += pink * counts.sum() * 0.6
        counts[CLASS_NAMES.index("grey")] *= 0.25
        counts[CLASS_NAMES.index("white")] *= 0.35

    if counts.sum() <= 0:
        return np.ones(len(CLASS_NAMES)) / len(CLASS_NAMES)
    return counts / counts.sum()


def multicolor_score(pixels: np.ndarray) -> float:
    """
    High score = many distinct hues (rainbow, gradients).
    Used to rely more on pixel voting than CNN object cues.
    """
    hsv = rgb_to_hsv(pixels)
    chromatic = hsv[hsv[:, 1] > 0.15]
    if len(chromatic) < 30:
        return 0.0
    hues = chromatic[:, 0]
    # Circular hue std
    sin_h = np.sin(2 * np.pi * hues).mean()
    cos_h = np.cos(2 * np.pi * hues).mean()
    R = np.sqrt(sin_h**2 + cos_h**2)
    circular_std = np.sqrt(max(0.0, -2 * np.log(max(R, 1e-6))))
    return float(min(1.0, circular_std / 1.2))


def analyze_image_colors(image_path: str) -> tuple[np.ndarray, float, bool]:
    img = Image.open(image_path).convert("RGB")
    pixels = sample_pixels(img)
    pixels = filter_chromatic_pixels(pixels)
    dom = dominant_color_probs(pixels)
    mc = multicolor_score(pixels)
    is_multicolor = mc > 0.45 or (dom.max() < 0.28 and mc > 0.3)
    return dom, mc, is_multicolor


def fuse_probabilities(
    model_probs: np.ndarray,
    dom_probs: np.ndarray,
    *,
    is_multicolor: bool,
    model_top_confidence: float,
) -> np.ndarray:
    """Blend CNN (texture/object) with pixel color voting."""
    model_probs = np.asarray(model_probs, dtype=np.float64)
    dom_probs = np.asarray(dom_probs, dtype=np.float64)
    model_probs = model_probs / model_probs.sum()
    dom_probs = dom_probs / dom_probs.sum()

    if is_multicolor:
        w_dom, w_model = 0.82, 0.18
    elif model_top_confidence < 0.38:
        w_dom, w_model = 0.65, 0.35
    else:
        w_dom, w_model = 0.42, 0.58

    fused = w_model * model_probs + w_dom * dom_probs
    return fused / fused.sum()


def build_ranked_list(probs: np.ndarray) -> list[dict]:
    ranked = sorted(
        [
            {
                "color": CLASS_NAMES[i],
                "confidence": round(float(probs[i]) * 100, 2),
                "hex": COLOR_HEX[CLASS_NAMES[i]],
            }
            for i in range(len(CLASS_NAMES))
        ],
        key=lambda x: x["confidence"],
        reverse=True,
    )
    return ranked
