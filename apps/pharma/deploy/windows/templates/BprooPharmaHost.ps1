#Requires -Version 5.1
<#
.SYNOPSIS
  Desktop host: start PHP silently, open Edge/Chrome in app mode (no browser chrome).
#>
param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8003,
    [switch]$ShowConsole
)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

$mutexName = "Local\BprooPharmaDesktopHost"
$mutex = $null
$createdNew = $false
try {
    $mutex = New-Object System.Threading.Mutex($false, $mutexName, [ref]$createdNew)
} catch {
    $createdNew = $true
}
if (-not $createdNew) {
    Add-Type -AssemblyName System.Windows.Forms -ErrorAction SilentlyContinue
    [System.Windows.Forms.MessageBox]::Show(
        "Bproo Pharma est deja en cours d'execution.",
        "Bproo Pharma",
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Information
    ) | Out-Null
    exit 0
}

$phpProc = $null
$browserProc = $null
$profileDir = Join-Path $Root "storage\app\desktop\webview-profile"
$logFile = Join-Path $Root "storage\logs\desktop-host.log"

function Write-HostLog([string]$Message) {
    $line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message
    try {
        $dir = Split-Path $logFile
        if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
        Add-Content -Path $logFile -Value $line -Encoding UTF8
    } catch {
        # ignore log failures
    }
    if ($ShowConsole) { Write-Host $line }
}

function Find-Browser {
    $pf = [Environment]::GetFolderPath("ProgramFiles")
    $pf86 = ${env:ProgramFiles(x86)}
    $candidates = @(
        (Join-Path $pf "Microsoft\Edge\Application\msedge.exe"),
        (Join-Path $pf86 "Microsoft\Edge\Application\msedge.exe"),
        (Join-Path $pf "Google\Chrome\Application\chrome.exe"),
        (Join-Path $pf86 "Google\Chrome\Application\chrome.exe"),
        (Join-Path $env:LOCALAPPDATA "Google\Chrome\Application\chrome.exe")
    )
    foreach ($c in $candidates) {
        if ($c -and (Test-Path -LiteralPath $c)) { return $c }
    }
    return $null
}

function Test-AppReady([string]$Url) {
    try {
        $req = [System.Net.WebRequest]::Create($Url)
        $req.Timeout = 2000
        $req.Method = "GET"
        $resp = $req.GetResponse()
        $code = [int]$resp.StatusCode
        $resp.Close()
        return ($code -ge 200 -and $code -lt 500)
    } catch {
        return $false
    }
}

function Stop-ProcessTree([System.Diagnostics.Process]$Proc) {
    if (-not $Proc) { return }
    try {
        if (-not $Proc.HasExited) {
            & taskkill.exe /PID $Proc.Id /T /F 2>$null | Out-Null
        }
    } catch {
        # ignore
    }
}

function Stop-BrowsersUsingProfile {
    try {
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                $_.Name -match '^(msedge|chrome)\.exe$' -and
                $_.CommandLine -and
                ($_.CommandLine -like '*webview-profile*')
            } |
            ForEach-Object {
                try { & taskkill.exe /PID $_.ProcessId /T /F 2>$null | Out-Null } catch { }
            }
    } catch {
        # ignore
    }
}

