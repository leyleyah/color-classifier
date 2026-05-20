# One-click deploy: GitHub + Vercel (colorcheck.vercel.app)
# Run in PowerShell:  .\deploy.ps1
$ErrorActionPreference = "Stop"
$ProjectRoot = $PSScriptRoot
$Git = "C:\Program Files\Git\cmd\git.exe"
$Node = "$env:LOCALAPPDATA\Programs\cursor\resources\app\resources\helpers\node.exe"

Set-Location $ProjectRoot

Write-Host "=== ColorCheck Deploy ===" -ForegroundColor Cyan

if (-not (Test-Path $Git)) {
    Write-Host "Install Git first: winget install Git.Git" -ForegroundColor Red
    exit 1
}

# Git commit
if (-not (Test-Path ".git")) {
    & $Git init
    & $Git branch -M main
}
& $Git add .
& $Git diff --cached --quiet
if ($LASTEXITCODE -ne 0) {
    & $Git commit -m "ColorCheck: deploy to Vercel + Render API"
}

# GitHub
$gh = Get-Command gh -ErrorAction SilentlyContinue
if ($gh) {
    gh auth status 2>$null
    if ($LASTEXITCODE -eq 0) {
        $remote = gh repo view colorcheck --json url 2>$null
        if ($LASTEXITCODE -ne 0) {
            gh repo create colorcheck --public --source=. --remote=origin --push
        } else {
            & $Git push -u origin main
        }
        Write-Host "GitHub: https://github.com/$(gh api user -q .login)/colorcheck" -ForegroundColor Green
    } else {
        Write-Host "Run: gh auth login" -ForegroundColor Yellow
    }
} else {
    Write-Host "Install GitHub CLI: winget install GitHub.cli" -ForegroundColor Yellow
}

# Vercel
if (Test-Path $Node) {
    Set-Location "$ProjectRoot\web"
    & $Node "$(Split-Path $Node)\npx.cmd" vercel link --project=colorcheck --yes 2>$null
    & $Node "$(Split-Path $Node)\npx.cmd" vercel --prod --yes
    Write-Host "Site: https://colorcheck.vercel.app" -ForegroundColor Green
} else {
    Write-Host "Deploy web/ folder at vercel.com — project name: colorcheck" -ForegroundColor Yellow
}

Write-Host "`nSet MODEL_API_URL in Vercel to your Render API URL (see DEPLOY.md)" -ForegroundColor Cyan
