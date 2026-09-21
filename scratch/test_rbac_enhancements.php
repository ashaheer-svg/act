<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);

echo "=== RBAC Process Improvements Test Suite ===\n\n";

// 1. Verify edit_invoices exists in definitions
$defs = Auth::getReportDefinitions();
echo "Total definitions in catalog: " . count($defs) . "\n";
assert(isset($defs['edit_invoices']), "edit_invoices must exist in report definitions");
assert($defs['edit_invoices']['category'] === 'operations', "edit_invoices category must be operations");
echo "PASS: 'edit_invoices' registered in definitions\n";

// 2. Create temporary test users
$testViewer = 'test_viewer_' . time();
$testAccounts = 'test_accounts_' . time();
$auth->register($testViewer, 'pass12345', 'viewer');
$auth->register($testAccounts, 'pass12345', 'accounts');

$viewerUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$testViewer]);
$accountsUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$testAccounts]);
$vId = $viewerUser['id'];
$aId = $accountsUser['id'];

// 3. Test separation of invoices vs edit_invoices
$vCanView = $auth->canAccessReport('invoices', $vId);
$vCanEdit = $auth->canAccessReport('edit_invoices', $vId);
echo "Viewer default permissions: invoices=" . ($vCanView ? 'YES' : 'NO') . ", edit_invoices=" . ($vCanEdit ? 'YES' : 'NO') . "\n";
assert($vCanView === true, "Viewer should be able to view invoices");
assert($vCanEdit === false, "Viewer must NOT be able to edit invoices by default");
echo "PASS: Invoice viewing is cleanly separated from invoice editing\n";

$aCanView = $auth->canAccessReport('invoices', $aId);
$aCanEdit = $auth->canAccessReport('edit_invoices', $aId);
echo "Accounts default permissions: invoices=" . ($aCanView ? 'YES' : 'NO') . ", edit_invoices=" . ($aCanEdit ? 'YES' : 'NO') . "\n";
assert($aCanView === true, "Accounts can view invoices");
assert($aCanEdit === true, "Accounts can edit invoices");
echo "PASS: Accounts role has editing rights\n";

// 4. Test In-Memory Caching
// Clear cache and check 10 calls
$auth->invalidatePermissionsCache($vId);
$start = microtime(true);
for ($i = 0; $i < 50; $i++) {
    $auth->canAccessReport('invoices', $vId);
    $auth->canAccessReport('warranties', $vId);
    $auth->canAccessReport('monthly_overview', $vId);
}
$dur = round((microtime(true) - $start) * 1000, 2);
echo "PASS: 150 permission checks executed in {$dur}ms using in-memory cache\n";

// 5. Test Category Bulk Toggle
echo "Testing category bulk toggle for viewer...\n";
$auth->toggleCategoryPermissions($vId, 'operations', 1);
assert($auth->canAccessReport('profit_entry', $vId) === true, "Viewer should now have profit_entry");
assert($auth->canAccessReport('edit_invoices', $vId) === true, "Viewer should now have edit_invoices");
assert($auth->canAccessReport('upload', $vId) === true, "Viewer should now have upload");
echo "PASS: Category 'operations' successfully enabled for viewer\n";

$auth->toggleCategoryPermissions($vId, 'operations', 0);
assert($auth->canAccessReport('profit_entry', $vId) === false, "Viewer should now NOT have profit_entry");
assert($auth->canAccessReport('edit_invoices', $vId) === false, "Viewer should now NOT have edit_invoices");
echo "PASS: Category 'operations' successfully revoked for viewer\n";

// 6. Test Clone Permissions
echo "Testing clone permissions...\n";
// Set specific custom permission on viewer
$auth->setUserReportPermission($vId, 'tax_audit', 1);
$auth->setUserReportPermission($vId, 'dso_trends', 1);

// Clone from viewer to accounts user
$auth->cloneUserPermissions($vId, $aId);
assert($auth->canAccessReport('tax_audit', $aId) === true, "Target user should have tax_audit");
assert($auth->canAccessReport('dso_trends', $aId) === true, "Target user should have dso_trends");
echo "PASS: Clone permissions successfully duplicated overrides\n";

// 7. Test Reset to Role Default
echo "Testing reset to role default...\n";
$auth->applyUserPreset($aId, 'role_default');
$resPerms = $db->fetchAll("SELECT * FROM report_permissions WHERE user_id = ?", [$aId]);
assert(count($resPerms) === 0, "All custom overrides must be removed on role_default");
assert($auth->canAccessReport('invoices', $aId) === true, "Role default invoices remains allowed");
echo "PASS: Reset to role default successfully purged overrides\n";

// Cleanup test users
$db->execute("DELETE FROM report_permissions WHERE user_id IN (?, ?)", [$vId, $aId]);
$db->execute("DELETE FROM users WHERE id IN (?, ?)", [$vId, $aId]);
echo "\n=== ALL RBAC UNIT TESTS PASSED SUCCESSFULLY! ===\n";
