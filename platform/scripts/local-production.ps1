param(
    [string]$Profile = '',
    [switch]$Serve,
    [int]$Port = 8000,
    [string[]]$ArtisanArgs = @()
)
$ErrorActionPreference = 'Stop'
$platformRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (!$Profile) { $Profile = Join-Path $platformRoot '.runtime/local-production-profile.json' }
$settings = Get-Content -LiteralPath $Profile -Raw | ConvertFrom-Json
if ($settings.APP_ENV -ne 'production' -or $settings.TRAINING_ENVIRONMENT -ne 'production' -or $settings.TRAINING_LOCALHOST_PRODUCTION -ne 'true') { throw 'Not a localhost production profile.' }
if ($settings.APP_URL -ne "http://127.0.0.1:$Port") { throw 'Profile URL must match the loopback port.' }
$database = [IO.Path]::GetFullPath($settings.DB_DATABASE)
$runtime = (Join-Path $platformRoot '.runtime') + [IO.Path]::DirectorySeparatorChar
if (!$database.StartsWith($runtime, [StringComparison]::OrdinalIgnoreCase) -or $database -eq (Join-Path $platformRoot '.runtime/m2-browser.sqlite') -or $settings.TRAINING_LOCALHOST_DATABASE -ne $settings.DB_DATABASE) { throw 'Database must be a separate designated runtime database.' }
if (!(Test-Path -LiteralPath $database)) { throw 'Designated database must be explicitly created before starting.' }
foreach ($item in $settings.PSObject.Properties) { [Environment]::SetEnvironmentVariable($item.Name, [string]$item.Value, 'Process') }
if (Test-Path -LiteralPath (Join-Path $platformRoot $settings.APP_CONFIG_CACHE)) { throw 'Unexpected cached configuration; inspect before running.' }
Push-Location $platformRoot
try {
    if ($Serve) {
        $probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $Port)
        $probe.Server.ExclusiveAddressUse = $true
        try { $probe.Start() } catch { throw 'Port is occupied; verify ownership before replacing its service.' } finally { $probe.Stop() }
        # Do not persist request paths containing password setup tokens.
        & php -S "127.0.0.1:$Port" -t public scripts/browser-router.php *> $null
    } else {
        if (!$ArtisanArgs.Count) { throw 'Supply an Artisan command or -Serve.' }
        & php artisan @ArtisanArgs
    }
    if ($LASTEXITCODE -ne 0) { throw "Application command failed (exit $LASTEXITCODE)." }
} finally { Pop-Location }
