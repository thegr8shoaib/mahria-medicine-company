# ============================================================
#  Mehria Medicine Company - Auto Setup & Run (Windows)
#  Paste this whole folder on a new PC, double-click SETUP.bat
#  Installs PHP, Composer, Node (if missing), configures the
#  app, starts the server and opens the app in Chrome.
#  If things are already installed, it skips straight to running.
# ============================================================
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$Root   = $PSScriptRoot
$Back   = Join-Path $Root 'backend'
$Front  = Join-Path $Root 'frontend'
$PhpDir = 'C:\php'
$AppUrl = 'http://127.0.0.1:8000'

function Say($m)  { Write-Host "[Mehria] $m" -ForegroundColor Cyan }
function Ok($m)   { Write-Host "[Mehria] $m" -ForegroundColor Green }
function Warn($m) { Write-Host "[Mehria] WARNING: $m" -ForegroundColor Yellow }
function Fail($m) { Write-Host "[Mehria] FAILED: $m" -ForegroundColor Red; exit 1 }

function Have($cmd) {
  try { $null = Get-Command $cmd -ErrorAction Stop; return $true } catch { return $false }
}

function Refresh-Env {
  $m = [Environment]::GetEnvironmentVariable('Path','Machine')
  $u = [Environment]::GetEnvironmentVariable('Path','User')
  $env:Path = "$m;$u"
}

