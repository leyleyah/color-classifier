# Color Classification AI

Detect dominant colors using a trained **MobileNetV2** model (`color_classifier.h5`) on the [Kaggle color dataset](https://www.kaggle.com/datasets/adikurniawan/color-dataset-for-color-recognition).

## Deployment architecture

| Part | Platform | Why |
|------|----------|-----|
| **Web UI** | [Vercel](https://vercel.com) | Next.js — fast, free hosting |
| **ML API** | [Render](https://render.com) | Python + TensorFlow (too large for Vercel serverless) |

Vercel cannot run TensorFlow/PHP locally; the model runs on Render and Vercel calls it.

## Live site

**https://colorcheck.vercel.app**

## Quick deploy

See **[DEPLOY.md](./DEPLOY.md)** and **[COLORCHECK_SETUP.md](./COLORCHECK_SETUP.md)** for GitHub + Render + Vercel instructions.

## Local development

```bat
# Terminal 1 — API
cd api
..\venv\Scripts\pip install -r requirements.txt
..\venv\Scripts\python.exe -m uvicorn api.main:app --reload --host 127.0.0.1 --port 8000

# Terminal 2 — Web (from web/)
cd web
npm install
set MODEL_API_URL=http://127.0.0.1:8000
npm run dev
```

Open http://localhost:3000

## Project layout

```
├── web/                 # Next.js → deploy to Vercel
├── api/                 # FastAPI → deploy to Render (Docker)
├── predict.py           # Inference + color fusion
├── color_classifier.h5  # Trained model
├── train_model.py       # Local training
└── Color_Classifier_Training_Colab.ipynb
```

## Train model

- **Colab:** `Color_Classifier_Training_Colab.ipynb` or `COLAB_CELLS.md`
- **Local:** `venv\Scripts\python.exe train_model.py`
