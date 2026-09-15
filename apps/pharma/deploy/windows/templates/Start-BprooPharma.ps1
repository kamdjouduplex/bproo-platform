#Requires -Version 5.1
param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8003
)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
$Php = Join-Path $Root "runtime\php\php.exe"
if (-not (Test-Path $Php)) { throw "PHP portable introuvable: $Php" }
$env:DESKTOP_RUNTIME = "1"
$phpDir = Join-Path $Root "runtime\php"
$env:Path = "$phpDir;$env:Path"
Write-Host "Bproo Pharma Desktop - http://${HostName}:${Port}" -ForegroundColor Cyan
Write-Host "Ctrl+C pour arreter."
& $Php artisan serve --host=$HostName --port=$Port
