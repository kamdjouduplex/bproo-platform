#Requires -Version 5.1
param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8003
)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

try {
    $Php = Join-Path $Root "runtime\php\php.exe"
    if (-not (Test-Path $Php)) { throw "PHP portable introuvable: $Php" }
    $env:DESKTOP_RUNTIME = "1"
    $phpDir = Join-Path $Root "runtime\php"
    $env:Path = "$phpDir;$env:Path"

    if (-not (Test-Path (Join-Path $Root ".env"))) {
        throw "Fichier .env manquant. Lancez d abord First-Run-Setup.ps1"
    }

    $state = Join-Path $Root "storage\app\desktop\state.json"
    if (-not (Test-Path $state)) {
        Write-Host "Licence pas encore activee (state.json absent)." -ForegroundColor Yellow
        Write-Host "Lancez Activate-Licence.ps1 (menu Demarrer > Bproo Pharma Desktop > Activer la licence)."
        throw "Activation requise avant de demarrer."
    }

    Write-Host "Bproo Pharma Desktop - http://${HostName}:${Port}" -ForegroundColor Cyan
    Write-Host "Ctrl+C pour arreter."
    & $Php artisan serve --host=$HostName --port=$Port
    if ($LASTEXITCODE -ne 0) { throw "artisan serve a quitte avec le code $LASTEXITCODE" }
} catch {
    Write-Host ""
    Write-Host "ERREUR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Read-Host "Appuyez sur Entree pour fermer"
    exit 1
}
