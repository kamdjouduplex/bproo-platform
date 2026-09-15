@echo off
REM Prefer silent VBS host (Edge/Chrome app mode). Fallback: console start script.
cd /d "%~dp0"
if exist "%~dp0BprooPharma.vbs" (
  wscript.exe "%~dp0BprooPharma.vbs"
  exit /b 0
)
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Start-BprooPharma.ps1"
if errorlevel 1 (
  echo.
  pause
)
