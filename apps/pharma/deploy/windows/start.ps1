#Requires -Version 5.1
param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8003
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Resolve-Path (Join-Path $ScriptDir "..\..")
Set-Location $AppDir

$env:DESKTOP_RUNTIME = "1"
Write-Host "Starting Bproo Pharma desktop on http://${HostName}:${Port}" -ForegroundColor Cyan
php artisan serve --host=$HostName --port=$Port
