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

    $hasSignKey = $false
    if (Test-Path $envFile) {
        $hasSignKey = [bool](Select-String -Path $envFile -Pattern '^DESKTOP_LICENCE_SIGNING_KEY=.+' -Quiet)
    }
    if (-not $hasSignKey) {
        Write-Host "DESKTOP_LICENCE_SIGNING_KEY manquante dans .env" -ForegroundColor Yellow
        Write-Host "Collez la meme valeur que sur le Control Center (ou APP_KEY du CC si la cle licence y est vide)." -ForegroundColor Yellow
        $signKey = Read-Host "DESKTOP_LICENCE_SIGNING_KEY"
        if (-not $signKey) { throw "Cle de signature requise pour verifier le jeton hors-ligne." }
        if (Select-String -Path $envFile -Pattern '^DESKTOP_LICENCE_SIGNING_KEY=' -Quiet) {
            (Get-Content $envFile) | ForEach-Object {
                if ($_ -match '^DESKTOP_LICENCE_SIGNING_KEY=') { "DESKTOP_LICENCE_SIGNING_KEY=$signKey" } else { $_ }
            } | Set-Content $envFile
        } else {
            Add-Content $envFile "`nDESKTOP_LICENCE_SIGNING_KEY=$signKey"
        }
    }

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

    Write-Host "==> Verification locale HMAC..."
    & $Php artisan desktop:licence-status
    if ($LASTEXITCODE -ne 0) { throw "Verification licence locale echouee." }

    Write-Host ""
    Write-Host "OK - licence activee. Dans le CC le statut doit passer a active." -ForegroundColor Green
    Write-Host "Ensuite lancez BprooPharma.vbs (ou le raccourci bureau)."
} catch {
    Write-Host ""
    Write-Host "ERREUR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Verifiez: Control Center demarre, URL dans .env (CONTROL_CENTER_URL), code non deja utilise."
    Pause-End
    exit 1
}

Pause-End
