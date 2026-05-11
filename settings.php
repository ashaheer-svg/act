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
        try {
            $vatRate = $_POST['vat_rate'] ?? '0.18';
            $currency = $_POST['currency_symbol'] ?? '$';
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
            $taxName = $_POST['tax_name'] ?? 'VAT';
            $taxRate = $_POST['tax_rate'] ?? '0.18';
            $effectiveFrom = $_POST['effective_from'] ?? date('Y-m-d');
            
            $db->addTaxRule($taxName, $taxRate, $effectiveFrom);
            $message = 'New tax rule added successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'TAX_RULE_ADDED', "Added $taxName rule: $taxRate from $effectiveFrom");
        } catch (Exception $e) {
            $message = 'Error adding tax rule: ' . $e->getMessage();
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
            $message = 'Sales rep mapping added/updated successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'REP_MAPPING_UPDATED', "Mapped $repCode to $repName");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_sales_rep') {
        try {
            $repCode = $_POST['rep_code'] ?? '';
            $db->deleteSalesRep($repCode);
            $message = 'Sales rep mapping deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'REP_MAPPING_DELETED', "Deleted mapping for $repCode");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'save_product_mapping') {
        try {
            $item = $_POST['item_description'] ?? '';
            $category = $_POST['product_category'] ?? '';
            if (empty($item) || empty($category)) {
                throw new Exception('Item and Category are required');
            }
            $db->saveProductMapping($item, $category);
            $message = "Product '$item' rationalized successfully. Historical records updated.";
            $messageType = 'success';
            $db->logActivity($user['id'], 'PRODUCT_MAPPED', "Mapped $item to $category");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'save_bulk_mappings') {
        try {
            $mappings = $_POST['mappings'] ?? [];
            $count = 0;
            foreach ($mappings as $encodedItem => $category) {
                $category = trim($category);
                if (!empty($category)) {
                    $item = base64_decode($encodedItem);
                    $db->saveProductMapping($item, $category);
                    $count++;
                }
            }
            if ($count > 0) {
                $message = "$count product mappings saved successfully. Historical records updated.";
                $messageType = 'success';
                $db->logActivity($user['id'], 'BULK_PRODUCT_MAPPED', "Mapped $count items");
            } else {
                $message = "No new categories were entered.";
                $messageType = 'info';
            }
        } catch (Exception $e) {
            $message = 'Bulk Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_product_mapping') {
        try {
            $mappingId = $_POST['mapping_id'] ?? 0;
            $db->deleteProductMapping($mappingId);
            $message = 'Product mapping rule deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'PRODUCT_MAPPING_DELETED', "Deleted mapping ID: $mappingId");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current settings
$vatRate = $db->getSetting('vat_rate', '0.18');
$currency = $db->getSetting('currency_symbol', '$');
$companyName = $db->getSetting('company_name', '');
$dbSize = $db->getDatabaseSize();
$taxRules = $db->getTaxRules();
$salesReps = $db->getSalesReps();

// Product Rationalization Data
$uncategorizedItems = $db->getUncategorizedItems();
$existingMappings = $db->getAllMappings();
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
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --secondary: #fb923c;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --error: #ef4444;
            --success: #10b981;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
        }

        /* --- Header --- */
        .header {
            background: white;
            padding: 0 40px;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .top-nav {
            display: flex;
            gap: 30px;
        }

        .top-nav-item {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            padding-bottom: 5px;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .top-nav-item:hover, .top-nav-item.active {
            color: var(--text-main);
            border-bottom-color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
    </style>
    <link rel="stylesheet" href="layout.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="mainSidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon"><i class="icon-bar-chart-2"></i></div>
                    <span>SYNC | ANALYTICS</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="icon-layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="icon-bar-chart-2"></i>
                    <span>Reporting</span>
                </a>
                <a href="settings.php#general" class="nav-item" id="sidebar-general">
                    <i class="icon-settings"></i>
                    <span>Settings</span>
                </a>
                <a href="settings.php#security" class="nav-item" id="sidebar-security">
                    <i class="icon-shield"></i>
                    <span>Security</span>
                </a>
                <a href="settings.php#team" class="nav-item" id="sidebar-team">
                    <i class="icon-users"></i>
                    <span>Team</span>
                </a>
                <a href="settings.php#rationalize" class="nav-item" id="sidebar-rationalize">
                    <i class="icon-git-branch"></i>
                    <span>Product Mapping</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <form method="POST" action="logout.php" style="margin: 0;">
                    <button type="submit" class="nav-item" style="background: none; border: none; width: 100%; cursor: pointer;">
                        <i class="icon-log-out"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <header class="top-header">
                <div class="header-left">
                    <button class="collapse-btn" onclick="toggleSidebar()">
                        <i class="icon-menu"></i>
                    </button>
                    <div class="search-container">
                        <i class="icon-search"></i>
                        <input type="text" class="search-input" placeholder="Search settings...">
                    </div>
                </div>
                <div class="header-right">
                    <button class="icon-btn">
                        <i class="icon-bell"></i>
                        <div class="notification-dot"></div>
                    </button>
                    <div class="user-dropdown">
                        <div class="user-trigger" onclick="toggleUserDropdown()">
                            <div class="user-profile" style="background: var(--sidebar-bg); border: 2px solid var(--border-color);">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div class="user-info-brief">
                                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                            </div>
                            <i class="icon-chevron-down" style="font-size: 12px; color: var(--text-muted); margin-left: 4px;"></i>
                        </div>
                        <div class="dropdown-menu" id="userDropdown">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <span><?php echo ucfirst($user['role']); ?> Management Account</span>
                            </div>
                            <a href="settings.php#security" class="dropdown-item"><i class="icon-lock"></i> Change Password</a>
                            <?php if ($auth->isAdmin()): ?>
                            <a href="users.php" class="dropdown-item"><i class="icon-users"></i> Manage Users</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="logout.php" style="margin: 0;">
                                <button type="submit" class="dropdown-item logout-link"><i class="icon-log-out"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-body">
                <div class="settings-nav" style="margin-bottom: 25px; border-radius: 12px; background: white; padding: 15px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">

                <div class="settings-nav-links">
                    <?php if ($auth->isAdmin() || $auth->isAccounts()): ?>
                    <button class="tab-btn active" onclick="showTab('general')"><i class="icon-settings"></i> General</button>
                    <button class="tab-btn" onclick="showTab('team')"><i class="icon-users"></i> Sales Team</button>
                    <button class="tab-btn" onclick="showTab('rationalize')"><i class="icon-tag"></i> Product Mapping</button>
                    <?php if ($auth->isAdmin()): ?>
                    <button class="tab-btn" onclick="showTab('tax')"><i class="icon-landmark"></i> Tax & History</button>
                    <?php endif; ?>
                    <div style="width: 1px; height: 24px; background: var(--border); margin: 0 10px;"></div>
                    <a href="profit_entry.php" class="tab-btn"><i class="icon-dollar-sign"></i> Profit Entry</a>
                    <a href="customers.php" class="tab-btn"><i class="icon-building-2"></i> Customers</a>
                    <a href="upload.php" class="tab-btn"><i class="icon-folder-up"></i> Data Upload</a>
                    <?php if ($auth->isAdmin()): ?>
                    <a href="users.php" class="tab-btn"><i class="icon-user"></i> User Mgmt</a>
                    <?php endif; ?>
                    <div style="width: 1px; height: 24px; background: var(--border); margin: 0 10px;"></div>
                    <?php endif; ?>
                    <button class="tab-btn" onclick="showTab('security')"><i class="icon-shield-check"></i> Security</button>
                </div>
                <?php if ($auth->isAdmin()): ?>
                <button class="tab-btn" onclick="showTab('advanced')" style="color: var(--error);"><i class="icon-triangle-alert"></i> Advanced</button>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <?php if ($auth->isAdmin() || $auth->isAccounts()): ?>
            <div id="general" class="tab-content active">
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
            </div>

            <div id="team" class="tab-content">
                <div class="card">
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
                                    <?php if (empty($reps)): ?>
                                    <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No sales rep mappings found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($reps as $r): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($r['rep_code']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($r['rep_name']); ?></strong></td>
                                            <td style="text-align: right;">
                                                <form method="POST" onsubmit="return confirm('Delete this mapping?');">
                                                    <input type="hidden" name="action" value="delete_sales_rep">
                                                    <input type="hidden" name="rep_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" style="background: none; border: none; color: var(--error); cursor: pointer; font-size: 18px;">🗑️</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid var(--border);">
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
            </div>

            <?php if ($auth->isAdmin()): ?>
            <div id="tax" class="tab-content">
                <div class="card">
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

                <div class="card" style="margin-top: 30px;">
                    <h2 style="display: flex; justify-content: space-between; align-items: center;">
                        Tax History & Future Rules
                        <span style="font-size: 11px; font-weight: 700; color: var(--success); background: #dcfce7; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Compliance Mode Active</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Define VAT changes based on effective dates. Invoices will automatically use the rate active on their transaction date.</p>

                    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 40px;">
                        <div>
                            <table class="tax-table">
                                <thead>
                                    <tr>
                                        <th>Tax Name</th>
                                        <th>Rate</th>
                                        <th>Effective From</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($taxRules)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No specific tax rules defined. Using global fallback.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($taxRules as $rule): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($rule['tax_name']); ?></strong></td>
                                            <td><span class="tax-badge"><?php echo ($rule['tax_rate'] * 100); ?>%</span></td>
                                            <td><?php echo date('M d, Y', strtotime($rule['effective_from'])); ?></td>
                                            <td style="text-align: right;">
                                                <form method="POST" onsubmit="return confirm('Delete this tax rule?');">
                                                    <input type="hidden" name="action" value="delete_tax_rule">
                                                    <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                    <button type="submit" style="background: none; border: none; color: var(--error); cursor: pointer; font-size: 18px;">🗑️</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid var(--border);">
                            <h3 style="font-size: 16px; margin-bottom: 20px;">Add New Tax Rule</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_tax_rule">
                                
                                <div class="form-group">
                                    <label>Tax Description</label>
                                    <input type="text" name="tax_name" class="form-control" value="VAT" required>
                                </div>

                                <div class="form-group">
                                    <label>Tax Rate (as decimal, e.g. 0.18)</label>
                                    <input type="number" step="0.001" name="tax_rate" class="form-control" value="0.180" required>
                                    <small style="color: var(--text-muted); font-size: 11px;">0.15 = 15%, 0.18 = 18%</small>
                                </div>

                                <div class="form-group">
                                    <label>Effective From Date</label>
                                    <input type="date" name="effective_from" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    <small style="color: var(--text-muted); font-size: 11px;">This rate applies to all invoices on or after this date.</small>
                                </div>

                                <button type="submit" class="btn btn-primary" style="width: 100%;">Add Rule</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div id="advanced" class="tab-content">
                <div class="card" style="border: 2px dashed #fee2e2;">
                    <h2 style="color: var(--error);">Testing & Maintenance</h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                        <strong>Reset Payment Data:</strong> This will clear all records in the <code>payments</code> table and reset the <code>paid_date</code> and <code>days_to_pay</code> metrics in your sales records. 
                        <br><br>
                        <span style="color: var(--error); font-weight: 700;">Note:</span> Your core sales invoices and customer profiles will NOT be affected.
                    </p>
                    <form method="POST" onsubmit="return confirm('RESET CONFIRMATION: This will clear ALL payment history and settlement metrics. Sales invoices will remain. Are you sure?');">
                        <input type="hidden" name="action" value="reset_database">
                        <button type="submit" class="btn btn-danger" style="background: #ef4444; border: none; padding: 12px 25px; border-radius: 10px; font-weight: 700; color: white; cursor: pointer;">Reset Payment & Settlement Data</button>
                    </form>

                    <form method="POST" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                        <input type="hidden" name="action" value="force_sync">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="font-weight: 700; font-size: 14px;">Database Schema Repair</p>
                                <p style="font-size: 12px; color: var(--text-muted);">Manually check and add missing columns/tables if you encounter SQL errors.</p>
                            </div>
                            <button type="submit" class="btn" style="background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border);">Force Sync Database</button>
                        </div>
                    </form>
                </div>
                <div style="margin-top: 30px; background: white; padding: 25px; border-radius: var(--radius-lg); box-shadow: var(--shadow);">
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
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted);">
                        Build: Premium Dashboard Edition v1.2.0
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div id="rationalize" class="tab-content">
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2>Product Category Rationalization</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Map uncategorized items to proper categories. Rules will apply to historical and future data.</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 40px;">
                        <div>
                            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--primary);">Items Missing Category</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_bulk_mappings">
                                <div style="max-height: 600px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px;">
                                    <table class="tax-table" style="margin-top: 0;">
                                        <thead style="position: sticky; top: 0; z-index: 10; background: white;">
                                            <tr>
                                                <th>Uncategorized Item Description</th>
                                                <th style="text-align: right;">Volume</th>
                                                <th style="width: 250px;">Assign Category</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($uncategorizedItems)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">Great! All your items are categorized.</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($uncategorizedItems as $item): ?>
                                                <tr>
                                                    <td style="font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($item['item_description']); ?></td>
                                                    <td style="text-align: right; color: var(--text-muted);"><?php echo $item['occurrence_count']; ?></td>
                                                    <td>
                                                        <input type="text" name="mappings[<?php echo base64_encode($item['item_description']); ?>]" class="form-control" placeholder="e.g. HDD:Internal" style="padding: 6px 10px; font-size: 12px;">
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($uncategorizedItems)): ?>
                                <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                                    <button type="submit" class="btn btn-primary">Save All Mappings</button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div>
                            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--secondary);">Existing Mapping Rules</h3>
                            <div style="max-height: 600px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; background: #fcfcfc;">
                                <table class="tax-table" style="margin-top: 0;">
                                    <thead style="position: sticky; top: 0; z-index: 10; background: white;">
                                        <tr>
                                            <th>Item</th>
                                            <th>Category</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($existingMappings)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No mapping rules defined yet.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($existingMappings as $rule): ?>
                                            <tr>
                                                <td style="font-size: 11px;"><?php echo htmlspecialchars($rule['item_description']); ?></td>
                                                <td style="font-size: 11px; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($rule['product_category']); ?></td>
                                                <td style="text-align: right;">
                                                    <form method="POST" onsubmit="return confirm('Delete this rule?');">
                                                        <input type="hidden" name="action" value="delete_product_mapping">
                                                        <input type="hidden" name="mapping_id" value="<?php echo $rule['id']; ?>">
                                                        <button type="submit" style="background: none; border: none; color: var(--error); cursor: pointer; font-size: 14px;">🗑️</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Security Tab -->
            <div id="security" class="tab-content">
                <div class="card" style="max-width: 600px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
                        <div style="width: 40px; height: 40px; background: #fee2e2; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 20px;">🔒</div>
                        <div>
                            <h2 style="margin: 0;">Account Security</h2>
                            <p style="color: var(--text-muted); font-size: 13px; margin: 0;">Update your password and manage session security.</p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" required placeholder="Min 6 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.sidebar .nav-item').forEach(el => el.classList.remove('active'));
        
        const targetTab = document.getElementById(tabId);
        if (targetTab) {
            targetTab.classList.add('active');
            // Find and activate the correct button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const onclick = btn.getAttribute('onclick');
                if (onclick && onclick.includes("'" + tabId + "'")) {
                    btn.classList.add('active');
                }
            });

            // Activate sidebar item if it exists
            const sidebarItem = document.getElementById('sidebar-' + tabId);
            if (sidebarItem) {
                sidebarItem.classList.add('active');
            } else if (tabId === 'general') {
                document.getElementById('sidebar-general').classList.add('active');
            }

            window.location.hash = tabId;
        }
    }

    function toggleSidebar() {
        document.getElementById('mainSidebar').classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', document.getElementById('mainSidebar').classList.contains('collapsed'));
    }

    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.getElementById('mainSidebar').classList.add('collapsed');
    }

    // Auto-select tab on load based on hash or last action
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.replace('#', '');
        <?php 
        $lastAction = $_POST['action'] ?? '';
        $jumpTo = '';
        if (strpos($lastAction, 'sales_rep') !== false) $jumpTo = 'team';
        if (strpos($lastAction, 'product_mapping') !== false || $lastAction === 'save_bulk_mappings') $jumpTo = 'rationalize';
        if ($lastAction === 'update_limit' || strpos($lastAction, 'tax_rule') !== false) $jumpTo = 'tax';
        if ($lastAction === 'reset_database' || $lastAction === 'force_sync') $jumpTo = 'advanced';
        ?>
        
        const jumpTo = "<?php echo $jumpTo; ?>";
        if (jumpTo) {
            showTab(jumpTo);
        } else if (hash) {
            showTab(hash);
        } else {
            showTab('general');
        }
    });

    function toggleUserDropdown() {
        document.getElementById('userDropdown').classList.toggle('active');
    }

    window.onclick = function(event) {
        if (!event.target.closest('.user-dropdown')) {
            const dropdowns = document.getElementsByClassName("dropdown-menu");
            for (let i = 0; i < dropdowns.length; i++) {
                dropdowns[i].classList.remove('active');
            }
        }
    }
    </script>
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->
</body>
</html>
