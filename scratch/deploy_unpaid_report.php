<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Reports.php' => 'classes/Reports.php',
    'reports.php' => 'reports.php',
    'includes/sidebar.php' => 'includes/sidebar.php',
    'unpaid_invoices.php' => 'unpaid_invoices.php',
    'scratch/remote_settle_pre2022.php' => 'remote_settle_pre2022.php'
];

echo "=== DEPLOYING UNPAID INVOICES REPORT TO PRODUCTION ===\n";

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
    }
}

echo "\nExecuting remote database migration...\n";
$migUrl = "https://act.active.lk/remote_settle_pre2022.php";
$ch = curl_init($migUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "Migration HTTP Status: $httpCode\n";
echo "Response: $resp\n\n";

// Cleanup migration script on remote
echo "Cleaning up remote migration script ... ";
$delCmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /remote_settle_pre2022.php\" \"$ftpBase/\"";
exec($delCmd, $delOut, $delRet);
if ($delRet === 0) {
    echo "OK (cleaned up)\n";
} else {
    // Try without leading slash
    $delCmd2 = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE remote_settle_pre2022.php\" \"$ftpBase/\"";
    exec($delCmd2, $delOut2, $delRet2);
    echo ($delRet2 === 0) ? "OK (cleaned up)\n" : "Note: deletion returned $delRet / $delRet2\n";
}

echo "=== DEPLOYMENT AND REMOTE MIGRATION COMPLETE ===\n";
