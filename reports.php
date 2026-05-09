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
$brand = $_GET['brand'] ?? null;
$customer_type = $_GET['customer_type'] ?? null;
$rep_code = $_GET['rep_code'] ?? null;
$salesReps = $db->getSalesReps();

$reportData = [];
$reportTitle = '';
$customerPivot = [];
$uniqueBrands = $reports->getUniqueBrands();

if ($type === 'matrix') {
    $customerPivot = $reports->getCustomerYearlyPivot($year, $brand, $customer_type, $rep_code);
    $reportTitle = "Customer Performance Matrix - $year" . ($brand ? " ($brand)" : "");
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
        case 'credit':
            $creditData = $reports->getCustomerCreditScores();
            $reportTitle = 'Customer Credit Health & Risk Assessment';
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
    <title><?php echo $reportTitle; ?> - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Inter+Tight:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            padding: 20px 30px;
            max-width: 1800px;
            margin: 0 auto;
        }

        /* --- Horizontal Nav --- */
        .report-nav {
            background: white;
            border-radius: var(--radius-lg);
            padding: 15px 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .report-nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }

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
        .metric-value { font-size: 20px; font-weight: 800; color: var(--text-main); }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .card h2 { font-size: 24px; font-weight: 800; margin-bottom: 5px; letter-spacing: -1px; }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table th { text-align: left; font-size: 9px; text-transform: uppercase; color: var(--text-muted); padding: 0 8px 5px 8px; white-space: nowrap;}
        .table td { background: #f8fafc; padding: 8px 8px; font-size: 11px; white-space: nowrap;}
        .table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; font-weight: 700; position: sticky; left: 0; background: #f1f5f9; z-index: 10; max-width: 250px; overflow: hidden; text-overflow: ellipsis; font-size: 11px;}
        .table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        
        .text-right { text-align: right; }
        .price-tag { font-weight: 800; color: var(--primary); }
        
        .matrix-val { font-size: 10px; font-weight: 500; color: var(--text-muted); text-align: right; }
        .matrix-val.active { color: var(--primary); font-weight: 700; }

        /* --- High Density Matrix --- */
        .matrix-table {
            border-spacing: 0 4px;
            font-family: 'Inter Tight', sans-serif;
            letter-spacing: -0.2px;
        }
        .matrix-table th { 
            padding: 0 4px 4px 4px; 
            font-size: 9px;
            font-weight: 800;
            text-align: center;
        }
        .matrix-table td { 
            padding: 6px 4px; 
            font-size: 10px;
        }
        .matrix-table tr td:first-child { 
            max-width: 160px; 
            padding-left: 10px;
            font-size: 10px;
            text-align: left;
        }
        .matrix-table .price-tag { 
            font-size: 10px;
            text-align: center;
        }
        .matrix-table .matrix-val {
            min-width: 40px;
            text-align: center;
        }
        .matrix-table .badge-vol {
            padding: 1px 4px;
            font-size: 9px;
        }
        
        .badge-vol { background: #e0e7ff; color: var(--primary); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }

        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-label { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .filter-select { padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 13px; min-width: 180px; }
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
            <a href="reports.php" class="top-nav-item active">Reporting</a>
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
            <div class="user-profile">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" style="background: none; border: none; font-size: 18px; cursor: pointer;">🚪</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="report-nav">
            <div class="report-nav-links">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-right: 10px;">Reports:</span>
                <a href="reports.php?type=monthly" class="report-link <?php echo $type === 'monthly' ? 'active' : ''; ?>"><span>📅</span> Monthly</a>
                <a href="reports.php?type=quarterly" class="report-link <?php echo $type === 'quarterly' ? 'active' : ''; ?>"><span>📈</span> Quarterly</a>
                <a href="reports.php?type=yearly" class="report-link <?php echo $type === 'yearly' ? 'active' : ''; ?>"><span>📊</span> Yearly</a>
                <a href="reports.php?type=matrix" class="report-link <?php echo $type === 'matrix' ? 'active' : ''; ?>"><span>🏢</span> Matrix</a>
                <a href="reports.php?type=credit" class="report-link <?php echo $type === 'credit' ? 'active' : ''; ?>"><span>🛡️</span> Credit Score</a>
            </div>
            
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Year:</span>
                <select onchange="window.location.href='reports.php?type=<?php echo $type; ?>&brand=<?php echo $brand; ?>&year='+this.value" style="padding: 8px 15px; border-radius: 8px; border: 1px solid var(--border); font-size: 13px; font-weight: 700;">
                    <option value="2024" <?php echo $year == '2024' ? 'selected' : ''; ?>>2024</option>
                    <option value="2025" <?php echo $year == '2025' ? 'selected' : ''; ?>>2025</option>
                    <option value="2026" <?php echo $year == '2026' ? 'selected' : ''; ?>>2026</option>
                </select>
            </div>
        </div>

        <div class="main-content">
            <?php if ($type === 'matrix'): ?>
                <div class="card">
                    <h2>Customer Matrix - Net Sales (Before VAT)</h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">12-month pivot of net revenue performance.</p>
                    
                    <div class="filter-controls">
                        <div class="filter-group">
                            <span class="filter-label">Brand</span>
                            <select class="filter-select" onchange="window.location.href='reports.php?type=matrix&year=<?php echo $year; ?>&customer_type=<?php echo $customer_type; ?>&rep_code=<?php echo $rep_code; ?>&brand='+encodeURIComponent(this.value)">
                                <option value="">All Brands</option>
                                <?php foreach($uniqueBrands as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo $brand === $b['product_category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['product_category']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <span class="filter-label">Customer Category</span>
                            <select class="filter-select" onchange="window.location.href='reports.php?type=matrix&year=<?php echo $year; ?>&brand=<?php echo $brand; ?>&rep_code=<?php echo $rep_code; ?>&customer_type='+encodeURIComponent(this.value)">
                                <option value="">All Customers</option>
                                <option value="Partner" <?php echo $customer_type === 'Partner' ? 'selected' : ''; ?>>Partners Only</option>
                                <option value="End Customer" <?php echo $customer_type === 'End Customer' ? 'selected' : ''; ?>>End Customers Only</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <span class="filter-label">Sales Rep</span>
                            <select class="filter-select" onchange="window.location.href='reports.php?type=matrix&year=<?php echo $year; ?>&brand=<?php echo $brand; ?>&customer_type=<?php echo $customer_type; ?>&rep_code='+encodeURIComponent(this.value)">
                                <option value="">All Reps</option>
                                <?php foreach($salesReps as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['rep_code']); ?>" <?php echo $rep_code === $r['rep_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['rep_name']); ?> (<?php echo htmlspecialchars($r['rep_code']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <table class="table matrix-table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th class="text-right">Total Net</th>
                                <th class="text-right">Vol</th>
                                <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                                <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($customerPivot as $row): ?>
                            <tr>
                                <td title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </td>
                                <td class="price-tag"><?php echo htmlspecialchars($currency); ?><?php echo number_format($row['total_revenue'], 0); ?></td>
                                <td><span class="badge-vol"><?php echo $row['total_volume']; ?></span></td>
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
            <?php elseif ($type === 'credit'): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2>Customer Credit Health Index</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Historical payment performance and risk scoring based on 'Days to Pay'.</p>
                        </div>
                        <div style="background: #f8fafc; padding: 10px 20px; border-radius: 12px; border: 1px solid var(--border); text-align: center;">
                            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Average Collection Period</div>
                            <?php 
                                $allAvg = count($creditData) > 0 ? array_sum(array_column($creditData, 'avg_days')) / count($creditData) : 0;
                            ?>
                            <div style="font-size: 20px; font-weight: 800; color: var(--primary);"><?php echo round($allAvg); ?> Days</div>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Partner / End Customer</th>
                                <th class="text-right">Total Volume</th>
                                <th class="text-right">Paid Inv</th>
                                <th class="text-right">Avg Days to Pay</th>
                                <th class="text-right">Max Delay</th>
                                <th class="text-center">Risk Level</th>
                                <th class="text-right">Credit Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($creditData as $row): 
                                $riskColor = '#10b981'; // Excellent
                                if ($row['risk_level'] === 'Good') $riskColor = '#6366f1';
                                if ($row['risk_level'] === 'Fair') $riskColor = '#f59e0b';
                                if ($row['risk_level'] === 'At Risk') $riskColor = '#fb923c';
                                if ($row['risk_level'] === 'Critical') $riskColor = '#ef4444';
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency); ?><?php echo number_format($row['total_volume'], 0); ?></td>
                                <td class="text-right"><?php echo $row['paid_count']; ?> / <?php echo $row['total_invoices']; ?></td>
                                <td class="text-right"><?php echo round($row['avg_days']); ?> Days</td>
                                <td class="text-right"><?php echo $row['max_days']; ?> Days</td>
                                <td class="text-center">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: <?php echo $riskColor; ?>20; color: <?php echo $riskColor; ?>; font-size: 11px; font-weight: 800; text-transform: uppercase; border: 1px solid <?php echo $riskColor; ?>40;">
                                        <?php echo $row['risk_level']; ?>
                                    </span>
                                </td>
                                <td class="text-right" style="font-family: 'Inter Tight', sans-serif; font-weight: 800; font-size: 16px;">
                                    <?php echo $row['credit_score']; ?>
                                </td>
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
