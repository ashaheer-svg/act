<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Validator.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAdmin();

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Initialize settings if not already done
$db->initializeSettings();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        try {
            $vatRate = $_POST['vat_rate'] ?? '0.18';
            $currency = $_POST['currency_symbol'] ?? '$';
            $companyName = $_POST['company_name'] ?? '';

            // Validate VAT rate
            $validation = Validator::validateVATRate($vatRate);
            if (!$validation['valid']) {
                $message = $validation['message'];
                $messageType = 'error';
            } else {
                $db->setSetting('vat_rate', $vatRate);
                $db->setSetting('currency_symbol', $currency);
                $db->setSetting('company_name', $companyName);

                $message = 'Settings updated successfully';
                $messageType = 'success';

                $db->logActivity($user['id'], 'SETTINGS_UPDATED', 'Settings updated: VAT=' . $vatRate . ', Currency=' . $currency);
            }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
            overflow-y: auto;
        }

        .nav-item {
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 5px;
            text-decoration: none;
            color: #bbb;
            display: block;
        }

        .nav-item:hover, .nav-item.active {
            background: #667eea;
            color: white;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            max-width: 400px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }

        .btn:hover {
            background: #764ba2;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #4caf50;
        }

        .message.error {
            background: #ffebee;
            color: #c33;
            border-color: #f44336;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }

        .stat {
            display: inline-block;
            background: #f8f9fa;
            padding: 15px 25px;
            border-radius: 5px;
            margin: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 5px;
        }

        .logout-btn {
            background: rgba(255,255,255,0.3);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Settings</h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3 style="margin-bottom: 20px; font-size: 14px;">Admin Panel</h3>
            <a href="index.php" class="nav-item">📊 Dashboard</a>
            <a href="upload.php" class="nav-item">📤 Upload Data</a>
            <a href="users.php" class="nav-item">👥 Manage Users</a>
            <a href="audit_log.php" class="nav-item">📋 Audit Log</a>
            <a href="backup.php" class="nav-item">💾 Backup</a>
            <a href="search.php" class="nav-item">🔍 Search</a>
            <a href="settings.php" class="nav-item active">⚙️ Settings</a>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>System Settings</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($companyName); ?>">
                    </div>

                    <div class="form-group">
                        <label for="vat_rate">VAT Rate (as decimal)</label>
                        <input type="number" id="vat_rate" name="vat_rate" value="<?php echo $vatRate; ?>" step="0.01" min="0" max="1" required>
                        <div class="info-box">
                            <strong>Example:</strong> 0.18 = 18% VAT
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="currency_symbol">Currency Symbol</label>
                        <input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($currency); ?>" maxlength="10" required>
                        <div class="info-box">
                            <strong>Example:</strong> $, €, £, etc.
                        </div>
                    </div>

                    <button type="submit" class="btn">Save Settings</button>
                </form>
            </div>

            <div class="card">
                <h2>Database Information</h2>

                <div style="margin: 20px 0;">
                    <div class="stat">
                        <div class="stat-label">Database Size</div>
                        <div class="stat-value"><?php echo $dbSize; ?> MB</div>
                    </div>

                    <div class="stat">
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value"><?php
                            $count = $db->fetch("SELECT COUNT(*) as count FROM sales WHERE invoice_type = 'Invoice'");
                            echo number_format($count['count'] ?? 0);
                        ?></div>
                    </div>

                    <div class="stat">
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-value"><?php
                            $customers = $db->fetch("SELECT COUNT(DISTINCT customer_name) as count FROM sales WHERE invoice_type = 'Invoice'");
                            echo number_format($customers['count'] ?? 0);
                        ?></div>
                    </div>
                </div>

                <div class="info-box">
                    <strong>💡 Tip:</strong> Database size grows with data. At ~1MB per 5,000 transactions, you can store 500,000+ transactions in just 100MB.
                </div>
            </div>

            <div class="card">
                <h2>Maintenance</h2>

                <div class="info-box">
                    <p><strong>📋 Recommended Actions:</strong></p>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>✅ Backup database weekly (see Backup section)</li>
                        <li>✅ Review audit log monthly (see Audit Log)</li>
                        <li>✅ Check for data quality issues (see Search)</li>
                        <li>✅ Monitor user activity (see Activity Log)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
