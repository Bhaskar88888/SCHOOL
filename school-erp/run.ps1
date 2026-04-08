# ================================================================
#  EduGlass School ERP - One-Command Run & Deploy Script
#  Usage (Development):  .\run.ps1
#  Usage (Build Only):   .\run.ps1 -build
#  Usage (Production):   .\run.ps1 -prod
#  Usage (Check Only):   .\run.ps1 -check
# ================================================================

param(
    [switch]$build,
    [switch]$prod,
    [switch]$check
)

$ErrorActionPreference = "Continue"
$ROOT = Split-Path -Parent $MyInvocation.MyCommand.Path
$SERVER = Join-Path $ROOT "server"
$CLIENT = Join-Path $ROOT "client"

function Write-Header($msg) {
    Write-Host ""
    Write-Host "========================================================" -ForegroundColor Cyan
    Write-Host "  $msg" -ForegroundColor Cyan
    Write-Host "========================================================" -ForegroundColor Cyan
}

function Write-Step($step, $msg) {
    Write-Host "  [$step] $msg" -ForegroundColor Yellow
}

function Write-Ok($msg) {
    Write-Host "  [OK] $msg" -ForegroundColor Green
}

function Write-Fail($msg) {
    Write-Host "  [FAIL] $msg" -ForegroundColor Red
}

function Write-Info($msg) {
    Write-Host "  [INFO] $msg" -ForegroundColor DarkGray
}

# --- Step 1: Pre-flight checks ---
Write-Header "EduGlass School ERP - Startup"

Write-Step "1/7" "Pre-flight checks..."

$nodeVersion = & node --version 2>$null
if (-not $nodeVersion) {
    Write-Fail "Node.js is not installed. Please install Node.js 18+ from https://nodejs.org"
    exit 1
}
Write-Ok "Node.js $nodeVersion found"

$npmVersion = & npm --version 2>$null
if (-not $npmVersion) {
    Write-Fail "npm is not installed."
    exit 1
}
Write-Ok "npm v$npmVersion found"

# --- Step 2: Check environment files ---
Write-Step "2/7" "Checking environment files..."

$serverEnv = Join-Path $SERVER ".env"
$clientEnv = Join-Path $CLIENT ".env"

if (Test-Path $serverEnv) {
    Write-Ok "Server .env found"
} else {
    Write-Fail "Server .env missing! Copying from .env.example..."
    $example = Join-Path $SERVER ".env.example"
    if (Test-Path $example) {
        Copy-Item $example $serverEnv
        Write-Info "Copied .env.example to .env - EDIT IT with your MongoDB URI and JWT_SECRET"
    } else {
        Write-Fail "No .env.example found either. Create server/.env manually."
        exit 1
    }
}

if (Test-Path $clientEnv) {
    Write-Ok "Client .env found"
} else {
    Write-Fail "Client .env missing! Creating default..."
    $defaultEnv = "PORT=3000`nREACT_APP_API_URL=http://localhost:5000/api`nREACT_APP_SCHOOL_NAME=EduGlass School`nREACT_APP_ENABLE_MOCK_TOAST=false"
    Set-Content -Path $clientEnv -Value $defaultEnv
    Write-Info "Created default client .env"
}

# --- Step 3: Install dependencies ---
Write-Step "3/7" "Installing dependencies..."

Write-Info "Installing server dependencies..."
Push-Location $SERVER
& npm install --loglevel=error 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Fail "Server npm install failed!"
    Pop-Location
    exit 1
}
Pop-Location
Write-Ok "Server dependencies installed"

Write-Info "Installing client dependencies..."
Push-Location $CLIENT
& npm install --loglevel=error 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Fail "Client npm install failed!"
    Pop-Location
    exit 1
}
Pop-Location
Write-Ok "Client dependencies installed"

# --- Step 4: Validate critical files ---
Write-Step "4/7" "Validating critical project files..."

$criticalFiles = @(
    "server\server.js",
    "server\config\db.js",
    "server\ai\nlpEngine.js",
    "server\ai\scanner.js",
    "server\routes\chatbot.js",
    "server\models\ChatbotLog.js",
    "client\src\App.jsx",
    "client\src\components\Chatbot.jsx",
    "client\src\utils\chatbotEngine.js",
    "client\src\utils\chatbotKnowledge.js",
    "client\src\utils\hindiExtendedKnowledge.js",
    "client\src\utils\assameseExtendedKnowledge.js",
    "client\src\components\Layout.jsx",
    "client\src\api\api.js"
)

$missingCount = 0
foreach ($file in $criticalFiles) {
    $fullPath = Join-Path $ROOT $file
    if (-not (Test-Path $fullPath)) {
        $missingCount++
        Write-Fail "MISSING: $file"
    }
}

if ($missingCount -eq 0) {
    Write-Ok "All $($criticalFiles.Count) critical files present"
} else {
    Write-Fail "$missingCount critical file(s) missing. Fix before running."
    exit 1
}

# --- Step 5: Check MongoDB ---
Write-Step "5/7" "Checking MongoDB configuration..."

$mongoUri = "mongodb://127.0.0.1:27017/school_erp"
$envContent = Get-Content $serverEnv -ErrorAction SilentlyContinue
foreach ($line in $envContent) {
    if ($line -match "^MONGODB_URI=(.+)$") {
        $mongoUri = $Matches[1].Trim()
    }
}

$displayUri = $mongoUri
if ($displayUri.Length -gt 50) {
    $displayUri = $displayUri.Substring(0, 50) + "..."
}
Write-Info "MongoDB URI: $displayUri"
Write-Ok "MongoDB URI configured (connectivity verified at startup)"

# --- Step 6: Build (if requested) ---
if ($build -or $prod) {
    Write-Step "6/7" "Building client for production..."
    Push-Location $CLIENT
    & npm run build 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "Client build failed!"
        Pop-Location
        exit 1
    }
    Pop-Location
    Write-Ok "Client built successfully -> client/build/"
} else {
    Write-Step "6/7" "Skipping production build (dev mode)"
}

# --- Step 7: Start servers ---
if ($check) {
    Write-Header "Pre-flight check complete - no servers started"
    Write-Ok "Everything looks good. Run without -check to start."
    exit 0
}

if ($prod) {
    Write-Step "7/7" "Starting PRODUCTION server..."
    Write-Info "Server will serve the client build at port 5000"

    Push-Location $SERVER
    $env:NODE_ENV = "production"
    & node server.js
    Pop-Location
} else {
    Write-Step "7/7" "Starting DEVELOPMENT servers..."
    Write-Info "Server -> http://localhost:5000"
    Write-Info "Client -> http://localhost:3000"
    Write-Info "Press Ctrl+C to stop both servers"
    Write-Host ""

    # Start server in background
    $serverJob = Start-Job -ScriptBlock {
        param($sp)
        Set-Location $sp
        & node server.js 2>&1
    } -ArgumentList $SERVER

    # Give server 3 seconds to boot
    Start-Sleep -Seconds 3

    # Start client in foreground
    Push-Location $CLIENT
    try {
        & npm start 2>&1
    } finally {
        Write-Info "Stopping server..."
        Stop-Job $serverJob -ErrorAction SilentlyContinue
        Remove-Job $serverJob -ErrorAction SilentlyContinue
        Pop-Location
    }
}
