#Requires -Version 5.1
param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8003
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if (Test-Path (Join-Path $ScriptDir "..\..\artisan")) {
    $AppDir = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path
} elseif (Test-Path (Join-Path $ScriptDir "artisan")) {
    $AppDir = $ScriptDir
} else {
    $AppDir = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path
}
Set-Location $AppDir

$bundled = Join-Path $AppDir "runtime\php\php.exe"
if (Test-Path $bundled) {
    $env:Path = "$(Split-Path $bundled -Parent);$env:Path"
    $Php = $bundled
} else {
    $Php = (Get-Command php -ErrorAction Stop).Source
}

$env:DESKTOP_RUNTIME = "1"
Write-Host "Starting Bproo Pharma desktop on http://${HostName}:${Port}" -ForegroundColor Cyan
Write-Host "PHP: $Php"
& $Php artisan serve --host=$HostName --port=$Port
