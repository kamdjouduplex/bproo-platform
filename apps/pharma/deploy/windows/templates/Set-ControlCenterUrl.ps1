#Requires -Version 5.1
param(
    [Parameter(Mandatory = $false)]
    [string]$ControlCenterUrl
)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

try {
    $envFile = Join-Path $Root ".env"
    if (-not (Test-Path $envFile)) {
        throw ".env manquant. Lancez First-Run-Setup.ps1 d abord."
    }

    if (-not $ControlCenterUrl) {
        Write-Host "Exemples:"
        Write-Host "  http://127.0.0.1:8000   (CC local sur le port 8000)"
        Write-Host "  http://127.0.0.1:8010   (CC local sur le port 8010)"
        Write-Host "  https://admin.votredomaine.com"
        $ControlCenterUrl = Read-Host "URL Control Center"
    }
    $ControlCenterUrl = $ControlCenterUrl.Trim().TrimEnd('/')
    if (-not $ControlCenterUrl) { throw "URL vide." }

    $lines = Get-Content $envFile
    $out = @()
    $seen = $false
    foreach ($line in $lines) {
        if ($line -match '^CONTROL_CENTER_URL=') {
            $out += "CONTROL_CENTER_URL=$ControlCenterUrl"
            $seen = $true
        } else {
            $out += $line
        }
    }
    if (-not $seen) { $out += "CONTROL_CENTER_URL=$ControlCenterUrl" }
    $out | Set-Content $envFile

    Write-Host "OK - CONTROL_CENTER_URL=$ControlCenterUrl" -ForegroundColor Green
    Write-Host "Relancez Activate-Licence.ps1 (Control Center doit tourner)."
} catch {
    Write-Host "ERREUR: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Read-Host "Appuyez sur Entree pour fermer"
