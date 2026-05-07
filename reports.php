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
$type = $_GET['type'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$quarter = $_GET['quarter'] ?? ceil(date('m') / 3);

$reportData = [];
$reportTitle = '';

switch($type) {
    case 'monthly':
        $reportData = $reports->getMonthlySales($year, $month);
        $reportTitle = 'Monthly Sales Report - ' . $reportData['period'];
        break;
    case 'quarterly':
        $reportData = $reports->getQuarterlySales($year, $quarter);
        $reportTitle = 'Quarterly Sales Report - ' . $reportData['period'];
        break;
    case 'yearly':
        $reportData = $reports->getYearlySales($year);
        $reportTitle = 'Yearly Sales Report - ' . $reportData['period'];
        break;
}

$summary = $reportData['summary'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $reportTitle; ?></title>
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
            overflow-y: auto;
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
            overflow-y: auto;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            font-size: 13px;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
        }

        .table tr:hover { background: #f8f9fa; }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .metric-label { font-size: 12px; color: #999; font-weight: 600; }
        .metric-value { font-size: 24px; font-weight: bold; color: #667eea; margin-top: 10px; }

        h2 { color: #333; margin-bottom: 20px; font-size: 24px; }
        h3 { color: #333; margin: 20px 0 15px 0; font-size: 16px; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($reportTitle); ?></h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" style="background: rgba(255,255,255,0.3); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3 style="margin-bottom: 20px; font-size: 14px;">Navigation</h3>
            <a href="index.php" class="nav-item">📊 Dashboard</a>
            <a href="reports.php?type=monthly" class="nav-item <?php echo $type === 'monthly' ? 'active' : ''; ?>">📅 Monthly</a>
            <a href="reports.php?type=quarterly" class="nav-item <?php echo $type === 'quarterly' ? 'active' : ''; ?>">📈 Quarterly</a>
            <a href="reports.php?type=yearly" class="nav-item <?php echo $type === 'yearly' ? 'active' : ''; ?>">📊 Yearly</a>
        </div>

        <div class="main-content">
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-label">Total Invoices</div>
                    <div class="metric-value"><?php echo $summary['total_invoices'] ?? 0; ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Total Revenue (Base)</div>
                    <div class="metric-value"><?php echo CURRENCY; ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Total VAT</div>
                    <div class="metric-value"><?php echo CURRENCY; ?><?php echo number_format($summary['total_vat'] ?? 0, 0); ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Total Amount</div>
                    <div class="metric-value"><?php echo CURRENCY; ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Unique Customers</div>
                    <div class="metric-value"><?php echo $summary['unique_customers'] ?? 0; ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Avg Invoice Value</div>
                    <div class="metric-value"><?php echo CURRENCY; ?><?php echo number_format($summary['avg_invoice_value'] ?? 0, 0); ?></div>
                </div>
            </div>

            <?php if ($type === 'yearly' && !empty($reportData['monthly_breakdown'])): ?>
            <div class="card">
                <h3>Monthly Breakdown</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-right">Invoices</th>
                            <th class="text-right">Revenue (Base)</th>
                            <th class="text-right">VAT</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reportData['monthly_breakdown'] as $month_data): ?>
                        <tr>
                            <td><?php echo $month_data['month_name']; ?></td>
                            <td class="text-right"><?php echo $month_data['invoice_count']; ?></td>
                            <td class="text-right"><?php echo CURRENCY; ?><?php echo number_format($month_data['revenue_base'], 0); ?></td>
                            <td class="text-right"><?php echo CURRENCY; ?><?php echo number_format($month_data['vat_total'], 0); ?></td>
                            <td class="text-right"><?php echo CURRENCY; ?><?php echo number_format($month_data['total'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h3>Transaction Details</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Item</th>
                            <th class="text-right">Base Value</th>
                            <th class="text-right">VAT</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reportData['data'] ?? [] as $row): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['customer_name'], 0, 30)); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['item_description'], 0, 20)); ?></td>
                            <td class="text-right"><?php echo CURRENCY; ?><?php echo number_format($row['base_value'], 0); ?></td>
                            <td class="text-right"><?php echo CURRENCY; ?><?php echo number_format($row['vat_component'], 0); ?></td>
                            <td class="text-right"><?php echo CURRENCY; ?><?php echo number_format($row['total_amount'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
