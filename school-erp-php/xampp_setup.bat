@echo off
REM ============================================================
REM School ERP PHP v3.0 - XAMPP LOCALHOST SETUP SCRIPT
REM ============================================================
REM Run this from the school-erp-php folder as Administrator
REM XAMPP must be installed at C:\xampp
REM ============================================================

title School ERP - XAMPP Setup

echo.
echo ============================================================
echo  School ERP PHP v3.0 - XAMPP Local Setup
echo ============================================================
echo.

REM ── Step 1: Verify XAMPP ────────────────────────────────────
echo [1/7] Checking XAMPP installation...
IF NOT EXIST "C:\xampp\htdocs" (
    echo ERROR: XAMPP not found at C:\xampp
    echo Please install XAMPP from https://www.apachefriends.org/
    pause
    exit /b 1
)
echo   OK: XAMPP found at C:\xampp

REM ── Step 2: Copy project to htdocs ─────────────────────────
echo.
echo [2/7] Copying project to XAMPP htdocs...
SET SRC=%~dp0
SET DEST=C:\xampp\htdocs\school-erp-php

REM Remove trailing backslash from SRC if present
IF "%SRC:~-1%"=="\" SET SRC=%SRC:~0,-1%

IF "%SRC%"=="%DEST%" (
    echo   INFO: Already running from htdocs, skipping copy.
) ELSE (
    IF EXIST "%DEST%" (
        echo   Removing old installation...
        rmdir /S /Q "%DEST%"
    )
    echo   Copying files...
    xcopy "%SRC%" "%DEST%\" /E /I /H /Y /Q
    IF ERRORLEVEL 1 (
        echo   ERROR: Failed to copy files. Run as Administrator.
        pause
        exit /b 1
    )
    echo   OK: Files copied to %DEST%
)

REM ── Step 3: Apply XAMPP .htaccess ───────────────────────────
echo.
echo [3/7] Applying XAMPP .htaccess...
IF EXIST "%DEST%\.htaccess.xampp" (
    copy /Y "%DEST%\.htaccess.xampp" "%DEST%\.htaccess" >nul
    echo   OK: XAMPP .htaccess applied (RewriteBase /school-erp-php/)
) ELSE (
    echo   WARNING: .htaccess.xampp not found, keeping existing .htaccess
)

REM ── Step 4: Verify .env.php ──────────────────────────────────
echo.
echo [4/7] Checking environment configuration...
IF EXIST "%DEST%\.env.php" (
    echo   OK: .env.php found (using localhost settings)
) ELSE (
    echo   Creating .env.php from defaults...
    copy /Y "%DEST%\.env.example" "%DEST%\.env.php" >nul 2>&1
    echo   OK: .env.php created
)

REM ── Step 5: Create uploads directory ────────────────────────
echo.
echo [5/7] Creating required directories...
IF NOT EXIST "%DEST%\uploads" mkdir "%DEST%\uploads"
IF NOT EXIST "%DEST%\tmp" mkdir "%DEST%\tmp"
echo   OK: uploads/ and tmp/ directories ready

REM ── Step 6: Start XAMPP services ────────────────────────────
echo.
echo [6/7] Starting XAMPP Apache and MySQL...
IF EXIST "C:\xampp\xampp_start.exe" (
    start "" "C:\xampp\xampp_start.exe" >nul 2>&1
    timeout /t 3 /nobreak >nul
    echo   OK: XAMPP start command sent
) ELSE (
    echo   INFO: Start XAMPP manually from XAMPP Control Panel
)

REM ── Step 7: Open database import instructions ─────────────
echo.
echo [7/7] Database setup required...
echo.
echo ============================================================
echo  MANUAL STEP REQUIRED: Import Database
echo ============================================================
echo.
echo  1. Open XAMPP Control Panel
echo  2. Start Apache + MySQL
echo  3. Open: http://localhost/phpmyadmin
echo  4. Create database: school_erp
echo  5. Import: C:\xampp\htdocs\school-erp-php\setup.sql
echo.
echo  OR: Click OK to open phpMyAdmin in your browser
echo.
echo ============================================================
echo.
echo  After database import, open:
echo  http://localhost/school-erp-php/
echo.
echo  Login credentials:
echo    Email:    admin@school.com
echo    Password: password
echo.
echo  Diagnostic page:
echo  http://localhost/school-erp-php/diagnostic.php
echo.
echo ============================================================

set /p OPEN_BROWSER="Open phpMyAdmin now? (Y/N): "
if /I "%OPEN_BROWSER%"=="Y" (
    start "" "http://localhost/phpmyadmin"
)

echo.
echo Setup complete! Press any key to close.
pause >nul
