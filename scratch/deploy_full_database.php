<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Database.php',
    'data/sales_bi.db'
];

echo "=== DEPLOYING COMPLETE 2009-2026 DATABASE & UPDATED CLASS TO PRODUCTION ===\n";

foreach ($files as $f) {
    $remoteUrl = "$ftpBase/$f";
    $sizeMB = round(filesize($f) / (1024 * 1024), 2);
    echo "Uploading $f ({$sizeMB} MB) -> $remoteUrl ... \n";
    $start = microtime(true);
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    $dur = round(microtime(true) - $start, 2);
    if ($ret === 0) {
        echo "  [OK] in {$dur}s\n";
    } else {
        echo "  [FAILED - code $ret] " . implode("\n", $out) . "\n";
    }
}
echo "=== DEPLOYMENT FINISHED ===\n";
