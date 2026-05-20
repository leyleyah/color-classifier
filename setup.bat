@echo off
cd /d "%~dp0"
echo Creating Python virtual environment...
py -3.11 -m venv venv
if errorlevel 1 (
    echo Failed to create venv. Install Python 3.11 and try again.
    exit /b 1
)
echo Installing dependencies...
venv\Scripts\pip install -r requirements.txt
if errorlevel 1 (
    echo Failed to install dependencies.
    exit /b 1
)
echo.
echo Setup complete. Open http://localhost/Color%%20Classifier/ in your browser.
pause
