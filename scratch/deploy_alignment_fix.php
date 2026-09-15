<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Reports.php',
    'reports.php'
];

foreach ($files as $f) {
    $remoteUrl = "$ftpBase/$f";
    echo "Uploading $f ... ";
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    if ($ret === 0) {
        echo "OK\n";
    } else {
        echo "FAILED: " . implode("\n", $out) . "\n";
    }
}
echo "DEPLOYMENT COMPLETE\n";
