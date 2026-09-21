<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Database.php' => 'classes/Database.php',
    'classes/DataSorter.php' => 'classes/DataSorter.php',
    'classes/Reports.php' => 'classes/Reports.php',
    'reports.php' => 'reports.php',
    'api/sync.php' => 'api/sync.php',
    'scratch/remote_migrate_end_customers.php' => 'remote_migrate_end_customers.php'
];

echo "=== DEPLOYING END CUSTOMER & CREDIT MEMO UPDATES TO PRODUCTION ===\n";

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

echo "\nExecuting remote migration on production...\n";
$migUrl = "https://act.active.lk/remote_migrate_end_customers.php";
$ch = curl_init($migUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n$resp\n";

echo "\nCleaning up remote migration script...\n";
$delCmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE remote_migrate_end_customers.php\" \"$ftpBase/\"";
exec($delCmd, $delOut, $delRet);
echo "Cleanup status: " . ($delRet === 0 ? "SUCCESS" : "Code $delRet") . "\n";

echo "\n=== DEPLOYMENT AND MIGRATION COMPLETE ===\n";
