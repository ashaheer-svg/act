<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'layout.css' => 'layout.css',
    'includes/report_methodology.php' => 'includes/report_methodology.php',
    'reports.php' => 'reports.php'
];

echo "=== DEPLOYING METHODOLOGY BUTTON MINIMIZATION UPDATE TO PRODUCTION ===\n";

foreach ($files as $local => $remote) {
    $remoteUrl = "$ftpBase/$remote";
    echo "Uploading $local -> $remote ... ";
    $start = microtime(true);
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$local\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    $dur = round(microtime(true) - $start, 2);
    if ($ret === 0) {
        echo "OK ({$dur}s)\n";
    } else {
        echo "FAILED (code $ret): " . implode("\n", $out) . "\n";
    }
}

echo "=== DEPLOYMENT COMPLETE ===\n";
