<?php
require_once 'classes/Auth.php';
$auth = new Auth();
$auth->requireLogin();

$iconDir = 'assets/icons/';
$icons = glob($iconDir . '*.svg');
sort($icons);

$search = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Icon Gallery - AI Sales</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        h1 { font-size: 24px; margin: 0; }
        .search-box {
            padding: 10px 20px;
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 300px;
            font-size: 14px;
        }
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 20px;
        }
        .icon-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        .icon-card:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .icon-svg {
            width: 32px;
            height: 32px;
            margin-bottom: 12px;
            color: var(--text);
        }
        .icon-name {
            font-size: 11px;
            color: #64748b;
            word-break: break-all;
            font-weight: 500;
        }
        .badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <span class="badge">SYSTEM ASSETS</span>
            <h1>Modern Icon Gallery</h1>
            <p style="color: #64748b; font-size: 14px; margin-top: 5px;">All available black & white vector icons for the redesign.</p>
        </div>
        <form method="GET">
            <input type="text" name="search" class="search-box" placeholder="Search icons..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>

    <div class="icon-grid">
        <?php foreach ($icons as $iconPath): 
            $name = basename($iconPath, '.svg');
            if ($search && strpos($name, $search) === false) continue;
        ?>
        <div class="icon-card" title="Click to copy name" onclick="copyToClipboard('<?php echo $name; ?>')">
            <div class="icon-svg">
                <?php echo file_get_contents($iconPath); ?>
            </div>
            <div class="icon-name"><?php echo $name; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Copied: ' + text);
        });
    }
    </script>
</body>
</html>
