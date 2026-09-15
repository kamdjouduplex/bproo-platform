#Requires -Version 5.1
<#
.SYNOPSIS
  Assemble a self-contained Bproo Pharma desktop payload (app + vendor + portable PHP).
.DESCRIPTION
  Output: deploy/windows/dist/payload
  Optional zip for Control Center update channel.
  Does not modify SaaS docker packaging.
#>
param(
    [string]$Version = "0.1.0",
    [string]$PhpVersion = "8.3.21",
    [string]$ControlCenterUrl = "https://admin.afroinov.com",
    [switch]$SkipPhpDownload,
    [switch]$SkipComposer,
    [switch]$ZipPayload
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path
$DistRoot = Join-Path $ScriptDir "dist"
$Payload = Join-Path $DistRoot "payload"
$CacheDir = Join-Path $ScriptDir ".cache"

Write-Host "==> Build Pharma desktop release $Version" -ForegroundColor Cyan
Write-Host "App:     $AppDir"
Write-Host "Payload: $Payload"

New-Item -ItemType Directory -Force -Path $CacheDir | Out-Null
if (Test-Path $Payload) {
    Write-Host "==> Cleaning previous payload"
    Remove-Item -Recurse -Force $Payload
}
New-Item -ItemType Directory -Force -Path $Payload | Out-Null

# --- Composer (mirror path repos so vendor is self-contained) ---
if (-not $SkipComposer) {
    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        throw "Composer introuvable dans le PATH (requis pour builder le payload)."
    }
    Write-Host "==> composer install --no-dev (COMPOSER_MIRROR_PATH_REPOS=1)"
    Push-Location $AppDir
    try {
        $env:COMPOSER_MIRROR_PATH_REPOS = "1"
        $env:DESKTOP_RUNTIME = "1"
        composer install --no-dev --optimize-autoloader --no-interaction
    } finally {
        Pop-Location
    }
}

