@echo off
REM =============================================
REM LUMIRA - Setup Windows Task Scheduler Jobs
REM =============================================
REM This script creates Windows Task Scheduler jobs for:
REM 1. Email-to-Ticket Processor (every 5 minutes)
REM 2. SLA Compliance Monitor (every 5 minutes)
REM
REM Run this as Administrator!
REM =============================================

echo ========================================
echo LUMIRA Task Scheduler Setup
echo ========================================
echo.

REM Find PHP executable
set PHP_PATH=C:\php\php.exe
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please update PHP_PATH in this script
    pause
    exit /b 1
)

echo PHP Found: %PHP_PATH%
echo.

REM Base path
set BASE_PATH=%~dp0

echo Creating Task: LUMIRA-Email-Processor
schtasks /create /tn "LUMIRA-Email-Processor" /tr "\"%PHP_PATH%\" \"%BASE_PATH%process-support-emails.php\"" /sc minute /mo 5 /f
if %errorlevel%==0 (
    echo ✓ Email Processor task created successfully
) else (
    echo ✗ Failed to create Email Processor task
)

echo.
echo Creating Task: LUMIRA-SLA-Monitor
schtasks /create /tn "LUMIRA-SLA-Monitor" /tr "\"%PHP_PATH%\" \"%BASE_PATH%check-sla-compliance.php\"" /sc minute /mo 5 /f
if %errorlevel%==0 (
    echo ✓ SLA Monitor task created successfully
) else (
    echo ✗ Failed to create SLA Monitor task
)

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Tasks created:
schtasks /query /tn "LUMIRA-Email-Processor"
echo.
schtasks /query /tn "LUMIRA-SLA-Monitor"
echo.
echo To view all LUMIRA tasks:
echo    schtasks /query /tn "LUMIRA-*"
echo.
echo To run manually:
echo    schtasks /run /tn "LUMIRA-Email-Processor"
echo    schtasks /run /tn "LUMIRA-SLA-Monitor"
echo.
echo To delete tasks:
echo    schtasks /delete /tn "LUMIRA-Email-Processor" /f
echo    schtasks /delete /tn "LUMIRA-SLA-Monitor" /f
echo.
pause
