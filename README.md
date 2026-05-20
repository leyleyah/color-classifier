# Color Check

Detect dominant colors using a trained **MobileNetV2** model (`color_classifier.h5`).

**Live:** https://colorcheck.vercel.app

## Architecture

| Part | Platform |
|------|----------|
| Web UI | [Vercel](https://vercel.com) — `web/` |
| ML API | [Render](https://render.com) — Docker + `api/` |

## Project layout

```
├── web/                  # Next.js (Vercel)
├── api/                  # FastAPI (Render)
├── predict.py            # Inference
├── color_utils.py        # Pixel color fusion
├── color_classifier.h5   # Trained model
├── class_indices.json
├── Dockerfile
├── render.yaml
├── train_model.py        # Retrain locally
└── Color_Classifier_Training_Colab.ipynb
```

## Local development

```bat
# API
venv\Scripts\pip install -r api\requirements.txt
venv\Scripts\python.exe -m uvicorn api.main:app --host 127.0.0.1 --port 8000

# Web (new terminal)
cd web
npm install
set MODEL_API_URL=http://127.0.0.1:8000
npm run dev
```

Open http://localhost:3000

## Deploy

See **[DEPLOY.md](./DEPLOY.md)**

## Retrain model

See **[TRAINING.md](./TRAINING.md)** or `Color_Classifier_Training_Colab.ipynb`