# --- Copy app tree ---
# IMPORTANT: /XD matches directory NAMES (leaf) anywhere in the tree.
# Never exclude bare names like "output" or "dist" (breaks Symfony Console / Livewire assets).
Write-Host "==> Copying application files"
$excludeDirs = @(
    (Join-Path $AppDir ".git"),
    (Join-Path $AppDir ".idea"),
    (Join-Path $AppDir ".vscode"),
    (Join-Path $AppDir "node_modules"),
    (Join-Path $AppDir "tests"),
    (Join-Path $AppDir "deploy\windows\.cache"),
    (Join-Path $AppDir "deploy\windows\output")
)
$xd = @('/XD') + $excludeDirs
& robocopy $AppDir $Payload /E /NFL /NDL /NJH /NJS /nc /ns /np @xd `
    /XF '.env' '.env.backup' '*.log' 'phpunit.xml' 'phpunit.xml.dist' | Out-Null
# robocopy exit codes 0-7 are success-ish
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed with code $LASTEXITCODE"
}

# Drop packaging artefacts copied from deploy/windows (do NOT /XD "dist" — it strips vendor/*/dist)
$payloadWindowsDist = Join-Path $Payload "deploy\windows\dist"
if (Test-Path $payloadWindowsDist) {
    Remove-Item -Recurse -Force $payloadWindowsDist
}

# Sanity: critical vendor files must exist (guards against exclude collisions)
$mustExist = @(
    "vendor\autoload.php",
    "vendor\symfony\console\Output\ConsoleOutput.php",
    "vendor\livewire\livewire\dist\manifest.json",
    "artisan"
)
foreach ($rel in $mustExist) {
    $p = Join-Path $Payload $rel
    if (-not (Test-Path $p)) {
        throw "Payload incomplete after copy: missing $rel"
    }
}
Write-Host "Payload vendor sanity check OK"

# Ensure clean storage skeleton
@(
    'storage\app\public',
    'storage\app\desktop\packages',
    'storage\app\desktop\staging',
    'storage\app\desktop\rollback',
    'storage\framework\cache',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs',
    'bootstrap\cache',
    'database'
) | ForEach-Object {
    New-Item -ItemType Directory -Force -Path (Join-Path $Payload $_) | Out-Null
}

# Drop windows deploy helpers into payload (already copied if under deploy/windows)
# Ensure desktop env example present
$envExampleSrc = Join-Path $AppDir ".env.desktop.example"
$envExampleDst = Join-Path $Payload ".env.desktop.example"
if (Test-Path $envExampleSrc) {
    Copy-Item $envExampleSrc $envExampleDst -Force
}

# Stamp version
Set-Content -Path (Join-Path $Payload "DESKTOP_VERSION.txt") -Value $Version -Encoding UTF8
Set-Content -Path (Join-Path $Payload "storage\app\desktop\CURRENT_VERSION") -Value $Version -Encoding UTF8

# --- Portable PHP ---
$PhpHome = Join-Path $Payload "runtime\php"
if (-not $SkipPhpDownload) {
    Write-Host "==> Fetching portable PHP $PhpVersion (VS16 x64 Thread Safe)"
    $phpZipName = "php-$PhpVersion-Win32-vs16-x64.zip"
    $phpUrl = "https://windows.php.net/downloads/releases/$phpZipName"
    $phpZip = Join-Path $CacheDir $phpZipName

    if (-not (Test-Path $phpZip)) {
        Write-Host "Downloading $phpUrl"
        try {
            Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip -UseBasicParsing
        } catch {
            # Fallback: archives folder for older builds
            $phpUrl = "https://windows.php.net/downloads/releases/archives/$phpZipName"
            Write-Host "Retry archives: $phpUrl"
            Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip -UseBasicParsing
        }
    } else {
        Write-Host "Using cached $phpZip"
    }

    if (Test-Path $PhpHome) { Remove-Item -Recurse -Force $PhpHome }
    New-Item -ItemType Directory -Force -Path $PhpHome | Out-Null
    Expand-Archive -Path $phpZip -DestinationPath $PhpHome -Force

    $phpIniProd = Join-Path $PhpHome "php.ini-production"
    $phpIni = Join-Path $PhpHome "php.ini"
    if (Test-Path $phpIniProd) {
        Copy-Item $phpIniProd $phpIni -Force
    } else {
        New-Item -ItemType File -Path $phpIni | Out-Null
    }

    $extDir = Join-Path $PhpHome "ext"
    $ini = Get-Content $phpIni -Raw
    $ini = $ini -replace ';?\s*extension_dir\s*=\s*"ext"', ("extension_dir = `"$extDir`"")
    if ($ini -notmatch 'extension_dir') {
        $ini += "`r`nextension_dir = `"$extDir`"`r`n"
    }
    $extensions = @(
        'curl', 'fileinfo', 'gd', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3',
        'tokenizer', 'xml', 'zip', 'intl', 'sodium'
    )
    foreach ($ext in $extensions) {
        $dll = Join-Path $extDir "php_$ext.dll"
        if (Test-Path $dll) {
            if ($ini -match "(?m)^;?\s*extension\s*=\s*$ext\s*$") {
                $ini = [regex]::Replace($ini, "(?m)^;?\s*extension\s*=\s*$ext\s*$", "extension=$ext")
            } elseif ($ini -notmatch "(?m)^extension\s*=\s*$ext\s*$") {
                $ini += "`r`nextension=$ext"
            }
        }
    }
    $ini += "`r`n`r`n; Bproo desktop`r`ndate.timezone=UTC`r`nmemory_limit=512M`r`nmax_execution_time=300`r`n"
    Set-Content -Path $phpIni -Value $ini -Encoding UTF8
    Write-Host "PHP ready: $(Join-Path $PhpHome 'php.exe')"
} else {
    Write-Host "==> SkipPhpDownload - expecting runtime\php already present"
}

# --- Composer phar (for rare on-box repairs; vendor already baked) ---
$composerPhar = Join-Path $Payload "runtime\composer.phar"
$composerCache = Join-Path $CacheDir "composer.phar"
if (-not (Test-Path $composerCache)) {
    Write-Host "==> Downloading composer.phar"
    Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" -OutFile $composerCache -UseBasicParsing
}
Copy-Item $composerCache $composerPhar -Force

# --- Launchers from templates (no nested here-strings) ---
Write-Host "==> Installing launchers"
$Templates = Join-Path $ScriptDir "templates"
Copy-Item (Join-Path $Templates "Start-BprooPharma.ps1") (Join-Path $Payload "Start-BprooPharma.ps1") -Force
Copy-Item (Join-Path $Templates "Start-BprooPharma.cmd") (Join-Path $Payload "Start-BprooPharma.cmd") -Force
Copy-Item (Join-Path $Templates "BprooPharmaHost.ps1") (Join-Path $Payload "BprooPharmaHost.ps1") -Force
Copy-Item (Join-Path $Templates "BprooPharma.vbs") (Join-Path $Payload "BprooPharma.vbs") -Force
Copy-Item (Join-Path $Templates "Activate-Licence.ps1") (Join-Path $Payload "Activate-Licence.ps1") -Force
Copy-Item (Join-Path $Templates "Set-ControlCenterUrl.ps1") (Join-Path $Payload "Set-ControlCenterUrl.ps1") -Force
Copy-Item (Join-Path $Templates "Repair-ComposerAutoload.ps1") (Join-Path $Payload "Repair-ComposerAutoload.ps1") -Force
if (Test-Path (Join-Path $Templates "Repair-DesktopBootstrap.ps1")) {
    Copy-Item (Join-Path $Templates "Repair-DesktopBootstrap.ps1") (Join-Path $Payload "Repair-DesktopBootstrap.ps1") -Force
}

Write-Host "==> Repairing Composer autoload for stand-alone vendor/"
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Payload "Repair-ComposerAutoload.ps1") -AppRoot $Payload
if ($LASTEXITCODE -ne 0) {
    throw "Repair-ComposerAutoload failed"
}

$setupTemplate = Get-Content (Join-Path $Templates "First-Run-Setup.ps1") -Raw
$setupTemplate = $setupTemplate.Replace("__CONTROL_CENTER_URL__", $ControlCenterUrl)
$setupTemplate = $setupTemplate.Replace("__APP_VERSION__", $Version)
Set-Content -Path (Join-Path $Payload "First-Run-Setup.ps1") -Value $setupTemplate -Encoding UTF8

# Manifest for ops / CC updates
$manifest = [ordered]@{
    product_key = "pharma"
    version = $Version
    built_at = (Get-Date).ToString("o")
    control_center_url_default = $ControlCenterUrl
    entrypoints = [ordered]@{
        setup = "First-Run-Setup.ps1"
        activate = "Activate-Licence.ps1"
        start = "Start-BprooPharma.cmd"
    }
} | ConvertTo-Json -Depth 5
Set-Content -Path (Join-Path $Payload "desktop-manifest.json") -Value $manifest -Encoding UTF8

if ($ZipPayload) {
    $zipPath = Join-Path $DistRoot "bproo-pharma-desktop-$Version.zip"
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    Write-Host "==> Zipping $zipPath"
    Compress-Archive -Path (Join-Path $Payload "*") -DestinationPath $zipPath -Force
    $hash = (Get-FileHash $zipPath -Algorithm SHA256).Hash
    Set-Content -Path "$zipPath.sha256" -Value $hash -Encoding ASCII
    Write-Host "ZIP SHA-256: $hash" -ForegroundColor Green
}

Write-Host ""
Write-Host "Payload ready: $Payload" -ForegroundColor Green
Write-Host "Next: .\compile-installer.ps1 -Version $Version"
Write-Host "Or zip already built with -ZipPayload for CC Updates desktop."
