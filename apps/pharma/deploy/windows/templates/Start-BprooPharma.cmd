@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Start-BprooPharma.ps1"
if errorlevel 1 (
  echo.
  pause
)
