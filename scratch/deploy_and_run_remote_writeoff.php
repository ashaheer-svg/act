<?php
/**
 * Deploy & Execute Write-off on Production
 */
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$localFile = __DIR__ . '/remote_writeoff_prod.php';
$remoteUrl = "$ftpBase/remote_writeoff_prod.php";

echo "=== 1. Uploading runner to production via FTPS ===\n";
$cmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$localFile\" \"$remoteUrl\"";
exec($cmd, $out, $ret);

if ($ret !== 0) {
    echo "FTPS Upload FAILED (code $ret): " . implode("\n", $out) . "\n";
    exit(1);
}
echo "Runner uploaded successfully.\n\n";

echo "=== 2. Triggering remote execution on production ===\n";
$ch = curl_init('https://active.lk/act/remote_writeoff_prod.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 30
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
unset($ch);

echo "HTTP Status: $httpCode\n";
if ($err) {
    echo "cURL Error: $err\n";
    exit(1);
}

echo "Response from Production:\n$resp\n";
