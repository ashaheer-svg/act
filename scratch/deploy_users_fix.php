<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = ["settings.php", "users.php"];
foreach ($files as $f) {
    echo "Uploading $f ... ";
    $cmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$ftpBase/$f\" 2>&1";
    $out = [];
    $ret = 0;
    exec($cmd, $out, $ret);
    if ($ret === 0) {
        echo "OK\n";
    } else {
        echo "FAILED: " . implode("\n", $out) . "\n";
    }
}
echo "Deploy completed.\n";
