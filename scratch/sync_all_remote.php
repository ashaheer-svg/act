<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'config.php',
    'layout.css',
    'reports.php',
    'customer_report.php',
    'explorer.php',
    'product_mapping.php',
    'classes/Database.php',
    'classes/Reports.php',
    'classes/Auth.php',
    'classes/DataImporter.php',
    'classes/DataSorter.php',
    'classes/AiExtractor.php',
    'api/sync.php',
    'includes/sidebar.php',
    'includes/layout_js.php',
    'includes/report_methodology.php',
    'data/sales_bi.db'
];

echo "========================================================\n";
echo "   COMPREHENSIVE REMOTE SERVER UPDATE (active.lk)       \n";
echo "========================================================\n\n";

$failed = [];
$uploaded = [];

foreach ($files as $f) {
    if (!file_exists($f)) {
        echo "[SKIP] File does not exist locally: $f\n";
        continue;
    }

    $remoteUrl = "$ftpBase/$f";
    $sizeBytes = filesize($f);
    $sizeStr = ($sizeBytes > 1024 * 1024) 
        ? round($sizeBytes / (1024 * 1024), 2) . " MB" 
        : round($sizeBytes / 1024, 1) . " KB";

    echo sprintf("Uploading %-30s (%8s) ... ", $f, $sizeStr);
    $start = microtime(true);
    $out = [];
    $ret = 0;
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    $dur = round(microtime(true) - $start, 2);

    if ($ret === 0) {
        echo "[OK] ({$dur}s)\n";
        $uploaded[] = $f;
    } else {
        echo "[FAILED code $ret]: " . implode(" ", $out) . "\n";
        $failed[] = $f;
    }
}

echo "\n--------------------------------------------------------\n";
echo "Uploaded: " . count($uploaded) . " files\n";
if (empty($failed)) {
    echo "STATUS: ALL FILES SUCCESSFULLY DEPLOYED TO REMOTE SERVER\n";
} else {
    echo "STATUS: ERRORS on " . count($failed) . " files: " . implode(", ", $failed) . "\n";
}
echo "--------------------------------------------------------\n";
