@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "PHP_EXE="
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE for /d %%D in ("C:\wamp64\bin\php\php*") do set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE where php >nul 2>&1 && set "PHP_EXE=php"

if not defined PHP_EXE (
  echo ERROR: Could not find php.exe.
  echo Install WAMP or add PHP to PATH.
  pause
  exit /b 1
)

echo.
echo  Color Classifier - dev server
echo  Site:    http://127.0.0.1:8899/
echo  Health:  http://127.0.0.1:8899/health.php  (instant, should load immediately)
echo.
echo  For FAST predictions (recommended):
echo    1. Run start_model_server.bat in another window and wait until it says "ready"
echo    2. Keep that window open while using the site
echo.
echo  First prediction without model server can take 2-5 minutes on CPU.
echo  Press Ctrl+C here to stop the web server.
echo.

"%PHP_EXE%" -d max_execution_time=600 -d default_socket_timeout=300 -d upload_max_filesize=40M -d post_max_size=42M -S 127.0.0.1:8899 -t "%CD%"
