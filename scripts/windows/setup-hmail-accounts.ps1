# LUMIRA - hMailServer Account Setup
# Simple script to configure email accounts

$AdminPassword = "Admin@2025!"
$Domain = "lumira.local"

Write-Host "Configuring hMailServer for LUMIRA..." -ForegroundColor Cyan
Write-Host ""

# Check service
$svc = Get-Service -Name "hMailServer" -ErrorAction SilentlyContinue
if (-not $svc -or $svc.Status -ne "Running") {
    Write-Host "hMailServer service not running!" -ForegroundColor Red
    exit 1
}

try {
    # Connect
    $hmail = New-Object -ComObject hMailServer.Application

    # Authenticate - try empty password first
    $auth = $false
    try {
        $auth = $hmail.Authenticate("Administrator", "")
        if ($auth) {
            Write-Host "Setting admin password..." -ForegroundColor Yellow
            $hmail.Settings.SetAdministratorPassword($AdminPassword)
        }
    } catch {}

    if (-not $auth) {
        $auth = $hmail.Authenticate("Administrator", $AdminPassword)
    }

    if (-not $auth) {
        Write-Host "Authentication failed!" -ForegroundColor Red
        exit 1
    }

    Write-Host "Connected to hMailServer" -ForegroundColor Green
    Write-Host ""

    # Create/Get Domain
    $domains = $hmail.Domains
    $dom = $domains.ItemByName($Domain)

    if ($null -eq $dom) {
        Write-Host "Creating domain: $Domain" -ForegroundColor Yellow
        $dom = $domains.Add()
        $dom.Name = $Domain
        $dom.Active = $true
        $dom.Save()
        $dom = $domains.ItemByName($Domain)
    }

    Write-Host "Domain: $Domain" -ForegroundColor Green
    Write-Host ""

    # Create Accounts
    $accts = @(
        @("noreply", "NoReply@2025!", 100),
        @("support", "Support@2025!", 500),
        @("sales", "Sales@2025!", 500),
        @("admin", "Admin@2025!", 1000)
    )

    foreach ($a in $accts) {
        $addr = "$($a[0])@$Domain"
        $ex = $dom.Accounts.ItemByAddress($addr)

        if ($null -eq $ex) {
            Write-Host "Creating: $addr" -ForegroundColor Yellow
            $na = $dom.Accounts.Add()
            $na.Address = $addr
            $na.Password = $a[1]
            $na.Active = $true
            $na.MaxSize = $a[2]
            $na.Save()
            Write-Host "  Created!" -ForegroundColor Green
        } else {
            Write-Host "Exists: $addr" -ForegroundColor Green
        }
    }

    Write-Host ""
    Write-Host "Configuration Complete!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Accounts created:" -ForegroundColor Yellow
    foreach ($a in $accts) {
        Write-Host "  $($a[0])@$Domain (password: $($a[1]))" -ForegroundColor White
    }

} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
