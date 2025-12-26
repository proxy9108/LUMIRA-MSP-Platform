# Fix internal links in all PHP files
# Replace lumira_ prefixed filenames with plain names

Write-Host "Fixing internal links in PHP files..." -ForegroundColor Cyan

$replacements = @{
    "/lumira_index.php" = "/index.php"
    "/lumira_products.php" = "/products.php"
    "/lumira_cart.php" = "/cart.php"
    "/lumira_checkout.php" = "/checkout.php"
    "/lumira_services.php" = "/services.php"
    "/lumira_admin.php" = "/admin.php"
    "lumira_dbtest.php" = "dbtest.php"
}

$phpFiles = Get-ChildItem -Filter "*.php" | Where-Object { $_.Name -ne "fix-links.ps1" }

foreach ($file in $phpFiles) {
    $content = Get-Content $file.FullName -Raw
    $modified = $false

    foreach ($old in $replacements.Keys) {
        $new = $replacements[$old]
        if ($content -match [regex]::Escape($old)) {
            $content = $content -replace [regex]::Escape($old), $new
            $modified = $true
        }
    }

    if ($modified) {
        Set-Content $file.FullName -Value $content -NoNewline
        Write-Host "  Updated: $($file.Name)" -ForegroundColor Green
    }
}

Write-Host "`nAll links fixed!" -ForegroundColor Green
