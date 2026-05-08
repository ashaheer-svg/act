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
    <title>Settings - Sales BI</title>
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

        .danger-zone {
            border: 2px dashed #fee2e2;
            background: #fffafb;
            padding: 30px;
            border-radius: var(--radius-lg);
        }
        .danger-zone h2 { color: var(--error); }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo-icon">Σ</div>
            act sales bi
        </div>
        
        <div class="top-nav">
            <a href="index.php" class="top-nav-item">Dashboard</a>
            <a href="reports.php" class="top-nav-item">Reporting</a>
            <?php if ($auth->isAdmin()): ?>
            <a href="customers.php" class="top-nav-item">Customers</a>
            <a href="upload.php" class="top-nav-item">Upload</a>
            <a href="users.php" class="top-nav-item">Users</a>
            <a href="settings.php" class="top-nav-item active">Settings</a>
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

            <div class="danger-zone">
                <h2>Danger Zone</h2>
                <p style="color: var(--text-muted); margin-bottom: 20px;">The following actions are destructive and cannot be undone. Please proceed with caution.</p>
                
                <div style="display: flex; align-items: center; justify-content: space-between; background: white; padding: 20px; border-radius: 12px; border: 1px solid #fee2e2;">
                    <div>
                        <h4 style="font-weight: 700;">Reset All Sales Data</h4>
                        <p style="font-size: 13px; color: var(--text-muted);">Delete all sales records, import logs, and activity history.</p>
                    </div>
                    <form method="POST" onsubmit="return confirm('CRITICAL WARNING: This will permanently delete ALL sales records and import history. This action cannot be undone. Are you absolutely sure?');">
                        <input type="hidden" name="action" value="reset_database">
                        <button type="submit" class="btn btn-danger">Reset Database</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
