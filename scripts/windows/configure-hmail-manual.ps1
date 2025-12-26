# LUMIRA - hMailServer Manual Configuration via COM
# Configures domain and email accounts for LUMIRA

param(
    [string]$AdminPassword = "Admin@2025!",
    [string]$Domain = "lumira.local"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  hMailServer Configuration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if service is running
$service = Get-Service -Name "hMailServer" -ErrorAction SilentlyContinue
if (-not $service -or $service.Status -ne "Running") {
    Write-Host "ERROR: hMailServer service is not running!" -ForegroundColor Red
    pause
    exit 1
}

Write-Host "Connecting to hMailServer..." -ForegroundColor Green

try {
    # Create COM object
    $hmail = New-Object -ComObject hMailServer.Application

    # Try to authenticate
    try {
        $result = $hmail.Authenticate("Administrator", "")
        Write-Host "  ✓ Connected with empty password (first run)" -ForegroundColor Green

        # Set password
        Write-Host "  Setting administrator password..." -ForegroundColor Yellow
        $hmail.Settings.SetAdministratorPassword($AdminPassword)
        Write-Host "  ✓ Password set" -ForegroundColor Green

    } catch {
        # Password already set, try with provided password
        $result = $hmail.Authenticate("Administrator", $AdminPassword)
        if ($result) {
            Write-Host "  ✓ Connected with administrator password" -ForegroundColor Green
        } else {
            throw "Authentication failed"
        }
    }

    Write-Host ""
    Write-Host "Creating domain: $Domain" -ForegroundColor Green

    # Get or create domain
    $domains = $hmail.Domains
    $domain = $domains.ItemByName($Domain)

    if ($null -eq $domain) {
        $domain = $domains.Add()
        $domain.Name = $Domain
        $domain.Active = $true
        $domain.Postmaster = "admin@$Domain"
        $domain.Save()
        Write-Host "  ✓ Domain created: $Domain" -ForegroundColor Green
    } else {
        Write-Host "  ✓ Domain already exists: $Domain" -ForegroundColor Green
    }

    # Refresh domain reference
    $domain = $domains.ItemByName($Domain)

    Write-Host ""
    Write-Host "Creating email accounts..." -ForegroundColor Green

    # Email accounts
    $accounts = @(
        @{User="noreply"; Pass="NoReply@2025!"; Size=100; Name="No Reply"},
        @{User="support"; Pass="Support@2025!"; Size=500; Name="Support Team"},
        @{User="sales"; Pass="Sales@2025!"; Size=500; Name="Sales Team"},
        @{User="admin"; Pass="Admin@2025!"; Size=1000; Name="Administrator"}
    )

    foreach ($acct in $accounts) {
        $email = "$($acct.User)@$Domain"
        $existing = $domain.Accounts.ItemByAddress($email)

        if ($null -eq $existing) {
            Write-Host "  Creating: $email" -ForegroundColor Yellow
            $newAcct = $domain.Accounts.Add()
            $newAcct.Address = $email
            $newAcct.Password = $acct.Pass
            $newAcct.Active = $true
            $newAcct.MaxSize = $acct.Size
            $newAcct.PersonFirstName = $acct.Name
            $newAcct.PersonLastName = "LUMIRA"
            $newAcct.Save()
            Write-Host "    ✓ Created: $email" -ForegroundColor Green
        } else {
            Write-Host "  ✓ Exists: $email" -ForegroundColor Green
        }
    }

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  Configuration Complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""

    Write-Host "Email Accounts:" -ForegroundColor Yellow
    foreach ($acct in $accounts) {
        Write-Host "  $($acct.User)@$Domain" -ForegroundColor White
        Write-Host "    Password: $($acct.Pass)" -ForegroundColor Gray
    }

    Write-Host ""
    Write-Host "Next: Run .\configure-lumira-email.ps1" -ForegroundColor Cyan
    Write-Host ""

} catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please configure manually using hMailServer Administrator" -ForegroundColor Yellow
    pause
    exit 1
}

pause
