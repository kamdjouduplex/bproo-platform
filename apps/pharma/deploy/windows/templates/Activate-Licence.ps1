#Requires -Version 5.1
param([string]$Code)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

function Pause-End {
    Write-Host ""
    Read-Host "Appuyez sur Entree pour fermer"
}

try {
    $Php = Join-Path $Root "runtime\php\php.exe"
    if (-not (Test-Path $Php)) { throw "PHP portable introuvable: $Php" }

    $env:DESKTOP_RUNTIME = "1"
    $phpDir = Join-Path $Root "runtime\php"
    $env:Path = "$phpDir;$env:Path"

    $envFile = Join-Path $Root ".env"
    $ccUrl = "http://127.0.0.1:8000"
    if (Test-Path $envFile) {
        $m = Select-String -Path $envFile -Pattern '^CONTROL_CENTER_URL=(.+)$' | Select-Object -First 1
        if ($m) { $ccUrl = $m.Matches[0].Groups[1].Value.Trim() }
    }

    Write-Host "Control Center vise: $ccUrl" -ForegroundColor Cyan
    Write-Host "Le Control Center DOIT tourner et etre joignable a cette URL." -ForegroundColor Yellow
    Write-Host ""

    if (-not $Code) {
        $Code = Read-Host "Code d activation Control Center"
    }
    if (-not $Code) { throw "Code vide." }

    Write-Host "==> Activation..."
    & $Php artisan desktop:activate $Code
    if ($LASTEXITCODE -ne 0) { throw "Activation echouee (exit $LASTEXITCODE)." }

    Write-Host "==> Heartbeat..."
    & $Php artisan desktop:heartbeat
    if ($LASTEXITCODE -ne 0) { throw "Heartbeat echoue (exit $LASTEXITCODE)." }

    Write-Host ""
    Write-Host "OK - licence activee. Dans le CC le statut doit passer a active." -ForegroundColor Green
    Write-Host "Ensuite lancez Start-BprooPharma.cmd (ou le raccourci bureau)."
} catch {
    Write-Host ""
    Write-Host "ERREUR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Verifiez: Control Center demarre, URL dans .env (CONTROL_CENTER_URL), code non deja utilise."
    Pause-End
    exit 1
}

Pause-End
