<?php
declare(strict_types=1);

$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

echo "=== 1. UPLOADING & RUNNING REMOTE DATABASE MIGRATION ===\n";
$migrationRunner = __DIR__ . '/remote_migrate_invoice_edit.php';
$remoteRunnerUrl = "$ftpBase/remote_migrate_invoice_edit.php";

$cmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$migrationRunner\" \"$remoteRunnerUrl\"";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    echo "ERROR: Upload failed: " . implode("\n", $out) . "\n";
    exit(1);
}
echo "Migration runner uploaded.\n";

echo "Triggering remote execution...\n";
$ch = curl_init('https://active.lk/act/remote_migrate_invoice_edit.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 60
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $resp\n\n";

$json = json_decode($resp, true);
if (!isset($json['status']) || $json['status'] !== 'success') {
    echo "ERROR: Remote migration failed.\n";
    exit(1);
}

// Cleanup remote migration runner
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /act/remote_migrate_invoice_edit.php\" \"$ftpBase/\" 2>&1");
echo "Migration runner cleaned up.\n\n";

echo "=== 2. UPLOADING APPLICATION CODE FILES ===\n";
$files = [
    'classes/Database.php',
    'classes/Reports.php',
    'classes/DataSorter.php',
    'reports.php',
    'invoice_edit.php'
];

$root = dirname(__DIR__);
foreach ($files as $file) {
    $localPath = "$root/$file";
    $remoteUrl = "$ftpBase/$file";
    echo "Uploading $file ... ";
    $uploadCmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$localPath\" \"$remoteUrl\" 2>&1";
    $output = [];
    exec($uploadCmd, $output, $status);
    if ($status === 0) {
        echo "OK\n";
    } else {
        echo "FAILED: " . implode("\n", $output) . "\n";
        exit(1);
    }
}

echo "\n=== ALL FILES SUCCESSFULLY DEPLOYED TO PRODUCTION ===\n";
