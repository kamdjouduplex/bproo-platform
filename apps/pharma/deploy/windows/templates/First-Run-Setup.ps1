#Requires -Version 5.1
param(
    [string]$ControlCenterUrl = "__CONTROL_CENTER_URL__",
    [string]$AppVersion = "__APP_VERSION__"
)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
$Php = Join-Path $Root "runtime\php\php.exe"
if (-not (Test-Path $Php)) { throw "PHP portable introuvable: $Php" }
$env:DESKTOP_RUNTIME = "1"
$phpDir = Join-Path $Root "runtime\php"
$env:Path = "$phpDir;$env:Path"

# Program Files is read-only for normal users — Laravel needs write on these trees
$writable = @(
    (Join-Path $Root "storage"),
    (Join-Path $Root "bootstrap\cache"),
    (Join-Path $Root "database")
)
foreach ($dir in $writable) {
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    # Users (S-1-5-32-545) modify, inherit to children — ignore failures if already correct
    & icacls $dir /grant "*S-1-5-32-545:(OI)(CI)M" /T 2>$null | Out-Null
}

$envExample = Join-Path $Root ".env.desktop.example"
$envFile = Join-Path $Root ".env"
if (-not (Test-Path $envFile)) {
    Copy-Item $envExample $envFile
}

$lines = Get-Content $envFile
$out = @()
$seenCc = $false
$seenDesktop = $false
$seenAppVer = $false
$seenDesktopVer = $false
foreach ($line in $lines) {
    if ($line -match '^CONTROL_CENTER_URL=') {
        $out += "CONTROL_CENTER_URL=$ControlCenterUrl"
        $seenCc = $true
        continue
    }
    if ($line -match '^DESKTOP_RUNTIME=') {
        $out += "DESKTOP_RUNTIME=1"
        $seenDesktop = $true
        continue
    }
    if ($line -match '^DESKTOP_APP_VERSION=') {
        $out += "DESKTOP_APP_VERSION=$AppVersion"
        $seenDesktopVer = $true
        continue
    }
    if ($line -match '^APP_VERSION=') {
        $out += "APP_VERSION=$AppVersion"
        $seenAppVer = $true
        continue
    }
    $out += $line
}
if (-not $seenCc) { $out += "CONTROL_CENTER_URL=$ControlCenterUrl" }
if (-not $seenDesktop) { $out += "DESKTOP_RUNTIME=1" }
if (-not $seenDesktopVer) { $out += "DESKTOP_APP_VERSION=$AppVersion" }
if (-not $seenAppVer) { $out += "APP_VERSION=$AppVersion" }

$dbFile = Join-Path $Root "database\desktop.sqlite"
if (-not (Test-Path $dbFile)) { New-Item -ItemType File -Path $dbFile | Out-Null }
$dbPosix = ($dbFile -replace '\\', '/')
# Dotenv breaks on unquoted paths with spaces (e.g. Program Files)
$dbEnvValue = '"' + $dbPosix + '"'
$final = @()
$seenDb = $false
$seenConn = $false
foreach ($line in $out) {
    if ($line -match '^DB_CONNECTION=') { $final += "DB_CONNECTION=sqlite"; $seenConn = $true; continue }
    if ($line -match '^DB_DATABASE=') { $final += "DB_DATABASE=$dbEnvValue"; $seenDb = $true; continue }
    $final += $line
}
if (-not $seenConn) { $final += "DB_CONNECTION=sqlite" }
if (-not $seenDb) { $final += "DB_DATABASE=$dbEnvValue" }
$final | Set-Content $envFile

if (-not (Select-String -Path $envFile -Pattern '^APP_KEY=base64:' -Quiet)) {
    & $Php artisan key:generate --force
}
& $Php artisan migrate --force
Write-Host "Setup OK. Lancez Activate-Licence.ps1 puis Start-BprooPharma.cmd" -ForegroundColor Green
