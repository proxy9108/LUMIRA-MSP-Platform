# LUMIRA - hMailServer Test Script
# Tests email sending functionality

param(
    [string]$TestEmailTo = ""
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  hMailServer Email Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if hMailServer service is running
$service = Get-Service -Name "hMailServer" -ErrorAction SilentlyContinue
if (-not $service) {
    Write-Host "ERROR: hMailServer service not found!" -ForegroundColor Red
    Write-Host "Please install hMailServer first." -ForegroundColor Yellow
    pause
    exit 1
}

if ($service.Status -ne "Running") {
    Write-Host "hMailServer service is not running. Starting..." -ForegroundColor Yellow
    Start-Service -Name "hMailServer"
    Start-Sleep -Seconds 3
}

Write-Host "✓ hMailServer service is running" -ForegroundColor Green
Write-Host ""

# Get test email address
if ([string]::IsNullOrEmpty($TestEmailTo)) {
    Write-Host "Enter your email address to receive test email:" -ForegroundColor Yellow
    $TestEmailTo = Read-Host "Email"

    if ([string]::IsNullOrEmpty($TestEmailTo)) {
        Write-Host "ERROR: Email address is required" -ForegroundColor Red
        pause
        exit 1
    }
}

Write-Host ""
Write-Host "Testing email configuration..." -ForegroundColor Green
Write-Host ""

# Test 1: Internal email test
Write-Host "[Test 1] Internal Email (noreply → support)" -ForegroundColor Cyan

try {
    $smtp = "localhost"
    $from = "noreply@lumira.local"
    $to = "support@lumira.local"
    $subject = "hMailServer Internal Test - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    $body = "This is an internal test email to verify hMailServer is working correctly."

    $credential = New-Object System.Management.Automation.PSCredential(
        $from,
        (ConvertTo-SecureString "NoReply@2025!" -AsPlainText -Force)
    )

    Send-MailMessage `
        -SmtpServer $smtp `
        -Port 587 `
        -From $from `
        -To $to `
        -Subject $subject `
        -Body $body `
        -Credential $credential `
        -UseSsl:$false

    Write-Host "  ✓ Internal email sent successfully!" -ForegroundColor Green
    Write-Host "  Check support@lumira.local inbox" -ForegroundColor Yellow
} catch {
    Write-Host "  ✗ Internal email failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 2: External email test
Write-Host "[Test 2] External Email (noreply → $TestEmailTo)" -ForegroundColor Cyan

try {
    $smtp = "localhost"
    $from = "noreply@lumira.local"
    $to = $TestEmailTo
    $subject = "LUMIRA Test Email - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    $body = @"
Hello from LUMIRA!

This is a test email to verify that hMailServer is configured correctly and can send emails to external addresses.

System Information:
- Server: 10.0.1.100
- Domain: lumira.local
- Test Time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

If you received this email, hMailServer is working perfectly!

--
LUMIRA Support
http://10.0.1.100
"@

    $credential = New-Object System.Management.Automation.PSCredential(
        $from,
        (ConvertTo-SecureString "NoReply@2025!" -AsPlainText -Force)
    )

    Send-MailMessage `
        -SmtpServer $smtp `
        -Port 587 `
        -From $from `
        -To $to `
        -Subject $subject `
        -Body $body `
        -Credential $credential `
        -UseSsl:$false

    Write-Host "  ✓ External email sent successfully!" -ForegroundColor Green
    Write-Host "  Check your inbox: $TestEmailTo" -ForegroundColor Yellow
    Write-Host "  (May take 1-2 minutes to arrive)" -ForegroundColor Gray
} catch {
    Write-Host "  ✗ External email failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Common issues:" -ForegroundColor Yellow
    Write-Host "  - Port 25 may be blocked by ISP (for outbound mail)" -ForegroundColor Gray
    Write-Host "  - Email may go to spam folder" -ForegroundColor Gray
    Write-Host "  - May need to configure SMTP relay" -ForegroundColor Gray
}

Write-Host ""

# Test 3: Check hMailServer logs
Write-Host "[Test 3] Checking Recent Logs" -ForegroundColor Cyan

$logPath = "C:\Program Files\hMailServer\Logs"
if (Test-Path $logPath) {
    $latestLog = Get-ChildItem $logPath -Filter "hmailserver_*.log" | Sort-Object LastWriteTime -Descending | Select-Object -First 1

    if ($latestLog) {
        Write-Host "  Latest log file: $($latestLog.Name)" -ForegroundColor Yellow
        Write-Host "  Last 10 lines:" -ForegroundColor Yellow
        Get-Content $latestLog.FullName -Tail 10 | ForEach-Object {
            Write-Host "    $_" -ForegroundColor Gray
        }
    }
} else {
    Write-Host "  Log directory not found" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Test Complete" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Summary:" -ForegroundColor Yellow
Write-Host "  - Internal test completed" -ForegroundColor White
Write-Host "  - External test sent to: $TestEmailTo" -ForegroundColor White
Write-Host ""
Write-Host "If external email didn't arrive:" -ForegroundColor Yellow
Write-Host "  1. Check spam/junk folder" -ForegroundColor White
Write-Host "  2. Check hMailServer Administrator → Utilities → Logging" -ForegroundColor White
Write-Host "  3. You may need to configure SMTP relay" -ForegroundColor White
Write-Host ""
Write-Host "Next: Run .\configure-lumira-email.ps1 to update LUMIRA" -ForegroundColor Cyan
Write-Host ""
pause
