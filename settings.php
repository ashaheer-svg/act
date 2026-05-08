<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Validator.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAccounts(); // Admin or Accounts

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Initialize settings
$db->initializeSettings();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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
            $db->resetSalesData();
            $message = 'Database reset successfully. All sales records and logs have been cleared.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'DATABASE_RESET', 'Full database reset performed');
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
}

// Get current settings
$vatRate = $db->getSetting('vat_rate', '0.18');
$currency = $db->getSetting('currency_symbol', '$');
$companyName = $db->getSetting('company_name', '');
$dbSize = $db->getDatabaseSize();
$taxRules = $db->getTaxRules();
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
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --secondary: #fb923c;
            --bg: #f1f5f9;
            --sidebar-bg: #ffffff;
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

        .user-profile {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--text-muted);
            border: 2px solid white;
            box-shadow: var(--shadow);
        }

        /* --- Layout --- */
        .container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
            padding: 30px 40px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* --- Sidebar --- */
        .sidebar {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 110px;
        }

        .sidebar h3 { font-size: 18px; font-weight: 700; margin-bottom: 25px; }

        .stat-small {
            margin-top: 20px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
        }

        .stat-small label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;}
        .stat-small value { font-size: 18px; font-weight: 800; color: var(--primary); display: block;}

        /* --- Main Content --- */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .tax-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .tax-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); padding-bottom: 10px;}
        .tax-table td { padding: 12px 0; font-size: 14px; border-bottom: 1px solid #f8fafc; }
        .tax-badge { background: #e0e7ff; color: var(--primary); padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;}

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .card h2 { font-size: 22px; font-weight: 800; margin-bottom: 25px; letter-spacing: -0.5px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text-main); }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            font-size: 14px;
            font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }

        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--error); color: white; }
        .btn-danger:hover { opacity: 0.9; }

        .message {
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            font-weight: 500;
            font-size: 14px;
        }
        .message.success { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
        .message.error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo-icon">A</div>
            Activity
        </div>
        
        <div class="top-nav">
            <a href="index.php" class="top-nav-item">Dashboard</a>
            <a href="reports.php" class="top-nav-item">Reporting</a>
            <?php if ($auth->isAdmin() || $auth->isAccounts()): ?>
            <a href="profit_entry.php" class="top-nav-item">Profit Entry</a>
            <a href="customers.php" class="top-nav-item">Customers</a>
            <a href="upload.php" class="top-nav-item">Upload</a>
            <a href="settings.php" class="top-nav-item active">Settings</a>
            <?php endif; ?>
            <?php if ($auth->isAdmin()): ?>
            <a href="users.php" class="top-nav-item">Users</a>
            <?php endif; ?>
        </div>

        <div class="header-actions">
            <div class="user-profile">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" style="background: none; border: none; font-size: 18px; cursor: pointer;">🚪</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3>System Info</h3>
            
            <div class="stat-small">
                <label>Database Size</label>
                <value><?php echo $dbSize; ?> MB</value>
            </div>

            <div class="stat-small">
                <label>Active Admin</label>
                <value><?php echo htmlspecialchars($user['username']); ?></value>
            </div>

            <div style="margin-top: 30px; font-size: 12px; color: var(--text-muted);">
                <p>Version 1.2.0</p>
                <p style="margin-top: 5px;">Build: Premium Dashboard Edition</p>
            </div>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

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

            <?php if ($auth->isAdmin()): ?>
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

            <div class="card" style="margin-top: 30px; border: 2px dashed #fee2e2;">
                <h2 style="color: var(--error);">Danger Zone</h2>
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">These actions are destructive and cannot be undone.</p>
                <form method="POST" onsubmit="return confirm('WARNING: This will delete ALL sales records and import logs. Are you absolutely sure?');">
                    <input type="hidden" name="action" value="reset_database">
                    <button type="submit" class="btn btn-danger">Full Database Reset</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
