@echo off
REM =====================================================
REM Quick Start - School ERP PHP Localhost
REM Just run this file to start the server
REM =====================================================

echo.
echo ====================================================
echo   School ERP PHP v3.0 - Quick Start
echo ====================================================
echo.

REM Check if .env.php exists
if not exist ".env.php" (
    echo [SETUP REQUIRED] Please run setup_localhost.bat first!
    echo.
    pause
    exit /b 1
)

echo [OK] Configuration found
echo.

REM Check PHP
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP not found! Please install XAMPP or PHP.
    pause
    exit /b 1
)

echo [OK] PHP is available
echo.

REM Create directories if missing
if not exist "uploads" mkdir uploads
if not exist "tmp\cache" mkdir tmp\cache
if not exist "backups" mkdir backups

REM Open browser
start http://localhost:8000

echo ====================================================
echo   Server Starting...
echo ====================================================
echo.
echo URL: http://localhost:8000
echo.
echo Default Login:
echo   Email: admin@school.com
echo   Password: admin123
echo.
echo Press Ctrl+C to stop the server.
echo ====================================================
echo.

REM Start server
php -S localhost:8000 -t .

echo.
echo Server stopped.
pause
