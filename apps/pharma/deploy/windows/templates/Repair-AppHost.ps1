#Requires -Version 5.1
# Patch current Program Files install with app-mode host (no terminal).
param(
    [string]$AppRoot = "C:\Program Files\Bproo\PharmaDesktop",
    [string]$RepoRoot = "D:\Projects\bproo-platform"
)
$ErrorActionPreference = "Stop"
$Templates = Join-Path $RepoRoot "apps\pharma\deploy\windows\templates"
foreach ($f in @("BprooPharmaHost.ps1", "BprooPharma.vbs", "Start-BprooPharma.cmd")) {
    Copy-Item (Join-Path $Templates $f) (Join-Path $AppRoot $f) -Force
}
# Desktop shortcut refresh hint
Write-Host "OK. Lancez: $AppRoot\BprooPharma.vbs" -ForegroundColor Green
Write-Host "Ou double-cliquez le raccourci apres l'avoir pointe vers BprooPharma.vbs" -ForegroundColor Yellow
