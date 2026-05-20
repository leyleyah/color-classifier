# Deploy ColorCheck → https://colorcheck.vercel.app

Everything is prepared on your PC. You only need **2 quick logins** (GitHub + Vercel), then one script.

## Already done for you

- Git repository initialized with all code committed
- Next.js app builds successfully (`web/`)
- Project name set to **colorcheck** (for `colorcheck.vercel.app`)
- Git installed

---

## Step 1 — GitHub (2 minutes)

Open **PowerShell** in this folder and run:

```powershell
cd "C:\wamp64\www\Color Classifier"
gh auth login
```

Choose: **GitHub.com** → **HTTPS** → **Login with a web browser** → paste the code.

Then create the repo and push:

```powershell
gh repo create colorcheck --public --source=. --remote=origin --push
```

Your repo will be: `https://github.com/YOUR_USERNAME/colorcheck`

---

## Step 2 — ML API on Render (required for predictions)

1. Go to https://dashboard.render.com → sign in with GitHub
2. **New +** → **Web Service** → select repo **colorcheck**
3. **Runtime:** Docker | **Dockerfile:** `./Dockerfile`
4. Deploy → copy URL, e.g. `https://colorcheck-api.onrender.com`
5. Test: open `https://YOUR-URL.onrender.com/health`

---

## Step 3 — Vercel → colorcheck.vercel.app

```powershell
cd "C:\wamp64\www\Color Classifier\web"
npx vercel login
npx vercel link --project=colorcheck
npx vercel env add MODEL_API_URL
# Paste your Render URL when prompted (production)
npx vercel --prod
```

Or use the website:

1. https://vercel.com → **Import** repo **colorcheck**
2. **Root Directory:** `web`
3. **Project Name:** `colorcheck`
4. **Environment variable:** `MODEL_API_URL` = your Render API URL
5. **Deploy**

Site: **https://colorcheck.vercel.app**

---

## One script (after Step 1 login)

```powershell
cd "C:\wamp64\www\Color Classifier"
.\deploy.ps1
```

---

## If the name colorcheck.vercel.app is taken

In Vercel → Project **colorcheck** → Settings → Domains → add `colorcheck.vercel.app` or pick another name.
