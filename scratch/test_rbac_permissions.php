<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);

echo "=== RBAC Report Permissions Test Suite ===\n\n";

// Test 1: Schema Initialization
echo "[Test 1] Initializing schema...\n";
$auth->initReportPermissionsSchema();
$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='report_permissions'");
if (empty($tables)) {
    die("FAILED: report_permissions table was not created.\n");
}
echo "✓ report_permissions table verified.\n\n";

// Test 2: Canonical Definitions Catalog
echo "[Test 2] Verifying Report Definitions Catalog...\n";
$defs = Auth::getReportDefinitions();
$count = count($defs);
echo "Catalog has $count report definitions.\n";
if ($count !== 32) {
    die("FAILED: Expected exactly 32 definitions, found $count.\n");
}
$categories = [];
foreach ($defs as $k => $d) {
    $categories[$d['category']] = ($categories[$d['category']] ?? 0) + 1;
}
echo "Category counts: " . json_encode($categories) . "\n";
if (($categories['core'] ?? 0) !== 6 || ($categories['analytics'] ?? 0) !== 11 || ($categories['operations'] ?? 0) !== 6 || ($categories['archived'] ?? 0) !== 9) {
    die("FAILED: Category module count mismatch.\n");
}
echo "✓ All 32 reports correctly registered and categorized.\n\n";

// Test 3: Admin User Access Immunity
echo "[Test 3] Verifying Admin immunity...\n";
$adminUser = $db->fetch("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if (!$adminUser) {
    die("FAILED: No admin user in database.\n");
}
$adminId = $adminUser['id'];
$adminPerms = $auth->getUserReportPermissions($adminId);
foreach ($defs as $k => $d) {
    if (!$auth->canAccessReport($k, $adminId)) {
        die("FAILED: Admin was denied access to '$k'.\n");
    }
    if (empty($adminPerms[$k])) {
        die("FAILED: Admin permission map showed false for '$k'.\n");
    }
}
echo "✓ Admin has 100% unrestricted access across all 32 reports.\n\n";

// Test 4: Dynamic Permissions & Role Presets on Test User
echo "[Test 4] Testing custom non-admin user with granular permissions...\n";
// Create or fetch test user
$testUsername = 'rbac_test_' . time();
$db->execute("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", [
    $testUsername,
    password_hash('password123', PASSWORD_BCRYPT),
    'viewer'
]);
$testUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$testUsername]);
$testId = $testUser['id'];

// Initial state for 'viewer': should default according to catalog
$initialPerms = $auth->getUserReportPermissions($testId);
$canInvoicesInit = $auth->canAccessReport('invoices', $testId);
$canProfitInit = $auth->canAccessReport('profit_entry', $testId);
echo "Initial 'viewer' perms: invoices=" . ($canInvoicesInit ? 'YES' : 'NO') . ", profit_entry=" . ($canProfitInit ? 'YES' : 'NO') . "\n";
if (!$canInvoicesInit || $canProfitInit) {
    die("FAILED: Viewer default roles fallback incorrect.\n");
}

// Grant explicit permission for profit_entry
echo "Granting explicit permission for 'profit_entry'...\n";
$auth->setUserReportPermission($testId, 'profit_entry', 1);
if (!$auth->canAccessReport('profit_entry', $testId)) {
    die("FAILED: Granted permission was not respected.\n");
}
echo "✓ Explicit grant verified.\n";

// Revoke explicit permission for 'invoices'
echo "Revoking permission for 'invoices'...\n";
$auth->setUserReportPermission($testId, 'invoices', 0);
if ($auth->canAccessReport('invoices', $testId)) {
    die("FAILED: Revoked permission was still allowed.\n");
}
echo "✓ Explicit revocation verified.\n";

// Test Preset 'finance'
echo "Testing 'finance' preset...\n";
$auth->applyUserPreset($testId, 'finance');
$finPerms = $auth->getUserReportPermissions($testId);
if (!$finPerms['profit_entry'] || !$finPerms['tax_audit'] || !$finPerms['vat_review'] || $finPerms['warranties']) {
    die("FAILED: Finance preset mismatch.\n");
}
echo "✓ Finance preset correctly applied.\n";

// Test Preset 'none'
echo "Testing 'none' preset...\n";
$auth->applyUserPreset($testId, 'none');
$nonePerms = $auth->getUserReportPermissions($testId);
foreach ($nonePerms as $k => $allowed) {
    if ($allowed) die("FAILED: Report '$k' was allowed under 'none' preset.\n");
}
echo "✓ None preset correctly locked all 32 reports.\n";

// Test Batch Permission Setting
echo "Testing 'setUserReportPermissionsBatch'...\n";
$auth->setUserReportPermissionsBatch($testId, ['invoices', 'vat_review', 'contracts']);
$batchPerms = $auth->getUserReportPermissions($testId);
if (!$batchPerms['invoices'] || !$batchPerms['vat_review'] || !$batchPerms['contracts'] || $batchPerms['tax_audit']) {
    die("FAILED: setUserReportPermissionsBatch mismatch.\n");
}
echo "✓ Batch permissions correctly applied.\n\n";

// Clean up test user
$db->execute("DELETE FROM report_permissions WHERE user_id = ?", [$testId]);
$db->execute("DELETE FROM users WHERE id = ?", [$testId]);
echo "✓ Test user cleaned up.\n\n";

echo "=== ALL RBAC TESTS PASSED SUCCESSFULLY! ===\n";
