# Compare shared inovcom packages vs leftover per-app copies + ERP/Pressing drift on non-shared packages
# Usage:
#   powershell -ExecutionPolicy Bypass -File tools/fingerprint/compare-inovcom-packages.ps1
#   powershell -ExecutionPolicy Bypass -File tools/fingerprint/compare-inovcom-packages.ps1 -FailOnDrift

param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$FailOnDrift,
    [string[]]$SharedPackages = @(
        'kernel', 'items', 'sales', 'purchases', 'providers', 'debts', 'inventory',
        'losses', 'payroll', 'batches', 'prescriptions', 'prospects', 'reservations',
        'tickets', 'returns', 'branding'
    )
)

$sharedRoot = Join-Path $Root 'packages\inovcom'
$erp = Join-Path $Root 'apps\erp\packages\inovcom'
$prs = Join-Path $Root 'apps\pressing\packages\inovcom'

if (-not (Test-Path $sharedRoot)) {
    Write-Error "Shared packages missing at $sharedRoot"
    exit 2
}

Write-Host "Shared: $sharedRoot"
Write-Host "ERP local: $erp"
Write-Host "PRS local: $prs"

$failed = @()

Write-Host "`n=== Shared packages (must exist once; must NOT exist under apps) ==="
foreach ($pkg in $SharedPackages) {
    $s = Test-Path (Join-Path $sharedRoot $pkg)
    $e = Test-Path (Join-Path $erp $pkg)
    $p = Test-Path (Join-Path $prs $pkg)
    $ok = $s -and (-not $e) -and (-not $p)
    $status = if ($ok) { 'OK' } else { 'FAIL' }
    Write-Host ("{0,-18} shared={1} erpCopy={2} prsCopy={3} {4}" -f $pkg, $s, $e, $p, $status)
    if (-not $ok) { $failed += $pkg }
}

function Compare-Pkg($pathA, $pathB) {
    $mapA = @{}; $mapB = @{}
    Get-ChildItem $pathA -Recurse -Include *.php -File -EA SilentlyContinue | ForEach-Object {
        $mapA[$_.FullName.Substring($pathA.Length).TrimStart('\')] = (Get-FileHash $_.FullName -Algorithm MD5).Hash
    }
    Get-ChildItem $pathB -Recurse -Include *.php -File -EA SilentlyContinue | ForEach-Object {
        $mapB[$_.FullName.Substring($pathB.Length).TrimStart('\')] = (Get-FileHash $_.FullName -Algorithm MD5).Hash
    }
    $all = ($mapA.Keys + $mapB.Keys) | Sort-Object -Unique
    $same = 0; $diff = 0
    foreach ($r in $all) {
        if ($mapA[$r] -and $mapB[$r]) {
            if ($mapA[$r] -eq $mapB[$r]) { $same++ } else { $diff++ }
        } else { $diff++ }
    }
    $u = [Math]::Max($all.Count, 1)
    [PSCustomObject]@{ Same = $same; Diff = $diff; DupPct = [math]::Round(100.0 * $same / $u, 1); Union = $all.Count }
}

Write-Host "`n=== Non-shared local packages (ERP vs Pressing drift - expected until M3) ==="
$localPkgs = @()
if (Test-Path $erp) { $localPkgs += (Get-ChildItem $erp -Directory).Name }
$localPkgs = $localPkgs | Sort-Object -Unique
foreach ($pkg in $localPkgs) {
    $pe = Join-Path $erp $pkg
    $pp = Join-Path $prs $pkg
    if (-not (Test-Path $pp)) { Write-Host ("{0,-18} only in ERP" -f $pkg); continue }
    $c = Compare-Pkg $pe $pp
    Write-Host ("{0,-18} {1,6}% same={2} diff={3}" -f $pkg, $c.DupPct, $c.Same, $c.Diff)
}

$reportDir = Join-Path $Root 'docs\fingerprint'
New-Item -ItemType Directory -Path $reportDir -Force | Out-Null
$out = Join-Path $reportDir ("m2-shared-check-{0:yyyyMMdd-HHmmss}.txt" -f (Get-Date))
"failed=$($failed -join ',')" | Set-Content $out
Write-Host "`nReport: $out"

if ($FailOnDrift -and $failed.Count -gt 0) {
    Write-Host "SHARED LAYOUT DRIFT" -ForegroundColor Red
    exit 1
}

if ($failed.Count -eq 0) {
    Write-Host "`nM2 shared layout OK." -ForegroundColor Green
    exit 0
}
Write-Host "`nIssues: $($failed -join ', ')" -ForegroundColor Yellow
exit 0
