@echo off
cd /d "%~dp0"
if not exist "venv\Scripts\python.exe" (
  echo Run setup.bat first to create venv.
  pause
  exit /b 1
)
echo Starting model server on http://127.0.0.1:8765
echo Keep this window open while using the site for fast predictions.
echo.
venv\Scripts\python.exe model_server.py
pause
