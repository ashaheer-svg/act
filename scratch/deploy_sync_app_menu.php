<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'sync_app.php' => 'sync_app.php',
    'includes/sidebar.php' => 'includes/sidebar.php',
    'settings.php' => 'settings.php',
    'app/binary/SalesBISync.zip' => 'app/binary/SalesBISync.zip',
    'app/binary/SalesBISync.exe' => 'app/binary/SalesBISync.exe'
];

echo "========================================================\n";
echo "DEPLOYING SYNC APP DOWNLOAD MENU & BINARIES TO PROD\n";
echo "========================================================\n";

foreach ($files as $local => $remote) {
    $remoteUrl = "$ftpBase/$remote";
    $sizeMB = round(filesize($local) / (1024 * 1024), 2);
    echo "Uploading $local ({$sizeMB} MB) -> $remote ... ";
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

echo "\nChecking remote sync_app.php HTTP response...\n";
$url = "https://act.active.lk/sync_app.php";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "sync_app.php HTTP Status: $code (Expected: 302 redirect to login.php for unauthenticated request)\n";

echo "\nChecking remote SalesBISync.zip download availability...\n";
$zipUrl = "https://act.active.lk/app/binary/SalesBISync.zip";
$ch2 = curl_init($zipUrl);
curl_setopt($ch2, CURLOPT_NOBODY, true);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch2);
$zipCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$zipLen = curl_getinfo($ch2, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
curl_close($ch2);
echo "SalesBISync.zip HTTP Status: $zipCode (Size: " . round($zipLen / (1024*1024), 1) . " MB)\n";

echo "\nChecking remote SalesBISync.exe download availability...\n";
$exeUrl = "https://act.active.lk/app/binary/SalesBISync.exe";
$ch3 = curl_init($exeUrl);
curl_setopt($ch3, CURLOPT_NOBODY, true);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch3);
$exeCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$exeLen = curl_getinfo($ch3, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
curl_close($ch3);
echo "SalesBISync.exe HTTP Status: $exeCode (Size: " . round($exeLen / (1024*1024), 1) . " MB)\n";

echo "\n========================================================\n";
echo "SYNC APP MENU DEPLOYMENT COMPLETE!\n";
echo "========================================================\n";
