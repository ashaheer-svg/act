<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAccounts(); // Admin or Accounts

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');

$message = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_type') {
    $name = $_POST['customer_name'] ?? '';
    $type = $_POST['customer_type'] ?? 'End Customer';
    
    if ($db->updateCustomerType($name, $type)) {
        $message = "Customer '$name' updated to $type.";
    } else {
        $error = "Failed to update customer.";
    }
}

$customers = $db->getCustomerProfiles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Activity</title>
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
            border-radius: 12px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
            transition: all 0.2s;
        }

        /* --- User Dropdown --- */
        .user-dropdown {
            position: relative;
            cursor: pointer;
        }
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px;
            padding-right: 12px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .user-trigger:hover { background: #f8fafc; }
        .user-info-brief {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .user-name { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .user-role { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 220px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            padding: 8px;
            display: none;
            z-index: 1000;
            transform-origin: top right;
            animation: dropdownFade 0.2s ease;
        }
        .dropdown-menu.active { display: block; }
        
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }
        .dropdown-header strong { display: block; font-size: 14px; color: var(--text-main); }
        .dropdown-header span { font-size: 11px; color: var(--text-muted); }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .dropdown-item:hover { background: #f8fafc; color: var(--primary); }
        .dropdown-item.logout-link:hover { background: #fef2f2; color: var(--error); }
        .dropdown-divider { height: 1px; background: var(--border); margin: 8px 0; }

        /* --- Content --- */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); padding: 0 15px 5px 15px; }
        .table td { background: #f8fafc; padding: 15px; font-size: 14px; }
        .table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; font-weight: 600; }
        .table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        .type-select {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-partner { background: #dcfce7; color: #15803d; }
        .badge-end { background: #e0e7ff; color: #4338ca; }

        .search-box {
            padding: 10px 15px;
            border-radius: 10px;
            border: 1px solid var(--border);
            width: 300px;
            margin-bottom: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* --- Settings Nav (Tabs) --- */
        .settings-nav {
            background: white;
            border-radius: var(--radius-lg);
            padding: 15px 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .settings-nav-links { display: flex; gap: 10px; align-items: center; }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            border: none;
            background: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .tab-btn:hover { background: #f8fafc; color: var(--text-main); }
        .tab-btn.active { background: #eef2ff; color: var(--primary); font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo-icon">A</div>
            Activity
        </div>
        
        <div class="top-nav">
            <a href="index.php" class="top-nav-item">Dashboard</a>
            <a href="reports.php" class="top-nav-item">Reporting</a>
            <a href="settings.php" class="top-nav-item active">Settings</a>
        </div>

        <div class="header-actions">
            <div class="user-dropdown">
                <div class="user-trigger" onclick="toggleUserDropdown()">
                    <div class="user-profile">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="user-info-brief">
                        <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                    <div style="font-size: 10px; color: var(--text-muted); margin-left: 4px;">▼</div>
                </div>
                
                <div class="dropdown-menu" id="userDropdown">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <span><?php echo ucfirst($user['role']); ?> Management Account</span>
                    </div>
                    <a href="settings.php#security" class="dropdown-item">🔒 Change Password</a>
                    <?php if ($auth->isAdmin()): ?>
                    <a href="users.php" class="dropdown-item">👥 Manage Users</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="logout.php" style="margin: 0;">
                        <button type="submit" class="dropdown-item logout-link">🚪 Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="max-width: 1400px;">
        <div class="settings-nav">
            <div class="settings-nav-links">
                <a href="settings.php#general" class="tab-btn">⚙️ General</a>
                <a href="settings.php#team" class="tab-btn">👥 Sales Team</a>
                <a href="settings.php#rationalize" class="tab-btn">🏷️ Product Mapping</a>
                <?php if ($auth->isAdmin()): ?>
                <a href="settings.php#tax" class="tab-btn">🏦 Tax & History</a>
                <?php endif; ?>
                <div style="width: 1px; height: 24px; background: var(--border); margin: 0 10px;"></div>
                <a href="profit_entry.php" class="tab-btn">💰 Profit Entry</a>
                <a href="customers.php" class="tab-btn active">🏢 Customers</a>
                <a href="upload.php" class="tab-btn">📁 Data Upload</a>
                <?php if ($auth->isAdmin()): ?>
                <a href="users.php" class="tab-btn">👤 User Mgmt</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-header">
            <div>
                <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -1px;">Customer Management</h1>
                <p style="color: var(--text-muted);">Classify your customers as Partners or End Customers.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <input type="text" id="customerSearch" class="search-box" placeholder="Search customers..." onkeyup="filterCustomers()">
            
            <table class="table" id="customerTable">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Type</th>
                        <th style="text-align: right;">Invoices</th>
                        <th style="text-align: right;">Lifetime Rev</th>
                        <th style="text-align: right; width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                        <td>
                            <span class="badge <?php echo $c['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>">
                                <?php echo htmlspecialchars($c['customer_type']); ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 600;"><?php echo $c['lifetime_invoices']; ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--primary);">
                            <?php echo htmlspecialchars($currency); ?><?php echo number_format($c['lifetime_revenue'] ?? 0, 0); ?>
                        </td>
                        <td style="text-align: right;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_type">
                                <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($c['customer_name']); ?>">
                                <select name="customer_type" class="type-select" onchange="this.form.submit()">
                                    <option value="End Customer" <?php echo $c['customer_type'] === 'End Customer' ? 'selected' : ''; ?>>End Customer</option>
                                    <option value="Partner" <?php echo $c['customer_type'] === 'Partner' ? 'selected' : ''; ?>>Partner</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function filterCustomers() {
            const input = document.getElementById('customerSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('customerTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>
    <script>
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
</body>
</html>
