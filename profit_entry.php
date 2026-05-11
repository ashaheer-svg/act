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
    <title>Profit Entry - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.2">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search entries...'; require_once 'includes/header.php'; ?>

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
                        <a href="profit_entry.php" class="tab-btn active"><i class="icon-dollar-sign"></i> Profit Entry</a>
                        <a href="customers.php" class="tab-btn"><i class="icon-building-2"></i> Customers</a>
                        <a href="upload.php" class="tab-btn"><i class="icon-folder-up"></i> Data Upload</a>
                        <?php if ($auth->isAdmin()): ?>
                        <a href="users.php" class="tab-btn"><i class="icon-user"></i> User Mgmt</a>
                        <?php endif; ?>
                    </div>
                </div>

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

        <?php require_once 'includes/layout_js.php'; ?>

            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->
</body>
</html>
