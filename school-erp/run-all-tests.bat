@echo off
REM =====================================================
REM EduGlass School ERP - Comprehensive Test Runner
REM Windows Batch File
REM =====================================================

echo.
echo ================================================================================
echo   EDUGLASS SCHOOL ERP - COMPREHENSIVE TEST SUITE
echo   Testing ALL Modules with 10,000+ Data Points
echo ================================================================================
echo.

REM Check if Node.js is installed
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Node.js is not installed or not in PATH
    echo Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

echo [INFO] Node.js version:
node --version
echo.

REM Check if server directory exists
if not exist "server\tests\master-test-runner.js" (
    echo [ERROR] Test suite not found!
    echo Please run this script from the school-erp root directory
    pause
    exit /b 1
)

echo [INFO] Starting comprehensive test suite...
echo.
echo This will:
echo   1. Generate 10,000+ mock records
echo   2. Test all 29+ modules
echo   3. Validate every feature and button
echo   4. Generate comprehensive reports
echo.
echo Estimated time: 5-10 minutes
echo.

pause

echo.
echo ================================================================================
echo   PHASE 1-3: Running Comprehensive Tests...
echo ================================================================================
echo.

REM Run the master test runner
call node server/tests/master-test-runner.js

REM Capture exit code
set EXIT_CODE=%ERRORLEVEL%

echo.
echo ================================================================================
echo   TEST EXECUTION COMPLETE
echo ================================================================================
echo.

REM Check if reports were generated
if exist "final-test-report.json" (
    echo [SUCCESS] Test reports generated:
    echo   - final-test-report.json
    echo   - final-test-report.txt
    if exist "test-results.json" echo   - test-results.json
    if exist "button-feature-test-report.json" echo   - button-feature-test-report.json
    echo.
) else (
    echo [WARNING] Test reports not found. Check errors above.
    echo.
)

if %EXIT_CODE% EQU 0 (
    echo [SUCCESS] All tests passed!
) else (
    echo [WARNING] Some tests failed. Check the reports for details.
)

echo.
echo To view reports, open:
echo   - final-test-report.txt (text format)
echo   - final-test-report.json (detailed JSON format)
echo.

pause

exit /b %EXIT_CODE%
