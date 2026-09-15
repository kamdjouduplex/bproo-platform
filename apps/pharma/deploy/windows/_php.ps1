#Requires -Version 5.1
<#
.SYNOPSIS
  Resolve php.exe — prefer bundled runtime\php, else system PATH.
#>
function Get-BprooPhp {
    param([string]$Root)
    $bundled = Join-Path $Root "runtime\php\php.exe"
    if (Test-Path $bundled) {
        $env:Path = "$(Split-Path $bundled -Parent);$env:Path"
        return $bundled
    }
    $sys = Get-Command php -ErrorAction SilentlyContinue
    if ($sys) { return $sys.Source }
    throw "PHP introuvable (ni runtime\php\php.exe ni PATH). Lance build-release.ps1 ou installe PHP."
}
