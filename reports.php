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
$currency = $db->getSetting('currency_symbol', '$');
$vatRate = $db->getSetting('vat_rate', '0.18');

$type = $_GET['type'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$quarter = $_GET['quarter'] ?? ceil(date('m') / 3);

$reportData = [];
$reportTitle = '';
$customerPivot = [];

if ($type === 'matrix') {
    $customerPivot = $reports->getCustomerYearlyPivot($year);
    $reportTitle = "Customer Performance Matrix - $year";
} else {
    switch($type) {
        case 'monthly':
            $reportData = $reports->getMonthlySales($year, $month);
            $reportTitle = 'Monthly Performance - ' . $reportData['period'];
            break;
        case 'quarterly':
            $reportData = $reports->getQuarterlySales($year, $quarter);
            $reportTitle = 'Quarterly Performance - ' . $reportData['period'];
            break;
        case 'yearly':
            $reportData = $reports->getYearlySales($year);
            $reportTitle = 'Yearly Performance - ' . $reportData['period'];
            break;
    }
}

$summary = $reportData['summary'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $reportTitle; ?> - Sales BI</title>
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

        .sidebar h3 { font-size: 18px; font-weight: 700; margin-bottom: 20px; }

        .report-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            transition: all 0.2s;
        }
        .report-link:hover { background: #f8fafc; color: var(--text-main); }
        .report-link.active { background: #f0f4ff; color: var(--primary); font-weight: 700; }

        /* --- Main Content --- */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
            overflow-x: hidden;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .metric-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .metric-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .metric-value { font-size: 24px; font-weight: 800; color: var(--text-main); }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .card h2 { font-size: 24px; font-weight: 800; margin-bottom: 5px; letter-spacing: -1px; }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); padding: 0 15px 5px 15px; white-space: nowrap;}
        .table td { background: #f8fafc; padding: 15px; font-size: 14px; white-space: nowrap;}
        .table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; font-weight: 600; position: sticky; left: 0; background: #f1f5f9; z-index: 10;}
        .table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        
        .text-right { text-align: right; }
        .price-tag { font-weight: 800; color: var(--primary); }
        
        .matrix-val { font-size: 12px; font-weight: 500; color: var(--text-muted); text-align: right; }
        .matrix-val.active { color: var(--primary); font-weight: 700; }
        
        .badge-vol { background: #e0e7ff; color: var(--primary); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
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
            <a href="reports.php" class="top-nav-item active">Reporting</a>
            <?php if ($auth->isAdmin()): ?>
            <a href="upload.php" class="top-nav-item">Upload</a>
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
            <h3>Standard Reports</h3>
            <a href="reports.php?type=monthly" class="report-link <?php echo $type === 'monthly' ? 'active' : ''; ?>"><span>📅</span> Monthly Report</a>
            <a href="reports.php?type=quarterly" class="report-link <?php echo $type === 'quarterly' ? 'active' : ''; ?>"><span>📈</span> Quarterly Report</a>
            <a href="reports.php?type=yearly" class="report-link <?php echo $type === 'yearly' ? 'active' : ''; ?>"><span>📊</span> Yearly Report</a>
            
            <h3 style="margin-top: 30px;">Advanced Analytics</h3>
            <a href="reports.php?type=matrix" class="report-link <?php echo $type === 'matrix' ? 'active' : ''; ?>"><span>🏢</span> Customer Matrix</a>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                <p style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px;">Selected Year</p>
                <select onchange="window.location.href='reports.php?type=<?php echo $type; ?>&year='+this.value" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                    <option value="2024" <?php echo $year == '2024' ? 'selected' : ''; ?>>2024</option>
                    <option value="2025" <?php echo $year == '2025' ? 'selected' : ''; ?>>2025</option>
                    <option value="2026" <?php echo $year == '2026' ? 'selected' : ''; ?>>2026</option>
                </select>
            </div>
        </div>

        <div class="main-content">
            <?php if ($type === 'matrix'): ?>
                <div class="card">
                    <h2>Customer Matrix - Yearly View</h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Side-by-side monthly purchasing performance by customer.</p>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th class="text-right">Total Rev</th>
                                <th class="text-right">Vol</th>
                                <th>Top Brand</th>
                                <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                                <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($customerPivot as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($row['customer_name'], 0, 25)); ?></td>
                                <td class="price-tag"><?php echo htmlspecialchars($currency); ?><?php echo number_format($row['total_revenue'], 0); ?></td>
                                <td><span class="badge-vol"><?php echo $row['total_volume']; ?></span></td>
                                <td style="font-size: 11px; font-weight: 700; color: var(--secondary);"><?php echo htmlspecialchars($row['top_category'] ?: 'Other'); ?></td>
                                <?php for($m=1; $m<=12; $m++): 
                                    $val = $row['month_'.$m];
                                ?>
                                <td class="matrix-val <?php echo $val > 0 ? 'active' : ''; ?>">
                                    <?php echo $val > 0 ? number_format($val, 0) : '-'; ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-label">Invoices</div>
                        <div class="metric-value"><?php echo $summary['total_invoices'] ?? 0; ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Revenue (Base)</div>
                        <div class="metric-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total After VAT</div>
                        <div class="metric-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                    </div>
                </div>

                <div class="card">
                    <h2><?php echo htmlspecialchars($reportTitle); ?></h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Granular view of transactions for the selected period.</p>
                    
                    <?php if ($type === 'yearly' && !empty($reportData['monthly_breakdown'])): ?>
                    <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <span style="color: var(--primary);">●</span> Monthly Breakdown
                    </h4>
                    <table class="table" style="margin-bottom: 40px;">
                        <thead>
                            <tr><th>Month</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">VAT</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($reportData['monthly_breakdown'] as $m): ?>
                            <tr>
                                <td><?php echo $m['month_name']; ?></td>
                                <td class="text-right"><?php echo $m['invoice_count']; ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($m['revenue_base'], 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($m['vat_total'], 0); ?></td>
                                <td class="text-right" class="price-tag"><?php echo htmlspecialchars($currency) . number_format($m['total'], 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <span style="color: var(--secondary);">●</span> Transaction Details
                    </h4>
                    <table class="table">
                        <thead>
                            <tr><th>Date</th><th>Invoice #</th><th>Customer</th><th class="text-right">Total Value</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($reportData['data'] ?? [] as $row): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?></td>
                                <td style="font-family: monospace; font-weight: 700;"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['customer_name'], 0, 40)); ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['total_amount'], 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
