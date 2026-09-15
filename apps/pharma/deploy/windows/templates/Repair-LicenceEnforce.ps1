#Requires -Version 5.1
# Deploy licence enforcement sources into an existing Program Files install.
param(
    [string]$AppRoot = "C:\Program Files\Bproo\PharmaDesktop",
    [string]$RepoRoot = "D:\Projects\bproo-platform"
)
$ErrorActionPreference = "Stop"
$DesktopPkg = Join-Path $RepoRoot "packages\platform\desktop"
$VendorDesktop = Join-Path $AppRoot "vendor\bproo\platform-desktop"
$Pharma = Join-Path $RepoRoot "apps\pharma"

if (-not (Test-Path $AppRoot)) { throw "Install missing: $AppRoot" }

Write-Host "==> Copy platform-desktop package" -ForegroundColor Cyan
robocopy (Join-Path $DesktopPkg "src") (Join-Path $VendorDesktop "src") /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
robocopy (Join-Path $DesktopPkg "config") (Join-Path $VendorDesktop "config") /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
robocopy (Join-Path $DesktopPkg "resources") (Join-Path $VendorDesktop "resources") /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null

Copy-Item (Join-Path $Pharma "app\Http\Kernel.php") (Join-Path $AppRoot "app\Http\Kernel.php") -Force
Copy-Item (Join-Path $Pharma "routes\web.php") (Join-Path $AppRoot "routes\web.php") -Force
Copy-Item (Join-Path $RepoRoot "packages\platform\tenancy\src\Http\Middleware\EnsureTenantActive.php") (Join-Path $AppRoot "vendor\bproo\platform-tenancy\src\Http\Middleware\EnsureTenantActive.php") -Force

$Templates = Join-Path $Pharma "deploy\windows\templates"
foreach ($f in @("BprooPharmaHost.ps1", "Activate-Licence.ps1", "BprooPharma.vbs", "Start-BprooPharma.cmd")) {
    Copy-Item (Join-Path $Templates $f) (Join-Path $AppRoot $f) -Force
}

Write-Host ""
Write-Host "OK. Next:" -ForegroundColor Green
Write-Host "1) Set DESKTOP_LICENCE_SIGNING_KEY in $AppRoot\.env (same as Control Center)"
Write-Host "2) Run Activate-Licence.ps1 (or desktop:heartbeat if already activated)"
Write-Host "3) php artisan desktop:licence-status"
