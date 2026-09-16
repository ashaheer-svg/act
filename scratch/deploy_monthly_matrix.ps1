$ErrorActionPreference = "Stop"

$ftpUser = "activeftp"
$ftpPass = "active***me"
$ftpBase = "ftp://active.lk:21/act"

$filesToUpload = @(
    "classes/Reports.php",
    "includes/report_methodology.php",
    "includes/sidebar.php",
    "layout.css",
    "reports.php"
)

Write-Host "=== Deploying Monthly Sales Matrix to active.lk/act ===" -ForegroundColor Cyan
Write-Host "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

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
Write-Host "=== All Monthly Sales Matrix files successfully deployed to active.lk/act! ===" -ForegroundColor Green
