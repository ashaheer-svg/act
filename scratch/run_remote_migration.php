<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$local = 'scratch/remote_settle_pre2022.php';
$remote = 'remote_settle_pre2022.php';
$remoteUrl = "$ftpBase/$remote";

echo "Uploading migration script to $remoteUrl ... ";
$cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$local\" \"$remoteUrl\"";
exec($cmd, $out, $ret);
if ($ret === 0) {
    echo "OK\n";
} else {
    echo "FAILED: " . implode("\n", $out) . "\n";
    exit(1);
}

echo "Executing remote settlement migration...\n";
$migUrl = "https://act.active.lk/remote_settle_pre2022.php";
$ch = curl_init($migUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $resp\n";
