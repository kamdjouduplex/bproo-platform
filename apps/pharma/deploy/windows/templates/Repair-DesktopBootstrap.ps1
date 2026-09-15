#Requires -Version 5.1
<#
.SYNOPSIS
  Patch a Program Files PharmaDesktop install with desktop:bootstrap and run it.
#>
param(
    [string]$AppRoot = "C:\Program Files\Bproo\PharmaDesktop",
    [string]$RepoRoot = ""
)
$ErrorActionPreference = "Stop"
if (-not $RepoRoot) {
    $RepoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..\..\..\..") -ErrorAction SilentlyContinue
    if (-not $RepoRoot) {
        $RepoRoot = "D:\Projects\bproo-platform"
    }
}
$RepoRoot = $RepoRoot.ToString().TrimEnd('\')
$Pharma = Join-Path $RepoRoot "apps\pharma"

if (-not (Test-Path $AppRoot)) { throw "Install introuvable: $AppRoot" }
if (-not (Test-Path $Pharma)) { throw "Repo pharma introuvable: $Pharma" }

$Php = Join-Path $AppRoot "runtime\php\php.exe"
if (-not (Test-Path $Php)) { throw "PHP portable introuvable: $Php" }

Write-Host "==> Copie fichiers bootstrap" -ForegroundColor Cyan
New-Item -ItemType Directory -Force -Path (Join-Path $AppRoot "app\Console\Commands") | Out-Null
Copy-Item (Join-Path $Pharma "app\Console\Commands\DesktopBootstrapCommand.php") (Join-Path $AppRoot "app\Console\Commands\DesktopBootstrapCommand.php") -Force
Copy-Item (Join-Path $RepoRoot "packages\platform\tenancy\src\Models\Tenant.php") (Join-Path $AppRoot "vendor\bproo\platform-tenancy\src\Models\Tenant.php") -Force
Copy-Item (Join-Path $Pharma "config\inovcom.php") (Join-Path $AppRoot "config\inovcom.php") -Force

# SQLite-safe migration patches used by bootstrap
$migPairs = @(
    @("database\migrations\tenant_modules\2026_01_29_000002_make_provider_id_nullable_on_purchase_orders.php"),
    @("database\migrations\tenant_modules\2026_05_30_000200_add_quotation_to_delivery_notes.php"),
    @("database\migrations\tenant_modules\2026_05_30_000301_add_superseded_status_to_invoices.php")
)
foreach ($rel in $migPairs) {
    $src = Join-Path $Pharma $rel
    $dst = Join-Path $AppRoot $rel
    if (Test-Path $src) {
        New-Item -ItemType Directory -Force -Path (Split-Path $dst) | Out-Null
        Copy-Item $src $dst -Force
    }
}

foreach ($dir in @("storage", "bootstrap\cache", "database")) {
    $p = Join-Path $AppRoot $dir
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Force -Path $p | Out-Null }
    & icacls $p /grant "*S-1-5-32-545:(OI)(CI)M" /T 2>$null | Out-Null
}

$envFile = Join-Path $AppRoot ".env"
if (-not (Test-Path $envFile)) { throw ".env manquant — lancez First-Run-Setup.ps1 d abord" }

$raw = Get-Content $envFile -Raw
if ($raw -notmatch '(?m)^INOVCOM_TENANT_DATABASE_DRIVER=') {
    Add-Content $envFile "`nINOVCOM_TENANT_DATABASE_DRIVER=sqlite"
} else {
    $raw = [regex]::Replace($raw, '(?m)^INOVCOM_TENANT_DATABASE_DRIVER=.*$', 'INOVCOM_TENANT_DATABASE_DRIVER=sqlite')
    Set-Content -Path $envFile -Value $raw -Encoding UTF8
}
& icacls $envFile /grant "*S-1-5-32-545:M" 2>$null | Out-Null

Set-Location $AppRoot
$env:DESKTOP_RUNTIME = "1"
$env:Path = "$(Join-Path $AppRoot 'runtime\php');$env:Path"

Write-Host "==> desktop:bootstrap" -ForegroundColor Cyan
& $Php artisan desktop:bootstrap --force
if ($LASTEXITCODE -ne 0) { throw "desktop:bootstrap failed ($LASTEXITCODE)" }

Write-Host ""
Write-Host "OK. Connexion: http://127.0.0.1:8003/app/login?tenant=pharma" -ForegroundColor Green
Write-Host "Compte: admin@officine.local / password" -ForegroundColor Yellow
