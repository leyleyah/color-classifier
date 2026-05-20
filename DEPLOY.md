# Deploy to GitHub + Vercel + Render

## Overview

1. Push code to **GitHub**
2. Deploy **Python API** on **Render** (runs `color_classifier.h5`)
3. Deploy **Next.js UI** on **Vercel** (calls the API)

---

## Step 1 — GitHub repository

Open PowerShell in the project folder:

```powershell
cd "C:\wamp64\www\Color Classifier"

git init
git add .
git commit -m "Color Classifier: Next.js UI + FastAPI ML backend"

# Create repo on GitHub (requires GitHub CLI: gh auth login)
gh repo create color-classifier --public --source=. --remote=origin --push
```

**Without `gh` CLI:** Create a new repo on https://github.com/new (name: `color-classifier`), then:

```powershell
git remote add origin https://github.com/YOUR_USERNAME/color-classifier.git
git branch -M main
git push -u origin main
```

> `color_classifier.h5` (~23 MB) is included. GitHub allows files up to 100 MB.

---

## Step 2 — Deploy API on Render (free)

1. Go to https://dashboard.render.com → **New +** → **Web Service**
2. Connect your **GitHub** repo `color-classifier`
3. Settings:
   - **Name:** `color-classifier-api`
   - **Runtime:** **Docker**
   - **Dockerfile path:** `./Dockerfile`
   - **Plan:** Free
4. **Create Web Service** and wait for build (~10–15 min first time, TensorFlow install)
5. Copy your service URL, e.g. `https://color-classifier-api.onrender.com`
6. Test: open `https://YOUR-API.onrender.com/health` → should show `"model_exists": true`

**Note:** Free Render apps sleep after inactivity; first request may take ~30s to wake up.

---

## Step 3 — Deploy UI on Vercel

1. Go to https://vercel.com → **Add New Project**
2. **Import** your GitHub repo
3. **Root Directory:** set to `web` (important!)
4. **Environment variables:**

   | Name | Value |
   |------|--------|
   | `MODEL_API_URL` | `https://color-classifier-api.onrender.com` (your Render URL, no trailing slash) |

5. Click **Deploy**

Your app will be live at `https://your-project.vercel.app`

---

## Step 4 — CORS (if needed)

On Render, add environment variable:

| Name | Value |
|------|--------|
| `CORS_ORIGINS` | `https://your-project.vercel.app` |

Or keep `*` for testing.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Vercel: `MODEL_API_URL is not set` | Add env var in Vercel project settings → Redeploy |
| Prediction timeout | Render free tier cold start; wait 30s and retry |
| 502 from Vercel | Check Render logs; model file must be in repo |
| Build fails on Render | Ensure `color_classifier.h5` is committed to Git |

---

## Optional: deploy only with Vercel CLI

```bash
cd web
npm install
vercel
# Set MODEL_API_URL when prompted or in Vercel dashboard
```
