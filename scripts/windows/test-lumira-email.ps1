# LUMIRA - Email Function Test Script
# Tests LUMIRA's email sending through PHP

param(
    [string]$TestEmailTo = ""
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LUMIRA Email Function Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
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
Write-Host "Creating PHP test script..." -ForegroundColor Green

# Create PHP test script
$phpTestScript = @"
<?php
/**
 * LUMIRA Email Test Script
 * Tests email functionality
 */

require_once 'inc/config.php';
require_once 'inc/email.php';

// Test email data
`$testEmail = '$TestEmailTo';
`$testTime = date('Y-m-d H:i:s');

echo "LUMIRA Email Test\n";
echo "=================\n\n";

echo "Testing email to: `$testEmail\n";
echo "Time: `$testTime\n\n";

// Test 1: Basic email
echo "[Test 1] Basic Email Test\n";
`$subject = "LUMIRA Test Email - `$testTime";
`$message = "Hello from LUMIRA!\n\n";
`$message .= "This is a test email to verify that your LUMIRA system can send emails through hMailServer.\n\n";
`$message .= "System Information:\n";
`$message .= "- Server: 10.0.1.100\n";
`$message .= "- Database: 10.0.1.200\n";
`$message .= "- Test Time: `$testTime\n\n";
`$message .= "If you received this email, the email system is working correctly!\n\n";
`$message .= "--\nLUMIRA Support Team\nhttp://10.0.1.100";

try {
    `$result = send_email(`$testEmail, `$subject, `$message);
    if (`$result) {
        echo "  ✓ Email sent successfully!\n";
    } else {
        echo "  ✗ Email sending failed\n";
    }
} catch (Exception `$e) {
    echo "  ✗ Error: " . `$e->getMessage() . "\n";
}

echo "\n";

// Test 2: Ticket confirmation email
echo "[Test 2] Ticket Confirmation Email\n";
`$ticketData = [
    'ticket_number' => 'TEST-' . date('Ymd') . '-ABCDEF',
    'customer_name' => 'Test Customer',
    'customer_email' => `$testEmail,
    'service_name' => 'Email System Test',
    'subject' => 'Testing Email Functionality',
    'description' => 'This is a test ticket to verify email notifications are working.',
    'status_name' => 'New',
    'priority_name' => 'Medium',
    'created_at' => `$testTime
];

try {
    send_ticket_confirmation(`$ticketData);
    echo "  ✓ Ticket confirmation email sent!\n";
} catch (Exception `$e) {
    echo "  ✗ Error: " . `$e->getMessage() . "\n";
}

echo "\n";

// Test 3: Order confirmation email
echo "[Test 3] Order Confirmation Email\n";
`$orderData = [
    'order_number' => 'TEST-' . date('Ymd') . '-123456',
    'customer_name' => 'Test Customer',
    'customer_email' => `$testEmail,
    'customer_address' => "123 Test Street\nTest City, TC 12345",
    'items' => [
        [
            'product_name' => 'Test Product 1',
            'qty' => 2,
            'price_cents' => 1999,
            'total_cents' => 3998
        ],
        [
            'product_name' => 'Test Product 2',
            'qty' => 1,
            'price_cents' => 4999,
            'total_cents' => 4999
        ]
    ],
    'subtotal_cents' => 8997,
    'tax_cents' => 720,
    'total_cents' => 9717,
    'created_at' => `$testTime
];

try {
    send_order_confirmation(`$orderData);
    echo "  ✓ Order confirmation email sent!\n";
} catch (Exception `$e) {
    echo "  ✗ Error: " . `$e->getMessage() . "\n";
}

echo "\n";
echo "=================\n";
echo "Test Complete!\n\n";
echo "Check your inbox: `$testEmail\n";
echo "You should receive 3 test emails:\n";
echo "  1. Basic test email\n";
echo "  2. Ticket confirmation\n";
echo "  3. Order confirmation\n\n";
echo "Note: Emails may take 1-2 minutes to arrive\n";
echo "Check spam/junk folder if not in inbox\n";
?>
"@

$phpTestPath = "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\test-email.php"
$phpTestScript | Out-File -FilePath $phpTestPath -Encoding UTF8 -NoNewline

Write-Host "  ✓ Test script created: test-email.php" -ForegroundColor Green
Write-Host ""
Write-Host "Running email tests..." -ForegroundColor Green
Write-Host ""

# Run PHP test
$phpPath = "C:\Users\Administrator\Documents\php\php.exe"
if (-not (Test-Path $phpPath)) {
    # Try alternative PHP path
    $phpPath = "..\php.exe"
    if (-not (Test-Path $phpPath)) {
        Write-Host "  ✗ PHP executable not found!" -ForegroundColor Red
        Write-Host "  Please run the test manually:" -ForegroundColor Yellow
        Write-Host "  cd C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html" -ForegroundColor White
        Write-Host "  ..\php.exe test-email.php" -ForegroundColor White
        pause
        exit 1
    }
}

# Execute test
try {
    Push-Location "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html"
    & $phpPath "test-email.php"
    Pop-Location
} catch {
    Write-Host "  ✗ Error running test: $($_.Exception.Message)" -ForegroundColor Red
    Pop-Location
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Test Execution Complete" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Check your email: $TestEmailTo" -ForegroundColor White
Write-Host "  2. Check spam/junk folder if not in inbox" -ForegroundColor White
Write-Host "  3. If no emails arrived, check hMailServer logs" -ForegroundColor White
Write-Host ""
Write-Host "Cleanup:" -ForegroundColor Yellow
Write-Host "  The test file test-email.php can be deleted after testing" -ForegroundColor White
Write-Host ""
pause
