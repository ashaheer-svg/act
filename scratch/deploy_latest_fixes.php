<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$filesToDeploy = [
    'classes/AiExtractor.php',
    'classes/Reports.php',
    'classes/Auth.php',
    'classes/Database.php',
    'classes/DataImporter.php',
    'classes/DataSorter.php',
    'api/sync.php',
    'includes/access_restricted_card.php',
    'includes/sidebar.php',
    'reports.php',
    'settings.php',
    'invoice_edit.php',
    'profit_entry.php',
    'upload.php',
    'customers.php',
    'customer_report.php',
    'explorer.php',
    'vat_review.php',
    'import_legacy_qb.php',
    'product_mapping.php',
    'sync_app.php',
    'index.php'
];

$viewFiles = glob('views/reports/*.php');
$allFiles = array_unique(array_merge($filesToDeploy, $viewFiles));

echo "Deploying " . count($allFiles) . " files to production (act.active.lk)...\n\n";

$failed = false;
foreach ($allFiles as $f) {
    if (!file_exists($f)) {
        echo "Skipping $f (not found)\n";
        continue;
    }
    $remoteUrl = "$ftpBase/$f";
    echo "Uploading $f ... ";
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\" 2>&1";
    $output = [];
    $ret = 0;
    exec($cmd, $output, $ret);
    if ($ret === 0) {
        echo "OK\n";
    } else {
        echo "FAILED (code $ret): " . implode("\n", $output) . "\n";
        $failed = true;
    }
}

if ($failed) {
    echo "\nSome files failed to deploy.\n";
    exit(1);
} else {
    echo "\nAll files deployed successfully!\n";
}

// Perform remote verification
$verifyScript = "remote_verify_status.php";
$verifyCode = <<<'PHP'
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);

$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='report_permissions'");
$defs = Auth::getReportDefinitions();

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'schema_ok' => !empty($tables),
    'report_count' => count($defs),
    'views_count' => count(glob(__DIR__ . '/views/reports/*.php')),
    'timestamp' => date('c')
]);
PHP;

file_put_contents(__DIR__ . "/remote_verify_status.php", $verifyCode);
$remoteVerifyUrl = "$ftpBase/$verifyScript";
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"" . __DIR__ . "/remote_verify_status.php\" \"$remoteVerifyUrl\" 2>&1");

$ch = curl_init("https://act.active.lk/remote_verify_status.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "Remote Verification HTTP: $httpCode\n";
echo "Response: $resp\n";

// Clean up
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /act/$verifyScript\" \"$ftpBase/\" 2>&1");
@unlink(__DIR__ . "/remote_verify_status.php");
echo "Done.\n";
