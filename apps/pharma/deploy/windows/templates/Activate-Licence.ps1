#Requires -Version 5.1
param([string]$Code)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
$Php = Join-Path $Root "runtime\php\php.exe"
if (-not (Test-Path $Php)) { throw "PHP portable introuvable: $Php" }
$env:DESKTOP_RUNTIME = "1"
$phpDir = Join-Path $Root "runtime\php"
$env:Path = "$phpDir;$env:Path"
if (-not $Code) { $Code = Read-Host "Code d activation Control Center" }
& $Php artisan desktop:activate $Code
& $Php artisan desktop:heartbeat
