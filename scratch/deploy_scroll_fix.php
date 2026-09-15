<?php
/**
 * Deploy reports.php and layout.css to production
 */
declare(strict_types=1);

$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'reports.php',
    'layout.css'
];

echo "=== Uploading reports.php and layout.css to production ===\n";

foreach ($files as $f) {
    $local = str_replace('/', DIRECTORY_SEPARATOR, $f);
    $remote = "$ftpBase/$f";
    $sizeFormatted = round(filesize($local) / 1024, 1) . ' KB';
    echo "Uploading $f ($sizeFormatted) ... ";
    
    $start = microtime(true);
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$local\" \"$remote\"";
    exec($cmd, $out, $ret);
    $elapsed = round(microtime(true) - $start, 2);
    
    if ($ret === 0) {
        echo "[OK] ({$elapsed}s)\n";
    } else {
        echo "[FAILED - code $ret]\n" . implode("\n", $out) . "\n";
        exit(1);
    }
}

echo "=== DEPLOYMENT COMPLETED SUCCESSFULLY ===\n";
