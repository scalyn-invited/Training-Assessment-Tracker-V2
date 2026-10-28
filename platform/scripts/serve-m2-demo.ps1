# Starts the existing isolated M2 local database; never seeds, migrates, or resets data.
$ErrorActionPreference = 'Stop'
$platformRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$demoDatabase = Join-Path $platformRoot '.runtime/m2-browser.sqlite'
if (!(Test-Path -LiteralPath $demoDatabase)) { throw 'Prepare the approved fresh M2 demo database first.' }
$demoCache = Join-Path $platformRoot '.runtime/m2-demo-config.php'
if (Test-Path -LiteralPath $demoCache) { throw 'Unexpected cached demo configuration; inspect before starting.' }
$env:APP_ENV='local'
$env:APP_DEBUG='false'
$env:APP_URL='http://127.0.0.1:8000'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=$demoDatabase
$env:DB_URL=''
$env:APP_CONFIG_CACHE=$demoCache
$env:TRAINING_AUTH_MODE='local_password'
$env:TRAINING_ENVIRONMENT='test'
$env:MOCK_IDENTITY_ENABLED='false'
$env:TRAINING_DEMO_ENABLED='false'
$env:SESSION_DRIVER='database'
$env:CACHE_STORE='database'
$env:QUEUE_CONNECTION='database'
$env:SESSION_COOKIE='training_m2_main_demo'
$probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 8000)
$probe.Server.ExclusiveAddressUse = $true
try { $probe.Start() } catch { throw 'Port 8000 is already in use. Preserve its service and resolve ownership first.' } finally { $probe.Stop() }
Push-Location $platformRoot
try {
    Write-Host 'M2 local development: http://127.0.0.1:8000/login (Ctrl+C stops this foreground server)'
    # The development server prints request paths. Discard output to protect setup/reset links.
    & php -S 127.0.0.1:8000 -t public scripts/browser-router.php *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Preview stopped with an error. Check that port 8000 is free.' }
} finally { Pop-Location }
