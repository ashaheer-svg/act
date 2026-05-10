<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/DataImporter.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAccounts(); // Admin or Accounts

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');
$importer = new DataImporter($db, $user['id']);

$message = '';
$messageType = '';
$importHistory = $importer->getImportHistory();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['sales_file'])) {
        $result = $importer->processUpload($_FILES['sales_file']);
        $messageType = $result['success'] ? 'success' : 'error';
        $message = $result['message'];
    } else if (isset($_FILES['ledger_file'])) {
        $result = $importer->processLedgerUpload($_FILES['ledger_file']);
        $messageType = $result['success'] ? 'success' : 'error';
        $message = $result['message'];
    }

    if (isset($result['success']) && $result['success']) {
        $importHistory = $importer->getImportHistory();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Data - Activity</title>
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
            border-radius: 12px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
            transition: all 0.2s;
        }

        /* --- User Dropdown --- */
        .user-dropdown {
            position: relative;
            cursor: pointer;
        }
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px;
            padding-right: 12px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .user-trigger:hover { background: #f8fafc; }
        .user-info-brief {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .user-name { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .user-role { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 220px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            padding: 8px;
            display: none;
            z-index: 1000;
            transform-origin: top right;
            animation: dropdownFade 0.2s ease;
        }
        .dropdown-menu.active { display: block; }
        
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }
        .dropdown-header strong { display: block; font-size: 14px; color: var(--text-main); }
        .dropdown-header span { font-size: 11px; color: var(--text-muted); }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .dropdown-item:hover { background: #f8fafc; color: var(--primary); }
        .dropdown-item.logout-link:hover { background: #fef2f2; color: var(--error); }
        .dropdown-divider { height: 1px; background: var(--border); margin: 8px 0; }

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

        .info-box {
            background: #eff6ff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            font-size: 13px;
            color: #1e40af;
            border: 1px solid #dbeafe;
        }

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

        .card h2 { font-size: 24px; font-weight: 800; margin-bottom: 25px; letter-spacing: -0.5px; }

        .upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-lg);
            padding: 60px 40px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.2s;
            cursor: pointer;
        }
        .upload-area:hover { border-color: var(--primary); background: #f1f5f9; }
        .upload-area input { display: none; }

        .btn {
            padding: 14px 28px;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-primary { background: var(--primary); color: white; margin-top: 20px; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }

        .message {
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .message.success { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
        .message.error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .skip-details {
            margin-top: 15px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        .skip-item label { font-size: 10px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block; }
        .skip-item value { font-size: 18px; font-weight: 800; display: block; }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); padding: 0 15px 5px 15px; }
        .table td { background: #f8fafc; padding: 15px; font-size: 14px; }
        .table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        /* Duplicate Audit Styling */
        .duplicate-audit {
            margin-top: 30px;
        }
        .audit-set {
            display: grid;
            grid-template-columns: 80px 1fr 1fr;
            gap: 20px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .audit-row-num {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-right: 1px solid var(--border);
        }
        .audit-card {
            padding: 10px;
            border-radius: 8px;
        }
        .audit-card.original { background: #f0fdf4; border: 1px solid #dcfce7; }
        .audit-card.duplicate { background: #fff7ed; border: 1px solid #ffedd5; }
        .audit-card label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;}
        .audit-data { font-size: 12px; margin-bottom: 3px; }
        .audit-data strong { color: var(--text-main); }

        /* --- Settings Nav (Tabs) --- */
        .settings-nav {
            background: white;
            border-radius: var(--radius-lg);
            padding: 15px 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .settings-nav-links { display: flex; gap: 10px; align-items: center; }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            border: none;
            background: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .tab-btn:hover { background: #f8fafc; color: var(--text-main); }
        .tab-btn.active { background: #eef2ff; color: var(--primary); font-weight: 700; }
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
            <a href="settings.php" class="top-nav-item active">Settings</a>
        </div>

        <div class="header-actions">
            <div class="user-dropdown">
                <div class="user-trigger" onclick="toggleUserDropdown()">
                    <div class="user-profile">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="user-info-brief">
                        <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                    <div style="font-size: 10px; color: var(--text-muted); margin-left: 4px;">▼</div>
                </div>
                
                <div class="dropdown-menu" id="userDropdown">
                    <div class="dropdown-header">
                        strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <span><?php echo ucfirst($user['role']); ?> Management Account</span>
                    </div>
                    <a href="settings.php#security" class="dropdown-item">🔒 Change Password</a>
                    <?php if ($auth->isAdmin()): ?>
                    <a href="users.php" class="dropdown-item">👥 Manage Users</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="logout.php" style="margin: 0;">
                        <button type="submit" class="dropdown-item logout-link">🚪 Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="display: block; max-width: 1600px; padding-bottom: 0;">
        <div class="settings-nav">
            <div class="settings-nav-links">
                <a href="settings.php#general" class="tab-btn">⚙️ General</a>
                <a href="settings.php#team" class="tab-btn">👥 Sales Team</a>
                <a href="settings.php#rationalize" class="tab-btn">🏷️ Product Mapping</a>
                <?php if ($auth->isAdmin()): ?>
                <a href="settings.php#tax" class="tab-btn">🏦 Tax & History</a>
                <?php endif; ?>
                <div style="width: 1px; height: 24px; background: var(--border); margin: 0 10px;"></div>
                <a href="profit_entry.php" class="tab-btn">💰 Profit Entry</a>
                <a href="customers.php" class="tab-btn">🏢 Customers</a>
                <a href="upload.php" class="tab-btn active">📁 Data Upload</a>
                <?php if ($auth->isAdmin()): ?>
                <a href="users.php" class="tab-btn">👤 User Mgmt</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container" style="padding-top: 0;">
        <div class="sidebar">
            <h3>Guidelines</h3>
            <div class="info-box">
                <p><strong>Accepted Formats:</strong></p>
                <ul style="margin: 10px 0 0 15px;">
                    <li>Excel (.xlsx, .xls)</li>
                    <li>CSV (.csv)</li>
                </ul>
                <p style="margin-top: 15px;"><strong>System Logic:</strong></p>
                <ul style="margin: 10px 0 0 15px;">
                    <li>Duplicates are skipped</li>
                    <li>VAT is auto-calculated</li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <div style="font-size: 24px;"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></div>
                <div style="flex: 1;">
                    <div style="font-weight: 800; margin-bottom: 4px;">Import Result</div>
                    <div><?php echo htmlspecialchars($message); ?></div>
                    
                    <?php if (isset($result['details'])): ?>
                    <div class="skip-details">
                        <div class="skip-item"><label>Imported</label><value style="color: var(--success);"><?php echo $result['imported']; ?></value></div>
                        <div class="skip-item"><label>Duplicates</label><value style="color: var(--secondary);"><?php echo $result['details']['duplicates']; ?></value></div>
                        <div class="skip-item"><label>Headers/Other</label><value style="color: var(--text-muted);"><?php echo $result['details']['missing_fields']; ?></value></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <!-- Sales Import Card -->
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <div style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">📦</div>
                        <h2>Import Sales Data</h2>
                    </div>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px; margin-top: -15px;">Standard itemized sales report with VAT details.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                            <div style="font-size: 40px; margin-bottom: 10px;">📊</div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 14px;">Drop sales file or click</div>
                            <input type="file" id="fileInput" name="sales_file" accept=".xlsx,.xls,.csv" required onchange="updateFileInfo(this, 'fileInfo')">
                            <div id="fileInfo" style="margin-top: 10px; font-weight: 700; color: var(--primary); font-size: 12px; display: none;"></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Import Sales</button>
                    </form>
                </div>

                <!-- Ledger Import Card -->
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <div style="width: 40px; height: 40px; background: var(--secondary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">💰</div>
                        <h2>QuickBooks Ledger</h2>
                    </div>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px; margin-top: -15px;">Customer ledger CSV for payments & collection tracking.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="upload-area" onclick="document.getElementById('ledgerInput').click()" style="border-color: #fbd38d;">
                            <div style="font-size: 40px; margin-bottom: 10px;">🏦</div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 14px;">Drop ledger file or click</div>
                            <input type="file" id="ledgerInput" name="ledger_file" accept=".csv" required onchange="updateFileInfo(this, 'ledgerInfo')">
                            <div id="ledgerInfo" style="margin-top: 10px; font-weight: 700; color: var(--secondary); font-size: 12px; display: none;"></div>
                        </div>
                        <button type="submit" class="btn" style="background: var(--secondary); color: white; margin-top: 20px;">Import Payments</button>
                    </form>
                </div>
            </div>
                      <?php if (!empty($result['details']['duplicate_sets'])): ?>
            <div class="card duplicate-audit">
                <h2 style="color: var(--secondary);">🔍 Duplicate Audit Detail</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Side-by-side comparison of duplicates found in the database.</p>
                
                <?php foreach($result['details']['duplicate_sets'] as $set): ?>
                <div class="audit-set">
                    <div class="audit-row-num">
                        <span style="font-size: 10px; font-weight: 700; color: var(--text-muted);">FILE ROW</span>
                        <span style="font-size: 24px; font-weight: 800;"><?php echo $set['row']; ?></span>
                    </div>
                    
                    <div class="audit-card original">
                        <label>Existing in Database</label>
                        <div class="audit-data">Inv #: <strong><?php echo htmlspecialchars($set['original']['num']); ?></strong></div>
                        <div class="audit-data">Customer: <strong><?php echo htmlspecialchars(substr($set['original']['name'], 0, 30)); ?></strong></div>
                        <div class="audit-data">Item: <strong><?php echo htmlspecialchars(substr($set['original']['item'], 0, 30)); ?></strong></div>
                        <div class="audit-data">Amount: <strong><?php echo CURRENCY . number_format((float)str_replace(',', '', $set['original']['amount']), 2); ?></strong></div>
                        <div style="font-size: 10px; color: var(--success); margin-top: 5px;">Imported on <?php echo $set['original']['imported_at']; ?></div>
                    </div>
                    <div class="audit-card duplicate">
                        <label>Incoming Duplicate</label>
                        <div class="audit-data">Inv #: <strong><?php echo htmlspecialchars($set['duplicate']['num']); ?></strong></div>
                        <div class="audit-data">Customer: <strong><?php echo htmlspecialchars(substr($set['duplicate']['name'], 0, 30)); ?></strong></div>
                        <div class="audit-data">Item: <strong><?php echo htmlspecialchars(substr($set['duplicate']['item'], 0, 30)); ?></strong></div>
                        <div class="audit-data">Amount: <strong><?php echo CURRENCY . number_format((float)str_replace(',', '', $set['duplicate']['amount']), 2); ?></strong></div>
                        <div style="font-size: 10px; color: var(--secondary); margin-top: 5px;">Row skipped by system</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($result['details']['skipped_rows'])): ?>
            <div class="card">
                <h2 style="color: var(--text-main);">📋 Detailed Skip Audit</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Complete list of records skipped and the specific reason for each.</p>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Row #</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($result['details']['skipped_rows'] as $row): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--text-main);">#<?php echo $row['row']; ?></td>
                            <td><?php echo htmlspecialchars($row['num']); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['name'], 0, 30)); ?></td>
                            <td style="font-weight: 700; color: var(--primary);"><?php echo CURRENCY . number_format((float)str_replace(',', '', $row['amount']), 2); ?></td>
                            <td>
                                <span class="badge" style="background: <?php echo str_contains($row['reason'], 'Duplicate') ? '#fff7ed' : '#fef2f2'; ?>; color: <?php echo str_contains($row['reason'], 'Duplicate') ? '#c2410c' : '#b91c1c'; ?>; border: 1px solid currentColor;">
                                    <?php echo htmlspecialchars($row['reason']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Import History</h2>
                <?php if (!empty($importHistory)): ?>
                <table class="table">
                    <thead>
                        <tr><th>Filename</th><th>Imported</th><th>Skipped</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($importHistory as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['filename']); ?></td>
                            <td style="font-weight: 700; color: var(--success);"><?php echo $log['records_imported']; ?></td>
                            <td style="color: var(--secondary);"><?php echo $log['records_skipped']; ?></td>
                            <td style="color: var(--text-muted); font-size: 12px;"><?php echo date('Y-m-d H:i', strtotime($log['import_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; padding: 40px; color: var(--text-muted);">No import history found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateFileInfo(input, infoId) {
            const info = document.getElementById(infoId);
            if (input.files && input.files[0]) {
                info.textContent = 'Selected: ' + input.files[0].name;
                info.style.display = 'block';
            }
        }
    </script>
    <script>
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
</body>
</html>
