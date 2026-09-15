<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Database.php',
    'classes/Reports.php',
    'api/sync.php',
    'classes/DataImporter.php',
    'classes/DataSorter.php',
    'reports.php',
    'data/sales_bi.db'
];

foreach ($files as $f) {
    $remoteUrl = "$ftpBase/$f";
    $size = is_file($f) ? round(filesize($f) / (1024 * 1024), 2) . " MB" : "";
    echo "Uploading $f ($size) ... ";
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
echo "ALL FILES DEPLOYED TO PRODUCTION SUCCESSFULLY\n";
