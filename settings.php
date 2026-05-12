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
$currency = $db->getSetting('currency_symbol', '$');
$companyName = $db->getSetting('company_name', '');
$dbSize = $db->getDatabaseSize();
$taxRules = $db->getTaxRules();
$salesReps = $db->getSalesReps();
$systemUsers = $db->fetchAll("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
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
