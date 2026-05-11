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
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
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
                <a href="reports.php" class="nav-item active">
                    <i class="icon-bar-chart-2"></i>
                    <span>Reporting</span>
                </a>
                <a href="settings.php#general" class="nav-item">
                    <i class="icon-settings"></i>
                    <span>Settings</span>
                </a>
                <a href="settings.php#security" class="nav-item">
                    <i class="icon-shield"></i>
                    <span>Security</span>
                </a>
                <a href="settings.php#team" class="nav-item">
                    <i class="icon-users"></i>
                    <span>Team</span>
                </a>
                <a href="settings.php#rationalize" class="nav-item">
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
                        <input type="text" class="search-input" placeholder="Search data...">
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
                        <a href="settings.php#general" class="tab-btn"><i class="icon-settings"></i> General</a>
                        <a href="settings.php#team" class="tab-btn"><i class="icon-users"></i> Sales Team</a>
                        <a href="settings.php#rationalize" class="tab-btn"><i class="icon-tag"></i> Product Mapping</a>
                        <?php if ($auth->isAdmin()): ?>
                        <a href="settings.php#tax" class="tab-btn"><i class="icon-landmark"></i> Tax & History</a>
                        <?php endif; ?>
                        <div style="width: 1px; height: 24px; background: var(--border-color); margin: 0 10px;"></div>
                        <a href="profit_entry.php" class="tab-btn"><i class="icon-dollar-sign"></i> Profit Entry</a>
                        <a href="customers.php" class="tab-btn"><i class="icon-building-2"></i> Customers</a>
                        <a href="upload.php" class="tab-btn active"><i class="icon-folder-up"></i> Data Upload</a>
                        <?php if ($auth->isAdmin()): ?>
                        <a href="users.php" class="tab-btn"><i class="icon-user"></i> User Mgmt</a>
                        <?php endif; ?>
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

        function toggleSidebar() {
            document.getElementById('mainSidebar').classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', document.getElementById('mainSidebar').classList.contains('collapsed'));
        }

        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.getElementById('mainSidebar').classList.add('collapsed');
        }

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
