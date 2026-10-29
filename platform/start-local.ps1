param(
    [string]$Profile = '',
    [ValidateRange(1024, 65535)][int]$Port = 8000,
    [switch]$CheckOnly
)

$ErrorActionPreference = 'Stop'
$previousScanDirectory = [Environment]::GetEnvironmentVariable('PHP_INI_SCAN_DIR', 'Process')
try {
    # Keep the installed PHP configuration and any existing scan directories.
    & php -r "exit(extension_loaded('gd') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) {
        $logoConfiguration = Join-Path $PSScriptRoot 'scripts/php/conf.d'
        $env:PHP_INI_SCAN_DIR = "$previousScanDirectory;$logoConfiguration"
    }
    & php -r "exit(function_exists('imagecreatefromstring') && function_exists('imagepng') && function_exists('imagejpeg') && function_exists('imagewebp') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) { throw 'PHP GD with PNG, JPEG and WebP support is required. Check your installed PHP extensions.' }
    Write-Host 'Project PHP: GD available with PNG, JPEG and WebP support.'
    if (!$CheckOnly) {
        # The existing launcher preserves the designated local production database,
        # loopback binding and private request-log handling. Its PHP child inherits GD.
        & (Join-Path $PSScriptRoot 'scripts/local-production.ps1') -Profile $Profile -Port $Port -Serve
    }
} finally {
    [Environment]::SetEnvironmentVariable('PHP_INI_SCAN_DIR', $previousScanDirectory, 'Process')
}
