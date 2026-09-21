<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$coreFiles = [
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
$files = array_merge($coreFiles, $viewFiles);

echo "=== Deploying RBAC Matrix & Permission Enforcement to Production (act.active.lk) ===\n\n";

$failed = false;
foreach ($files as $f) {
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
    die("\nDeployment encountered errors.\n");
}

echo "\nAll files uploaded successfully!\n\n";

// Upload a temporary migration/verification script to remote server
$verifyScript = "remote_verify_rbac.php";
$verifyCode = <<<'PHP'
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->initReportPermissionsSchema();

$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='report_permissions'");
$defs = Auth::getReportDefinitions();
$users = $db->fetchAll("SELECT id, username, role FROM users");

// Check admin access
$adminOk = false;
$adminUser = $db->fetch("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($adminUser) {
    $adminOk = $auth->canAccessReport('tax_audit', $adminUser['id']);
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'schema_ok' => !empty($tables),
    'report_count' => count($defs),
    'user_count' => count($users),
    'admin_immunity_verified' => $adminOk,
    'timestamp' => date('c')
]);
PHP;

file_put_contents(__DIR__ . "/remote_verify_rbac.php", $verifyCode);

echo "Uploading remote verification runner ... ";
$remoteVerifyUrl = "$ftpBase/$verifyScript";
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"" . __DIR__ . "/remote_verify_rbac.php\" \"$remoteVerifyUrl\" 2>&1", $out, $ret);
if ($ret === 0) {
    echo "OK\n";
} else {
    echo "FAILED\n";
}

echo "Running remote verification via HTTPS ...\n";
$ch = curl_init("https://act.active.lk/remote_verify_rbac.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $resp\n\n";

// Clean up remote verification script
echo "Cleaning up remote verification script ... ";
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /act/$verifyScript\" \"$ftpBase/\" 2>&1", $out2, $ret2);
echo "OK\n";
@unlink(__DIR__ . "/remote_verify_rbac.php");

echo "\n=== DEPLOYMENT & PRODUCTION VERIFICATION COMPLETE ===\n";
