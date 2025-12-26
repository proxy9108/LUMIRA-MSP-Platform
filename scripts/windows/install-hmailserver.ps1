# LUMIRA - hMailServer Automated Installation Script
# This script downloads and installs hMailServer with minimal user interaction

param(
    [string]$AdminPassword = "Admin@2025!",
    [string]$Domain = "lumira.local"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LUMIRA hMailServer Installation" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "[1/7] Checking prerequisites..." -ForegroundColor Green

# Check .NET Framework
$dotNetVersion = Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full" -ErrorAction SilentlyContinue
if ($dotNetVersion -and $dotNetVersion.Release -ge 378389) {
    Write-Host "  ✓ .NET Framework 4.5+ is installed" -ForegroundColor Green
} else {
    Write-Host "  ✗ .NET Framework 4.5+ is required!" -ForegroundColor Red
    Write-Host "  Please install .NET Framework 4.8 from:" -ForegroundColor Yellow
    Write-Host "  https://dotnet.microsoft.com/download/dotnet-framework/net48" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host ""
Write-Host "[2/7] Downloading hMailServer..." -ForegroundColor Green

$downloadUrl = 'https://www.hmailserver.com/download_getfile/?performdownload=1&downloadid=254'
$installerPath = "$env:TEMP\hMailServer-5.6.9-B2578.exe"

try {
    # Download installer
    Write-Host "  Downloading from hmailserver.com..." -ForegroundColor Yellow
    $ProgressPreference = 'SilentlyContinue'
    Invoke-WebRequest -Uri $downloadUrl -OutFile $installerPath -UseBasicParsing
    Write-Host "  ✓ Download complete: $installerPath" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Download failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Manual download:" -ForegroundColor Yellow
    Write-Host "  1. Go to: https://www.hmailserver.com/download" -ForegroundColor Yellow
    Write-Host "  2. Download hMailServer 5.6.9 (64-bit)" -ForegroundColor Yellow
    Write-Host "  3. Save to: $installerPath" -ForegroundColor Yellow
    Write-Host "  4. Run this script again" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host ""
Write-Host "[3/7] Installing hMailServer..." -ForegroundColor Green
Write-Host "  This may take 2-3 minutes..." -ForegroundColor Yellow

# Silent installation parameters
$installArgs = @(
    "/SILENT",
    "/SUPPRESSMSGBOXES",
    "/NORESTART",
    "/COMPONENTS=server,admintools",
    "/DIR=C:\Program Files\hMailServer"
)

try {
    Start-Process -FilePath $installerPath -ArgumentList $installArgs -Wait -NoNewWindow
    Write-Host "  ✓ Installation complete" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Installation failed: $($_.Exception.Message)" -ForegroundColor Red
    pause
    exit 1
}

# Wait for service to be created
Write-Host "  Waiting for service to initialize..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

Write-Host ""
Write-Host "[4/7] Configuring hMailServer..." -ForegroundColor Green

# Check if service exists
$service = Get-Service -Name "hMailServer" -ErrorAction SilentlyContinue
if ($service) {
    Write-Host "  ✓ hMailServer service found" -ForegroundColor Green

    # Start service if not running
    if ($service.Status -ne "Running") {
        Write-Host "  Starting hMailServer service..." -ForegroundColor Yellow
        Start-Service -Name "hMailServer"
        Start-Sleep -Seconds 3
    }
    Write-Host "  ✓ hMailServer service is running" -ForegroundColor Green
} else {
    Write-Host "  ✗ hMailServer service not found!" -ForegroundColor Red
    Write-Host "  The installation may have failed." -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host ""
Write-Host "[5/7] Setting up COM object for configuration..." -ForegroundColor Green

try {
    # Create hMailServer COM object
    $hmail = New-Object -ComObject hMailServer.Application
    $hmail.Authenticate("Administrator", $AdminPassword) | Out-Null
    Write-Host "  ✓ Successfully connected to hMailServer" -ForegroundColor Green
} catch {
    Write-Host "  ! First-time setup detected" -ForegroundColor Yellow
    Write-Host "  Setting administrator password..." -ForegroundColor Yellow

    # For first run, the password needs to be set via database or INI file
    # We'll create a configuration script instead
    $configScript = @"
# hMailServer Configuration Script
`$hmail = New-Object -ComObject hMailServer.Application

# Authenticate (empty password for first time)
try {
    `$hmail.Authenticate("Administrator", "") | Out-Null
    Write-Host "Authenticated with empty password" -ForegroundColor Green

    # Set new password
    `$hmail.Settings.SetAdministratorPassword("$AdminPassword")
    Write-Host "Administrator password set" -ForegroundColor Green

} catch {
    Write-Host "Using provided password" -ForegroundColor Yellow
    `$hmail.Authenticate("Administrator", "$AdminPassword") | Out-Null
}
"@

    $configScript | Out-File -FilePath "$env:TEMP\hmailserver-setpassword.ps1" -Encoding UTF8
    & "$env:TEMP\hmailserver-setpassword.ps1"

    # Reconnect
    $hmail = New-Object -ComObject hMailServer.Application
    $hmail.Authenticate("Administrator", $AdminPassword) | Out-Null
}

Write-Host ""
Write-Host "[6/7] Creating domain and email accounts..." -ForegroundColor Green

# Create domain
$domains = $hmail.Domains
$domain = $domains.ItemByName($Domain)

if ($null -eq $domain) {
    Write-Host "  Creating domain: $Domain" -ForegroundColor Yellow
    $domain = $domains.Add()
    $domain.Name = $Domain
    $domain.Active = $true
    $domain.Save()
    Write-Host "  ✓ Domain created: $Domain" -ForegroundColor Green
} else {
    Write-Host "  ✓ Domain already exists: $Domain" -ForegroundColor Green
}

# Reload domain
$domain = $domains.ItemByName($Domain)

# Email accounts to create
$emailAccounts = @(
    @{Address="support"; Password="Support@2025!"; Size=500; Description="Main support inbox"},
    @{Address="noreply"; Password="NoReply@2025!"; Size=100; Description="Automated notifications"},
    @{Address="sales"; Password="Sales@2025!"; Size=500; Description="Sales inquiries"},
    @{Address="admin"; Password="Admin@2025!"; Size=1000; Description="Administrator"}
)

foreach ($acct in $emailAccounts) {
    $fullEmail = "$($acct.Address)@$Domain"
    $account = $domain.Accounts.ItemByAddress($fullEmail)

    if ($null -eq $account) {
        Write-Host "  Creating account: $fullEmail" -ForegroundColor Yellow
        $account = $domain.Accounts.Add()
        $account.Address = $fullEmail
        $account.Password = $acct.Password
        $account.Active = $true
        $account.MaxSize = $acct.Size
        $account.PersonFirstName = $acct.Address
        $account.PersonLastName = "LUMIRA"
        $account.Save()
        Write-Host "  ✓ Created: $fullEmail" -ForegroundColor Green
    } else {
        Write-Host "  ✓ Already exists: $fullEmail" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "[7/7] Configuring SMTP settings..." -ForegroundColor Green

# Configure TCP/IP ports
$tcpipPorts = $hmail.Settings.TCPIPPorts

# Check if port 587 exists
$port587 = $null
for ($i = 0; $i -lt $tcpipPorts.Count; $i++) {
    $port = $tcpipPorts.Item($i)
    if ($port.PortNumber -eq 587) {
        $port587 = $port
        break
    }
}

if ($null -eq $port587) {
    Write-Host "  Adding SMTP submission port 587..." -ForegroundColor Yellow
    $port587 = $tcpipPorts.Add()
    $port587.Protocol = 1  # SMTP
    $port587.PortNumber = 587
    $port587.UseSSL = $false
    $port587.Address = "0.0.0.0"
    $port587.Save()
    Write-Host "  ✓ Port 587 configured" -ForegroundColor Green
} else {
    Write-Host "  ✓ Port 587 already configured" -ForegroundColor Green
}

# Configure SMTP settings
Write-Host "  Configuring SMTP relay..." -ForegroundColor Yellow
$hmail.Settings.HostName = $Domain
$hmail.Settings.WelcomeSMTP = "220 $Domain ESMTP hMailServer"

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Installation Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "hMailServer is now installed and configured!" -ForegroundColor Cyan
Write-Host ""
Write-Host "Configuration Details:" -ForegroundColor Yellow
Write-Host "  Domain: $Domain" -ForegroundColor White
Write-Host "  Administrator Password: $AdminPassword" -ForegroundColor White
Write-Host ""
Write-Host "Email Accounts Created:" -ForegroundColor Yellow
foreach ($acct in $emailAccounts) {
    Write-Host "  $($acct.Address)@$Domain" -ForegroundColor White
    Write-Host "    Password: $($acct.Password)" -ForegroundColor Gray
    Write-Host "    Purpose: $($acct.Description)" -ForegroundColor Gray
}
Write-Host ""
Write-Host "Ports Configured:" -ForegroundColor Yellow
Write-Host "  SMTP: 25 (incoming)" -ForegroundColor White
Write-Host "  SMTP Submission: 587 (outgoing)" -ForegroundColor White
Write-Host "  POP3: 110" -ForegroundColor White
Write-Host "  IMAP: 143" -ForegroundColor White
Write-Host ""
Write-Host "Admin Panel:" -ForegroundColor Yellow
Write-Host "  Start Menu → hMailServer Administrator" -ForegroundColor White
Write-Host "  Server: localhost" -ForegroundColor White
Write-Host "  Username: Administrator" -ForegroundColor White
Write-Host "  Password: $AdminPassword" -ForegroundColor White
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Run: .\test-hmailserver.ps1 (to test email)" -ForegroundColor White
Write-Host "  2. Run: .\configure-lumira-email.ps1 (to update LUMIRA)" -ForegroundColor White
Write-Host ""
Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
