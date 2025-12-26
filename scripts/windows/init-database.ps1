# LUMIRA Database Initialization Script
# Run this script to create database and load schema + seed data

$env:PGPASSWORD = "StrongPassword123!"
$psqlPath = "C:\Program Files\PostgreSQL\18\bin\psql.exe"
$createdbPath = "C:\Program Files\PostgreSQL\18\bin\createdb.exe"
$dbHost = "10.0.1.200"
$dbUser = "admin"
$dbName = "LUMIRA"

Write-Host "LUMIRA Database Initialization" -ForegroundColor Cyan
Write-Host "=" * 50

# Step 1: Create database
Write-Host "`nStep 1: Creating database '$dbName'..." -ForegroundColor Yellow
try {
    & $createdbPath -h $dbHost -U $dbUser $dbName 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  Database created successfully!" -ForegroundColor Green
    } else {
        Write-Host "  Database already exists or creation failed (continuing...)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  Warning: $($_.Exception.Message)" -ForegroundColor Yellow
}

# Step 2: Load schema
Write-Host "`nStep 2: Loading schema..." -ForegroundColor Yellow
& $psqlPath -h $dbHost -U $dbUser -d $dbName -f "schema.sql"
if ($LASTEXITCODE -eq 0) {
    Write-Host "  Schema loaded successfully!" -ForegroundColor Green
} else {
    Write-Host "  Schema loading failed!" -ForegroundColor Red
    exit 1
}

# Step 3: Load seed data
Write-Host "`nStep 3: Loading seed data..." -ForegroundColor Yellow
& $psqlPath -h $dbHost -U $dbUser -d $dbName -f "seed.sql"
if ($LASTEXITCODE -eq 0) {
    Write-Host "  Seed data loaded successfully!" -ForegroundColor Green
} else {
    Write-Host "  Seed data loading failed!" -ForegroundColor Red
    exit 1
}

Write-Host "`n" + ("=" * 50)
Write-Host "Database initialization complete!" -ForegroundColor Green
Write-Host "`nNext steps:" -ForegroundColor Cyan
Write-Host "1. Test connection: http://localhost/lumira_dbtest.php"
Write-Host "2. Visit site: http://localhost/lumira_index.php"
Write-Host "3. Admin panel: http://localhost/lumira_admin.php (password: Admin@2025!)"
