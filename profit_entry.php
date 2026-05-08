<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAccounts(); // Admin or Accounts

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Handle AJAX Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gp') {
    $id = $_POST['id'];
    $gp = floatval($_POST['gp']);
    
    if ($db->updateGrossProfit($id, $gp)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

$sales = $db->getSalesForProfitEntry($year, $month);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Entry - Sales BI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
            --success: #10b981;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
        }

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
        }

        .top-nav { display: flex; gap: 30px; }
        .top-nav-item {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            padding-bottom: 5px;
            border-bottom: 2px solid transparent;
        }
        .top-nav-item.active { color: var(--text-main); border-bottom-color: var(--primary); }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 40px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .filter-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow);
            align-items: center;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); padding: 0 15px 5px 15px; }
        .table td { background: #f8fafc; padding: 12px 15px; font-size: 13px; }
        .table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        .gp-input {
            width: 120px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-weight: 700;
            color: var(--primary);
            text-align: right;
        }
        .gp-input:focus { border-color: var(--primary); outline: none; }
        .gp-input.saving { background: #fef3c7; }
        .gp-input.saved { border-color: var(--success); background: #f0fdf4; }

        .badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .search-box {
            padding: 10px 15px;
            border-radius: 10px;
            border: 1px solid var(--border);
            width: 300px;
        }
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
            <a href="profit_entry.php" class="top-nav-item active">Profit Entry</a>
            <?php if ($auth->isAdmin() || $auth->isAccounts()): ?>
            <a href="customers.php" class="top-nav-item">Customers</a>
            <?php endif; ?>
            <?php if ($auth->isAdmin()): ?>
            <a href="upload.php" class="top-nav-item">Upload</a>
            <a href="users.php" class="top-nav-item">Users</a>
            <a href="settings.php" class="top-nav-item">Settings</a>
            <?php endif; ?>
        </div>

        <div class="header-actions">
            <div style="background: #e2e8f0; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" style="background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 15px;">🚪</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -1px;">Profit Data Entry</h1>
            <p style="color: var(--text-muted);">Enter Gross Profit (GP) for each transaction line.</p>
        </div>

        <div class="filter-bar">
            <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 13px; font-weight: 700;">Year:</label>
                <select onchange="location.href='?month=<?php echo $month; ?>&year='+this.value" style="padding: 8px; border-radius: 8px; border: 1px solid var(--border);">
                    <?php for($y=2023; $y<=2026; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 13px; font-weight: 700;">Month:</label>
                <select onchange="location.href='?year=<?php echo $year; ?>&month='+this.value" style="padding: 8px; border-radius: 8px; border: 1px solid var(--border);">
                    <?php for($m=1; $m<=12; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?php echo $mStr; ?>" <?php echo $month == $mStr ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="flex-grow: 1;"></div>
            <input type="text" id="rowSearch" class="search-box" placeholder="Search customer or invoice..." onkeyup="filterTable()">
        </div>

        <div class="card">
            <table class="table" id="profitTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Item Description</th>
                        <th class="text-right">Net Revenue</th>
                        <th class="text-right" style="width: 150px;">Gross Profit (GP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $row): ?>
                    <tr>
                        <td><?php echo $row['invoice_date']; ?></td>
                        <td style="font-family: monospace; font-weight: 700;"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td title="<?php echo htmlspecialchars($row['item_description']); ?>">
                            <?php echo htmlspecialchars(substr($row['item_description'], 0, 40)); ?>...
                        </td>
                        <td style="text-align: right; font-weight: 700; color: var(--primary);">
                            <?php echo htmlspecialchars($currency); ?><?php echo number_format($row['base_value'], 2); ?>
                        </td>
                        <td style="text-align: right;">
                            <input type="number" step="0.01" class="gp-input" 
                                   value="<?php echo $row['gross_profit']; ?>" 
                                   onchange="saveGP(<?php echo $row['id']; ?>, this.value, this)">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function saveGP(id, value, input) {
            input.classList.add('saving');
            
            const formData = new FormData();
            formData.append('action', 'save_gp');
            formData.append('id', id);
            formData.append('gp', value);

            fetch('profit_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                input.classList.remove('saving');
                if (data.success) {
                    input.classList.add('saved');
                    setTimeout(() => input.classList.remove('saved'), 1500);
                }
            })
            .catch(error => {
                input.classList.remove('saving');
                alert('Error saving data');
            });
        }

        function filterTable() {
            const input = document.getElementById('rowSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('profitTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td1 = tr[i].getElementsByTagName('td')[1]; // Invoice
                const td2 = tr[i].getElementsByTagName('td')[2]; // Customer
                if (td1 || td2) {
                    const txt = (td1.textContent + ' ' + td2.textContent).toLowerCase();
                    tr[i].style.display = txt.indexOf(filter) > -1 ? "" : "none";
                }
            }
        }
    </script>
</body>
</html>
