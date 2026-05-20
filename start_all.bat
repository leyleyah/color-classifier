@echo off
setlocal EnableExtensions
cd /d "%~dp0"

if not exist "venv\Scripts\python.exe" (
  echo Run setup.bat first.
  pause
  exit /b 1
)

set "PHP_EXE="
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE for /d %%D in ("C:\wamp64\bin\php\php*") do set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE where php >nul 2>&1 && set "PHP_EXE=php"
if not defined PHP_EXE (
  echo Install XAMPP or add php.exe to PATH.
  pause
  exit /b 1
)

echo Starting model server...
start "Color Model Server" cmd /k "cd /d "%~dp0" && venv\Scripts\python.exe model_server.py"

timeout /t 3 /nobreak >nul

echo Starting web server...
start "Color Classifier Web" cmd /k "cd /d "%~dp0" && "%PHP_EXE%" -d max_execution_time=600 -S 127.0.0.1:8899 -t "%~dp0""

timeout /t 2 /nobreak >nul
start http://127.0.0.1:8899/

echo.
echo Both servers started in separate windows.
echo Site: http://127.0.0.1:8899/
echo Close those windows to stop the servers.
pause