# ------------------------------------------------------------
# 0. Need admin only when PHP must be installed (C:\php)
# ------------------------------------------------------------
if ((-not (Have php)) -and (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator))) {
  Warn 'Installing PHP needs administrator rights. Restarting as administrator...'
  Start-Process powershell -Verb RunAs -Wait -ArgumentList @('-ExecutionPolicy','Bypass','-File',"`"$PSCommandPath`"")
  exit 0
}

# ------------------------------------------------------------
# 1. Install PHP 8.3 (portable) if missing
# ------------------------------------------------------------
if (-not (Have php)) {
  Say 'PHP not found - downloading PHP 8.3 (latest patch)...'
  $releases = 'https://windows.php.net/downloads/releases/'
  $zip = $null
  try {
    $sums = (Invoke-WebRequest -Uri ($releases + 'sha256sum.txt') -UseBasicParsing).Content
    $zip = ($sums -split "`n" | ForEach-Object { ($_ -split '\s+')[-1] } |
           Where-Object { $_ -match '^php-8\.3\.\d+-nts-Win32-vs17-x64\.zip$' } | Sort-Object -Descending | Select-Object -First 1)
  } catch {}
  if (-not $zip) { $zip = 'php-8.3.14-nts-Win32-vs17-x64.zip' }
  Say "Downloading $zip ..."
  Invoke-WebRequest -Uri ($releases + $zip) -OutFile (Join-Path $env:TEMP $zip) -UseBasicParsing
  New-Item -ItemType Directory -Force -Path $PhpDir | Out-Null
  Expand-Archive -Path (Join-Path $env:TEMP $zip) -DestinationPath $PhpDir -Force
  $phpIni = Join-Path $PhpDir 'php.ini'
  if (-not (Test-Path $phpIni)) {
    Copy-Item (Join-Path $PhpDir 'php.ini-development') $phpIni
    $ini = Get-Content $phpIni
    foreach ($ext in @('mbstring','openssl','curl','fileinfo','pdo_sqlite','sqlite3','pdo_mysql','mysqli','sodium','gd')) {
      $ini = $ini -replace ("^;extension=" + $ext), ("extension=" + $ext)
    }
    try {
      $ca = Join-Path $PhpDir 'cacert.pem'
      Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -OutFile $ca -UseBasicParsing
      $ini = $ini -replace '^;?openssl.cafile=.*', ("openssl.cafile=`"" + $ca + "`"")
    } catch { Warn 'Could not fetch CA certificates (Composer HTTPS may fail).' }
    $ini | Set-Content $phpIni
  }
  $oldPath = [Environment]::GetEnvironmentVariable('Path','User')
  [Environment]::SetEnvironmentVariable('Path', "$PhpDir;$oldPath", 'User')
  Refresh-Env
  if (-not (Have php)) { Fail 'PHP install failed - it is not on the PATH.' }
  Ok 'PHP installed.'
} else {
  Ok 'PHP already installed - skipping.'
}

# ------------------------------------------------------------
# 2. Install Node.js LTS if missing (only needed for building)
# ------------------------------------------------------------
if (-not (Have node)) {
  Say 'Node.js not found - installing latest LTS...'
  if (Have winget) {
    winget install --id OpenJS.NodeJS.LTS -e --silent --accept-package-agreements --accept-source-agreements | Out-Null
    Refresh-Env
  }
  if (-not (Have node)) {
    Say 'winget unavailable - downloading Node installer directly...'
    $rel = (Invoke-RestMethod 'https://nodejs.org/dist/index.json') | Where-Object { $_.lts } | Select-Object -First 1
    $ver = $rel.version
    $msi = "node-$($ver.Substring(1))-x64.msi"
    Invoke-WebRequest -Uri ("https://nodejs.org/dist/$ver/$msi") -OutFile (Join-Path $env:TEMP $msi) -UseBasicParsing
    Start-Process msiexec -Wait -ArgumentList "/i `"$(Join-Path $env:TEMP $msi)`" /qn /norestart"
    Refresh-Env
  }
  if (-not (Have node)) { Warn 'Node.js install did not complete. Run SETUP.bat again after rebooting.' }
  else { Ok 'Node.js installed.' }
} else {
  Ok 'Node.js already installed - skipping.'
}

# ------------------------------------------------------------
# 3. Install Composer if missing
# ------------------------------------------------------------
if (-not (Have composer) -and -not (Test-Path (Join-Path $PhpDir 'composer.phar'))) {
  Say 'Composer not found - installing...'
  $inst = Join-Path $env:TEMP 'composer-setup.php'
  Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile $inst -UseBasicParsing
  & php $inst -- --install-dir=$PhpDir --filename=composer.phar --quiet 2>&1 | Out-Null
  if (-not (Test-Path (Join-Path $PhpDir 'composer.phar'))) { Fail 'Composer install failed.' }
  Set-Content -Path (Join-Path $PhpDir 'composer.bat') -Value "@echo off`r`nphp `"%~dp0composer.phar`" %*"
  Ok 'Composer installed.'
} else {
  Ok 'Composer already installed - skipping.'
}

# ------------------------------------------------------------
# 4. Configure the backend (env, key, sqlite file, composer deps)
# ------------------------------------------------------------
Set-Location $Back
$envFile = Join-Path $Back '.env'
if (-not (Test-Path $envFile)) {
  Say 'Creating .env from example...'
  Copy-Item (Join-Path $Back '.env.example') $envFile
  $envLines = Get-Content $envFile
  $envLines = $envLines -replace '^DB_CONNECTION=.*', 'DB_CONNECTION=sqlite'
  $envLines = $envLines -replace '^APP_URL=.*', "APP_URL=$AppUrl"
  if (-not ($envLines -match '^DB_DATABASE=')) { $envLines += 'DB_DATABASE=database/database.sqlite' }
  $envLines | Set-Content $envFile
}
$sqlite = Join-Path $Back 'database\database.sqlite'
if (-not (Test-Path $sqlite)) { New-Item -ItemType File -Force -Path $sqlite | Out-Null }

if (-not (Test-Path (Join-Path $Back 'vendor'))) {
  Say 'Installing backend packages (composer install) - a few minutes...'
  & php (Join-Path $PhpDir 'composer.phar') install --no-interaction --no-progress 2>&1 | Out-Null
  if ($LASTEXITCODE -ne 0) { Fail 'composer install failed - check internet and rerun.' }
  Ok 'Backend packages installed.'
}

if (-not ((Get-Content $envFile | Select-String '^APP_KEY=.+')) -or ((Get-Content $envFile | Select-String '^APP_KEY=$'))) {
  & php artisan key:generate --force 2>&1 | Out-Null
}

# ------------------------------------------------------------
# 5. Build the frontend once, copy build into backend/public
# ------------------------------------------------------------
if (-not (Test-Path (Join-Path $Back 'public\index.html'))) {
  Set-Location $Front
  if (-not (Test-Path (Join-Path $Front 'node_modules'))) {
    Say 'Installing frontend packages (npm install) - a few minutes...'
    npm install --no-audit --no-fund 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail 'npm install failed.' }
  }
  Say 'Building the app (npm run build)...'
  npm run build 2>&1 | Out-Null
  if ($LASTEXITCODE -ne 0) { Fail 'npm run build failed.' }
  Copy-Item (Join-Path $Front 'dist\index.html') (Join-Path $Back 'public\index.html') -Force
  if (Test-Path (Join-Path $Back 'public\assets')) { Remove-Item (Join-Path $Back 'public\assets') -Recurse -Force }
  Copy-Item (Join-Path $Front 'dist\assets') (Join-Path $Back 'public\assets') -Recurse -Force
  Ok 'Frontend built and deployed.'
  Set-Location $Back
}

# ------------------------------------------------------------
# 6. Database: create tables always, seed only on fresh DB
# ------------------------------------------------------------
Say 'Checking database...'
& php artisan migrate --force 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'Migration failed.' }
$fresh = (Get-Item (Join-Path $Back 'database\database.sqlite')).Length -le 0
if ($fresh) {
  Say 'Fresh database - seeding demo data and admin user...'
  & php artisan db:seed --force 2>&1 | Out-Null
  if ($LASTEXITCODE -ne 0) { Fail 'Seeding failed.' }
}

# ------------------------------------------------------------
# 7. Start the server and open Chrome
# ------------------------------------------------------------
$portBusy = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if (-not $portBusy) {
  Say 'Starting server...'
  Start-Process php -WorkingDirectory $Back -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8000')
  Start-Sleep -Seconds 4
}
Ok "App is running at $AppUrl"

$chrome = @(
  "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
  "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
  "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if ($chrome) { Start-Process $chrome -ArgumentList $AppUrl }
else { Start-Process $AppUrl }

Ok 'Login:  admin@pharmacy.test  /  password'
Write-Host ''
Write-Host 'To start the app again later, just double-click START.bat' -ForegroundColor Yellow
Read-Host 'Press Enter to close'