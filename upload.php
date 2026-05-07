<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/DataImporter.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAdmin();

$user = $auth->getCurrentUser();
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
    <title>Upload Sales Data</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a56d4;
            --sidebar: #0f172a;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
        }
        .header {
            background: white;
            color: var(--text-main);
            padding: 0 30px;
            height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: relative;
            z-index: 10;
        }
        .header h1 { font-size: 20px; font-weight: 700; }
        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        .sidebar {
            width: 260px;
            background: var(--sidebar);
            color: white;
            padding: 30px 20px;
            flex-shrink: 0;
        }
        .nav-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin: 25px 0 10px 10px;
            font-weight: 700;
        }
        .nav-item {
            padding: 12px 15px;
            margin: 4px 0;
            cursor: pointer;
            border-radius: 10px;
            text-decoration: none;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }
        .nav-item.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        .main-content {
            flex: 1;
            padding: 40px;
            background: var(--bg);
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 30px;
        }
        .card h2 {
            margin-bottom: 20px;
            color: var(--text-main);
            font-size: 20px;
            font-weight: 700;
        }
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 60px 40px;
            text-align: center;
            background: #fcfdfe;
            transition: all 0.2s;
        }
        .upload-area:hover {
            background: #f8fafc;
            border-color: var(--primary);
        }
        .upload-area input[type="file"] {
            display: none;
        }
        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .upload-button {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .upload-button:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .message {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .message.success { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
        .message.error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        .skip-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .skip-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .skip-label { font-size: 12px; font-weight: 600; text-transform: uppercase; opacity: 0.7; }
        .skip-value { font-size: 18px; font-weight: 700; }
        .info-box {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1e40af;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 12px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .table tr:hover { background: #f8fafc; }
        .logout-btn {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }
        .user-badge {
            background: #e0e7ff;
            color: #4361ee;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
    </style>
    </style>
</head>
<body>
    <div class="header">
        <h1>📤 Upload Sales Data</h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <span style="font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($user['username']); ?></span>
            <div class="user-badge">ADMIN</div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="nav-section-title">Main Menu</div>
            <a href="index.php" class="nav-item"><span>📊</span> Dashboard</a>
            
            <div class="nav-section-title">Administration</div>
            <a href="upload.php" class="nav-item active"><span>📤</span> Upload Data</a>
            <a href="users.php" class="nav-item"><span>👥</span> Manage Users</a>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <div style="font-size: 24px;"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></div>
                <div style="flex: 1;">
                    <div style="font-weight: 700; margin-bottom: 5px;"><?php echo $messageType === 'success' ? 'Import Successful' : 'Import Failed'; ?></div>
                    <div><?php echo htmlspecialchars($message); ?></div>
                    
                    <?php if (isset($result['details'])): ?>
                    <div class="skip-details">
                        <div class="skip-item">
                            <div class="skip-label">Imported</div>
                            <div class="skip-value" style="color: var(--success);"><?php echo $result['imported']; ?></div>
                        </div>
                        <div class="skip-item">
                            <div class="skip-label">Duplicates</div>
                            <div class="skip-value" style="color: var(--warning);"><?php echo $result['details']['duplicates']; ?></div>
                        </div>
                        <div class="skip-item">
                            <div class="skip-label">Headers/Empty</div>
                            <div class="skip-value" style="color: var(--text-muted);"><?php echo $result['details']['missing_fields']; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Import Sales Data</h2>

                <div class="info-box">
                    <strong>📋 Instructions:</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <li>Upload your QuickBooks export file (Excel or CSV)</li>
                        <li>Supports columns: Type, Date, Num, Name, Item, Sales Tax Code, Qty, Amount</li>
                        <li>Only records newer than existing data will be imported</li>
                        <li>VAT will be automatically calculated based on Tax Code</li>
                        <li>Maximum file size: 5MB</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <label class="upload-label">
                            <div class="upload-icon">📁</div>
                            <div class="upload-text">Drag and drop your file here or click to select</div>
                            <button type="button" class="upload-button" onclick="document.getElementById('fileInput').click();">
                                Choose File
                            </button>
                            <input type="file" id="fileInput" name="sales_file" accept=".xlsx,.xls,.csv" required>
                        </label>
                        <div id="fileInfo" style="margin-top: 15px; display: none; color: #667eea; font-weight: 600;"></div>
                    </div>

                    <button type="submit" class="upload-button" style="width: 100%; margin-top: 20px; padding: 15px;">
                        Upload and Import
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>Import History</h2>
                <p style="color: #666; margin-bottom: 15px;">Recent uploads and import logs</p>

                <?php if (!empty($importHistory)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Imported By</th>
                            <th>Records Imported</th>
                            <th>Records Skipped</th>
                            <th>Import Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($importHistory as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['filename']); ?></td>
                            <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                            <td style="color: #4caf50; font-weight: 600;"><?php echo $log['records_imported']; ?></td>
                            <td style="color: #ff9800;"><?php echo $log['records_skipped']; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($log['import_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999;">No imports yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileInfo(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateFileInfo(e.target.files[0]);
            }
        });

        function updateFileInfo(file) {
            const size = (file.size / 1024 / 1024).toFixed(2);
            fileInfo.textContent = `✓ Selected: ${file.name} (${size}MB)`;
            fileInfo.style.display = 'block';
        }
    </script>
</body>
</html>
