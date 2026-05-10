<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$reports = new Reports($db);

$customerName = $_GET['name'] ?? '';
if (empty($customerName)) {
    die("Customer name required.");
}

$summary = $reports->getCustomerSummary($customerName);
$history = $reports->getCustomerHistory($customerName);
$trend = array_reverse($reports->getCustomerMonthlyTrend($customerName));
$products = $reports->getCustomerTopProducts($customerName);

// Use the new efficient targeted scoring method
$customerScore = $reports->getCustomerCreditScore($customerName);
if (!$customerScore) {
    die("Customer data not found.");
}

$currency = $db->getSetting('currency_symbol', '$');
$companyName = $db->getSetting('company_name', 'Sales Analytics Platform');

$riskColor = '#10b981';
if ($customerScore['risk_level'] === 'Good') $riskColor = '#6366f1';
if ($customerScore['risk_level'] === 'Fair') $riskColor = '#f59e0b';
if ($customerScore['risk_level'] === 'At Risk') $riskColor = '#fb923c';
if ($customerScore['risk_level'] === 'Critical') $riskColor = '#ef4444';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dossier: <?php echo htmlspecialchars($customerName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg-main: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --radius-lg: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-main); color: var(--text-main); line-height: 1.5; padding: 40px 20px; }

        .report-container { max-width: 1200px; margin: 0 auto; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); overflow: hidden; }
        
        /* Header Section */
        .report-header { padding: 40px; background: #f8fafc; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .company-info h1 { font-family: 'Inter Tight', sans-serif; font-size: 32px; letter-spacing: -1.5px; margin-bottom: 5px; }
        .customer-id { color: var(--text-muted); font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        
        .actions-header { display: flex; gap: 15px; }
        .btn { padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; text-decoration: none; border: none; font-size: 14px; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
        .btn-print { background: var(--text-main); color: white; }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .btn-back { background: #e2e8f0; color: var(--text-main); }

        /* KPI Dashboard */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 40px; }
        .kpi-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 25px; transition: all 0.3s ease; }
        .kpi-card:hover { border-color: var(--primary); transform: translateY(-5px); box-shadow: var(--shadow); }
        .kpi-label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .kpi-value { font-size: 24px; font-weight: 800; font-family: 'Inter Tight', sans-serif; }
        .kpi-sub { font-size: 12px; color: var(--text-muted); margin-top: 5px; font-weight: 500; }

        /* Main Content Grid */
        .content-grid { display: grid; grid-template-columns: 1fr 380px; gap: 40px; padding: 0 40px 40px 40px; }
        .section-title { font-family: 'Inter Tight', sans-serif; font-size: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ''; flex-grow: 1; height: 1px; background: var(--border); }

        .chart-container { background: #f8fafc; border-radius: 20px; padding: 30px; border: 1px solid var(--border); margin-bottom: 40px; }
        
        /* Tables */
        .table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .table th { text-align: left; font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; padding: 0 15px 10px 15px; }
        .table td { background: #f8fafc; padding: 15px; font-size: 13px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .table td:first-child { border-left: 1px solid var(--border); border-top-left-radius: 12px; border-bottom-left-radius: 12px; font-weight: 600; }
        .table td:last-child { border-right: 1px solid var(--border); border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

        /* Risk Badge */
        .risk-meter { background: #f8fafc; border-radius: 20px; padding: 30px; border: 1px solid var(--border); text-align: center; }
        .score-circle { width: 150px; height: 150px; border-radius: 50%; border: 10px solid #e2e8f0; margin: 0 auto 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; }
        .score-num { font-size: 42px; font-weight: 800; font-family: 'Inter Tight', sans-serif; color: var(--text-main); line-height: 1; }
        .score-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-top: 5px; }
        
        .risk-badge { display: inline-block; padding: 8px 20px; border-radius: 30px; font-weight: 800; font-size: 12px; text-transform: uppercase; border: 1px solid; margin-bottom: 15px; }

        /* Print Styles */
        @media print {
            body { background: white; padding: 0; color: black; }
            .report-container { box-shadow: none; width: 100%; max-width: 100%; border: none; }
            .btn-back, .btn-print, .actions-header { display: none; }
            .kpi-card { border: 1px solid #ddd; }
            .chart-container, .risk-meter { background: white; border: 1px solid #ddd; page-break-inside: avoid; }
            .table td { background: white; border: 1px solid #eee; }
            .section-title { color: black; border-bottom: 2px solid black; }
            .score-circle { border-color: #ddd; }
        }
    </style>
</head>
<body>

    <div class="report-container">
        <!-- Header -->
        <div class="report-header">
            <div class="company-info">
                <p class="customer-id"><?php echo htmlspecialchars($companyName); ?> • Strategic Dossier</p>
                <h1><?php echo htmlspecialchars($customerName); ?></h1>
            </div>
            <div class="actions-header">
                <a href="reports.php?type=credit" class="btn btn-back">Back to List</a>
                <button onclick="window.print()" class="btn btn-print">Print Dossier</button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <p class="kpi-label">Lifetime Value</p>
                <p class="kpi-value"><?php echo $currency; ?><?php echo number_format($summary['total_volume'], 0); ?></p>
                <p class="kpi-sub"><?php echo $summary['total_invoices']; ?> Total Transactions</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Outstanding Balance</p>
                <p class="kpi-value" style="color: #ef4444;"><?php echo $currency; ?><?php echo number_format($summary['outstanding_amount'], 0); ?></p>
                <p class="kpi-sub"><?php echo $summary['unpaid_count']; ?> Unpaid Invoices</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Avg collection speed</p>
                <p class="kpi-value"><?php echo round($summary['avg_days']); ?> Days</p>
                <p class="kpi-sub">Includes live aging penalty</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Collection Rate</p>
                <p class="kpi-value"><?php echo round(($summary['paid_count'] / $summary['total_invoices']) * 100); ?>%</p>
                <p class="kpi-sub"><?php echo $summary['paid_count']; ?> Settled invoices</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Left Side: Trends & Items -->
            <div class="main-report-body">
                <h2 class="section-title">Sales Momentum (Last 24 Months)</h2>
                <div class="chart-container">
                    <canvas id="salesTrendChart" height="150"></canvas>
                </div>

                <h2 class="section-title">Purchasing Habits & Product Distribution</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Category</th>
                            <th class="text-right">Freq</th>
                            <th class="text-right">Value (<?php echo $currency; ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['item_description']); ?></td>
                            <td><span style="font-size: 10px; color: var(--text-muted); font-weight: 700; background: #e2e8f0; padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($p['product_category']); ?></span></td>
                            <td class="text-right"><?php echo $p['frequency']; ?></td>
                            <td class="text-right" style="font-weight: 800;"><?php echo number_format($p['total_value'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Right Side: Risk & Profile -->
            <div class="sidebar-report">
                <h2 class="section-title">Credit Health</h2>
                <div class="risk-meter">
                    <div class="score-circle" style="border-color: <?php echo $riskColor; ?>40;">
                        <span class="score-num"><?php echo $customerScore['credit_score']; ?></span>
                        <span class="score-label">Index</span>
                    </div>
                    <div class="risk-badge" style="background: <?php echo $riskColor; ?>20; color: <?php echo $riskColor; ?>; border-color: <?php echo $riskColor; ?>;">
                        <?php echo $customerScore['risk_level']; ?> Risk Profile
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                        This score is calculated based on an Average Collection Period of <strong><?php echo round($customerScore['avg_days']); ?> days</strong>. 
                        <?php if ($summary['unpaid_count'] > 0): ?>
                        Current risk is elevated by <strong><?php echo $summary['unpaid_count']; ?> outstanding invoices</strong> representing <?php echo $currency; ?><?php echo number_format($summary['outstanding_amount'], 0); ?>.
                        <?php endif; ?>
                    </p>
                </div>

                <h2 class="section-title" style="margin-top: 40px;">Recent Activity</h2>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php 
                    $limit = 5;
                    $count = 0;
                    foreach($history as $h): 
                        if ($count >= $limit) break;
                        $count++;
                        $isPaid = $h['paid_date'] !== null;
                    ?>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border-left: 4px solid <?php echo $isPaid ? '#10b981' : '#ef4444'; ?>;">
                        <p style="font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($h['invoice_number']); ?></p>
                        <p style="font-size: 11px; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($h['invoice_date'])); ?> • <?php echo $currency; ?><?php echo number_format($h['amount'], 0); ?></p>
                        <p style="font-size: 11px; font-weight: 800; color: <?php echo $isPaid ? '#10b981' : '#ef4444'; ?>; text-transform: uppercase; margin-top: 5px;">
                            <?php echo $isPaid ? "Paid in " . $h['days_to_pay'] . " Days" : "Outstanding"; ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trend, 'month')); ?>,
                datasets: [{
                    label: 'Monthly Sales Volume (<?php echo $currency; ?>)',
                    data: <?php echo json_encode(array_column($trend, 'total')); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e2e8f0' },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    </script>
</body>
</html>
