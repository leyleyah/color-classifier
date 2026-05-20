"""
FastAPI backend for color prediction (deploy on Render / Railway).
Vercel frontend calls this API via MODEL_API_URL.
"""
from __future__ import annotations

import os
import sys
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware

# ML modules live in project root
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from predict import predict  # noqa: E402

app = FastAPI(title="Color Classifier API", version="1.0.0")

origins = os.getenv("CORS_ORIGINS", "*").split(",")
app.add_middleware(
    CORSMiddleware,
    allow_origins=[o.strip() for o in origins if o.strip()],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/")
def root():
    return {"ok": True, "service": "color-classifier-api"}


@app.get("/health")
def health():
    model_path = ROOT / "color_classifier.h5"
    return {
        "ok": True,
        "model_exists": model_path.is_file(),
        "model_path": str(model_path),
    }


@app.post("/predict")
async def predict_color(file: UploadFile = File(...)):
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(400, "Upload an image file (JPEG, PNG, etc.)")

    suffix = Path(file.filename or "image.jpg").suffix or ".jpg"
    tmp_path: str | None = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            content = await file.read()
            if len(content) > 15 * 1024 * 1024:
                raise HTTPException(413, "Image too large (max 15MB)")
            tmp.write(content)
            tmp_path = tmp.name

        result = predict(tmp_path)
        if not result.get("success"):
            raise HTTPException(500, result.get("error", "Prediction failed"))
        return result
    finally:
        if tmp_path and os.path.isfile(tmp_path):
            os.unlink(tmp_path)


if __name__ == "__main__":
    import uvicorn

    port = int(os.getenv("PORT", "8000"))
    uvicorn.run(app, host="0.0.0.0", port=port)
