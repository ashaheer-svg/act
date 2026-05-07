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
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f9f9f9;
            transition: all 0.3s;
        }

        .upload-area:hover {
            background: #f0f0ff;
            border-color: #764ba2;
        }

        .upload-area.dragover {
            background: #e3f2fd;
            border-color: #667eea;
        }

        .upload-area input[type="file"] {
            display: none;
        }

        .upload-label {
            cursor: pointer;
            display: inline-block;
        }

        .upload-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .upload-text {
            color: #666;
            margin-bottom: 10px;
        }

        .upload-button {
            background: #667eea;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 15px;
        }

        .upload-button:hover {
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
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .logout-btn {
            background: rgba(255,255,255,0.3);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📤 Upload Sales Data</h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <div class="user-badge">ADMIN</div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3 style="margin-bottom: 20px; font-size: 14px;">Navigation</h3>
            <a href="index.php" class="nav-item">📊 Dashboard</a>
            <a href="upload.php" class="nav-item active">📤 Upload Data</a>
            <a href="users.php" class="nav-item">👥 Manage Users</a>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
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
