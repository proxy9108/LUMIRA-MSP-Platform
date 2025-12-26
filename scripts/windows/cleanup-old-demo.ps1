# Cleanup Old Demo Website Files
# This script moves old demo files to an archive folder

Write-Host "LUMIRA - Cleaning up old demo files" -ForegroundColor Cyan
Write-Host "=" * 50

$archiveFolder = ".\old_demo_archive"

# Create archive folder if it doesn't exist
if (!(Test-Path $archiveFolder)) {
    New-Item -ItemType Directory -Name $archiveFolder | Out-Null
    Write-Host "Created archive folder: $archiveFolder" -ForegroundColor Green
}

# List of old demo files and folders to archive
$filesToArchive = @(
    ".\app",
    ".\config",
    ".\migrations",
    ".\pages",
    ".\public",
    ".\.env",
    ".\index.html",
    ".\50x.html",
    ".\info.php",
    ".\dbtest.php",
    ".\index.php",
    ".\migrate.php",
    ".\seed-data.php",
    ".\run-migrations.php",
    ".\run-migrations.ps1",
    ".\README.md",
    ".\lumira-website.code-workspace"
)

$movedCount = 0

foreach ($file in $filesToArchive) {
    if (Test-Path $file) {
        $fileName = Split-Path $file -Leaf
        $destination = Join-Path $archiveFolder $fileName

        # If destination exists, remove it first
        if (Test-Path $destination) {
            Remove-Item $destination -Recurse -Force
        }

        Move-Item $file $destination -Force
        Write-Host "  Archived: $fileName" -ForegroundColor Yellow
        $movedCount++
    }
}

Write-Host "`n" + ("=" * 50)
Write-Host "Cleanup complete! Archived $movedCount items" -ForegroundColor Green
Write-Host "`nOld files moved to: $archiveFolder"
Write-Host "You can delete this folder later if not needed."
