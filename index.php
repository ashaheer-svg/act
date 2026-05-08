<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$reports = new Reports($db);

$user = $auth->getCurrentUser();

// Load dynamic settings
$currency = $db->getSetting('currency_symbol', '$');
$vatRate = $db->getSetting('vat_rate', '0.18');

// Get date range for reports
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$reportType = $_GET['report'] ?? 'dashboard';

$dateFrom = "$year-$month-01";
$dateTo = date('Y-m-t', strtotime($dateFrom));

// Get dashboard data with error handling
try {
    $summary = $reports->getDashboardSummary($dateFrom, $dateTo);
    $topCustomers = $reports->getTopCustomers(5, $dateFrom, $dateTo);
    $topProducts = $reports->getTopProducts(5, $dateFrom, $dateTo);
    $categoryAnalysis = $reports->getSalesByCategory($dateFrom, $dateTo);
    $concentration = $reports->getCustomerConcentration($dateFrom, $dateTo);
    $vatSummary = $reports->getVATSummary($dateFrom, $dateTo);
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity - Sales Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1; /* Soft Indigo */
            --primary-hover: #4f46e5;
            --secondary: #fb923c; /* Orange for badges */
            --bg: #f1f5f9; /* Soft Slate background */
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.07), 0 4px 6px -4px rgb(0 0 0 / 0.07);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
            overflow-x: hidden;
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
            overflow: hidden;
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

        /* --- Sidebar / Filters --- */
        .sidebar {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 110px;
        }

        .sidebar h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reset-link {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .filter-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .filter-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 15px;
            display: block;
        }

        .custom-select {
            width: 100%;
            padding: 12px 15px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: #f8fafc;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
        }

        /* --- Main Content --- */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .report-header {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            color: white;
        }

        .badge-primary { background: var(--primary); }
        .badge-secondary { background: var(--secondary); }
        .badge-muted { background: #e2e8f0; color: var(--text-muted); }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border);
        }

        .card-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 15px;
        }

        .card-value {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: -1px;
            word-break: break-all;
            color: var(--text-main);
            line-height: 1;
        }

        .card-footer {
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* --- Table Styling --- */
        .table-card {
            grid-column: span 3;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h4 { font-size: 16px; font-weight: 700; }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0 15px 10px 15px;
        }

        .table td {
            background: #f8fafc;
            padding: 18px 15px;
            font-size: 14px;
        }

        .table tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; font-weight: 600; }
        .table tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

        .price-tag {
            font-size: 16px;
            font-weight: 800;
            color: var(--primary);
        }

        .price-sub {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 400;
        }

        /* --- Buttons --- */
        .btn-primary {
            background: var(--text-main);
            color: white;
            padding: 14px 28px;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            width: 100%;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: scale(0.98);
        }

        @media (max-width: 1200px) {
            .container { grid-template-columns: 1fr; }
            .sidebar { position: relative; top: 0; width: 100%; }
            .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
            .table-card { grid-column: span 2; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo-icon">A</div>
            Activity
        </div>
        
        <div class="top-nav">
            <a href="index.php" class="top-nav-item active">Dashboard</a>
            <a href="reports.php" class="top-nav-item">Reporting</a>
            <?php if ($auth->isAdmin() || $auth->isAccounts()): ?>
            <a href="profit_entry.php" class="top-nav-item">Profit Entry</a>
            <a href="customers.php" class="top-nav-item">Customers</a>
            <a href="upload.php" class="top-nav-item">Upload</a>
            <?php endif; ?>
            <?php if ($auth->isAdmin()): ?>
            <a href="users.php" class="top-nav-item">Users</a>
            <a href="settings.php" class="top-nav-item">Settings</a>
            <?php endif; ?>
        </div>

        <div class="header-actions">
            <div style="font-size: 18px; color: var(--text-muted); cursor: pointer;">⚙️</div>
            <div style="font-size: 18px; color: var(--text-muted); cursor: pointer;">🔔</div>
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
            <h3>Filters <a href="index.php" class="reset-link">Reset</a></h3>
            
            <div class="filter-section">
                <span class="filter-label">Reporting Period</span>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <span style="font-size: 11px; color: var(--text-muted); margin-bottom: 5px; display: block;">Year</span>
                        <select id="yearSelect" class="custom-select" onchange="applyFilters()">
                            <option value="2024" <?php echo $year == '2024' ? 'selected' : ''; ?>>2024</option>
                            <option value="2025" <?php echo $year == '2025' ? 'selected' : ''; ?>>2025</option>
                            <option value="2026" <?php echo $year == '2026' ? 'selected' : ''; ?>>2026</option>
                        </select>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-muted); margin-bottom: 5px; display: block;">Month</span>
                        <select id="monthSelect" class="custom-select" onchange="applyFilters()">
                            <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="filter-section" style="border: none;">
                <span class="filter-label">Quick Actions</span>
                <a href="upload.php" class="btn-primary" style="text-decoration: none; display: block;">Upload New Data</a>
            </div>
            
            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 15px; margin-top: 10px;">
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">Active Session</div>
                <div style="font-size: 14px; font-weight: 700;"><?php echo htmlspecialchars($user['username']); ?></div>
                <div style="font-size: 11px; color: var(--primary); font-weight: 600; text-transform: uppercase; margin-top: 4px;">● Admin Access</div>
            </div>
        </div>

        <div class="main-content">
            <div class="report-header">
                <div class="badge badge-secondary">Cheapest month</div>
                <div class="badge badge-primary">Current View</div>
                <div class="badge badge-muted"><?php echo count($topCustomers); ?> customers active</div>
            </div>
            
            <h2 style="font-size: 28px; font-weight: 800; letter-spacing: -1px; margin-bottom: 5px;">Dashboard - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h2>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 10px;">Sales and VAT performance metrics</p>

            <div class="dashboard-grid">
                <?php if (($summary['total_invoices'] ?? 0) == 0): ?>
                <div class="card table-card" style="text-align: center; padding: 60px 20px; border: 2px dashed var(--border); background: #fcfdfe;">
                    <div style="font-size: 50px; margin-bottom: 20px;">📂</div>
                    <h2 style="margin-bottom: 10px;">No sales data available yet</h2>
                    <p style="color: var(--text-muted); margin-bottom: 30px;">Start by uploading your first sales CSV file to see the analytics.</p>
                    <a href="upload.php" class="btn-primary" style="text-decoration: none; display: inline-block; width: auto;">Upload Your First File</a>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-label">Total Revenue (Base)</div>
                    <div class="card-value" style="color: var(--primary);"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                    <div class="card-footer"><span>📄</span> <?php echo $summary['total_invoices'] ?? 0; ?> invoices</div>
                </div>

                <div class="card">
                    <div class="card-label">Total After VAT</div>
                    <div class="card-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                    <div class="card-footer">Revenue to collect</div>
                </div>

                <div class="card">
                    <div class="card-label">VAT Collected</div>
                    <div class="card-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_vat'] ?? 0, 0); ?></div>
                    <div class="card-footer">To be remitted</div>
                </div>

                <div class="card">
                    <div class="card-label">Unique Customers</div>
                    <div class="card-value"><?php echo $summary['unique_customers'] ?? 0; ?></div>
                    <div class="card-footer">Customer base</div>
                </div>

                <div class="card">
                    <div class="card-label">Avg Invoice Value</div>
                    <div class="card-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['avg_invoice_value'] ?? 0, 0); ?></div>
                    <div class="card-footer">Per transaction</div>
                </div>

                <div class="card">
                    <div class="card-label">Customer Concentration</div>
                    <div class="card-value"><?php echo $concentration['top_3_percentage'] ?? 0; ?>%</div>
                    <div class="card-footer">
                        Top 3 customers 
                        <span class="badge" style="background: <?php echo $concentration['risk_level'] === 'High' ? 'var(--error)' : ($concentration['risk_level'] === 'Medium' ? 'var(--warning)' : 'var(--success)'); ?>; padding: 2px 8px; font-size: 10px;">
                            <?php echo strtoupper($concentration['risk_level']); ?>
                        </span>
                    </div>
                </div>

                <div class="card table-card">
                    <div class="table-header">
                        <h4>Top 5 Customers</h4>
                        <span class="badge badge-muted">Revenue ranking</span>
                    </div>
                    <table class="table">
                        <thead><tr><th>Customer Name</th><th>Purchases</th><th>Revenue</th></tr></thead>
                        <tbody>
                            <?php foreach($topCustomers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($customer['customer_name'], 0, 35)); ?></td>
                                <td><?php echo $customer['invoice_count']; ?> orders</td>
                                <td>
                                    <div class="price-tag"><?php echo htmlspecialchars($currency); ?><?php echo number_format($customer['total_revenue'], 0); ?></div>
                                    <div class="price-sub"><?php echo $customer['revenue_percentage']; ?>% of total</div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card table-card">
                    <div class="table-header">
                        <h4>Top 5 Products</h4>
                        <span class="badge badge-muted">Inventory performance</span>
                    </div>
                    <table class="table">
                        <thead><tr><th>Item Description</th><th>Quantity Sold</th><th>Total Revenue</th></tr></thead>
                        <tbody>
                            <?php foreach($topProducts as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($product['item_description'], 0, 40)); ?></td>
                                <td><?php echo (int)$product['total_quantity']; ?> units</td>
                                <td>
                                    <div class="price-tag"><?php echo htmlspecialchars($currency); ?><?php echo number_format($product['total_revenue'], 0); ?></div>
                                    <div class="price-sub"><?php echo number_format($product['total_revenue'] / max(1, $product['total_quantity']), 2); ?> avg price</div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-label">VAT - Taxable Sales</div>
                    <div class="card-value" style="font-size: 24px;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($vatSummary['taxable_vat'] ?? 0, 0); ?></div>
                    <div class="card-footer">Base: <?php echo htmlspecialchars($currency); ?><?php echo number_format($vatSummary['taxable_base'] ?? 0, 0); ?></div>
                </div>

                <div class="card">
                    <div class="card-label">VAT - Non-Taxable</div>
                    <div class="card-value" style="font-size: 24px;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($vatSummary['non_taxable_vat'] ?? 0, 0); ?></div>
                    <div class="card-footer">Base: <?php echo CURRENCY; ?><?php echo number_format($vatSummary['non_taxable_base'] ?? 0, 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log("Dashboard loaded");
        function applyFilters() {
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;
            window.location.href = `index.php?year=${year}&month=${month}`;
        }
    </script>
</body>
</html>
