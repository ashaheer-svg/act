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
    <link rel="stylesheet" href="layout.css?v=1.0.3">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search data...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <div class="page-header">
                    <div>
                        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -1px;">Data Import Center</h1>
                        <p style="color: var(--text-muted);">Upload your sales reports and ledger files to sync the system.</p>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>" style="margin-bottom: 30px;">
                    <div style="font-size: 24px;">
                        <i class="<?php echo $messageType === 'success' ? 'icon-check-circle' : 'icon-alert-circle'; ?>"></i>
                    </div>
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

                <!-- Top Section: Primary Upload Actions -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 40px; align-items: stretch;">
                    <!-- Sales Import Card -->
                    <div class="card" style="border-top: 4px solid var(--primary); display: flex; flex-direction: column; height: 100%;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                            <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="icon-file-text" style="font-size: 24px;"></i>
                            </div>
                            <div>
                                <h2 style="margin: 0;">Sales Report</h2>
                                <p style="color: var(--text-muted); font-size: 13px; margin: 2px 0 0 0;">Import itemized sales data with VAT details.</p>
                            </div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" style="flex: 1; display: flex; flex-direction: column;">
                            <div class="upload-area" onclick="document.getElementById('fileInput').click()" id="salesDropZone">
                                <i class="icon-upload-cloud"></i>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 15px;">Click to select Sales File</div>
                                <div class="btn btn-outline" style="margin-top: 10px; font-size: 12px; pointer-events: none; padding: 6px 15px;">Browse Files</div>
                                <input type="file" id="fileInput" name="sales_file" accept=".xlsx,.xls,.csv" required onchange="updateFileInfo(this, 'fileInfo')" style="display: none;">
                                <div id="fileInfo" style="margin-top: 15px; font-weight: 700; color: var(--primary); font-size: 13px; display: none; background: #e0f2fe; padding: 5px 12px; border-radius: 6px;"></div>
                            </div>
                            <div style="margin-top: auto; padding-top: 25px;">
                                <button type="submit" class="btn btn-primary" style="width: 100%; height: 45px; font-weight: 700;">
                                    <i class="icon-send" style="font-size: 16px;"></i> Start Sales Import
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Ledger Import Card -->
                    <div class="card" style="border-top: 4px solid var(--secondary); display: flex; flex-direction: column; height: 100%;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                            <div style="width: 48px; height: 48px; background: var(--secondary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="icon-dollar-sign" style="font-size: 24px;"></i>
                            </div>
                            <div>
                                <h2 style="margin: 0;">QuickBooks Ledger</h2>
                                <p style="color: var(--text-muted); font-size: 13px; margin: 2px 0 0 0;">Import customer payments and collection tracking.</p>
                            </div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" style="flex: 1; display: flex; flex-direction: column;">
                            <div class="upload-area" onclick="document.getElementById('ledgerInput').click()" style="border-color: #fbd38d;">
                                <i class="icon-upload-cloud" style="color: var(--secondary);"></i>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 15px;">Click to select Ledger File</div>
                                <div class="btn btn-outline" style="margin-top: 10px; font-size: 12px; pointer-events: none; padding: 6px 15px;">Browse Files</div>
                                <input type="file" id="ledgerInput" name="ledger_file" accept=".csv" required onchange="updateFileInfo(this, 'ledgerInfo')" style="display: none;">
                                <div id="ledgerInfo" style="margin-top: 15px; font-weight: 700; color: var(--secondary); font-size: 13px; display: none; background: #fff7ed; padding: 5px 12px; border-radius: 6px;"></div>
                            </div>
                            <div style="margin-top: auto; padding-top: 25px;">
                                <button type="submit" class="btn btn-warning" style="width: 100%; height: 45px; font-weight: 700;">
                                    <i class="icon-send" style="font-size: 16px;"></i> Import Payments
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bottom Section: Results & Guidelines -->
                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px; align-items: start;">
                    <!-- Main Results Column -->
                    <div>
                        <?php if (!empty($result['details']['duplicate_sets'])): ?>
                        <div class="card duplicate-audit">
                            <h2 style="color: var(--secondary); display: flex; align-items: center; gap: 10px;">
                                <i class="icon-search"></i> Duplicate Audit Detail
                            </h2>
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
                            <h2 style="display: flex; align-items: center; gap: 10px;">
                                <i class="icon-list"></i> Detailed Skip Audit
                            </h2>
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
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h2 style="display: flex; align-items: center; gap: 10px;">
                                    <i class="icon-history"></i> Recent Import Activity
                                </h2>
                            </div>
                            <?php if (!empty($importHistory)): ?>
                            <table class="table">
                                <thead>
                                    <tr><th>Filename</th><th>Imported</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach(array_slice($importHistory, 0, 5) as $log): ?>
                                    <tr>
                                        <td><span style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($log['filename']); ?></span></td>
                                        <td style="font-weight: 700; color: var(--success);"><?php echo $log['records_imported']; ?> recs</td>
                                        <td style="color: var(--text-muted); font-size: 12px;"><?php echo date('M d, H:i', strtotime($log['import_date'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p style="text-align: center; padding: 40px; color: var(--text-muted);">No import history found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sidebar Guidelines Column -->
                    <div>
                        <div class="card" style="background: #f8fafc; border: 1px solid var(--border-color);">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                                <i class="icon-info" style="font-size: 18px; color: var(--primary);"></i> Guidelines
                            </h3>
                            <div class="info-box" style="background: transparent; border: none; padding: 0;">
                                <p style="font-size: 13px; line-height: 1.5;"><strong>Accepted Formats:</strong></p>
                                <ul style="margin: 8px 0 20px 18px; font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                                    <li>Excel (.xlsx, .xls)</li>
                                    <li>CSV (.csv) UTF-8</li>
                                </ul>
                                
                                <p style="font-size: 13px; line-height: 1.5;"><strong>System Logic:</strong></p>
                                <ul style="margin: 8px 0 20px 18px; font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                                    <li><strong>Duplicates:</strong> Skipped automatically based on invoice + amount.</li>
                                    <li><strong>VAT:</strong> Auto-calculated based on date rules in settings.</li>
                                    <li><strong>Profit:</strong> Resets for the month when new data is uploaded.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- System Status Card -->
                        <div class="card" style="margin-top: 20px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
                            <h4 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">
                                <i class="icon-activity" style="font-size: 14px; margin-right: 5px;"></i> System Health
                            </h4>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 13px;">Database Status</span>
                                    <span class="badge" style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700;">Connected</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 13px;">Importer Version</span>
                                    <span style="font-size: 12px; font-weight: 700; color: var(--text-main);">v2.4.0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
        function updateFileInfo(input, infoId) {
            const info = document.getElementById(infoId);
            if (input.files && input.files[0]) {
                info.textContent = 'Selected: ' + input.files[0].name;
                info.style.display = 'inline-block';
            }
        }
    </script>
</body>
</html>
