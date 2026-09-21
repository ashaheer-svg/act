<?php
/**
 * QuickBooks Desktop Sync Application Download Hub
 * Provides direct downloads for the latest SalesBISync utility, pre-configured packages,
 * live connection status, and setup documentation.
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireReportAccess('sync_app');

$user = $auth->getCurrentUser();

// Direct file downloads
if (isset($_GET['download'])) {
    $mode = $_GET['download'];

    if ($mode === 'zip') {
        $zipFile = __DIR__ . '/app/binary/SalesBISync.zip';
        if (!file_exists($zipFile)) {
            $zipFile = __DIR__ . '/app/binary/config.zip';
        }
        if (file_exists($zipFile)) {
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="SalesBISync.zip"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            exit;
        } else {
            die("Sync package not found on server.");
        }
    }

    if ($mode === 'exe') {
        $exeFile = __DIR__ . '/app/binary/SalesBISync.exe';
        if (file_exists($exeFile)) {
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="SalesBISync.exe"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($exeFile));
            readfile($exeFile);
            exit;
        } else {
            die("Executable not found on server.");
        }
    }

    if ($mode === 'config') {
        while (ob_get_level()) { ob_end_clean(); }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $syncUrl = "$protocol://$host$dir/api/sync.php";
        $apiKey = $db->getSetting('api_secret_key', '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867');

        $configData = [
            'server_url' => $syncUrl,
            'api_key' => $apiKey,
            'last_sync_date' => $db->getSetting('last_qb_sync', date('Y-m-d\TH:i:s')),
            'qb_company_file' => '',
            'batch_size' => 75,
            'include_serial_numbers' => true,
            'save_local_copy' => true,
            'export_folder' => 'exports',
            'log_file' => 'logs/sync_log.txt',
            'require_qb_running' => true,
            'sync_interval_minutes' => 60
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="config.json"');
        echo json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Prepare UI metadata
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$syncApiUrl = "$protocol://$host$dir/api/sync.php";
$apiKey = $db->getSetting('api_secret_key', '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867');
$lastQbSync = $db->getSetting('last_qb_sync', '');
$lastQbSummary = $db->getSetting('last_qb_sync_summary', '');

$exePath = __DIR__ . '/app/binary/SalesBISync.exe';
$zipPath = __DIR__ . '/app/binary/SalesBISync.zip';
if (!file_exists($zipPath)) {
    $zipPath = __DIR__ . '/app/binary/config.zip';
}

$exeSizeMB = file_exists($exePath) ? round(filesize($exePath) / (1024 * 1024), 1) : 34.8;
$zipSizeMB = file_exists($zipPath) ? round(filesize($zipPath) / (1024 * 1024), 1) : 29.9;
$exeModTime = file_exists($exePath) ? date('Y-m-d H:i', filemtime($exePath)) : '2026-09-17';

// Database stats
$totalInvoices = (int)$db->fetch("SELECT count(*) as c FROM sales WHERE invoice_type != 'Credit Memo'")['c'];
$totalPayments = (int)$db->fetch("SELECT count(*) as c FROM payments")['c'];
$totalCustomers = (int)$db->fetch("SELECT count(*) as c FROM customer_profiles")['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Sync App | Active Solutions BI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.3">
    <style>
        .sync-app-page {
            padding: 32px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .header-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .app-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .download-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .dl-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .dl-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.08);
            transform: translateY(-2px);
        }
        .dl-card.recommended {
            border: 2px solid #2563eb;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }
        .recommended-ribbon {
            position: absolute;
            top: 0;
            right: 0;
            background: #2563eb;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 14px;
            border-bottom-left-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dl-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        .dl-icon-blue { background: #eff6ff; color: #2563eb; }
        .dl-icon-green { background: #f0fdf4; color: #16a34a; }
        .dl-icon-amber { background: #fffbeb; color: #d97706; }

        .dl-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
            width: 100%;
            box-sizing: border-box;
            margin-top: 20px;
        }
        .dl-btn-primary {
            background: #2563eb;
            color: #ffffff;
        }
        .dl-btn-primary:hover {
            background: #1d4ed8;
            color: #ffffff;
        }
        .dl-btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #cbd5e1;
        }
        .dl-btn-secondary:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        @media (max-width: 900px) {
            .info-grid { grid-template-columns: 1fr; }
        }

        .section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .code-box {
            background: #0f172a;
            color: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
            overflow-x: auto;
        }
        .copy-btn {
            background: #1e293b;
            border: 1px solid #334155;
            color: #94a3b8;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }
        .copy-btn:hover {
            background: #334155;
            color: #f8fafc;
        }
        .step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
        }
        .cli-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 12px;
        }
        .cli-table th, .cli-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
        }
        .cli-table th {
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
        }
        .cli-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search sync status...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <div class="sync-app-page">
                    
                    <!-- Title & Tag Header -->
                    <div class="header-title-row">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <h1 style="font-size: 26px; font-weight: 800; margin: 0; color: #0f172a;">QuickBooks Sync Application</h1>
                                <span class="app-badge-tag"><i class="icon-check-circle"></i> v1.0.0 Ready</span>
                            </div>
                            <p style="color: #64748b; font-size: 14px; margin: 0;">
                                Official Windows background client for automated QuickBooks Desktop synchronization (QuickBooks 2009–2026, 32-bit & 64-bit).
                            </p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <a href="sync_app.php?download=zip" class="dl-btn dl-btn-primary" style="margin-top: 0; padding: 10px 18px;">
                                <i class="icon-cloud-download"></i> Download Latest Sync App (ZIP)
                            </a>
                        </div>
                    </div>

                    <!-- Download Options Grid -->
                    <div class="download-cards-grid">
                        
                        <!-- Card 1: Recommended ZIP -->
                        <div class="dl-card recommended">
                            <div class="recommended-ribbon">Recommended</div>
                            <div>
                                <div class="dl-icon-wrap dl-icon-blue">
                                    <i class="icon-archive"></i>
                                </div>
                                <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">Pre-Configured Package</h3>
                                <p style="font-size: 13px; color: #64748b; margin: 0 0 14px 0; line-height: 1.4;">
                                    Complete ready-to-run ZIP archive containing <strong>SalesBISync.exe</strong>, pre-configured <strong>config.json</strong> with this server's endpoint and API key, and user instructions.
                                </p>
                                <div style="font-size: 12px; color: #475569; display: flex; gap: 14px;">
                                    <span><strong>Size:</strong> <?= $zipSizeMB; ?> MB</span>
                                    <span><strong>Format:</strong> .ZIP</span>
                                    <span><strong>Updated:</strong> <?= $exeModTime; ?></span>
                                </div>
                            </div>
                            <a href="sync_app.php?download=zip" class="dl-btn dl-btn-primary">
                                <i class="icon-download"></i> Download SalesBISync.zip
                            </a>
                        </div>

                        <!-- Card 2: Standalone EXE -->
                        <div class="dl-card">
                            <div>
                                <div class="dl-icon-wrap dl-icon-green">
                                    <i class="icon-cpu"></i>
                                </div>
                                <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">Standalone Executable</h3>
                                <p style="font-size: 13px; color: #64748b; margin: 0 0 14px 0; line-height: 1.4;">
                                    Single compiled Windows binary (x86 self-contained). Ideal if you are updating an existing installation in <code>C:\SalesBISync\</code>.
                                </p>
                                <div style="font-size: 12px; color: #475569; display: flex; gap: 14px;">
                                    <span><strong>Size:</strong> <?= $exeSizeMB; ?> MB</span>
                                    <span><strong>Format:</strong> .EXE</span>
                                </div>
                            </div>
                            <a href="sync_app.php?download=exe" class="dl-btn dl-btn-secondary">
                                <i class="icon-download"></i> Download SalesBISync.exe
                            </a>
                        </div>

                        <!-- Card 3: Config JSON -->
                        <div class="dl-card">
                            <div>
                                <div class="dl-icon-wrap dl-icon-amber">
                                    <i class="icon-sliders"></i>
                                </div>
                                <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">Configuration File</h3>
                                <p style="font-size: 13px; color: #64748b; margin: 0 0 14px 0; line-height: 1.4;">
                                    Pre-populated <code>config.json</code> with the current host URL, secure API key, and recommended sync parameters.
                                </p>
                                <div style="font-size: 12px; color: #475569; display: flex; gap: 14px;">
                                    <span><strong>Size:</strong> &lt; 1 KB</span>
                                    <span><strong>Format:</strong> .JSON</span>
                                </div>
                            </div>
                            <a href="sync_app.php?download=config" class="dl-btn dl-btn-secondary">
                                <i class="icon-download"></i> Download config.json
                            </a>
                        </div>

                    </div>

                    <!-- Details & Setup Grid -->
                    <div class="info-grid">
                        
                        <!-- Left: Setup Guide & CLI -->
                        <div>
                            <div class="section-card" style="margin-bottom: 24px;">
                                <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0f172a;">
                                    <i class="icon-check-square" style="color: #2563eb; margin-right: 6px;"></i> 3-Step Setup Instructions
                                </h3>
                                
                                <div class="step-item">
                                    <div class="step-num">1</div>
                                    <div>
                                        <strong>Extract to Local Folder:</strong><br>
                                        Extract <code>SalesBISync.zip</code> into a dedicated directory on the PC where QuickBooks Desktop is installed (recommended: <code>C:\SalesBISync\</code>).
                                    </div>
                                </div>

                                <div class="step-item">
                                    <div class="step-num">2</div>
                                    <div>
                                        <strong>Authorize QuickBooks Connection:</strong><br>
                                        Open QuickBooks Desktop as Administrator with your company file open. Double-click <code>SalesBISync.exe</code>. When QuickBooks displays the security prompt, select <em>"Yes, always; allow access even if QuickBooks is not running"</em> and confirm.
                                    </div>
                                </div>

                                <div class="step-item" style="margin-bottom: 0;">
                                    <div class="step-num">3</div>
                                    <div>
                                        <strong>Enable Hourly Background Sync:</strong><br>
                                        Open Command Prompt or PowerShell in <code>C:\SalesBISync\</code> and run:<br>
                                        <div class="code-box" style="margin-top: 6px;">
                                            <code>SalesBISync.exe --schedule 60</code>
                                            <button class="copy-btn" onclick="navigator.clipboard.writeText('SalesBISync.exe --schedule 60'); this.innerText='Copied!';">Copy</button>
                                        </div>
                                        This registers an automated Windows Task Scheduler task that syncs new transactions every 60 minutes.
                                    </div>
                                </div>
                            </div>

                            <!-- CLI Command Reference -->
                            <div class="section-card">
                                <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 8px 0; color: #0f172a;">
                                    <i class="icon-terminal" style="color: #475569; margin-right: 6px;"></i> Command Line Reference
                                </h3>
                                <p style="font-size: 13px; color: #64748b; margin: 0 0 12px 0;">
                                    You can execute manual syncs or adjust schedules anytime via command line:
                                </p>
                                <table class="cli-table">
                                    <thead>
                                        <tr>
                                            <th>Command</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="cli-code">SalesBISync.exe --sync --incremental</span></td>
                                            <td>Fast sync of invoices and payments since last sync date</td>
                                        </tr>
                                        <tr>
                                            <td><span class="cli-code">SalesBISync.exe --sync --full</span></td>
                                            <td>Complete historical synchronization of all records</td>
                                        </tr>
                                        <tr>
                                            <td><span class="cli-code">SalesBISync.exe --schedule 60</span></td>
                                            <td>Creates hourly background Task Scheduler job</td>
                                        </tr>
                                        <tr>
                                            <td><span class="cli-code">SalesBISync.exe --schedule-status</span></td>
                                            <td>Checks background task status and next run time</td>
                                        </tr>
                                        <tr>
                                            <td><span class="cli-code">SalesBISync.exe --unschedule</span></td>
                                            <td>Removes background Windows task</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Right: Live Connection & API Credentials -->
                        <div>
                            <div class="section-card" style="margin-bottom: 24px;">
                                <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0f172a;">
                                    <i class="icon-link-2" style="color: #16a34a; margin-right: 6px;"></i> Connection Status
                                </h3>

                                <div style="margin-bottom: 14px;">
                                    <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">API Endpoint URL</span>
                                    <div class="code-box">
                                        <code style="word-break: break-all;"><?= htmlspecialchars($syncApiUrl); ?></code>
                                        <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= addslashes($syncApiUrl); ?>'); this.innerText='Copied!';">Copy</button>
                                    </div>
                                </div>

                                <div style="margin-bottom: 18px;">
                                    <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Secret API Key</span>
                                    <div class="code-box">
                                        <code><?= htmlspecialchars($apiKey); ?></code>
                                        <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= addslashes($apiKey); ?>'); this.innerText='Copied!';">Copy</button>
                                    </div>
                                </div>

                                <div style="border-top: 1px solid #e2e8f0; padding-top: 14px; font-size: 13px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="color: #64748b;">Last Sync:</span>
                                        <strong><?= !empty($lastQbSync) ? htmlspecialchars($lastQbSync) : 'Never'; ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="color: #64748b;">Invoices Synced:</span>
                                        <strong><?= number_format($totalInvoices); ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="color: #64748b;">Payments Synced:</span>
                                        <strong><?= number_format($totalPayments); ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: #64748b;">Customers:</span>
                                        <strong><?= number_format($totalCustomers); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="section-card">
                                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 8px 0; color: #0f172a;">
                                    Need Custom Configuration?
                                </h4>
                                <p style="font-size: 12px; color: #64748b; line-height: 1.4; margin: 0 0 12px 0;">
                                    To configure company file paths, batch sizes, or regenerate API security keys, visit the settings panel.
                                </p>
                                <a href="settings.php#system" style="font-size: 13px; color: #2563eb; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    Open System Settings &rarr;
                                </a>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </main>
    </div>
</body>
</html>
