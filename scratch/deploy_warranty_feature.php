<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Reports.php',
    'classes/Database.php',
    'classes/Auth.php',
    'reports.php',
    'includes/sidebar.php',
    'warranty_lookup.php'
];

foreach ($files as $f) {
    $remoteUrl = "$ftpBase/$f";
    echo "Uploading $f ... ";
    $start = microtime(true);
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    $dur = round(microtime(true) - $start, 2);
    if ($ret === 0) {
        echo "OK in {$dur}s\n";
    } else {
        echo "FAILED (code $ret): " . implode("\n", $out) . "\n";
    }
}
echo "DEPLOY COMPLETE\n";
