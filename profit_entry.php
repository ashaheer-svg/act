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
                        <input type="text" class="search-input" placeholder="Search entries...">
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
