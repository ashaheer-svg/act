<?php
/**
 * Settings Module: General System Configuration
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// Handle POST actions for System Configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        $auth->requireAdmin();
        try {
            $vatRateInput = $_POST['vat_rate'] ?? '0.18';
            $currencyInput = $_POST['currency_symbol'] ?? 'LKR ';
            $companyNameInput = $_POST['company_name'] ?? '';

            $validation = Validator::validateVATRate($vatRateInput);
            if (!$validation['valid']) {
                $message = $validation['message'];
                $messageType = 'error';
            } else {
                $db->setSetting('vat_rate', $vatRateInput);
                $db->setSetting('currency_symbol', $currencyInput);
                $db->setSetting('company_name', $companyNameInput);

                $message = 'System settings updated successfully';
                $messageType = 'success';
                $db->logActivity($user['id'], 'SETTINGS_UPDATED', 'Settings updated');
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
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
}

// Fetch current values
$vatRate = $db->getSetting('vat_rate', '0.18');
$currency = $db->getSetting('currency_symbol', 'LKR ');
$companyName = $db->getSetting('company_name', '');
$dbSize = $db->getDatabaseSize();
?>

<!-- System Setup Content -->
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
                <label>Default VAT Rate (e.g., 0.18 for 18%)</label>
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
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Non-admin users (Accounts/Viewers) can only view reports up to this date. Useful for locking reports during profit entry or accounting audits.</p>
    
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

<div style="margin-top: 30px; background: white; padding: 25px; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
    <h3>System Status</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
        <div class="stat-small">
            <label style="font-size: 12px; color: var(--text-muted); display: block;">Database Size</label>
            <strong style="font-size: 18px; color: var(--text-main);"><?php echo $dbSize; ?> MB</strong>
        </div>
        <div class="stat-small">
            <label style="font-size: 12px; color: var(--text-muted); display: block;">Active User</label>
            <strong style="font-size: 18px; color: var(--text-main);"><?php echo htmlspecialchars($user['username']); ?> (<?php echo strtoupper($user['role']); ?>)</strong>
        </div>
    </div>
    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); font-size: 11px; color: var(--text-muted);">
        Build: Premium Dashboard Edition v2.1.0 • Isolated System Module
    </div>
</div>
<?php endif; ?>