try {
    Write-HostLog "Host start root=$Root"

    $phpWin = Join-Path $Root "runtime\php\php-win.exe"
    $phpCli = Join-Path $Root "runtime\php\php.exe"
    if (Test-Path -LiteralPath $phpWin) { $php = $phpWin } else { $php = $phpCli }
    if (-not (Test-Path -LiteralPath $php)) { throw "PHP portable introuvable: $php" }

    if (-not (Test-Path -LiteralPath (Join-Path $Root ".env"))) {
        throw "Fichier .env manquant. Lancez First-Run-Setup (menu Demarrer)."
    }

    $state = Join-Path $Root "storage\app\desktop\state.json"
    if (-not (Test-Path -LiteralPath $state)) {
        throw "Licence non activee. Lancez Activer la licence (menu Demarrer)."
    }

    $envFile = Join-Path $Root ".env"
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
        Set-Content -Path $envFile -Value $raw -Encoding UTF8
    }

    $env:DESKTOP_RUNTIME = "1"
    $phpDir = Join-Path $Root "runtime\php"
    $env:Path = "$phpDir;$env:Path"

    $url = "http://${HostName}:${Port}"
    if (-not (Test-AppReady $url)) {
        Write-HostLog "Starting PHP server on $url"
        $psi = New-Object System.Diagnostics.ProcessStartInfo
        $psi.FileName = $php
        $psi.Arguments = "artisan serve --host=$HostName --port=$Port"
        $psi.WorkingDirectory = $Root
        $psi.UseShellExecute = $false
        $psi.CreateNoWindow = $true
        $psi.RedirectStandardOutput = $true
        $psi.RedirectStandardError = $true
        $psi.EnvironmentVariables["DESKTOP_RUNTIME"] = "1"
        $psi.EnvironmentVariables["Path"] = "$phpDir;" + [Environment]::GetEnvironmentVariable("Path", "Process")
        $phpProc = New-Object System.Diagnostics.Process
        $phpProc.StartInfo = $psi
        [void]$phpProc.Start()
        Write-HostLog "PHP pid=$($phpProc.Id)"

        $ready = $false
        for ($i = 0; $i -lt 60; $i++) {
            Start-Sleep -Milliseconds 500
            if ($phpProc.HasExited) {
                throw "Le serveur PHP s'est arrete (exit $($phpProc.ExitCode)). Voir storage/logs."
            }
            if (Test-AppReady $url) { $ready = $true; break }
        }
        if (-not $ready) { throw "Timeout: l'application ne repond pas sur $url" }
    } else {
        Write-HostLog "Server already responding on $url"
    }

    $browser = Find-Browser
    if (-not $browser) {
        Write-HostLog "No Edge/Chrome - opening default browser"
        Start-Process $url
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.MessageBox]::Show(
            "Bproo Pharma tourne sur $url`nFermez cette boite pour arreter le serveur.",
            "Bproo Pharma",
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Information
        ) | Out-Null
        exit 0
    }

    if (-not (Test-Path -LiteralPath $profileDir)) {
        New-Item -ItemType Directory -Force -Path $profileDir | Out-Null
    }

    $browserArgs = @(
        "--app=$url",
        "--user-data-dir=$profileDir",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-extensions"
    )
    Write-HostLog "Launch browser $browser"
    $browserProc = Start-Process -FilePath $browser -ArgumentList $browserArgs -PassThru
    Start-Sleep -Seconds 2

    Write-HostLog "Waiting for app window close..."
    while ($true) {
        Start-Sleep -Seconds 2
        $alive = $false
        try {
            $matches = @(Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
                Where-Object {
                    $_.Name -match '^(msedge|chrome)\.exe$' -and
                    $_.CommandLine -and
                    ($_.CommandLine -like '*webview-profile*')
                })
            $alive = $matches.Count -gt 0
        } catch {
            $alive = $false
        }
        if (-not $alive) { break }

        if ($phpProc -and $phpProc.HasExited) {
            Write-HostLog "PHP died unexpectedly"
            break
        }
    }
}
catch {
    Write-HostLog "ERROR: $($_.Exception.Message)"
    try {
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.MessageBox]::Show(
            $_.Exception.Message,
            "Bproo Pharma - erreur",
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Error
        ) | Out-Null
    } catch {
        if ($ShowConsole) { Write-Host $_.Exception.Message -ForegroundColor Red; Read-Host "Entree" }
    }
    exit 1
}
finally {
    Write-HostLog "Shutting down"
    Stop-BrowsersUsingProfile
    Stop-ProcessTree $phpProc
    if ($mutex) {
        try { $mutex.ReleaseMutex() } catch { }
        $mutex.Dispose()
    }
}
