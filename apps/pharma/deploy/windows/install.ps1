#Requires -Version 5.1
<#
.SYNOPSIS
  Bootstrap a local Bproo Pharma desktop runtime (Phase 4 / packaging).
.DESCRIPTION
  Prefers bundled runtime\php from build-release.ps1; falls back to system PHP.
  Does not modify SaaS docker deploy.
#>
param(
    [string]$ControlCenterUrl = "http://127.0.0.1:8010",
    [string]$ListenHost = "127.0.0.1",
    [int]$Port = 8003
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
# When run from payload root via First-Run-Setup, AppDir is payload; from deploy/windows AppDir is apps/pharma
if (Test-Path (Join-Path $ScriptDir "..\..\artisan")) {
    $AppDir = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path
} elseif (Test-Path (Join-Path $ScriptDir "artisan")) {
    $AppDir = $ScriptDir
} else {
    $AppDir = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path
}
Set-Location $AppDir

function Resolve-Php {
    $bundled = Join-Path $AppDir "runtime\php\php.exe"
    if (Test-Path $bundled) {
        $env:Path = "$(Split-Path $bundled -Parent);$env:Path"
        return $bundled
    }
    $sys = Get-Command php -ErrorAction SilentlyContinue
    if ($sys) { return $sys.Source }
    throw "PHP introuvable (ni runtime\php\php.exe ni PATH)."
}

Write-Host "==> Bproo Pharma desktop install" -ForegroundColor Cyan
Write-Host "App: $AppDir"

$Php = Resolve-Php
Write-Host "PHP: $Php"

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

$envContent = Get-Content $envFile -Raw
if ($envContent -notmatch "DESKTOP_RUNTIME=") {
    Add-Content $envFile "`nDESKTOP_RUNTIME=1"
}
if ((Get-Content $envFile -Raw) -notmatch "CONTROL_CENTER_URL=") {
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

$dbPosix = ($dbFile -replace '\\', '/')
$dbEnvValue = '"' + $dbPosix + '"'
$lines = Get-Content $envFile
$out = @()
$seenDb = $false
$seenConn = $false
foreach ($line in $lines) {
    if ($line -match '^DB_CONNECTION=') { $out += "DB_CONNECTION=sqlite"; $seenConn = $true; continue }
    if ($line -match '^DB_DATABASE=') { $out += "DB_DATABASE=$dbEnvValue"; $seenDb = $true; continue }
    $out += $line
}
if (-not $seenConn) { $out += "DB_CONNECTION=sqlite" }
if (-not $seenDb) { $out += "DB_DATABASE=$dbEnvValue" }
$out | Set-Content $envFile

New-Item -ItemType Directory -Force -Path (Join-Path $AppDir "storage\app\desktop\staging") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $AppDir "storage\app\desktop\rollback") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $AppDir "storage\app\desktop\packages") | Out-Null

# Vendor expected from build-release; only run composer if missing and available
if (-not (Test-Path (Join-Path $AppDir "vendor\autoload.php"))) {
    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        throw "vendor/ manquant et Composer introuvable. Rebuild avec build-release.ps1."
    }
    Write-Host "==> composer install"
    $env:COMPOSER_MIRROR_PATH_REPOS = "1"
    composer install --no-interaction
}

Write-Host "==> APP_KEY / migrate"
$env:DESKTOP_RUNTIME = "1"
$envRaw = Get-Content $envFile -Raw
if ($envRaw -notmatch '(?m)^APP_KEY=base64:[A-Za-z0-9+/=]+') {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $key = 'base64:' + [Convert]::ToBase64String($bytes)
    if ($envRaw -match '(?m)^APP_KEY=') {
        $envRaw = [regex]::Replace($envRaw, '(?m)^APP_KEY=.*$', "APP_KEY=$key")
    } else {
        $envRaw = "APP_KEY=$key`r`n" + $envRaw
    }
    Set-Content -Path $envFile -Value $envRaw -Encoding UTF8
}
& icacls $envFile /grant "*S-1-5-32-545:M" 2>$null | Out-Null
& $Php artisan migrate --force

Write-Host ""
Write-Host "Install OK." -ForegroundColor Green
Write-Host "Next: Activate-Licence.ps1 (payload) or: php artisan desktop:activate `"CODE`""
Write-Host "Control Center: $ControlCenterUrl"
