$ErrorActionPreference = "Stop"

$ftpUser = "activeftp"
$ftpPass = "active***me"
$ftpBase = "ftp://active.lk:21/act"

$filesToUpload = @(
    "classes/Database.php",
    "classes/DataImporter.php",
    "classes/DataSorter.php",
    "classes/AiExtractor.php",
    "classes/Reports.php",
    "classes/Auth.php",
    "api/sync.php",
    "bin/batch_extract_assets.php",
    "bin/sort_existing_data.php",
    "import_legacy_qb.php",
    "includes/sidebar.php",
    "includes/layout_js.php",
    "includes/header.php",
    "includes/report_methodology.php",
    "reports.php",
    "explorer.php",
    "settings.php",
    "index.php",
    "customers.php",
    "customer_report.php",
    "profit_entry.php",
    "upload.php",
    "config.php",
    "layout.css",
    "product_mapping.php",
    "ENHANCEMENTS.md",
    "app/binary/config.zip",
    "app/binary/README.md",
    "data/sales_bi.db"
)

Write-Host "=== Deploying to active.lk/act via FTPS ===" -ForegroundColor Cyan
Write-Host "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Host ""

$total = $filesToUpload.Count
$current = 0

foreach ($file in $filesToUpload) {
    $current++
    $localFile = $file -replace "/", "\"
    
    if (-not (Test-Path $localFile)) {
        Write-Warning "File not found locally: $localFile (skipping)"
        continue
    }

    $fileSize = (Get-Item $localFile).Length
    $sizeFormatted = if ($fileSize -gt 1MB) { "{0:N2} MB" -f ($fileSize / 1MB) } else { "{0:N2} KB" -f ($fileSize / 1KB) }
    
    $remoteUrl = "$ftpBase/$file"
    Write-Host "[$current/$total] Uploading $file ($sizeFormatted)..." -NoNewline
    
    $start = Get-Date
    & curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user "$($ftpUser):$($ftpPass)" -T "$localFile" "$remoteUrl"
    $exitCode = $LASTEXITCODE
    $duration = [Math]::Round(((Get-Date) - $start).TotalSeconds, 2)
    
    if ($exitCode -eq 0) {
        Write-Host " [OK] in ${duration}s" -ForegroundColor Green
    } else {
        Write-Host " [FAILED - exit $exitCode]" -ForegroundColor Red
        throw "Failed to upload $file"
    }
}

Write-Host ""
Write-Host "=== All files successfully transferred to active.lk/act! ===" -ForegroundColor Green
