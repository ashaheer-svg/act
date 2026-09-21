<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    "classes/Database.php",
    "classes/DataImporter.php",
    "api/sync.php",
    "data/sales_bi.db"
];

echo "=== Deploying Post-2024 VAT Inclusive Changes to Production ===\n";

foreach ($files as $f) {
    echo "Uploading $f ... ";
    $start = microtime(true);
    $cmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$ftpBase/$f\" 2>&1";
    $out = [];
    $ret = 0;
    exec($cmd, $out, $ret);
    $elapsed = round(microtime(true) - $start, 1);
    if ($ret === 0) {
        echo "OK ({$elapsed}s)\n";
    } else {
        echo "FAILED in {$elapsed}s: " . implode("\n", $out) . "\n";
        exit(1);
    }
}

echo "All files uploaded successfully.\n";
