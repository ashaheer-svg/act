<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Reports.php',
    'reports.php',
    'includes/sidebar.php',
    'layout.css',
    'includes/layout_js.php',
    'classes/Database.php',
    'classes/DataImporter.php',
    'api/sync.php'
];

echo "=== Uploading to $ftpBase ===\n";

foreach ($files as $f) {
    $remoteUrl = "$ftpBase/$f";
    echo "Uploading $f -> $remoteUrl ... ";
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    if ($ret === 0) {
        echo "OK\n";
    } else {
        echo "FAILED (code $ret): " . implode("\n", $out) . "\n";
    }
}
echo "=== COMPLETED ===\n";
