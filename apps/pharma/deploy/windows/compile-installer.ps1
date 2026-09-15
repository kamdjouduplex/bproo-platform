#Requires -Version 5.1
<#
.SYNOPSIS
  Compile the Inno Setup installer from dist/payload.
#>
param(
    [string]$Version = "0.1.0",
    [string]$IsccPath = ""
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Payload = Join-Path $ScriptDir "dist\payload"
$Iss = Join-Path $ScriptDir "innosetup\bproo-pharma.iss"
$OutputDir = Join-Path $ScriptDir "output"

if (-not (Test-Path $Payload)) {
    throw "Payload manquant: $Payload`nLance d'abord: .\build-release.ps1 -Version $Version"
}
if (-not (Test-Path (Join-Path $Payload "artisan"))) {
    throw "Payload incomplet (artisan manquant)."
}
if (-not (Test-Path (Join-Path $Payload "runtime\php\php.exe"))) {
    Write-Warning "PHP portable absent du payload — l'install client exigera PHP systeme."
}

if (-not $IsccPath) {
    $candidates = @(
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
        "${env:LocalAppData}\Programs\Inno Setup 6\ISCC.exe"
    )
    foreach ($c in $candidates) {
        if (Test-Path $c) { $IsccPath = $c; break }
    }
}

if (-not $IsccPath -or -not (Test-Path $IsccPath)) {
    throw @"
Inno Setup 6 (ISCC.exe) introuvable.
1) Installe https://jrsoftware.org/isdl.php
2) Relance: .\compile-installer.ps1 -Version $Version
   ou: .\compile-installer.ps1 -IsccPath 'C:\Path\ISCC.exe'
"@
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
Write-Host "==> Compiling with $IsccPath" -ForegroundColor Cyan
& $IsccPath "/DMyAppVersion=$Version" $Iss
if ($LASTEXITCODE -ne 0) {
    throw "ISCC failed with code $LASTEXITCODE"
}

$setup = Get-ChildItem $OutputDir -Filter "bproo-pharma-desktop-$Version-setup.exe" | Select-Object -First 1
if ($setup) {
    $hash = (Get-FileHash $setup.FullName -Algorithm SHA256).Hash
    Set-Content -Path ($setup.FullName + ".sha256") -Value $hash -Encoding ASCII
    Write-Host "Installer: $($setup.FullName)" -ForegroundColor Green
    Write-Host "SHA-256:   $hash"
} else {
    Write-Host "Compile OK — check $OutputDir" -ForegroundColor Green
}
