@echo off
setlocal EnableExtensions
if not exist "%~dp0venv\Scripts\python.exe" exit /b 2
"%~dp0venv\Scripts\python.exe" "%~dp0predict.py" %*
exit /b %ERRORLEVEL%
