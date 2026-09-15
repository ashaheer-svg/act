<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/DataSorter.php' => 'classes/DataSorter.php',
    'classes/DataImporter.php' => 'classes/DataImporter.php',
    'scratch/remote_normalize_synology.php' => 'remote_normalize_synology.php'
];

echo "=== DEPLOYING SYNOLOGY NORMALIZATION TO PRODUCTION ===\n";

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
        exit(1);
    }
}

echo "Executing remote normalization migration...\n";
$migUrl = "https://act.active.lk/remote_normalize_synology.php";
$ch = curl_init($migUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $resp\n";
echo "=== DEPLOYMENT AND NORMALIZATION COMPLETE ===\n";
