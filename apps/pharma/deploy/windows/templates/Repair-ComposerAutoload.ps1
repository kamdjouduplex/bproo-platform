#Requires -Version 5.1
<#
.SYNOPSIS
  Rewrite Composer autoload so monorepo path-repos point at vendor/bproo copies.
#>
param(
    [string]$AppRoot = ""
)

$ErrorActionPreference = "Stop"
if (-not $AppRoot) {
    $AppRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
}
$ComposerDir = Join-Path $AppRoot "vendor\composer"
if (-not (Test-Path $ComposerDir)) {
    throw "vendor/composer introuvable sous $AppRoot"
}

function Convert-PackagePaths([string]$content) {
    # $baseDir . '/../../packages/inovcom/X -> $vendorDir . '/bproo/X
    $content = [regex]::Replace($content, "\`$baseDir\s*\.\s*'/\.\./\.\./packages/inovcom/", "`$vendorDir . '/bproo/")
    $content = [regex]::Replace($content, "\`$baseDir\s*\.\s*'/\.\./\.\./packages/verticals/", "`$vendorDir . '/bproo/")
    $content = [regex]::Replace($content, "\`$baseDir\s*\.\s*'/\.\./\.\./packages/platform/([^/]+)/", "`$vendorDir . '/bproo/platform-`$1/")
    $content = [regex]::Replace($content, "\`$baseDir\s*\.\s*'/\.\./\.\./packages/ui/core/", "`$vendorDir . '/bproo/ui-core/")

    # autoload_static.php: __DIR__ . '/../..' . '/../../packages/...
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\.'\s*\.\s*'/\.\./\.\./packages/inovcom/", "__DIR__ . '/..' . '/bproo/")
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\.'\s*\.\s*'/\.\./\.\./packages/verticals/", "__DIR__ . '/..' . '/bproo/")
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\.'\s*\.\s*'/\.\./\.\./packages/platform/([^/]+)/", "__DIR__ . '/..' . '/bproo/platform-`$1/")
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\.'\s*\.\s*'/\.\./\.\./packages/ui/core/", "__DIR__ . '/..' . '/bproo/ui-core/")

    # installed.php
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\./\.\./packages/inovcom/", "__DIR__ . '/../bproo/")
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\./\.\./packages/verticals/", "__DIR__ . '/../bproo/")
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\./\.\./packages/platform/([^/]+)/", "__DIR__ . '/../bproo/platform-`$1/")
    $content = [regex]::Replace($content, "__DIR__\s*\.\s*'/\.\./\.\./\.\./packages/ui/core/", "__DIR__ . '/../bproo/ui-core/")

    return $content
}

function Convert-Psr4DualEntries([string]$content) {
    # array($baseDir . '/../../packages/...', $vendorDir . '/bproo/...') -> array($vendorDir . '/bproo/...')
    return [regex]::Replace($content, "\`$baseDir\s*\.\s*'/\.\./\.\./packages/[^']+',\s*", "")
}

$changed = 0
foreach ($name in @("autoload_classmap.php", "autoload_files.php", "autoload_static.php", "installed.php", "autoload_psr4.php")) {
    $path = Join-Path $ComposerDir $name
    if (-not (Test-Path $path)) { continue }
    $before = [System.IO.File]::ReadAllText($path)
    $after = Convert-PackagePaths $before
    if ($name -eq "autoload_psr4.php") {
        $after = Convert-Psr4DualEntries $after
    }
    if ($after -cne $before) {
        [System.IO.File]::WriteAllText($path, $after)
        $changed++
        Write-Host "Rewrote $name"
    } else {
        Write-Host "No change: $name"
    }
}

$leftover = Select-String -Path (Join-Path $ComposerDir "autoload_*.php") -Pattern "/\.\./\.\./packages/" -ErrorAction SilentlyContinue
if ($leftover) {
    Write-Warning "Remaining packages/ path refs: $($leftover.Count)"
    $leftover | Select-Object -First 5 | ForEach-Object { Write-Warning $_.Line.Trim() }
} else {
    Write-Host "No monorepo packages/ refs left in autoload_*.php" -ForegroundColor Green
}

# Sanity: classmap must still map AttendanceServiceProvider to a .php path
$cm = Join-Path $ComposerDir "autoload_classmap.php"
$sample = Select-String -Path $cm -Pattern "AttendanceServiceProvider" | Select-Object -First 1
if (-not $sample -or ($sample.Line -notmatch "\.php'")) {
    throw "Autoload repair failed sanity check (AttendanceServiceProvider classmap broken)."
}
Write-Host "Sanity OK: $($sample.Line.Trim())"
Write-Host "Autoload repair done ($changed file(s)) under $AppRoot" -ForegroundColor Green
