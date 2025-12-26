# Rename LUMIRA files to be the default site
# Removes lumira_ prefix from filenames

Write-Host "LUMIRA - Setting as default website" -ForegroundColor Cyan
Write-Host "=" * 50

$filesToRename = @{
    "lumira_index.php" = "index.php"
    "lumira_products.php" = "products.php"
    "lumira_cart.php" = "cart.php"
    "lumira_checkout.php" = "checkout.php"
    "lumira_services.php" = "services.php"
    "lumira_admin.php" = "admin.php"
    "lumira_dbtest.php" = "dbtest.php"
}

foreach ($oldName in $filesToRename.Keys) {
    $newName = $filesToRename[$oldName]

    if (Test-Path $oldName) {
        # Remove destination if it exists
        if (Test-Path $newName) {
            Remove-Item $newName -Force
        }

        Rename-Item $oldName $newName
        Write-Host "  Renamed: $oldName → $newName" -ForegroundColor Green
    }
}

Write-Host "`n" + ("=" * 50)
Write-Host "LUMIRA is now the default website!" -ForegroundColor Green
Write-Host "`nAccess at: http://localhost/"
Write-Host "           http://localhost/index.php"
