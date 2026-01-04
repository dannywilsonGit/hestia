# HESTIA - Dev launcher (Engine + Desktop)
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\tools\scripts\dev.ps1

$ErrorActionPreference = "Stop"

$root = (Resolve-Path "$PSScriptRoot\..\..").Path

# Paths
$enginePath  = Join-Path $root "apps\engine"
$desktopPath = Join-Path $root "apps\desktop"

# Engine config
$configPath = Join-Path $enginePath "config\app.php"
if (-not (Test-Path $configPath)) {
  throw "Missing engine config: $configPath"
}

# Read config values via PHP (use single quotes so PowerShell doesn't expand $cfg)
$configReader = Join-Path $enginePath "bin\read-config.php"

$engineHost    = (php $configReader "server.host").Trim()
$enginePort    = (php $configReader "server.port").Trim()
$engineBaseUrl = (php $configReader "server.base_url").Trim()


if ([string]::IsNullOrWhiteSpace($engineHost) -or [string]::IsNullOrWhiteSpace($enginePort)) {
  throw "Engine host/port not found in config."
}

Write-Host "== HESTIA DEV =="
Write-Host "Root:   $root"
Write-Host "Engine: $enginePath"
Write-Host "UI:     $desktopPath"
Write-Host "Engine URL: $engineBaseUrl"

# Start PHP built-in server
Write-Host "`n[1/3] Starting engine (PHP server)..."
$enginePublic = Join-Path $enginePath "public"

# If port already in use, tell it clearly
$tcp = Test-NetConnection -ComputerName $engineHost -Port $enginePort -WarningAction SilentlyContinue
if ($tcp.TcpTestSucceeded) {
  throw "Port $enginePort is already in use on $engineHost. Stop the process using it, then retry."
}

$engineProc = Start-Process -FilePath "php" `
  -ArgumentList @("-S", "$engineHost`:$enginePort", "-t", $enginePublic) `
  -WorkingDirectory $enginePath `
  -PassThru `
  -NoNewWindow

Write-Host "Engine PID: $($engineProc.Id)"

# Wait for /health
Write-Host "`n[2/3] Waiting for engine /health..."
$healthUrl = "$engineBaseUrl/health"
$maxSeconds = 20
$ready = $false

for ($i = 1; $i -le $maxSeconds; $i++) {
  try {
    $resp = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 2
    if ($resp.StatusCode -eq 200) { $ready = $true; break }
  } catch {
    Start-Sleep -Seconds 1
  }
}

if (-not $ready) {
  try { Stop-Process -Id $engineProc.Id -Force } catch {}
  throw "Engine did not become ready at $healthUrl after $maxSeconds seconds."
}

Write-Host "Engine ready: $healthUrl"

# Start Tauri dev
Write-Host "`n[3/3] Starting desktop (Tauri)..."
try {
  Push-Location $desktopPath
  npx tauri dev
} finally {
  Pop-Location
  Write-Host "`nStopping engine..."
  try { Stop-Process -Id $engineProc.Id -Force } catch {}
  Write-Host "Done."
}
