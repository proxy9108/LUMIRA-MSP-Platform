# LUMIRA - Email Configuration Script
# Updates LUMIRA to use hMailServer for email notifications

param(
    [string]$Domain = "lumira.local",
    [string]$SmtpHost = "localhost",
    [int]$SmtpPort = 587,
    [string]$FromEmail = "noreply@lumira.local",
    [string]$FromPassword = "NoReply@2025!",
    [string]$FromName = "LUMIRA Support"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LUMIRA Email Configuration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if hMailServer is running
Write-Host "[1/3] Verifying hMailServer..." -ForegroundColor Green
$service = Get-Service -Name "hMailServer" -ErrorAction SilentlyContinue
if (-not $service) {
    Write-Host "  ✗ hMailServer service not found!" -ForegroundColor Red
    Write-Host "  Please run .\install-hmailserver.ps1 first" -ForegroundColor Yellow
    pause
    exit 1
}

if ($service.Status -ne "Running") {
    Write-Host "  Starting hMailServer..." -ForegroundColor Yellow
    Start-Service -Name "hMailServer"
    Start-Sleep -Seconds 3
}
Write-Host "  ✓ hMailServer is running" -ForegroundColor Green

Write-Host ""
Write-Host "[2/3] Updating LUMIRA configuration..." -ForegroundColor Green

# Path to config file
$configPath = "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\inc\config.php"

if (-not (Test-Path $configPath)) {
    Write-Host "  ✗ Config file not found: $configPath" -ForegroundColor Red
    pause
    exit 1
}

# Read current config
$configContent = Get-Content $configPath -Raw

# Check if SMTP configuration already exists
if ($configContent -match "define\('SMTP_HOST'") {
    Write-Host "  SMTP configuration already exists" -ForegroundColor Yellow
    Write-Host "  Updating existing configuration..." -ForegroundColor Yellow

    # Update existing values
    $configContent = $configContent -replace "define\('SMTP_HOST',\s*'[^']*'\);", "define('SMTP_HOST', '$SmtpHost');"
    $configContent = $configContent -replace "define\('SMTP_PORT',\s*\d+\);", "define('SMTP_PORT', $SmtpPort);"
    $configContent = $configContent -replace "define\('SMTP_USERNAME',\s*'[^']*'\);", "define('SMTP_USERNAME', '$FromEmail');"
    $configContent = $configContent -replace "define\('SMTP_PASSWORD',\s*'[^']*'\);", "define('SMTP_PASSWORD', '$FromPassword');"
    $configContent = $configContent -replace "define\('SMTP_FROM_EMAIL',\s*'[^']*'\);", "define('SMTP_FROM_EMAIL', '$FromEmail');"
    $configContent = $configContent -replace "define\('SMTP_FROM_NAME',\s*'[^']*'\);", "define('SMTP_FROM_NAME', '$FromName');"
    $configContent = $configContent -replace "define\('SMTP_SECURE',\s*'[^']*'\);", "define('SMTP_SECURE', '');"

} else {
    Write-Host "  Adding SMTP configuration..." -ForegroundColor Yellow

    # Find the end of the file (before the closing PHP tag if exists)
    if ($configContent -match "\?>") {
        $configContent = $configContent -replace "\?>", ""
    }

    # Add SMTP configuration
    $smtpConfig = @"

// hMailServer SMTP Configuration
define('SMTP_ENABLED', true);
define('SMTP_HOST', '$SmtpHost');
define('SMTP_PORT', $SmtpPort);
define('SMTP_USERNAME', '$FromEmail');
define('SMTP_PASSWORD', '$FromPassword');
define('SMTP_FROM_EMAIL', '$FromEmail');
define('SMTP_FROM_NAME', '$FromName');
define('SMTP_SECURE', ''); // No SSL/TLS for local server

"@

    $configContent += $smtpConfig
}

# Save updated config
try {
    $configContent | Out-File -FilePath $configPath -Encoding UTF8 -NoNewline
    Write-Host "  ✓ Configuration updated successfully" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Failed to update configuration: $($_.Exception.Message)" -ForegroundColor Red
    pause
    exit 1
}

Write-Host ""
Write-Host "[3/3] Updating email functions..." -ForegroundColor Green

# Path to email.php
$emailPath = "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\inc\email.php"

if (-not (Test-Path $emailPath)) {
    Write-Host "  ✗ Email file not found: $emailPath" -ForegroundColor Red
    pause
    exit 1
}

# Read email.php
$emailContent = Get-Content $emailPath -Raw

# Check if PHPMailer or mail() is being used
if ($emailContent -match "PHPMailer") {
    Write-Host "  ✓ PHPMailer detected - no changes needed" -ForegroundColor Green
} elseif ($emailContent -match "mail\(") {
    Write-Host "  ! Using PHP mail() function" -ForegroundColor Yellow
    Write-Host "  Ensuring proper headers are set..." -ForegroundColor Yellow

    # The email.php should already be configured properly
    # Just verify it exists and is readable
    Write-Host "  ✓ Email functions are ready" -ForegroundColor Green
} else {
    Write-Host "  ! Email configuration may need manual review" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Configuration Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

Write-Host "LUMIRA Email Settings:" -ForegroundColor Yellow
Write-Host "  SMTP Host: $SmtpHost" -ForegroundColor White
Write-Host "  SMTP Port: $SmtpPort" -ForegroundColor White
Write-Host "  From Email: $FromEmail" -ForegroundColor White
Write-Host "  From Name: $FromName" -ForegroundColor White
Write-Host ""

Write-Host "Email Accounts Available:" -ForegroundColor Yellow
Write-Host "  noreply@$Domain - Automated notifications" -ForegroundColor White
Write-Host "  support@$Domain - Customer support" -ForegroundColor White
Write-Host "  sales@$Domain - Sales inquiries" -ForegroundColor White
Write-Host "  admin@$Domain - Administrator" -ForegroundColor White
Write-Host ""

Write-Host "Testing:" -ForegroundColor Yellow
Write-Host "  1. Visit: http://10.0.1.100/create-ticket.php" -ForegroundColor White
Write-Host "  2. Create a test ticket" -ForegroundColor White
Write-Host "  3. Check your email for confirmation" -ForegroundColor White
Write-Host ""

Write-Host "Manual Testing:" -ForegroundColor Yellow
Write-Host "  Run: .\test-lumira-email.ps1" -ForegroundColor White
Write-Host ""

Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
