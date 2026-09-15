#Requires -Version 5.1
<#
  Registers two Windows Scheduled Tasks (current user) for licence heartbeat + OUT sync.
  Run once as the shop PC user after install.ps1 + desktop:activate.
#>
param(
    [string]$TaskPrefix = "BprooPharmaDesktop"
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path
$Php = (Get-Command php).Source

$heartbeat = @"
Set-Location '$AppDir'
`$env:DESKTOP_RUNTIME='1'
& '$Php' artisan desktop:heartbeat
"@
$sync = @"
Set-Location '$AppDir'
`$env:DESKTOP_RUNTIME='1'
& '$Php' artisan desktop:sync-out
"@

$hbFile = Join-Path $env:TEMP "bproo-desktop-heartbeat.ps1"
$syncFile = Join-Path $env:TEMP "bproo-desktop-sync.ps1"
Set-Content -Path $hbFile -Value $heartbeat -Encoding UTF8
Set-Content -Path $syncFile -Value $sync -Encoding UTF8

Register-ScheduledTask -TaskName "$TaskPrefix-Heartbeat" -Trigger (New-ScheduledTaskTrigger -Daily -At 9am) `
    -Action (New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -File `"$hbFile`"") `
    -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries) `
    -Force | Out-Null

Register-ScheduledTask -TaskName "$TaskPrefix-SyncOut" -Trigger (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(5) -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration ([TimeSpan]::MaxValue)) `
    -Action (New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -File `"$syncFile`"") `
    -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries) `
    -Force | Out-Null

Write-Host "Scheduled: $TaskPrefix-Heartbeat (daily 09:00) and $TaskPrefix-SyncOut (hourly)" -ForegroundColor Green
