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

    $envFile = Join-Path $Root ".env"
    if (-not (Test-Path $envFile)) {
        throw "Fichier .env manquant. Lancez d abord First-Run-Setup.ps1"
    }

    # Safety net: empty APP_KEY from a partial first-run
    if (-not (Select-String -Path $envFile -Pattern '^APP_KEY=base64:' -Quiet)) {
        $bytes = New-Object byte[] 32
        [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
        $key = 'base64:' + [Convert]::ToBase64String($bytes)
        $raw = Get-Content $envFile -Raw
        if ($raw -match '(?m)^APP_KEY=') {
            $raw = [regex]::Replace($raw, '(?m)^APP_KEY=.*$', "APP_KEY=$key")
        } else {
            $raw = "APP_KEY=$key`r`n" + $raw
        }
        try {
            Set-Content -Path $envFile -Value $raw -Encoding UTF8
        } catch {
            throw "APP_KEY manquante et .env non modifiable. Relancez First-Run-Setup.ps1 en Administrateur."
        }
        if (-not (Select-String -Path $envFile -Pattern '^APP_KEY=base64:' -Quiet)) {
            throw "APP_KEY manquante. Relancez First-Run-Setup.ps1 en Administrateur."
        }
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
