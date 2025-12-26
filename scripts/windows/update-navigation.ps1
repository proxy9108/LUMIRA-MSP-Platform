# Script to update navigation in all PHP files

$files = @(
    "services.php",
    "cart.php",
    "checkout.php",
    "login.php",
    "dashboard-admin.php",
    "dashboard-customer.php"
)

foreach ($file in $files) {
    $filePath = "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\$file"

    if (Test-Path $filePath) {
        $content = Get-Content $filePath -Raw

        # Replace the old nav block with new nav include
        $oldNav = @'
    <nav>
        <div class="container">
            <ul>
                <li><a href="/index.php"( class="active")?>Home</a></li>
                <li><a href="/products.php"( class="active")?>Products</a></li>
                <li><a href="/services.php"( class="active")?>Services</a></li>
                <li><a href="/cart.php"( class="active")?>Cart.*?</a></li>
                <li><a href="/login.php"( class="active")?>Login</a></li>
            </ul>
        </div>
    </nav>
'@

        $newNav = '    <?php require_once ''inc/nav.php''; ?>'

        # Use regex to match the nav block
        $pattern = '<nav>.*?</nav>'

        if ($content -match $pattern) {
            $content = $content -replace $pattern, "<?php require_once 'inc/nav.php'; ?>"
            Set-Content -Path $filePath -Value $content -NoNewline
            Write-Host "Updated $file" -ForegroundColor Green
        } else {
            Write-Host "Pattern not found in $file" -ForegroundColor Yellow
        }
    } else {
        Write-Host "File not found: $file" -ForegroundColor Red
    }
}

Write-Host "`nNavigation update complete!" -ForegroundColor Cyan
