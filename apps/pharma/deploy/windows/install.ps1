#Requires -Version 5.1
<#
.SYNOPSIS
  Bootstrap a local Bproo Pharma desktop runtime (Phase 4).
.DESCRIPTION
  Prepares .env from .env.desktop.example, SQLite DB, composer install,
  app key, and migrations (including desktop_outbox_events).
  Does not modify SaaS docker deploy.
#>
param(
    [string]$ControlCenterUrl = "http://127.0.0.1:8010",
    [string]$ListenHost = "127.0.0.1",
    [int]$Port = 8003
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Resolve-Path (Join-Path $ScriptDir "..\..")
Set-Location $AppDir

Write-Host "==> Bproo Pharma desktop install" -ForegroundColor Cyan
Write-Host "App: $AppDir"

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw "PHP introuvable dans le PATH."
}
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw "Composer introuvable dans le PATH."
}

$envExample = Join-Path $AppDir ".env.desktop.example"
$envFile = Join-Path $AppDir ".env"
if (-not (Test-Path $envExample)) {
    throw "Missing .env.desktop.example"
}

if (-not (Test-Path $envFile)) {
    Copy-Item $envExample $envFile
    Write-Host "Created .env from .env.desktop.example"
} else {
    Write-Host ".env already exists — leaving it (set DESKTOP_RUNTIME=1 manually if needed)"
}

# Ensure desktop flags
$envContent = Get-Content $envFile -Raw
if ($envContent -notmatch "DESKTOP_RUNTIME=") {
    Add-Content $envFile "`nDESKTOP_RUNTIME=1"
}
$envContent = Get-Content $envFile -Raw
if ($envContent -notmatch "CONTROL_CENTER_URL=") {
    Add-Content $envFile "`nCONTROL_CENTER_URL=$ControlCenterUrl"
} else {
    (Get-Content $envFile) | ForEach-Object {
        if ($_ -match '^CONTROL_CENTER_URL=') { "CONTROL_CENTER_URL=$ControlCenterUrl" } else { $_ }
    } | Set-Content $envFile
}

$dbDir = Join-Path $AppDir "database"
New-Item -ItemType Directory -Force -Path $dbDir | Out-Null
$dbFile = Join-Path $dbDir "desktop.sqlite"
if (-not (Test-Path $dbFile)) {
    New-Item -ItemType File -Path $dbFile | Out-Null
}

# Force sqlite path in .env (absolute, forward slashes for PHP)
$dbPosix = ($dbFile -replace '\\', '/')
$lines = Get-Content $envFile
$out = @()
$seenDb = $false
$seenConn = $false
foreach ($line in $lines) {
    if ($line -match '^DB_CONNECTION=') { $out += "DB_CONNECTION=sqlite"; $seenConn = $true; continue }
    if ($line -match '^DB_DATABASE=') { $out += "DB_DATABASE=$dbPosix"; $seenDb = $true; continue }
    $out += $line
}
if (-not $seenConn) { $out += "DB_CONNECTION=sqlite" }
if (-not $seenDb) { $out += "DB_DATABASE=$dbPosix" }
$out | Set-Content $envFile

New-Item -ItemType Directory -Force -Path (Join-Path $AppDir "storage\app\desktop\staging") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $AppDir "storage\app\desktop\rollback") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $AppDir "storage\app\desktop\packages") | Out-Null

Write-Host "==> composer install"
composer install --no-interaction

if (-not (Select-String -Path $envFile -Pattern '^APP_KEY=base64:' -Quiet)) {
    Write-Host "==> php artisan key:generate"
    php artisan key:generate --force
}

Write-Host "==> php artisan migrate"
$env:DESKTOP_RUNTIME = "1"
php artisan migrate --force

Write-Host ""
Write-Host "Install OK." -ForegroundColor Green
Write-Host "Next:"
Write-Host "  1. .\start.ps1"
Write-Host "  2. php artisan desktop:activate `"YOUR-CODE`""
Write-Host "  3. php artisan desktop:heartbeat"
Write-Host "Control Center: $ControlCenterUrl"
