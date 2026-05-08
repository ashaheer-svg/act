<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/DataImporter.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAdmin();

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');
$importer = new DataImporter($db, $user['id']);

$message = '';
$messageType = '';
$importHistory = $importer->getImportHistory();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sales_file'])) {
    $result = $importer->processUpload($_FILES['sales_file']);

    $messageType = $result['success'] ? 'success' : 'error';
    $message = $result['message'];

    if ($result['success']) {
        $importHistory = $importer->getImportHistory();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Data - Sales BI</title>
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
            <a href="upload.php" class="top-nav-item active">Upload</a>
            <a href="users.php" class="top-nav-item">Users</a>
            <a href="settings.php" class="top-nav-item">Settings</a>
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

            <div class="card">
                <h2>Import Sales Data</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <div style="font-size: 48px; margin-bottom: 15px;">📁</div>
                        <div style="font-weight: 700; color: var(--text-main);">Drop file here or click to browse</div>
                        <div style="font-size: 13px; color: var(--text-muted); margin-top: 5px;">Supports .xlsx, .xls, and .csv files up to 5MB</div>
                        <input type="file" id="fileInput" name="sales_file" accept=".xlsx,.xls,.csv" required onchange="updateFileInfo(this)">
                        <div id="fileInfo" style="margin-top: 15px; font-weight: 700; color: var(--primary); display: none;"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Start Import Process</button>
                </form>
            </div>

            <?php if (!empty($result['details']['duplicate_sets'])): ?>
            <div class="card duplicate-audit">
                <h2 style="color: var(--secondary);">🔍 Duplicate Audit Detail</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">The following records were found in the database and skipped during this import.</p>
                
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
                        <div class="audit-data">Amount: <strong><?php echo CURRENCY . number_format($set['original']['amount'], 2); ?></strong></div>
                        <div style="font-size: 10px; color: var(--success); margin-top: 5px;">Imported on <?php echo $set['original']['imported_at']; ?></div>
                    </div>

                    <div class="audit-card duplicate">
                        <label>Incoming Duplicate</label>
                        <div class="audit-data">Inv #: <strong><?php echo htmlspecialchars($set['duplicate']['num']); ?></strong></div>
                        <div class="audit-data">Customer: <strong><?php echo htmlspecialchars(substr($set['duplicate']['name'], 0, 30)); ?></strong></div>
                        <div class="audit-data">Item: <strong><?php echo htmlspecialchars(substr($set['duplicate']['item'], 0, 30)); ?></strong></div>
                        <div class="audit-data">Amount: <strong><?php echo CURRENCY . number_format($set['duplicate']['amount'], 2); ?></strong></div>
                        <div style="font-size: 10px; color: var(--secondary); margin-top: 5px;">Row skipped by system</div>
                    </div>
                </div>
                <?php endforeach; ?>
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
        function updateFileInfo(input) {
            const fileInfo = document.getElementById('fileInfo');
            if (input.files.length > 0) {
                fileInfo.textContent = "Selected: " + input.files[0].name;
                fileInfo.style.display = 'block';
            }
        }
    </script>
</body>
</html>
