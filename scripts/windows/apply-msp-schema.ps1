# LUMIRA MSP Database Schema Application Script
# This script will apply the comprehensive MSP schema to your database

$env:PGPASSWORD = "StrongPassword123"
$psqlPath = "C:\Program Files\PostgreSQL\18\bin\psql.exe"
$dbHost = "10.0.1.200"
$dbUser = "postgres"
$dbName = "LUMIRA"
$scriptDir = "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "LUMIRA MSP Database Schema Application" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Apply MSP Schema
Write-Host "Step 1: Applying MSP schema..." -ForegroundColor Yellow
try {
    & $psqlPath -h $dbHost -U $dbUser -d $dbName -f "$scriptDir\msp_schema.sql"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  MSP schema applied successfully!" -ForegroundColor Green
    } else {
        Write-Host "  Schema application failed!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Step 2: Load Seed Data
Write-Host "Step 2: Loading seed data..." -ForegroundColor Yellow
try {
    & $psqlPath -h $dbHost -U $dbUser -d $dbName -f "$scriptDir\msp_seed_data.sql"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  Seed data loaded successfully!" -ForegroundColor Green
    } else {
        Write-Host "  Seed data loading failed!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Database setup complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Default Admin Credentials:" -ForegroundColor Yellow
Write-Host "  Email: admin@lumira.com" -ForegroundColor White
Write-Host "  Password: Admin@2025!" -ForegroundColor White
Write-Host ""
Write-Host "IMPORTANT: Change the admin password after first login!" -ForegroundColor Red
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Update your PHP application to use the new tables" -ForegroundColor White
Write-Host "2. Test database connection" -ForegroundColor White
Write-Host "3. Implement user registration and login" -ForegroundColor White
Write-Host ""
