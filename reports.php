<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$reports = new Reports($db);

// AJAX Handler for Customer Details
if (isset($_GET['ajax_customer_history'])) {
    header('Content-Type: application/json');
    $name = $_GET['ajax_customer_history'];
    echo json_encode($reports->getCustomerHistory($name));
    exit;
}

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
        case 'aging':
            $bracket = $_GET['bracket'] ?? 'all';
            $status = $_GET['status'] ?? 'all';
            $sortBy = $_GET['sort'] ?? 'invoice_number';
            $agingData = $reports->getAgingReport($bracket, $status, $sortBy);
            $reportTitle = 'Aging & Collections Report';
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
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <style>
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
                        <input type="text" class="search-input" placeholder="Search reports...">
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
                <div class="report-nav" style="margin-bottom: 25px; border-radius: 12px; background: white; padding: 15px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">

            <div class="report-nav-links">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-right: 10px;">Reports:</span>
                <a href="reports.php?type=monthly" class="report-link <?php echo $type === 'monthly' ? 'active' : ''; ?>"><i class="icon-calendar"></i> Monthly</a>
                <a href="reports.php?type=quarterly" class="report-link <?php echo $type === 'quarterly' ? 'active' : ''; ?>"><i class="icon-trending-up"></i> Quarterly</a>
                <a href="reports.php?type=yearly" class="report-link <?php echo $type === 'yearly' ? 'active' : ''; ?>"><i class="icon-bar-chart-3"></i> Yearly</a>
                <a href="reports.php?type=matrix" class="report-link <?php echo $type === 'matrix' ? 'active' : ''; ?>"><i class="icon-building-2"></i> Matrix</a>
                <a href="reports.php?type=credit" class="report-link <?php echo $type === 'credit' ? 'active' : ''; ?>"><i class="icon-shield-check"></i> Credit Score</a>
                <a href="reports.php?type=aging" class="report-link <?php echo $type === 'aging' ? 'active' : ''; ?>"><i class="icon-clock"></i> Aging Report</a>
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
                                <th class="text-center">Actions</th>
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
                                <td class="text-center">
                                    <button onclick="viewCustomerDetails('<?php echo addslashes($row['customer_name']); ?>')" class="btn-view">More Info</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($type === 'aging'): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2>Aging & Collections Analysis</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Monitor collection momentum and identify high-risk outstanding balances.</p>
                        </div>
                        
                        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
                            <input type="hidden" name="type" value="aging">
                            
                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Aging Bracket</span>
                                <select name="bracket" class="filter-select" style="min-width: 140px;" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($bracket ?? '') == 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="30" <?php echo ($bracket ?? '') == '30' ? 'selected' : ''; ?>>0-30 Days</option>
                                    <option value="60" <?php echo ($bracket ?? '') == '60' ? 'selected' : ''; ?>>31-60 Days</option>
                                    <option value="90" <?php echo ($bracket ?? '') == '90' ? 'selected' : ''; ?>>61-90 Days</option>
                                    <option value="180" <?php echo ($bracket ?? '') == '180' ? 'selected' : ''; ?>>91-180 Days</option>
                                    <option value="365" <?php echo ($bracket ?? '') == '365' ? 'selected' : ''; ?>>181-365 Days</option>
                                    <option value="old" <?php echo ($bracket ?? '') == 'old' ? 'selected' : ''; ?>>Over 1 Year</option>
                                </select>
                            </div>

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Status</span>
                                <select name="status" class="filter-select" style="min-width: 120px;" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($status ?? '') == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="unpaid" <?php echo ($status ?? '') == 'unpaid' ? 'selected' : ''; ?>>Unpaid Only</option>
                                    <option value="paid" <?php echo ($status ?? '') == 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                </select>
                            </div>

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Sort By</span>
                                <select name="sort" class="filter-select" style="min-width: 140px;" onchange="this.form.submit()">
                                    <option value="invoice_number" <?php echo ($sortBy ?? '') == 'invoice_number' ? 'selected' : ''; ?>>Invoice #</option>
                                    <option value="customer_name" <?php echo ($sortBy ?? '') == 'customer_name' ? 'selected' : ''; ?>>Customer Name</option>
                                    <option value="aging" <?php echo ($sortBy ?? '') == 'aging' ? 'selected' : ''; ?>>Aging Severity</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer Name</th>
                                <th>Invoice Date</th>
                                <th>Status</th>
                                <th class="text-right">Days</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($agingData)): ?>
                                <tr><td colspan="7" style="text-align: center; padding: 50px; color: var(--text-muted);">No invoices found for the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach($agingData as $row): 
                                    $isPaid = $row['paid_date'] !== null;
                                    $days = $row['aging_days'];
                                    
                                    $agingColor = '#10b981';
                                    if (!$isPaid) {
                                        if ($days > 180) $agingColor = '#ef4444';
                                        else if ($days > 90) $agingColor = '#fb923c';
                                        else if ($days > 60) $agingColor = '#f59e0b';
                                        else $agingColor = '#6366f1';
                                    }
                                ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td style="color: var(--text-muted);"><?php echo date('M d, Y', strtotime($row['invoice_date'])); ?></td>
                                    <td>
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: <?php echo $isPaid ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $isPaid ? '#15803d' : '#991b1b'; ?>;">
                                            <?php echo $isPaid ? 'Paid' : 'Unpaid'; ?>
                                        </span>
                                    </td>
                                    <td class="text-right" style="font-weight: 800; color: <?php echo $agingColor; ?>;">
                                        <?php echo $days; ?>
                                    </td>
                                    <td class="text-right price-tag">
                                        <?php echo htmlspecialchars($currency) . number_format($row['total_amount'], 0); ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" class="btn-view" style="font-size: 10px; padding: 6px 12px; text-decoration: none;">Strategic Dossier</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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

    <!-- Details Modal -->
    <div id="detailsModalOverlay" class="modal-overlay" onclick="if(event.target === this) closeCustomerDetails()">
        <div class="modal">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle" style="margin: 0; font-size: 24px;">Customer Details</h2>
                    <p id="modalSubtitle" style="color: var(--text-muted); font-size: 14px; margin-top: 4px;"></p>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <a id="modalReportLink" href="#" class="btn-view" style="background: var(--text-main); text-decoration: none; padding: 10px 20px;">Full Analytical Dossier</a>
                    <button class="modal-close" onclick="closeCustomerDetails()">×</button>
                </div>
            </div>
            <div class="modal-body">
                <table class="table" id="detailsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Document / Reference</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="detailsBody">
                        <!-- Content loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function viewCustomerDetails(customerName) {
            const overlay = document.getElementById('detailsModalOverlay');
            const title = document.getElementById('modalTitle');
            const subtitle = document.getElementById('modalSubtitle');
            const body = document.getElementById('detailsBody');
            const reportLink = document.getElementById('modalReportLink');
            
            title.innerText = customerName;
            subtitle.innerText = 'Transaction History & Settlement Audit';
            reportLink.href = `customer_report.php?name=${encodeURIComponent(customerName)}`;
            body.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 50px;">Loading historical data...</td></tr>';
            
            overlay.style.display = 'flex';
            
            fetch(`reports.php?ajax_customer_history=${encodeURIComponent(customerName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        body.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 50px;">No historical invoices found.</td></tr>';
                        return;
                    }
                    
                    let html = '';
                    data.forEach(row => {
                        const isPayment = row.entry_type === 'Payment';
                        const statusColor = (row.status === 'Settled' || row.status === 'Applied') ? '#10b981' : '#f59e0b';
                        const rowStyle = isPayment ? 'background: #fafbfc; color: var(--text-muted);' : '';
                        const typeLabel = isPayment ? 'Payment' : 'Invoice';
                        const docNum = isPayment ? (row.reference ? row.reference : row.doc_num || 'Reference') : row.doc_num;
                        const amountPrefix = isPayment ? '−' : '';
                        
                        html += `
                            <tr style="${rowStyle}">
                                <td>${row.invoice_date}</td>
                                <td>
                                    <span style="font-size: 9px; font-weight: 800; text-transform: uppercase; color: ${isPayment ? 'var(--primary)' : 'var(--text-main)'};">
                                        ${typeLabel}
                                    </span>
                                </td>
                                <td style="font-weight: ${isPayment ? '400' : '600'}; padding-left: ${isPayment ? '25px' : '10px'};">
                                    ${isPayment ? '<span style="color: var(--primary); margin-right: 5px;">↳</span>' : ''}${docNum}
                                </td>
                                <td class="text-right" style="font-weight: 700; color: ${isPayment ? '#059669' : 'var(--primary)'};">
                                    ${amountPrefix}${new Intl.NumberFormat().format(row.amount)}
                                </td>
                                <td class="text-center">
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 20px; background: ${statusColor}20; color: ${statusColor}; font-size: 9px; font-weight: 800; text-transform: uppercase;">
                                        ${row.status}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });
                    body.innerHTML = html;
                })
                .catch(err => {
                    body.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 50px; color: var(--error);">Error loading data. Please try again.</td></tr>';
                    console.error(err);
                });
        }

        function closeCustomerDetails() {
            document.getElementById('detailsModalOverlay').style.display = 'none';
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
