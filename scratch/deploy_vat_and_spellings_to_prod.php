<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/DataSorter.php' => 'classes/DataSorter.php',
    'classes/DataImporter.php' => 'classes/DataImporter.php',
    'classes/Database.php' => 'classes/Database.php',
    'api/sync.php' => 'api/sync.php',
    'invoice_edit.php' => 'invoice_edit.php',
    'settings.php' => 'settings.php',
    'vat_review.php' => 'vat_review.php',
    'data/sales_bi.db' => 'data/sales_bi.db',
    'scratch/remote_verify_all.php' => 'remote_verify_all.php'
];

echo "========================================================\n";
echo "DEPLOYING VAT REVISION & SPELLING NORMALIZATION TO PROD\n";
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

echo "\nTriggering remote verification audit...\n";
$auditUrl = "https://act.active.lk/remote_verify_all.php";
$ch = curl_init($auditUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Verification Audit Response:\n$resp\n";

echo "\nChecking vat_review.php HTTP response...\n";
$vrUrl = "https://act.active.lk/vat_review.php";
$ch2 = curl_init($vrUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch2);
$vrHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "vat_review.php HTTP Status: $vrHttpCode (Expected: 302 redirect to login.php for unauthenticated request)\n";

echo "\n========================================================\n";
echo "PRODUCTION DEPLOYMENT AND VERIFICATION FINISHED!\n";
echo "========================================================\n";
