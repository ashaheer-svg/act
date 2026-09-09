<?php
/**
 * Legacy QuickBooks (2009-2021) Historical Data Importer
 *
 * Imports historical QuickBooks Desktop export files (CSV/Excel)
 * from older company archives, applying the historical sequence-based VAT rules:
 * - AS004001 - AS005147: 12%
 * - AS005148 - AS006560: 0% (Exempt)
 * - AS006561 - AS008154: 15%
 * - AS008155 - AS008211: 8%
 * - AS008212 - AS010020: 0% (Exempt)
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/DataImporter.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAccounts();

$user = $auth->getCurrentUser();
$importer = new DataImporter($db, $user['id']);

$message = '';
$messageType = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['legacy_file'])) {
    $result = $importer->processUpload($_FILES['legacy_file']);
    $messageType = $result['success'] ? 'success' : 'error';
    $message = $result['message'];

    // Automatically recalculate historical VAT to ensure legacy sequences are applied
    if ($result['success']) {
        $db->recalculateHistoricalVat();
    }
}

// Check historical stats
$legacyStats = $db->fetch("
    SELECT COUNT(DISTINCT invoice_number) as inv_count,
           COUNT(*) as line_count,
           MIN(invoice_date) as min_date,
           MAX(invoice_date) as max_date,
           SUM(total_amount) as total_revenue
    FROM sales
    WHERE invoice_date < '2022-01-01'
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legacy QuickBooks (2009-2021) Bridge - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.3">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search legacy records...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <div class="page-header" style="margin-bottom: 25px;">
                    <div>
                        <h1 style="font-size: 26px; font-weight: 800; letter-spacing: -0.5px; margin: 0; display: flex; align-items: center; gap: 10px;">
                            Legacy QuickBooks Data Bridge (2009 – 2021)
                            <span style="font-size: 11px; font-weight: 700; color: #1e40af; background: #dbeafe; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Historical Archive</span>
                        </h1>
                        <p style="color: var(--text-muted); margin-top: 5px; font-size: 14px;">
                            Ingest invoices and customer history from your older version of QuickBooks Desktop. Historical VAT rates (12%, 15%, 8%, 0%) are automatically applied based on invoice sequence ranges.
                        </p>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>" style="margin-bottom: 25px;">
                    <div style="font-size: 24px;">
                        <i class="<?php echo $messageType === 'success' ? 'icon-check-circle' : 'icon-alert-circle'; ?>"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 800; margin-bottom: 4px;">Import Result</div>
                        <div><?php echo htmlspecialchars($message); ?></div>
                        <?php if (!empty($result['imported'])): ?>
                        <div style="margin-top: 10px; font-size: 13px;">
                            <strong><?php echo $result['imported']; ?></strong> lines successfully imported and mapped to statutory tax sequences.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Top Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
                    <div class="card" style="margin: 0; padding: 20px;">
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Legacy Invoices In System</span>
                        <div style="font-size: 24px; font-weight: 800; color: var(--primary); margin-top: 8px;">
                            <?php echo number_format($legacyStats['inv_count'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="card" style="margin: 0; padding: 20px;">
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Sales Lines</span>
                        <div style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-top: 8px;">
                            <?php echo number_format($legacyStats['line_count'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="card" style="margin: 0; padding: 20px;">
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Historical Period Covered</span>
                        <div style="font-size: 18px; font-weight: 700; color: #059669; margin-top: 10px;">
                            <?php echo ($legacyStats['min_date'] ? date('M Y', strtotime($legacyStats['min_date'])) : 'N/A') . ' → ' . ($legacyStats['max_date'] ? date('M Y', strtotime($legacyStats['max_date'])) : 'N/A'); ?>
                        </div>
                    </div>
                    <div class="card" style="margin: 0; padding: 20px;">
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Legacy Revenue Logged</span>
                        <div style="font-size: 22px; font-weight: 800; color: #7c3aed; margin-top: 8px;">
                            LKR <?php echo number_format($legacyStats['total_revenue'] ?? 0, 2); ?>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                    <!-- Upload File Card -->
                    <div class="card" style="border-top: 4px solid var(--primary);">
                        <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">Method A: Web File Importer (CSV / Excel)</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">
                            Export a <strong>Sales by Item Detail</strong> report from your older QuickBooks Desktop installation and upload it below.
                        </p>

                        <form method="POST" enctype="multipart/form-data" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 35px 20px; text-align: center; cursor: pointer;" onclick="document.getElementById('legacyFileInput').click();">
                            <input type="file" name="legacy_file" id="legacyFileInput" accept=".csv,.xlsx,.xls" style="display: none;" onchange="document.getElementById('selectedFileName').textContent = this.files[0] ? this.files[0].name : ''; document.getElementById('submitUploadBtn').style.display = 'inline-block';">
                            <i class="icon-upload-cloud" style="font-size: 40px; color: var(--primary);"></i>
                            <div style="font-weight: 700; font-size: 15px; margin-top: 10px; color: var(--text-main);">Click to select Legacy QuickBooks Export (.CSV / .XLSX)</div>
                            <div style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">Supports Sales Detail, Transaction List by Customer, or Item Export</div>
                            <div id="selectedFileName" style="font-weight: 700; color: #059669; font-size: 14px; margin-top: 10px;"></div>
                            <button type="submit" id="submitUploadBtn" class="btn btn-primary" style="display: none; margin-top: 15px; padding: 10px 24px;" onclick="event.stopPropagation();">
                                Upload & Ingest Legacy Data
                            </button>
                        </form>

                        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Direct Batch AI Processing</h4>
                            <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 12px;">Once legacy records are uploaded, run the AI entity extractor to normalize hardware assets and serial numbers.</p>
                            <a href="bin/batch_extract_assets.php?limit=100" target="_blank" class="btn" style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 13px;">
                                <span>🤖</span> Run Batch AI Extractor on Queued Invoices
                            </a>
                        </div>
                    </div>

                    <!-- Direct SDK Instructions Card -->
                    <div class="card" style="border-top: 4px solid #059669;">
                        <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">Method B: Live QuickBooks SDK Extraction</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">
                            If you have the older QuickBooks company file accessible on this Windows computer, you can run the sync utility directly targeting the historical period:
                        </p>

                        <div style="background: #0f172a; color: #38bdf8; padding: 15px 20px; border-radius: 10px; font-family: monospace; font-size: 13px; margin-bottom: 20px; line-height: 1.6;">
                            # Open 2009-2021 Company File in QuickBooks Desktop<br>
                            # Open Command Prompt in your app folder and run:<br><br>
                            <span style="color: #fcd34d;">SalesBISync.exe</span> --from <span style="color: #4ade80;">2009-01-01</span> --to <span style="color: #4ade80;">2021-12-31</span>
                        </div>

                        <h3 style="font-size: 14px; margin-top: 20px; margin-bottom: 10px;">Active Statutory VAT Sequences Applied:</h3>
                        <ul style="font-size: 13px; color: var(--text-muted); line-height: 1.8; padding-left: 20px; margin: 0;">
                            <li><strong style="color: var(--text-main);">AS004001 → AS005147</strong>: 12% Statutory VAT</li>
                            <li><strong style="color: var(--text-main);">AS005148 → AS006560</strong>: 0% VAT (Exempt)</li>
                            <li><strong style="color: var(--text-main);">AS006561 → AS008154</strong>: 15% Statutory VAT</li>
                            <li><strong style="color: var(--text-main);">AS008155 → AS008211</strong>: 8% Statutory VAT</li>
                            <li><strong style="color: var(--text-main);">AS008212 → AS010020</strong>: 0% VAT (Exempt)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
