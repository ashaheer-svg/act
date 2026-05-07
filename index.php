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
<head>
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-badge {
            background: #e0e7ff;
            color: #4361ee;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .logout-btn {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .logout-btn:hover { background: #fecaca; }
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
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label { font-weight: 600; font-size: 12px; color: #666; }
        .filter-group select, .filter-group input { padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        .filter-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .filter-btn:hover { background: #764ba2; }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.06);
            border-color: var(--primary);
        }
        .card-title {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .card-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -1px;
        }
        .card-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .warning-card {
            border-left: 4px solid #ff9800;
            background: #fff9f5;
        }
        .warning-card .card-value {
            color: #ff9800;
        }
        .table-card {
            grid-column: span 2;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            font-size: 12px;
            color: #666;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
        }
        .table tr:hover { background: #f8f9fa; }
        .percentage {
            background: #e3f2fd;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: 600;
            color: #667eea;
        }
        .risk-high { background: #ffebee; color: #c33; font-weight: 600; }
        .risk-medium { background: #fff3e0; color: #ff9800; font-weight: 600; }
        .risk-low { background: #e8f5e9; color: #4caf50; font-weight: 600; }
        h2 { margin-bottom: 20px; color: #333; font-size: 20px; }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .table-card { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Sales BI Dashboard</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <div class="user-badge"><?php echo strtoupper($user['role']); ?></div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="nav-section-title">Main Menu</div>
            <a href="index.php" class="nav-item active"><span>📊</span> Dashboard</a>
            <a href="reports.php?type=monthly" class="nav-item"><span>📅</span> Monthly Report</a>
            <a href="reports.php?type=quarterly" class="nav-item"><span>📈</span> Quarterly Report</a>
            <a href="reports.php?type=yearly" class="nav-item"><span>📊</span> Yearly Report</a>

            <?php if ($auth->isAdmin()): ?>
            <div class="nav-section-title">Administration</div>
            <a href="upload.php" class="nav-item" style="color: #4361ee; background: rgba(67,97,238,0.1);"><span>📤</span> Upload Data</a>
            <a href="users.php" class="nav-item"><span>👥</span> Manage Users</a>
            <?php endif; ?>
        </div>

        <div class="main-content">
            <div class="filters">
                <div class="filter-group">
                    <label>Year</label>
                    <select id="yearSelect" onchange="applyFilters()">
                        <option value="2024" <?php echo $year == '2024' ? 'selected' : ''; ?>>2024</option>
                        <option value="2025" <?php echo $year == '2025' ? 'selected' : ''; ?>>2025</option>
                        <option value="2026" <?php echo $year == '2026' ? 'selected' : ''; ?>>2026</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Month</label>
                    <select id="monthSelect" onchange="applyFilters()">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <h2>Dashboard - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h2>

            <div class="dashboard-grid">
                <?php if (($summary['total_invoices'] ?? 0) == 0): ?>
                <div class="card table-card" style="grid-column: span 3; text-align: center; padding: 60px 20px; border: 2px dashed var(--border); background: #fcfdfe;">
                    <div style="font-size: 50px; margin-bottom: 20px;">📂</div>
                    <h2 style="margin-bottom: 10px;">No sales data available yet</h2>
                    <p style="color: var(--text-muted); margin-bottom: 30px;">Start by uploading your first sales CSV file to see the analytics.</p>
                    <a href="upload.php" class="filter-btn" style="text-decoration: none; display: inline-block;">Upload Your First File</a>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-title">Total Revenue (Base)</div>
                    <div class="card-value" style="color: var(--primary);"><?php echo CURRENCY; ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                    <div class="card-subtitle"><span>📄</span> <?php echo $summary['total_invoices'] ?? 0; ?> invoices</div>
                </div>

                <div class="card">
                    <div class="card-title">Total After VAT</div>
                    <div class="card-value"><?php echo CURRENCY; ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Revenue to collect</div>
                </div>

                <div class="card">
                    <div class="card-title">VAT Collected</div>
                    <div class="card-value"><?php echo CURRENCY; ?><?php echo number_format($summary['total_vat'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">To be remitted</div>
                </div>

                <div class="card">
                    <div class="card-title">Unique Customers</div>
                    <div class="card-value"><?php echo $summary['unique_customers'] ?? 0; ?></div>
                </div>

                <div class="card">
                    <div class="card-title">Avg Invoice Value</div>
                    <div class="card-value"><?php echo CURRENCY; ?><?php echo number_format($summary['avg_invoice_value'] ?? 0, 0); ?></div>
                </div>

                <div class="card warning-card">
                    <div class="card-title">Customer Concentration</div>
                    <div class="card-value"><?php echo $concentration['top_3_percentage'] ?? 0; ?>%</div>
                    <div class="card-subtitle">Top 3 customers <span class="<?php echo 'risk-' . strtolower($concentration['risk_level']); ?>"><?php echo $concentration['risk_level']; ?></span></div>
                </div>

                <div class="card table-card">
                    <div class="card-title">Top 5 Customers</div>
                    <table class="table">
                        <thead><tr><th>Customer</th><th>Revenue</th><th>% of Total</th><th>Purchases</th></tr></thead>
                        <tbody>
                            <?php foreach($topCustomers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($customer['customer_name'], 0, 30)); ?></td>
                                <td><?php echo CURRENCY; ?><?php echo number_format($customer['total_revenue'], 0); ?></td>
                                <td><span class="percentage"><?php echo $customer['revenue_percentage']; ?>%</span></td>
                                <td><?php echo $customer['invoice_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card table-card">
                    <div class="card-title">Top 5 Products</div>
                    <table class="table">
                        <thead><tr><th>Product</th><th>Revenue</th><th>Qty</th></tr></thead>
                        <tbody>
                            <?php foreach($topProducts as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($product['item_description'], 0, 25)); ?></td>
                                <td><?php echo CURRENCY; ?><?php echo number_format($product['total_revenue'], 0); ?></td>
                                <td><?php echo (int)$product['total_quantity']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-title">VAT - Taxable Sales</div>
                    <div class="card-value"><?php echo CURRENCY; ?><?php echo number_format($vatSummary['taxable_vat'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Base: <?php echo CURRENCY; ?><?php echo number_format($vatSummary['taxable_base'] ?? 0, 0); ?></div>
                </div>

                <div class="card">
                    <div class="card-title">VAT - Non-Taxable</div>
                    <div class="card-value"><?php echo CURRENCY; ?><?php echo number_format($vatSummary['non_taxable_vat'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Base: <?php echo CURRENCY; ?><?php echo number_format($vatSummary['non_taxable_base'] ?? 0, 0); ?></div>
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
