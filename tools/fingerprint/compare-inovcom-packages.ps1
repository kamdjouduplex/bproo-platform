# Compare inovcom package fingerprints — ERP vs Pressing
# Usage:
#   pwsh tools/fingerprint/compare-inovcom-packages.ps1
#   pwsh tools/fingerprint/compare-inovcom-packages.ps1 -FailOnDrift
# Exit code 1 if -FailOnDrift and any tracked package has Diff/Only* > 0 for packages that should stay identical.

param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$FailOnDrift,
    # Packages that must remain 100% identical during freeze / after M2
    [string[]]$StrictPackages = @(
        'kernel', 'items', 'sales', 'purchases', 'providers', 'debts', 'inventory',
        'losses', 'payroll', 'batches', 'prescriptions', 'prospects', 'reservations',
        'tickets', 'returns', 'branding'
    )
)

$erp = Join-Path $Root 'apps\erp\packages\inovcom'
$prs = Join-Path $Root 'apps\pressing\packages\inovcom'

if (-not (Test-Path $erp) -or -not (Test-Path $prs)) {
    Write-Error "Expected packages at:`n  $erp`n  $prs"
    exit 2
}

function Compare-Pkg($pathA, $pathB) {
    $mapA = @{}
    Get-ChildItem $pathA -Recurse -Include *.php -File -ErrorAction SilentlyContinue | ForEach-Object {
        $rel = $_.FullName.Substring($pathA.Length).TrimStart('\')
        $mapA[$rel] = (Get-FileHash $_.FullName -Algorithm MD5).Hash
    }
    $mapB = @{}
    Get-ChildItem $pathB -Recurse -Include *.php -File -ErrorAction SilentlyContinue | ForEach-Object {
        $rel = $_.FullName.Substring($pathB.Length).TrimStart('\')
        $mapB[$rel] = (Get-FileHash $_.FullName -Algorithm MD5).Hash
    }
    $all = ($mapA.Keys + $mapB.Keys) | Sort-Object -Unique
    $same = 0; $diff = 0; $onlyA = 0; $onlyB = 0
    foreach ($r in $all) {
        if ($mapA[$r] -and $mapB[$r]) {
            if ($mapA[$r] -eq $mapB[$r]) { $same++ } else { $diff++ }
        } elseif ($mapA[$r]) { $onlyA++ } else { $onlyB++ }
    }
    $union = [Math]::Max($all.Count, 1)
    [PSCustomObject]@{
        Same    = $same
        Diff    = $diff
        OnlyERP = $onlyA
        OnlyPRS = $onlyB
        Union   = $all.Count
        DupPct  = [math]::Round(100.0 * $same / $union, 1)
    }
}

$pkgs = (Get-ChildItem $erp -Directory).Name | Sort-Object
$failed = @()
$rows = @()

Write-Host "ERP: $erp"
Write-Host "PRS: $prs"
Write-Host ("{0,-20} {1,7} {2,6} {3,6} {4,8} {5,8} {6}" -f 'Package', 'Dup%', 'Same', 'Diff', 'OnlyERP', 'OnlyPRS', 'Strict')
Write-Host ('-' * 72)

foreach ($pkg in $pkgs) {
    $pe = Join-Path $erp $pkg
    $pp = Join-Path $prs $pkg
    if (-not (Test-Path $pp)) {
        Write-Host ("{0,-20} MISSING in Pressing" -f $pkg)
        continue
    }
    $c = Compare-Pkg $pe $pp
    $strict = $StrictPackages -contains $pkg
    $flag = if ($strict) { 'YES' } else { '' }
    Write-Host ("{0,-20} {1,6}% {2,6} {3,6} {4,8} {5,8} {6}" -f $pkg, $c.DupPct, $c.Same, $c.Diff, $c.OnlyERP, $c.OnlyPRS, $flag)
    $rows += [PSCustomObject]@{ Package = $pkg; DupPct = $c.DupPct; Diff = $c.Diff; OnlyERP = $c.OnlyERP; OnlyPRS = $c.OnlyPRS; Strict = $strict }
    if ($strict -and ($c.Diff -gt 0 -or $c.OnlyERP -gt 0 -or $c.OnlyPRS -gt 0)) {
        $failed += $pkg
    }
}

$reportDir = Join-Path $Root 'docs\fingerprint'
New-Item -ItemType Directory -Path $reportDir -Force | Out-Null
$out = Join-Path $reportDir ("inovcom-erp-pressing-{0:yyyyMMdd-HHmmss}.csv" -f (Get-Date))
$rows | Export-Csv -Path $out -NoTypeInformation
Write-Host "`nReport: $out"

if ($FailOnDrift -and $failed.Count -gt 0) {
    Write-Host "`nSTRICT DRIFT DETECTED:" -ForegroundColor Red
    $failed | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    exit 1
}

if ($failed.Count -gt 0) {
    Write-Host "`nNote: strict packages already drifted (expected until M2/M3): $($failed -join ', ')" -ForegroundColor Yellow
    exit 0
}

Write-Host "`nAll strict packages identical." -ForegroundColor Green
exit 0
