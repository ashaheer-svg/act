<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Validator.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Initialize settings
$db->initializeSettings();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'download_sync_config') {
        $auth->requireAccounts();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $syncUrl = "$protocol://$host$dir/api/sync.php";
        $configData = [
            'server_url' => $syncUrl,
            'api_key' => $db->getSetting('api_secret_key'),
            'last_sync_date' => $db->getSetting('last_qb_sync', ''),
            'qb_company_file' => '',
            'batch_size' => 250
        ];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="config.json"');
        echo json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'regenerate_api_key') {
        $auth->requireAdmin();
        $newKey = bin2hex(random_bytes(24));
        $db->setSetting('api_secret_key', $newKey);
        $message = 'QuickBooks API Key has been regenerated. Update your Windows sync app config.';
        $messageType = 'success';
        $db->logActivity($user['id'], 'API_KEY_REGENERATED', 'Regenerated QuickBooks sync API key');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            $message = 'New passwords do not match';
            $messageType = 'error';
        } else if (strlen($new) < 6) {
            $message = 'Password must be at least 6 characters';
            $messageType = 'error';
        } else {
            $result = $auth->changePassword($user['id'], $current, $new);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }

    if ($action === 'update_settings') {
        $auth->requireAdmin();
        try {
            $vatRate = $_POST['vat_rate'] ?? '0.18';
            $currency = $_POST['currency_symbol'] ?? 'LKR ';
            $companyName = $_POST['company_name'] ?? '';

            $validation = Validator::validateVATRate($vatRate);
            if (!$validation['valid']) {
                $message = $validation['message'];
                $messageType = 'error';
            } else {
                $db->setSetting('vat_rate', $vatRate);
                $db->setSetting('currency_symbol', $currency);
                $db->setSetting('company_name', $companyName);

                $message = 'System settings updated successfully';
                $messageType = 'success';
                $db->logActivity($user['id'], 'SETTINGS_UPDATED', 'Settings updated');
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'reset_database') {
        $auth->requireAdmin();
        try {
            $db->resetPaymentData();
            $message = 'Payment and settlement data has been reset. All payments and collection speed metrics have been cleared. Sales records remain intact.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'PAYMENT_RESET', 'Payment data reset performed');
        } catch (Exception $e) {
            $message = 'Reset Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'add_tax_rule') {
        try {
            $db->saveTaxRule([
                'tax_name' => $_POST['tax_name'] ?? 'VAT Rule',
                'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
                'effective_from' => !empty($_POST['effective_from']) ? $_POST['effective_from'] : null,
                'effective_to' => !empty($_POST['effective_to']) ? $_POST['effective_to'] : null,
                'invoice_range_start' => !empty($_POST['invoice_range_start']) ? trim($_POST['invoice_range_start']) : null,
                'invoice_range_end' => !empty($_POST['invoice_range_end']) ? trim($_POST['invoice_range_end']) : null,
                'is_inclusive_default' => isset($_POST['is_inclusive_default']) ? intval($_POST['is_inclusive_default']) : 1,
                'notes' => trim($_POST['notes'] ?? '')
            ]);
            $message = 'Tax rule saved successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'TAX_RULE_SAVED', "Saved tax rule: " . ($_POST['tax_name'] ?? ''));
        } catch (Exception $e) {
            $message = 'Error saving tax rule: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_tax_rule') {
        try {
            $ruleId = $_POST['rule_id'] ?? 0;
            $db->deleteTaxRule($ruleId);
            $message = 'Tax rule deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'TAX_RULE_DELETED', "Deleted tax rule ID: $ruleId");
        } catch (Exception $e) {
            $message = 'Error deleting tax rule: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'recalculate_historical_vat') {
        try {
            $recalculated = $db->recalculateHistoricalVat();
            $message = "Successfully recalculated VAT and inclusivity across $recalculated sales records!";
            $messageType = 'success';
            $db->logActivity($user['id'], 'VAT_RECALCULATED', "Recalculated VAT on $recalculated records");
        } catch (Exception $e) {
            $message = 'Recalculation error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'update_ai_settings') {
        $auth->requireAdmin();
        try {
            $provider = $_POST['ai_provider'] ?? 'gemini';
            $apiKeyInput = trim($_POST['gemini_api_key'] ?? '');
            $model = trim($_POST['ai_model'] ?? 'gemini-1.5-flash');
            $customEndpoint = trim($_POST['ai_custom_endpoint'] ?? '');

            $db->setSetting('ai_provider', $provider);
            if (!empty($apiKeyInput) && strpos($apiKeyInput, '••••') === false) {
                $db->setSetting('gemini_api_key', $apiKeyInput);
            }
            $db->setSetting('ai_model', $model);
            $db->setSetting('ai_custom_endpoint', $customEndpoint);

            $message = 'AI Entity Extractor settings updated successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'AI_SETTINGS_UPDATED', "Updated AI settings (Provider: $provider, Model: $model)");
        } catch (Exception $e) {
            $message = 'Error saving AI settings: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'sort_all_data') {
        $auth->requireAdmin();
        try {
            require_once __DIR__ . '/classes/DataSorter.php';
            $sorter = new DataSorter($db);
            $candidates = $db->fetchAll("SELECT DISTINCT invoice_number FROM sales WHERE total_amount > 0 ORDER BY invoice_date DESC");
            $processed = 0;
            $itemsTotal = 0;
            $hwTotal = 0;
            $subTotal = 0;
            foreach ($candidates as $c) {
                $sorted = $sorter->sortInvoice($c['invoice_number']);
                $res = $sorter->persistSortedData($sorted);
                $processed++;
                $itemsTotal += $res['items'];
                $hwTotal += $res['hardware_assets'];
                $subTotal += $res['subscriptions'];
            }
            $message = "Successfully sorted and normalized all {$processed} invoices! Extracted {$itemsTotal} clean catalog items, {$hwTotal} hardware assets (with serials & warranties), and {$subTotal} software/MA contracts.";
            $messageType = 'success';
            $db->logActivity($user['id'], 'ALL_DATA_SORTED', "Sorted {$processed} invoices into operational registries");
        } catch (Exception $e) {
            $message = 'Data Sorting Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    if ($action === 'update_limit') {
        try {
            $limitYear = $_POST['limit_year'] ?? date('Y');
            $limitMonth = $_POST['limit_month'] ?? date('m');
            
            $db->setSetting('limit_year', $limitYear);
            $db->setSetting('limit_month', $limitMonth);
            
            $message = 'Reporting period limit updated';
            $messageType = 'success';
            $db->logActivity($user['id'], 'LIMIT_UPDATED', "Limit set to $limitYear-$limitMonth");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'force_sync') {
        $syncResult = $db->syncSchema();
        if ($syncResult['success']) {
            $message = 'Database sync successful. ' . implode(' ', $syncResult['messages']);
            $messageType = 'success';
            if (empty($syncResult['messages'])) $message = 'Database is already up to date.';
        } else {
            $message = 'Database sync failed: ' . implode(' ', $syncResult['messages']);
            $messageType = 'error';
        }
    }

    if ($action === 'add_sales_rep') {
        try {
            $repCode = $_POST['rep_code'] ?? '';
            $repName = $_POST['rep_name'] ?? '';
            if (empty($repCode) || empty($repName)) {
                throw new Exception('Code and Name are required');
            }
            $db->addSalesRep($repCode, $repName);
            $message = 'Sales representative mapped successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'SALES_REP_ADDED', "Mapped $repCode to $repName");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_sales_rep') {
        try {
            $repId = $_POST['rep_id'] ?? 0;
            $db->deleteSalesRep($repId);
            $message = 'Sales representative mapping deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'SALES_REP_DELETED', "Deleted rep mapping ID: $repId");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'create_user') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'viewer';

        if (empty($username) || empty($password)) {
            $message = 'Username and password are required';
            $messageType = 'error';
        } else {
            try {
                if ($auth->register($username, $password, $role)) {
                    $message = "User '$username' created successfully";
                    $messageType = 'success';
                } else {
                    $message = "Username '$username' already exists";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    if ($action === 'delete_user') {
        $userId = $_POST['user_id'] ?? 0;
        if ($userId == $user['id']) {
            $message = 'You cannot delete your own account';
            $messageType = 'error';
        } else {
            $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
            $message = 'User deleted successfully';
            $messageType = 'success';
        }
    }
}

// Get current settings
$vatRate = $db->getSetting('vat_rate', '0.18');
$currency = $db->getSetting('currency_symbol', 'LKR ');
$companyName = $db->getSetting('company_name', '');
$dbSize = $db->getDatabaseSize();
$taxRules = $db->getTaxRules();
$aiProvider = $db->getSetting('ai_provider', 'gemini');
$geminiApiKey = $db->getSetting('gemini_api_key', '');
$aiModel = $db->getSetting('ai_model', 'gemini-3.6-flash');
$aiCustomEndpoint = $db->getSetting('ai_custom_endpoint', '');
$salesReps = $db->getSalesReps();
$systemUsers = $db->fetchAll("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$apiKey = $db->getSetting('api_secret_key');
$lastQbSync = $db->getSetting('last_qb_sync', '');
$lastQbSummary = $db->getSetting('last_qb_sync_summary', '');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$syncApiUrl = "$protocol://$host$dir/api/sync.php";

// Data Sorter & Registry Statistics
try {
    $totalInvoicesCount = (int)($db->fetch("SELECT COUNT(DISTINCT invoice_number) as c FROM sales WHERE total_amount > 0")['c'] ?? 0);
    $sortedInvoicesCount = (int)($db->fetch("SELECT COUNT(DISTINCT invoice_number) as c FROM invoice_items")['c'] ?? 0);
    $totalItemsCount = (int)($db->fetch("SELECT COUNT(*) as c FROM invoice_items")['c'] ?? 0);
    $totalHardwareAssets = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets")['c'] ?? 0);
    $hardwareWithSerials = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")['c'] ?? 0);
    $activeWarranties = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE warranty_status = 'ACTIVE'")['c'] ?? 0);
    $totalSubscriptions = (int)($db->fetch("SELECT COUNT(*) as c FROM software_subscriptions")['c'] ?? 0);
    $activeSubscriptions = (int)($db->fetch("SELECT COUNT(*) as c FROM software_subscriptions WHERE renewal_status = 'ACTIVE' OR renewal_status = 'UPCOMING'")['c'] ?? 0);
} catch (Exception $e) {
    $totalInvoicesCount = $sortedInvoicesCount = $totalItemsCount = $totalHardwareAssets = $hardwareWithSerials = $activeWarranties = $totalSubscriptions = $activeSubscriptions = 0;
}
$sortProgressPct = $totalInvoicesCount > 0 ? round(($sortedInvoicesCount / $totalInvoicesCount) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.2">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search settings...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($auth->isAdmin() || $auth->isAccounts()): ?>
                <!-- System Setup Tab -->
                <div id="system" class="tab-content active">
                    <div class="card">
                        <h2>General Configuration</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($companyName); ?>">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>VAT Rate (e.g., 0.18 for 18%)</label>
                                    <input type="number" name="vat_rate" class="form-control" value="<?php echo $vatRate; ?>" step="0.01" min="0" max="1">
                                </div>
                                <div class="form-group">
                                    <label>Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($currency); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>

                    <!-- QuickBooks Desktop Sync Card -->
                    <div class="card" style="margin-top: 30px; border-left: 4px solid var(--primary);">
                        <h2 style="display: flex; justify-content: space-between; align-items: center;">
                            QuickBooks Desktop Automated Sync
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #dcfce7; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">REST API Ready</span>
                        </h2>
                        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                            Connect the local Windows <strong>SalesBISync.exe</strong> utility to extract all invoice details (including full item descriptions and serial numbers) and customer payments in read-only mode.
                        </p>

                        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <div style="margin-bottom: 15px;">
                                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">API Endpoint URL</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="text" readonly class="form-control" value="<?php echo htmlspecialchars($syncApiUrl); ?>" id="syncApiUrlInput" style="background: white; font-family: monospace;">
                                    <button type="button" class="btn" style="background: #e2e8f0; color: var(--text-main);" onclick="navigator.clipboard.writeText(document.getElementById('syncApiUrlInput').value); alert('API URL copied to clipboard!');">Copy</button>
                                </div>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Secret API Key</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="text" readonly class="form-control" value="<?php echo htmlspecialchars($apiKey); ?>" id="syncApiKeyInput" style="background: white; font-family: monospace;">
                                    <button type="button" class="btn" style="background: #e2e8f0; color: var(--text-main);" onclick="navigator.clipboard.writeText(document.getElementById('syncApiKeyInput').value); alert('API Key copied to clipboard!');">Copy</button>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                                <div>
                                    <span style="font-size: 12px; color: var(--text-muted); display: block;">Last Sync Timestamp</span>
                                    <strong style="font-size: 14px;"><?php echo htmlspecialchars($lastQbSync ?: 'Never'); ?></strong>
                                </div>
                                <div>
                                    <span style="font-size: 12px; color: var(--text-muted); display: block;">Last Sync Status</span>
                                    <span style="font-size: 13px; color: var(--text-main);"><?php echo htmlspecialchars($lastQbSummary ?: 'No sync activity recorded yet'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="download_sync_config">
                                <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                                    <i class="icon-download"></i> Download config.json for Windows App
                                </button>
                            </form>

                            <?php if ($auth->isAdmin()): ?>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Regenerating this key will disconnect any sync clients using the old key. Continue?');">
                                <input type="hidden" name="action" value="regenerate_api_key">
                                <button type="submit" class="btn" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;">
                                    Regenerate API Key
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($auth->isAdmin()): ?>
                    <!-- Admin Specific System Options -->
                    <div class="card" style="margin-top: 30px;">
                        <h2 style="display: flex; justify-content: space-between; align-items: center;">
                            Reporting Visibility Limit
                            <span style="font-size: 11px; font-weight: 700; color: #1e40af; background: #dbeafe; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Control Period</span>
                        </h2>
                        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Non-admin users (Accounts/Viewers) can only view reports up to this date. Useful for locking reports during profit entry.</p>
                        
                        <form method="POST" style="display: flex; gap: 20px; align-items: flex-end;">
                            <input type="hidden" name="action" value="update_limit">
                            
                            <div class="form-group" style="flex: 1; margin: 0;">
                                <label>Limit Year</label>
                                <select name="limit_year" class="form-control">
                                    <?php 
                                    $currLimitY = $db->getSetting('limit_year', date('Y'));
                                    for($y=2023; $y<=2026; $y++): 
                                    ?>
                                    <option value="<?php echo $y; ?>" <?php echo $currLimitY == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group" style="flex: 1; margin: 0;">
                                <label>Limit Month</label>
                                <select name="limit_month" class="form-control">
                                    <?php 
                                    $currLimitM = $db->getSetting('limit_month', date('m'));
                                    for($m=1; $m<=12; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                                    ?>
                                    <option value="<?php echo $mStr; ?>" <?php echo $currLimitM == $mStr ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 200px;">Set Limit</button>
                        </form>
                    </div>

                    <!-- Multi-Period VAT Regimes & Invoice Sequences Card -->
                    <div class="card" style="margin-top: 30px; border-left: 4px solid var(--primary);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                    Multi-Period VAT Regimes & Invoice Sequences
                                    <span style="font-size: 11px; font-weight: 700; color: #166534; background: #dcfce7; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Deterministic Engine</span>
                                </h2>
                                <p style="color: var(--text-muted); font-size: 13px; margin-top: 5px; margin-bottom: 0;">
                                    Matches invoices by <strong>Invoice Number Sequence</strong> (highest precision), falling back to <strong>Date Ranges</strong>. Automatically calculates base amounts and VAT components according to historical tax laws.
                                </p>
                            </div>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Recalculate VAT across all historical sales records using these active sequence rules? This runs in < 1 second.');">
                                <input type="hidden" name="action" value="recalculate_historical_vat">
                                <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.2);">
                                    <span>⚡</span> Recalculate Historical VAT
                                </button>
                            </form>
                        </div>

                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 16px; margin-bottom: 25px; font-size: 13px; color: #1e40af;">
                            <strong>Inclusivity Rule:</strong> For any taxable regime (>0%), invoices without an explicit separate VAT line are automatically calculated as <strong>VAT-inclusive</strong> (<code>base = amount / (1 + rate)</code>, <code>vat = amount - base</code>).
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 30px;">
                            <div style="overflow-x: auto;">
                                <table class="tax-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                                            <th style="padding: 10px 8px;">Regime / Name</th>
                                            <th style="padding: 10px 8px;">Rate</th>
                                            <th style="padding: 10px 8px;">Invoice Sequence</th>
                                            <th style="padding: 10px 8px;">Effective Dates</th>
                                            <th style="padding: 10px 8px;">Default Mode</th>
                                            <th style="padding: 10px 8px; text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($taxRules)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No tax rules defined. Click 'Recalculate Historical VAT' to seed default sequences.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($taxRules as $rule): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: 10px 8px;">
                                                    <strong><?php echo htmlspecialchars($rule['tax_name']); ?></strong>
                                                    <?php if (!empty($rule['notes'])): ?>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($rule['notes']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 10px 8px;">
                                                    <?php if ($rule['tax_rate'] > 0): ?>
                                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; background: #dbeafe; color: #1e40af;">
                                                        <?php echo ($rule['tax_rate'] * 100); ?>%
                                                    </span>
                                                    <?php else: ?>
                                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; background: #f1f5f9; color: #475569;">
                                                        0% (Exempt)
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 10px 8px; font-family: monospace; font-size: 12px;">
                                                    <?php if (!empty($rule['invoice_range_start'])): ?>
                                                        <span style="background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                                            <?php echo htmlspecialchars($rule['invoice_range_start']); ?> → <?php echo htmlspecialchars($rule['invoice_range_end'] ?: 'Open'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted);">Any / Date-based</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 10px 8px; font-size: 12px; color: var(--text-muted);">
                                                    <?php 
                                                    if (!empty($rule['effective_from']) && !empty($rule['effective_to'])) {
                                                        echo date('Y-m-d', strtotime($rule['effective_from'])) . ' to ' . date('Y-m-d', strtotime($rule['effective_to']));
                                                    } elseif (!empty($rule['effective_from'])) {
                                                        echo 'From ' . date('Y-m-d', strtotime($rule['effective_from']));
                                                    } else {
                                                        echo 'All Dates';
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding: 10px 8px; font-size: 11px;">
                                                    <?php if ($rule['tax_rate'] == 0): ?>
                                                        <span style="color: #64748b;">Exempt</span>
                                                    <?php elseif (!empty($rule['is_inclusive_default'])): ?>
                                                        <span style="color: #059669; font-weight: 600;">VAT Inclusive</span>
                                                    <?php else: ?>
                                                        <span style="color: #d97706; font-weight: 600;">Pre-Tax Base</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 10px 8px; text-align: right;">
                                                    <form method="POST" onsubmit="return confirm('Delete tax rule \'<?php echo addslashes($rule['tax_name']); ?>\'?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_tax_rule">
                                                        <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                        <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 16px; padding: 4px;">🗑️</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); height: fit-content;">
                                <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 15px; color: var(--text-main);">Add Custom Sequence Rule</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_tax_rule">
                                    
                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label style="font-size: 12px;">Regime Description</label>
                                        <input type="text" name="tax_name" class="form-control" placeholder="e.g. 15% VAT Regime" required style="font-size: 13px;">
                                    </div>

                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label style="font-size: 12px;">Tax Rate (Decimal e.g. 0.18 for 18%)</label>
                                        <input type="number" step="0.001" name="tax_rate" class="form-control" value="0.180" required style="font-size: 13px;">
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                                        <div class="form-group" style="margin: 0;">
                                            <label style="font-size: 11px;">Invoice Start</label>
                                            <input type="text" name="invoice_range_start" class="form-control" placeholder="AS010021" style="font-size: 12px; font-family: monospace;">
                                        </div>
                                        <div class="form-group" style="margin: 0;">
                                            <label style="font-size: 11px;">Invoice End</label>
                                            <input type="text" name="invoice_range_end" class="form-control" placeholder="AS011260" style="font-size: 12px; font-family: monospace;">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                                        <div class="form-group" style="margin: 0;">
                                            <label style="font-size: 11px;">Date From (Opt)</label>
                                            <input type="date" name="effective_from" class="form-control" style="font-size: 12px;">
                                        </div>
                                        <div class="form-group" style="margin: 0;">
                                            <label style="font-size: 11px;">Date To (Opt)</label>
                                            <input type="date" name="effective_to" class="form-control" style="font-size: 12px;">
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label style="font-size: 12px;">Default Treatment</label>
                                        <select name="is_inclusive_default" class="form-control" style="font-size: 13px;">
                                            <option value="1">VAT-Inclusive (Amount contains VAT)</option>
                                            <option value="0">VAT-Exclusive (Pre-tax Base Amount)</option>
                                        </select>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label style="font-size: 12px;">Notes / Reference</label>
                                        <input type="text" name="notes" class="form-control" placeholder="Statutory gazette or reason" style="font-size: 12px;">
                                    </div>

                                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 13px;">Save Tax Rule</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- AI Entity Extractor & Subscription Intelligence Card -->
                    <div class="card" style="margin-top: 30px; border-left: 4px solid #8b5cf6;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                    AI Entity Extractor & Subscription Intelligence
                                    <span style="font-size: 11px; font-weight: 700; color: #6d28d9; background: #ede9fe; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Pluggable Architecture</span>
                                </h2>
                                <p style="color: var(--text-muted); font-size: 13px; margin-top: 5px; margin-bottom: 0;">
                                    Extracts hardware serial numbers, warranty terms, and software/SaaS recurring subscription periods from multi-line QuickBooks invoice text into normalized operational registries.
                                </p>
                            </div>
                            <div>
                                <?php if (!empty($geminiApiKey)): ?>
                                <span style="font-size: 12px; font-weight: 700; color: #166534; background: #dcfce7; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                                    <span>●</span> AI Key Configured
                                </span>
                                <?php else: ?>
                                <span style="font-size: 12px; font-weight: 700; color: #9a3412; background: #ffedd5; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                                    <span>○</span> API Key Required
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST" style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 22px; margin-top: 15px;">
                            <input type="hidden" name="action" value="update_ai_settings">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; font-size: 13px;">AI Engine Provider</label>
                                    <select name="ai_provider" class="form-control" style="font-size: 13px;">
                                        <option value="gemini" <?php echo $aiProvider === 'gemini' ? 'selected' : ''; ?>>Google Gemini API (Recommended - Fast & JSON Mode)</option>
                                        <option value="openai" <?php echo $aiProvider === 'openai' ? 'selected' : ''; ?>>OpenAI / DeepSeek (OpenAI-compatible API)</option>
                                        <option value="custom" <?php echo $aiProvider === 'custom' ? 'selected' : ''; ?>>Custom Endpoint (Local Ollama / vLLM)</option>
                                    </select>
                                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Default is Google Gemini for high-speed structured entity extraction.</small>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; font-size: 13px;">Model Identifier</label>
                                    <input type="text" name="ai_model" class="form-control" value="<?php echo htmlspecialchars($aiModel); ?>" placeholder="gemini-3.6-flash" style="font-size: 13px; font-family: monospace;">
                                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Recommended: <code>gemini-3.6-flash</code>, <code>gemini-2.5-pro</code>, <code>gpt-4o-mini</code>.</small>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: 600; font-size: 13px;">Google Gemini API Secret Key</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="password" name="gemini_api_key" id="geminiApiKeyInput" class="form-control" value="<?php echo !empty($geminiApiKey) ? '••••••••••••••••••••••••' : ''; ?>" placeholder="Paste your Gemini API key (AQ.Ab... / AIzaSy...)" style="font-size: 13px; font-family: monospace;">
                                    <button type="button" class="btn" style="background: white; border: 1px solid #cbd5e1; color: var(--text-main); font-size: 12px;" onclick="const el = document.getElementById('geminiApiKeyInput'); el.type = el.type === 'password' ? 'text' : 'password'; this.textContent = el.type === 'password' ? 'Show' : 'Hide';">Show</button>
                                </div>
                                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Get your key from <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color: #7c3aed; text-decoration: underline;">Google AI Studio</a>.</small>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: 600; font-size: 13px;">Custom / Local API Endpoint (Optional)</label>
                                <input type="url" name="ai_custom_endpoint" class="form-control" value="<?php echo htmlspecialchars($aiCustomEndpoint); ?>" placeholder="http://localhost:11434/v1 (Leave blank for official Google/OpenAI cloud)" style="font-size: 13px; font-family: monospace;">
                                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Only required if running local Ollama, vLLM, or self-hosted LLM endpoints.</small>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                                <button type="submit" class="btn btn-primary" style="background: #7c3aed; border-color: #6d28d9; padding: 10px 24px;">Save AI Configuration</button>
                            </div>
                        </form>
                    </div>

                    <!-- Data Sorting & Commercial Asset Normalization Card -->
                    <div class="card" style="margin-top: 30px; border-left: 4px solid #0284c7;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                    Data Sorting & Commercial Asset Normalization
                                    <span style="font-size: 11px; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Deterministic + AI Engine</span>
                                </h2>
                                <p style="color: var(--text-muted); font-size: 13px; margin-top: 5px; margin-bottom: 0;">
                                    Disaggregates multi-line QuickBooks raw invoice items into clean product lines, extracts discrete hardware serial numbers and warranty lifecycles, and normalizes Software and Maintenance Agreement (MA) recurring contracts.
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Sort and normalize all <?php echo number_format($totalInvoicesCount); ?> historical sales invoices into operational registries? This will process in just a few seconds.');">
                                    <input type="hidden" name="action" value="sort_all_data">
                                    <button type="submit" class="btn btn-primary" style="background: #0284c7; border-color: #0369a1; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(2,132,199,0.25);">
                                        <span>⚡</span> Sort & Normalize All Invoices
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Metric Tiles -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px;">
                                <span style="font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Invoices Processed</span>
                                <div style="font-size: 24px; font-weight: 800; color: #0c4a6e; margin-top: 4px;">
                                    <?php echo number_format($sortedInvoicesCount); ?> <span style="font-size: 14px; font-weight: 500; color: #64748b;">/ <?php echo number_format($totalInvoicesCount); ?></span>
                                </div>
                                <div style="width: 100%; background: #e2e8f0; height: 6px; border-radius: 3px; margin-top: 10px; overflow: hidden;">
                                    <div style="width: <?php echo min(100, $sortProgressPct); ?>%; background: #0284c7; height: 100%; border-radius: 3px;"></div>
                                </div>
                                <small style="color: #0369a1; font-size: 11px; font-weight: 600; margin-top: 6px; display: block;"><?php echo $sortProgressPct; ?>% Normalized</small>
                            </div>

                            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 16px;">
                                <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Catalog Line Items</span>
                                <div style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-top: 4px;">
                                    <?php echo number_format($totalItemsCount); ?>
                                </div>
                                <small style="color: var(--text-muted); font-size: 11px; margin-top: 6px; display: block;">Clean items (excludes zero-value serial notes & levies)</small>
                            </div>

                            <div style="background: #fdf4ff; border: 1px solid #f5d0fe; border-radius: 12px; padding: 16px;">
                                <span style="font-size: 11px; font-weight: 700; color: #86198f; text-transform: uppercase; letter-spacing: 0.5px;">Hardware Assets</span>
                                <div style="font-size: 24px; font-weight: 800; color: #701a75; margin-top: 4px;">
                                    <?php echo number_format($totalHardwareAssets); ?>
                                </div>
                                <div style="display: flex; gap: 8px; margin-top: 6px; font-size: 11px; color: #86198f;">
                                    <span><strong><?php echo number_format($hardwareWithSerials); ?></strong> with Serials</span>
                                    <span>•</span>
                                    <span><strong><?php echo number_format($activeWarranties); ?></strong> Active</span>
                                </div>
                            </div>

                            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 16px;">
                                <span style="font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px;">Software & MA Contracts</span>
                                <div style="font-size: 24px; font-weight: 800; color: #064e3b; margin-top: 4px;">
                                    <?php echo number_format($totalSubscriptions); ?>
                                </div>
                                <small style="color: #047857; font-size: 11px; font-weight: 600; margin-top: 6px; display: block;">
                                    <strong><?php echo number_format($activeSubscriptions); ?></strong> Active / Upcoming Renewals
                                </small>
                            </div>
                        </div>

                        <!-- Quick Action & CLI Reference -->
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <a href="reports.php?tab=warranty" class="btn" style="background: #f8fafc; border: 1px solid #cbd5e1; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="icon-shield"></i> Open Warranty Report
                                </a>
                                <a href="reports.php?tab=renewals" class="btn" style="background: #f8fafc; border: 1px solid #cbd5e1; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="icon-repeat"></i> Open Renewals Report
                                </a>
                            </div>

                            <div style="font-size: 12px; color: var(--text-muted); font-family: monospace; background: #f8fafc; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                                CLI: <code>php bin/sort_existing_data.php --help</code>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 30px; border: 2px dashed #fee2e2;">
                        <h2 style="color: var(--danger);">Testing & Maintenance</h2>
                        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                            <strong>Reset Payment Data:</strong> This will clear all records in the <code>payments</code> table and reset the <code>paid_date</code> and <code>days_to_pay</code> metrics in your sales records. 
                            <br><br>
                            <span style="color: var(--danger); font-weight: 700;">Note:</span> Your core sales invoices and customer profiles will NOT be affected.
                        </p>
                        <form method="POST" onsubmit="return confirm('RESET CONFIRMATION: This will clear ALL payment history and settlement metrics. Sales invoices will remain. Are you sure?');">
                            <input type="hidden" name="action" value="reset_database">
                            <button type="submit" class="btn btn-danger">Reset Payment & Settlement Data</button>
                        </form>

                        <form method="POST" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                            <input type="hidden" name="action" value="force_sync">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <p style="font-weight: 700; font-size: 14px;">Database Schema Repair</p>
                                    <p style="font-size: 12px; color: var(--text-muted);">Manually check and add missing columns/tables if you encounter SQL errors.</p>
                                </div>
                                <button type="submit" class="btn" style="background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color);">Force Sync Database</button>
                            </div>
                        </form>
                    </div>

                    <div style="margin-top: 30px; background: white; padding: 25px; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
                        <h3>System Status</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                            <div class="stat-small">
                                <label>Database Size</label>
                                <value><?php echo $dbSize; ?> MB</value>
                            </div>
                            <div class="stat-small">
                                <label>Active User</label>
                                <value><?php echo htmlspecialchars($user['username']); ?></value>
                            </div>
                        </div>
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); font-size: 11px; color: var(--text-muted);">
                            Build: Premium Dashboard Edition v1.2.0
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Access & Team Tab -->
                <div id="team" class="tab-content">
                    <div class="card">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
                            <div style="width: 40px; height: 40px; background: #fee2e2; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 20px;">🔒</div>
                            <div>
                                <h2 style="margin: 0;">Account Security</h2>
                                <p style="color: var(--text-muted); font-size: 13px; margin: 0;">Update your password and manage session security.</p>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group" style="max-width: 400px;">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group" style="max-width: 400px;">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" required placeholder="Min 6 characters">
                            </div>
                            <div class="form-group" style="max-width: 400px;">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div style="margin-top: 30px;">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    </div>

                    <div class="card" style="margin-top: 30px;">
                        <h2 style="display: flex; justify-content: space-between; align-items: center;">
                            Sales Rep Mapping
                            <span style="font-size: 11px; font-weight: 700; color: #7c3aed; background: #f5f3ff; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Team Management</span>
                        </h2>
                        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Map system Sales Rep codes to their actual names for easier reporting.</p>

                        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 40px;">
                            <div>
                                <table class="tax-table">
                                    <thead>
                                        <tr>
                                            <th>Rep Code</th>
                                            <th>Display Name</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($salesReps)): ?>
                                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No sales rep mappings found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($salesReps as $r): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($r['rep_code']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($r['rep_name']); ?></strong></td>
                                                <td style="text-align: right;">
                                                    <form method="POST" onsubmit="return confirm('Delete this mapping?');">
                                                        <input type="hidden" name="action" value="delete_sales_rep">
                                                        <input type="hidden" name="rep_id" value="<?php echo $r['id']; ?>">
                                                        <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 18px;">🗑️</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid var(--border-color);">
                                <h3 style="font-size: 16px; margin-bottom: 20px;">Add New Mapping</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_sales_rep">
                                    
                                    <div class="form-group">
                                        <label>Sales Rep Code (from ERP)</label>
                                        <input type="text" name="rep_code" class="form-control" placeholder="e.g. SR01" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Full Name / Display Name</label>
                                        <input type="text" name="rep_name" class="form-control" placeholder="e.g. John Doe" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary" style="width: 100%;">Save Mapping</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($auth->isAdmin()): ?>
                    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 30px; margin-top: 30px;">
                        <div class="card" style="height: fit-content;">
                            <h3>Add New User</h3>
                            <form method="POST" style="margin-top: 20px;">
                                <input type="hidden" name="action" value="create_user">
                                
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Role</label>
                                    <select name="role" class="form-control">
                                        <option value="viewer">Viewer (Read-only)</option>
                                        <option value="accounts">Accounts (Finance & CRM)</option>
                                        <option value="admin">Administrator (Full Access)</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
                            </form>
                        </div>

                        <div class="card">
                            <h2>System Users</h2>
                            <table class="table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 10px;">Username</th>
                                        <th style="text-align: left; padding: 10px;">Role</th>
                                        <th style="text-align: left; padding: 10px;">Created At</th>
                                        <th style="text-align: right; padding: 10px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($systemUsers as $u): ?>
                                    <tr>
                                        <td style="padding: 10px; border-bottom: 1px solid var(--border-color);"><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                            <span style="font-size: 11px; font-weight: 700; color: #7c3aed; background: #f5f3ff; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">
                                                <?php echo strtoupper($u['role']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 12px;">
                                            <?php echo date('Y-m-d', strtotime($u['created_at'])); ?>
                                        </td>
                                        <td style="padding: 10px; border-bottom: 1px solid var(--border-color); text-align: right;">
                                            <?php if ($u['id'] != $user['id']): ?>
                                            <form method="POST" onsubmit="return confirm('Delete this user account?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-danger-link">Remove</button>
                                            </form>
                                            <?php else: ?>
                                            <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">(You)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
    function showTab(tabId) {
        if (!tabId) tabId = 'system';
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.sidebar .sub-nav-item').forEach(el => el.classList.remove('active'));
        
        const targetTab = document.getElementById(tabId);
        if (targetTab) {
            targetTab.classList.add('active');
            
            // Activate sidebar item
            const sidebarItem = document.querySelector('.sidebar a[href="settings.php#' + tabId + '"]');
            if (sidebarItem) {
                sidebarItem.classList.add('active');
            }
            
            // Update hash without jumping
            if (window.location.hash.replace('#', '') !== tabId) {
                window.history.replaceState(null, null, '#' + tabId);
            }
        }
    }

    // Auto-select tab on load based on hash or last action
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.replace('#', '');
        <?php 
        $lastAction = $_POST['action'] ?? '';
        $jumpTo = '';
        if (strpos($lastAction, 'sales_rep') !== false || strpos($lastAction, 'user') !== false || strpos($lastAction, 'password') !== false) {
            $jumpTo = 'team';
        }
        if (strpos($lastAction, 'tax_rule') !== false || $lastAction === 'update_settings' || $lastAction === 'reset_database' || $lastAction === 'force_sync' || $lastAction === 'update_limit') {
            $jumpTo = 'system';
        }
        ?>
        
        const jumpTo = "<?php echo $jumpTo; ?>";
        if (jumpTo) {
            showTab(jumpTo);
        } else if (hash) {
            showTab(hash);
        } else {
            showTab('system');
        }
    });

    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.replace('#', '');
        if (hash) showTab(hash);
    });
    </script>
</body>
</html>
