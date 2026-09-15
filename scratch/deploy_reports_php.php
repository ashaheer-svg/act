<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$f = 'reports.php';
$remoteUrl = "$ftpBase/$f";

echo "Uploading $f to $remoteUrl ... ";
$cmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
exec($cmd, $out, $ret);

if ($ret === 0) {
    echo "SUCCESS!\n";
    exit(0);
} else {
    echo "FAILED (code $ret): " . implode("\n", $out) . "\n";
    exit(1);
}
