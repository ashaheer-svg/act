<?php
/**
 * Deploy Taxonomy Feature & Run Remote Database Migration on Production
 */
declare(strict_types=1);

$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

echo "=======================================================\n";
echo "=== 1. UPLOADING & RUNNING REMOTE DATABASE MIGRATION ==\n";
echo "=======================================================\n";

$migrationRunner = __DIR__ . '/remote_migrate_taxonomy.php';
$remoteRunnerUrl = "$ftpBase/remote_migrate_taxonomy.php";

$cmd = "curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$migrationRunner\" \"$remoteRunnerUrl\"";
exec($cmd, $out, $ret);

if ($ret !== 0) {
    echo "ERROR: Failed to upload remote migration runner: " . implode("\n", $out) . "\n";
    exit(1);
}
echo "Migration runner uploaded to production.\n";

echo "Triggering remote execution at https://active.lk/act/remote_migrate_taxonomy.php ...\n";
$ch = curl_init('https://active.lk/act/remote_migrate_taxonomy.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 45
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
unset($ch);

echo "HTTP Code: $httpCode\n";
if ($err) {
    echo "cURL Error: $err\n";
    exit(1);
}
echo "Production Migration Response:\n$resp\n\n";

$json = json_decode($resp, true);
if (!isset($json['status']) || $json['status'] !== 'success') {
    echo "ERROR: Migration did not report success.\n";
    exit(1);
}

echo "=======================================================\n";
echo "=== 2. UPLOADING MODIFIED APPLICATION CODE FILES ======\n";
echo "=======================================================\n";

$files = [
    'classes/Database.php',
    'classes/Reports.php',
    'classes/DataSorter.php',
    'product_mapping.php',
    'reports.php',
    'includes/sidebar.php'
];

foreach ($files as $f) {
    $local = str_replace('/', DIRECTORY_SEPARATOR, $f);
    $remote = "$ftpBase/$f";
    $sizeFormatted = round(filesize($local) / 1024, 1) . ' KB';
    echo "Uploading $f ($sizeFormatted) ... ";
    
    $start = microtime(true);
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$local\" \"$remote\"";
    exec($cmd, $out2, $ret2);
    $elapsed = round(microtime(true) - $start, 2);
    
    if ($ret2 === 0) {
        echo "[OK] ({$elapsed}s)\n";
    } else {
        echo "[FAILED - code $ret2]\n" . implode("\n", $out2) . "\n";
        exit(1);
    }
}

echo "\n=======================================================\n";
echo "=== ALL FILES AND MIGRATIONS SUCCESSFULLY DEPLOYED! ===\n";
echo "=======================================================\n";
