<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';
require_once 'includes/report_methodology.php';

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

// AJAX Handler for Invoice Details (Full Line Items, Serials, Payments)
if (isset($_GET['ajax_invoice_details'])) {
    header('Content-Type: application/json');
    $inv = $_GET['ajax_invoice_details'];
    echo json_encode($reports->getInvoiceDetails($inv));
    exit;
}

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', 'LKR ');
$vatRate = $db->getSetting('vat_rate', '0.18');

$availableYears = $reports->getAvailableYears();
$type = $_GET['type'] ?? 'invoices';
$year = $_GET['year'] ?? (!empty($availableYears) ? $availableYears[0] : date('Y'));
$month = $_GET['month'] ?? ($type === 'invoices' ? 'all' : date('m'));
$quarter = $_GET['quarter'] ?? ceil(date('m') / 3);
$brand = $_GET['brand'] ?? null;
$customer_type = $_GET['customer_type'] ?? null;
$rep_code = $_GET['rep_code'] ?? null;
$salesReps = $db->getSalesReps();

// CSV Export Handler for Invoices Summary
if ($type === 'invoices' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $invoiceFilters = [
        'year' => $year,
        'month' => $month,
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? 'all',
        'brand' => $brand,
        'customer_type' => $customer_type,
        'rep_code' => $rep_code,
        'sort' => $_GET['sort'] ?? 'invoice_date_desc'
    ];
    $exportResult = $reports->getInvoiceSummaryReport($invoiceFilters, 1, 10000);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=invoices_summary_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice Number', 'Type', 'Date', 'Customer Name', 'Customer Type', 'Sales Rep', 'PO Number', 'Line Items', 'Quantity Units', 'Base Net (LKR)', '18% VAT (LKR)', 'Gross Total (LKR)', 'Settlement Status', 'Paid Date', 'Days to Pay']);
    foreach ($exportResult['invoices'] as $r) {
        $statusLabel = (!empty($r['paid_date'])) ? 'Settled' : 'Unpaid';
        fputcsv($out, [
            $r['invoice_number'],
            $r['invoice_type'],
            $r['invoice_date'],
            $r['customer_name'],
            $r['customer_type'] ?? '',
            $r['rep_name'] ?? $r['sales_rep_code'],
            $r['po_number'] ?? '',
            $r['line_count'],
            $r['total_quantity'],
            round($r['total_base_value'], 2),
            round($r['total_vat_component'], 2),
            round($r['total_gross_amount'], 2),
            $statusLabel,
            $r['paid_date'] ?? '',
            $r['days_to_pay'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

$reportData = [];
$reportTitle = '';
$customerPivot = [];
$uniqueBrands = $reports->getUniqueBrands();

if ($type === 'matrix') {
    $customerPivot = $reports->getCustomerYearlyPivot($year, $brand, $customer_type, $rep_code);
    $reportTitle = "Customer Performance Matrix - $year" . ($brand ? " ($brand)" : "");
} else {
    switch($type) {
        case 'invoices':
            $status = $_GET['status'] ?? 'all';
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? 'invoice_date_desc';
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $invoiceFilters = [
                'year' => $year,
                'month' => $month,
                'search' => $search,
                'status' => $status,
                'brand' => $brand,
                'customer_type' => $customer_type,
                'rep_code' => $rep_code,
                'sort' => $sort
            ];
            $invoiceResult = $reports->getInvoiceSummaryReport($invoiceFilters, $p, $limit);
            $invoiceData = $invoiceResult['invoices'];
            $invoiceTotal = $invoiceResult['total'];
            $invoicePages = $invoiceResult['pages'];
            $invoiceSummary = $invoiceResult['summary'];
            $reportTitle = 'Invoice Summary & Line-Item Audit';
            break;
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
        case 'stock':
            $fsn = $_GET['fsn'] ?? 'all';
            $search = $_GET['search'] ?? '';
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 50;
            $offset = ($p - 1) * $limit;
            $stockResult = $reports->getStockMovementAnalysis($brand, $fsn, $search, $limit, $offset);
            $stockData = $stockResult['items'];
            $stockTotal = $stockResult['total'];
            $stockPages = max(1, (int)ceil($stockTotal / $limit));
            $reportTitle = 'Stock Movement & Inventory Velocity (FSN)';
            break;
        case 'rfm':
            $segment = $_GET['segment'] ?? 'all';
            $rfmData = $reports->getRFMAnalysis($segment);
            $reportTitle = 'RFM Customer Segmentation & Churn Risk';
            break;
        case 'partners':
            $cohortData = $reports->getPartnerCohortAnalysis();
            $reportTitle = 'Partner vs. End-Customer Cohort Analysis';
            break;
        case 'reps':
            $repsData = $reports->getSalesRepPerformance($year);
            $reportTitle = 'Sales Rep Performance & Collection Health';
            break;
        case 'warranties':
            $warrantyStatus = $_GET['status'] ?? 'all';
            $warrantySearch = $_GET['search'] ?? '';
            $warrantyBrand = $_GET['brand'] ?? 'all';
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 50;
            $warrantyFilters = [
                'status' => $warrantyStatus,
                'search' => $warrantySearch,
                'brand' => $warrantyBrand
            ];
            $warrantyResult = $reports->getWarrantyReport($warrantyFilters, $p, $limit);
            $warrantyAssets = $warrantyResult['assets'];
            $warrantyTotal = $warrantyResult['total'];
            $warrantyPages = $warrantyResult['pages'];
            $warrantyKpis = $warrantyResult['kpis'];
            $reportTitle = 'Hardware Warranty Registry & Serial Number Tracking';
            break;
        case 'renewals':
            $renewalStatus = $_GET['status'] ?? 'all';
            $renewalSearch = $_GET['search'] ?? '';
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 50;
            $renewalFilters = [
                'status' => $renewalStatus,
                'search' => $renewalSearch
            ];
            $renewalResult = $reports->getRenewalsReport($renewalFilters, $p, $limit);
            $renewalSubs = $renewalResult['subscriptions'];
            $renewalTotal = $renewalResult['total'];
            $renewalPages = $renewalResult['pages'];
            $renewalKpis = $renewalResult['kpis'];
            $renewalCalendar = $renewalResult['calendar'];
            $reportTitle = 'Software & SaaS Renewals Pipeline';
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
    <link rel="stylesheet" href="layout.css?v=2.0.0">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search reports...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <!-- 38px Unified Command Bar -->
                <div class="command-bar">
                    <div class="cmd-left">
                        <div class="cmd-group">
                            <span class="cmd-label"><i class="icon-sliders" style="font-size: 11px;"></i> View:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type='+this.value">
                                <option value="invoices" <?php echo $type === 'invoices' ? 'selected' : ''; ?>>Commercial Invoices</option>
                                <option value="warranties" <?php echo $type === 'warranties' ? 'selected' : ''; ?>>Hardware S/N Registry</option>
                                <option value="renewals" <?php echo $type === 'renewals' ? 'selected' : ''; ?>>SaaS & Renewals Pipeline</option>
                                <option value="monthly" <?php echo $type === 'monthly' ? 'selected' : ''; ?>>Monthly Performance</option>
                                <option value="quarterly" <?php echo $type === 'quarterly' ? 'selected' : ''; ?>>Quarterly Performance</option>
                                <option value="yearly" <?php echo $type === 'yearly' ? 'selected' : ''; ?>>Yearly Performance</option>
                                <option value="matrix" <?php echo $type === 'matrix' ? 'selected' : ''; ?>>Customer Matrix</option>
                                <option value="stock" <?php echo $type === 'stock' ? 'selected' : ''; ?>>Stock Movement (FSN)</option>
                                <option value="rfm" <?php echo $type === 'rfm' ? 'selected' : ''; ?>>RFM / Churn Risk</option>
                                <option value="partners" <?php echo $type === 'partners' ? 'selected' : ''; ?>>Partner Cohorts</option>
                                <option value="reps" <?php echo $type === 'reps' ? 'selected' : ''; ?>>Sales Reps & DSO</option>
                                <option value="credit" <?php echo $type === 'credit' ? 'selected' : ''; ?>>Credit Health</option>
                                <option value="aging" <?php echo $type === 'aging' ? 'selected' : ''; ?>>Aging & Collections</option>
                            </select>
                        </div>

                        <div class="cmd-group">
                            <span class="cmd-label">Year:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=<?php echo $type; ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&status=<?php echo urlencode($status ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&month=<?php echo urlencode($month ?? ''); ?>&year='+this.value">
                                <option value="all" <?php echo $year === 'all' ? 'selected' : ''; ?>>All Years</option>
                                <?php foreach ($availableYears as $y): ?>
                                    <option value="<?php echo htmlspecialchars($y); ?>" <?php echo (string)$year === (string)$y ? 'selected' : ''; ?>><?php echo htmlspecialchars($y); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($type === 'invoices'): ?>
                        <div class="cmd-group">
                            <span class="cmd-label">Month:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=invoices&year=<?php echo urlencode($year); ?>&status=<?php echo urlencode($status ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&month='+this.value">
                                <option value="all" <?php echo ($month === 'all' || empty($month)) ? 'selected' : ''; ?>>All Months</option>
                                <?php 
                                $monthsList = [
                                    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar',
                                    '04' => 'Apr', '05' => 'May', '06' => 'Jun',
                                    '07' => 'Jul', '08' => 'Aug', '09' => 'Sep',
                                    '10' => 'Oct', '11' => 'Nov', '12' => 'Dec'
                                ];
                                foreach($monthsList as $mCode => $mName): ?>
                                    <option value="<?php echo $mCode; ?>" <?php echo ((string)$month === (string)$mCode || (string)$month === (string)(int)$mCode) ? 'selected' : ''; ?>><?php echo $mName; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cmd-group">
                            <span class="cmd-label">Status:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=invoices&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&status='+this.value">
                                <option value="all" <?php echo ($status ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="settled" <?php echo ($status ?? '') === 'settled' ? 'selected' : ''; ?>>Settled</option>
                                <option value="unpaid" <?php echo ($status ?? '') === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            </select>
                        </div>

                        <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 4px; margin: 0;">
                            <input type="hidden" name="type" value="invoices">
                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
                            <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? 'all'); ?>">
                            <?php if (!empty($brand)): ?><input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>"><?php endif; ?>
                            <?php if (!empty($customer_type)): ?><input type="hidden" name="customer_type" value="<?php echo htmlspecialchars($customer_type); ?>"><?php endif; ?>
                            <?php if (!empty($rep_code)): ?><input type="hidden" name="rep_code" value="<?php echo htmlspecialchars($rep_code); ?>"><?php endif; ?>
                            <input type="text" name="search" class="cmd-input" placeholder="Search invoice, customer, PO, S/N..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="width: 190px;">
                            <button type="submit" class="cmd-btn" title="Search"><i class="icon-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="reports.php?type=invoices&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month); ?>&status=<?php echo urlencode($status ?? 'all'); ?>" class="cmd-btn" title="Clear search"><i class="icon-x"></i></a>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="cmd-right">
                        <?php if ($type === 'invoices'): ?>
                            <a href="reports.php?type=invoices&export=csv&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&search=<?php echo urlencode($search ?? ''); ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&sort=<?php echo urlencode($sort ?? ''); ?>" class="cmd-btn cmd-btn-primary" title="Download filtered data as CSV">
                                <i class="icon-download"></i> CSV
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

        <div class="main-content">
            <?php renderReportMethodology($type, $currency); ?>

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

            <?php elseif ($type === 'stock'): ?>
                <!-- Stock Movement & Velocity View -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2>Stock Movement & Inventory Velocity (FSN)</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Fast, slow, and non-moving stock classification based on dispatch velocity.</p>
                        </div>

                        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="type" value="stock">

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Search SKU / Serial</span>
                                <input type="text" name="search" class="filter-select" placeholder="Search item or serial..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="min-width: 180px;">
                            </div>
                            
                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Brand / Category</span>
                                <select name="brand" class="filter-select" onchange="this.form.submit()">
                                    <option value="">All Brands</option>
                                    <?php foreach($uniqueBrands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo ($brand ?? '') === $b['product_category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['product_category']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Velocity (FSN)</span>
                                <select name="fsn" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($fsn ?? '') == 'all' ? 'selected' : ''; ?>>All Velocities</option>
                                    <option value="F" <?php echo ($fsn ?? '') == 'F' ? 'selected' : ''; ?>>Fast-Moving (F)</option>
                                    <option value="S" <?php echo ($fsn ?? '') == 'S' ? 'selected' : ''; ?>>Slow-Moving (S)</option>
                                    <option value="N" <?php echo ($fsn ?? '') == 'N' ? 'selected' : ''; ?>>Non-Moving / Dormant (N)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-view" style="padding: 8px 16px;">Filter</button>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product / SKU Description</th>
                                <th>Brand / Category</th>
                                <th class="text-right">Units Dispatched</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Active Months</th>
                                <th>Last Movement</th>
                                <th class="text-right">Days Inactive</th>
                                <th class="text-center">Velocity</th>
                                <th class="text-right">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stockData)): ?>
                                <tr><td colspan="9" style="text-align: center; padding: 50px; color: var(--text-muted);">No stock movement records match the filters.</td></tr>
                            <?php else: ?>
                                <?php foreach($stockData as $row): 
                                    $vColor = '#10b981'; // Fast
                                    if ($row['velocity_code'] === 'S') $vColor = '#f59e0b'; // Slow
                                    if ($row['velocity_code'] === 'N') $vColor = '#64748b'; // Non-moving
                                ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($row['item_description']); ?>
                                        <?php if ($row['is_serialized']): ?>
                                            <span style="font-size: 10px; background: #e0e7ff; color: #4338ca; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 6px;">SERIALIZED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge" style="background: #f1f5f9; color: #334155;"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                    <td class="text-right" style="font-weight: 800; font-family: 'Inter Tight', sans-serif;"><?php echo number_format($row['total_units']); ?></td>
                                    <td class="text-right"><?php echo number_format($row['dispatch_count']); ?></td>
                                    <td class="text-right"><?php echo $row['active_months']; ?> mos</td>
                                    <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('M d, Y', strtotime($row['last_dispatch'])); ?></td>
                                    <td class="text-right" style="font-weight: 700; color: <?php echo $row['days_since_dispatch'] > 180 ? '#ef4444' : ($row['days_since_dispatch'] > 60 ? '#f59e0b' : '#10b981'); ?>;">
                                        <?php echo $row['days_since_dispatch']; ?>d
                                    </td>
                                    <td class="text-center">
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: <?php echo $vColor; ?>20; color: <?php echo $vColor; ?>; border: 1px solid <?php echo $vColor; ?>40;">
                                            <?php echo $row['velocity']; ?>
                                        </span>
                                    </td>
                                    <td class="text-right price-tag">
                                        <?php echo htmlspecialchars($currency) . number_format($row['total_revenue'], 0); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($stockPages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-top: 1px solid var(--border-color); background: #f8fafc; flex-wrap: wrap; gap: 10px;">
                        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
                            Showing <strong><?php echo min($stockTotal, $offset + 1); ?>-<?php echo min($stockTotal, $offset + count($stockData)); ?></strong> of <strong><?php echo number_format($stockTotal); ?></strong> products
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <?php if ($p > 1): ?>
                                <a href="reports.php?type=stock&brand=<?php echo urlencode($brand ?? ''); ?>&fsn=<?php echo urlencode($fsn ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&p=<?php echo $p - 1; ?>" class="btn-view" style="text-decoration: none; padding: 6px 12px;">Previous</a>
                            <?php endif; ?>
                            <span style="font-size: 13px; font-weight: 700; align-self: center; padding: 0 10px;">Page <?php echo $p; ?> of <?php echo $stockPages; ?></span>
                            <?php if ($p < $stockPages): ?>
                                <a href="reports.php?type=stock&brand=<?php echo urlencode($brand ?? ''); ?>&fsn=<?php echo urlencode($fsn ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&p=<?php echo $p + 1; ?>" class="btn-view" style="text-decoration: none; padding: 6px 12px;">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($type === 'rfm'): ?>
                <!-- RFM Customer Segmentation & Churn Risk View -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2>RFM Customer Segmentation & Churn Prevention</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Behavioral clustering based on Recency, Frequency, and Monetary spend.</p>
                        </div>

                        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
                            <input type="hidden" name="type" value="rfm">
                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Behavioral Segment</span>
                                <select name="segment" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($segment ?? '') == 'all' ? 'selected' : ''; ?>>All Segments</option>
                                    <option value="Champion" <?php echo ($segment ?? '') == 'Champion' ? 'selected' : ''; ?>>Champions</option>
                                    <option value="Loyal Account" <?php echo ($segment ?? '') == 'Loyal Account' ? 'selected' : ''; ?>>Loyal Accounts</option>
                                    <option value="Potential Loyalist" <?php echo ($segment ?? '') == 'Potential Loyalist' ? 'selected' : ''; ?>>Potential Loyalists</option>
                                    <option value="Recent Buyer" <?php echo ($segment ?? '') == 'Recent Buyer' ? 'selected' : ''; ?>>Recent Buyers</option>
                                    <option value="At Risk" <?php echo ($segment ?? '') == 'At Risk' ? 'selected' : ''; ?>>At Risk (Churn Alert)</option>
                                    <option value="Needs Attention" <?php echo ($segment ?? '') == 'Needs Attention' ? 'selected' : ''; ?>>Needs Attention</option>
                                    <option value="Lost / Dormant" <?php echo ($segment ?? '') == 'Lost / Dormant' ? 'selected' : ''; ?>>Lost / Dormant</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Channel</th>
                                <th>Sales Rep</th>
                                <th>Last Order</th>
                                <th class="text-right">Inactivity</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Net Base Spend</th>
                                <th class="text-center">Segment</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rfmData as $row): ?>
                            <tr>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><span class="badge <?php echo $row['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>"><?php echo htmlspecialchars($row['customer_type']); ?></span></td>
                                <td style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($row['sales_rep']); ?></td>
                                <td style="color: var(--text-muted);"><?php echo date('M d, Y', strtotime($row['last_order_date'])); ?></td>
                                <td class="text-right" style="font-weight: 800; color: <?php echo $row['recency_days'] > 120 ? '#ef4444' : ($row['recency_days'] > 60 ? '#f59e0b' : '#10b981'); ?>;">
                                    <?php echo $row['recency_days']; ?> Days
                                </td>
                                <td class="text-right"><?php echo $row['frequency']; ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['monetary'], 0); ?></td>
                                <td class="text-center">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; background: <?php echo $row['segment_color']; ?>20; color: <?php echo $row['segment_color']; ?>; border: 1px solid <?php echo $row['segment_color']; ?>40;">
                                        <?php echo $row['segment']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" class="btn-view" style="font-size: 10px; padding: 6px 12px; text-decoration: none;">Strategic Dossier</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($type === 'partners'): ?>
                <!-- Partner vs End Customer Cohort View -->
                <div class="card" style="margin-bottom: 25px;">
                    <h2>Partner vs. End-Customer Cohort Analysis</h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Channel performance breakdown comparing B2B partners against direct end customers.</p>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer Channel</th>
                                <th class="text-right">Active Accounts</th>
                                <th class="text-right">Orders / Invoices</th>
                                <th class="text-right">Gross Revenue</th>
                                <th class="text-right">Net Base Revenue</th>
                                <th class="text-right">Avg Order Value</th>
                                <th class="text-right">Avg Turnaround (DSO)</th>
                                <th class="text-right">Revenue Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cohortData as $row): ?>
                            <tr>
                                <td style="font-weight: 700; font-size: 15px;">
                                    <span class="badge <?php echo $row['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>" style="font-size: 13px; padding: 6px 14px;">
                                        <?php echo htmlspecialchars($row['customer_type']); ?>
                                    </span>
                                </td>
                                <td class="text-right" style="font-weight: 700;"><?php echo number_format($row['total_accounts']); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_orders']); ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['total_gross'], 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($row['total_base'], 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($row['avg_order_value'], 0); ?></td>
                                <td class="text-right" style="font-weight: 800; color: #0284c7;"><?php echo $row['avg_days_to_pay']; ?> Days</td>
                                <td class="text-right" style="font-weight: 800; font-family: 'Inter Tight', sans-serif;"><?php echo $row['revenue_share_pct']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($type === 'reps'): ?>
                <!-- Sales Rep Performance & Collection Health View -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2>Sales Rep Performance & DSO Collection Efficiency</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Revenue contribution, customer reach, and payment collection turnaround per representative.</p>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rep Code</th>
                                <th>Representative Name</th>
                                <th class="text-right">Invoices</th>
                                <th class="text-right">Active Clients</th>
                                <th class="text-right">Gross Sales</th>
                                <th class="text-right">Collected Revenue</th>
                                <th class="text-right">Outstanding</th>
                                <th class="text-right">Collection Rate</th>
                                <th class="text-right">Avg DSO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($repsData as $row): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 800; color: var(--primary); font-size: 14px;"><?php echo htmlspecialchars($row['sales_rep_code']); ?></td>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($row['rep_name']); ?></td>
                                <td class="text-right"><?php echo number_format($row['invoice_count']); ?></td>
                                <td class="text-right"><?php echo number_format($row['client_count']); ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['gross_revenue'], 0); ?></td>
                                <td class="text-right" style="color: #15803d; font-weight: 700;"><?php echo htmlspecialchars($currency) . number_format($row['collected_revenue'], 0); ?></td>
                                <td class="text-right" style="color: #b91c1c; font-weight: 700;"><?php echo htmlspecialchars($currency) . number_format($row['outstanding_revenue'], 0); ?></td>
                                <td class="text-right" style="font-weight: 800;"><?php echo $row['collection_rate_pct']; ?>%</td>
                                <td class="text-right" style="font-weight: 800; color: <?php echo $row['avg_dso'] > 60 ? '#ef4444' : ($row['avg_dso'] > 40 ? '#f59e0b' : '#10b981'); ?>;">
                                    <?php echo $row['avg_dso']; ?> Days
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($type === 'invoices'): ?>
                <!-- High-Density Financial Metrics Ribbon -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Invoices</span>
                        <span class="metric-pill-val"><?php echo number_format($invoiceTotal); ?></span>
                        <span class="metric-pill-sub"><?php echo number_format($invoiceSummary['unique_customers'] ?? 0); ?> Unique Clients</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Billed</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($invoiceSummary['grand_gross_revenue'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub"><?php echo number_format($invoiceSummary['grand_total_qty'] ?? 0); ?> Units Dispatched</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Net Base (Pre-VAT)</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($invoiceSummary['grand_base_value'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Core Recognized Sales</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Statutory 18% VAT</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo htmlspecialchars($currency) . number_format($invoiceSummary['grand_total_vat'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">IRD Tax Liability</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Settlement Realization</span>
                        <span class="metric-pill-val" style="color: #15803d; font-size: 14px;">
                            Settled: <?php echo htmlspecialchars($currency) . number_format($invoiceSummary['settled_amount'] ?? 0, 0); ?>
                        </span>
                        <span class="metric-pill-sub" style="color: #b91c1c; font-weight: 600;">
                            Unpaid: <?php echo htmlspecialchars($currency) . number_format($invoiceSummary['unpaid_amount'] ?? 0, 0); ?> (<?php echo number_format($invoiceSummary['unpaid_invoices_count'] ?? 0); ?> inv)
                        </span>
                    </div>
                </div>

                <!-- Secondary Quick Filters -->
                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="invoices">
                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? 'all'); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Brand:</span>
                        <select name="brand" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Brands</option>
                            <?php foreach($uniqueBrands as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo ($brand ?? '') === $b['product_category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['product_category']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Category:</span>
                        <select name="customer_type" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="Partner" <?php echo ($customer_type ?? '') === 'Partner' ? 'selected' : ''; ?>>Partners Only</option>
                            <option value="End Customer" <?php echo ($customer_type ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customers</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Rep:</span>
                        <select name="rep_code" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Reps</option>
                            <?php foreach($salesReps as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['rep_code']); ?>" <?php echo ($rep_code ?? '') === $r['rep_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['rep_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Sort:</span>
                        <select name="sort" class="cmd-select" onchange="this.form.submit()">
                            <option value="invoice_date_desc" <?php echo ($sort ?? '') === 'invoice_date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="invoice_date_asc" <?php echo ($sort ?? '') === 'invoice_date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="amount_desc" <?php echo ($sort ?? '') === 'amount_desc' ? 'selected' : ''; ?>>Amount (High to Low)</option>
                            <option value="amount_asc" <?php echo ($sort ?? '') === 'amount_asc' ? 'selected' : ''; ?>>Amount (Low to High)</option>
                            <option value="invoice_number_asc" <?php echo ($sort ?? '') === 'invoice_number_asc' ? 'selected' : ''; ?>>Invoice # (A-Z)</option>
                        </select>
                    </div>

                    <?php if (!empty($brand) || !empty($customer_type) || !empty($rep_code) || ($sort ?? '') !== 'invoice_date_desc'): ?>
                        <a href="reports.php?type=invoices&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&search=<?php echo urlencode($search ?? ''); ?>" class="cmd-btn" style="height: 24px; font-size: 10.5px;">Reset Filters</a>
                    <?php endif; ?>
                </form>

                <!-- Split Master-Detail Layout -->
                <div class="split-layout" id="splitLayout">
                    <!-- Left Grid: High-Density Table -->
                    <div class="split-grid" id="splitGrid">
                        <div class="split-table-wrapper">
                            <table class="rational-table" id="invoicesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 76px;">Date</th>
                                        <th style="width: 98px;">Invoice #</th>
                                        <th>Customer</th>
                                        <th style="width: 44px;">Rep</th>
                                        <th style="width: 80px;">PO #</th>
                                        <th style="width: 140px;">Identified Data</th>
                                        <th class="text-right" style="width: 92px;">Base Net</th>
                                        <th class="text-right" style="width: 84px;">18% VAT</th>
                                        <th class="text-right" style="width: 98px;">Gross Total</th>
                                        <th class="text-center" style="width: 68px;">Status</th>
                                        <th class="text-center" style="width: 58px;">Audit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($invoiceData)): ?>
                                    <tr>
                                        <td colspan="11" style="text-align: center; padding: 40px 15px; color: var(--text-muted);">
                                            No commercial invoices found for the active criteria. Try adjusting the search keywords or year filter.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($invoiceData as $row): 
                                        $isCredit = strcasecmp($row['invoice_type'], 'Credit Memo') === 0;
                                        $isSettled = !empty($row['paid_date']);
                                        $hasSerials = !empty($row['has_serials']);
                                        $taxTreat = $row['invoice_vat_treatment'] ?? '';
                                    ?>
                                    <tr onclick="selectInvoiceRow('<?php echo htmlspecialchars($row['invoice_number']); ?>', this)">
                                        <td style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo htmlspecialchars($row['invoice_date']); ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <span class="dense-doc-num">
                                                    <?php echo htmlspecialchars($row['invoice_number']); ?>
                                                </span>
                                                <?php if ($isCredit): ?>
                                                    <span class="dense-badge dense-badge-credit">CR</span>
                                                <?php endif; ?>
                                                <?php if ($hasSerials): ?>
                                                    <span class="dense-badge dense-badge-sn" title="Hardware Serial Numbers Registered">S/N</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;">
                                                <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size: 11px; color: #475569;" title="<?php echo htmlspecialchars($row['rep_name']); ?>">
                                                <?php echo htmlspecialchars($row['sales_rep_code'] ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['po_number'])): ?>
                                                <span style="font-size: 10.5px; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; color: #334155;">
                                                    <?php echo htmlspecialchars(substr($row['po_number'], 0, 14)); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 4px; flex-wrap: nowrap;">
                                                <span style="font-size: 10.5px; font-weight: 600; color: #475569;">
                                                    <?php echo $row['items_count'] ?: $row['line_count']; ?> itm
                                                </span>
                                                <?php if (!empty($row['hardware_count'])): ?>
                                                    <span class="dense-badge dense-badge-hw" title="<?php echo $row['hardware_count']; ?> hardware units (<?php echo $row['serials_count']; ?> serialized)">
                                                        HW:<?php echo $row['hardware_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['subscriptions_count'])): ?>
                                                    <span class="dense-badge dense-badge-ma" title="<?php echo $row['subscriptions_count']; ?> software subscriptions / maintenance agreements">
                                                        MA:<?php echo $row['subscriptions_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num" style="color: #475569;">
                                            <?php echo number_format($row['total_base_value'], 0); ?>
                                        </td>
                                        <td class="text-right dense-num" style="color: #64748b;">
                                            <?php echo number_format($row['total_vat_component'], 0); ?>
                                        </td>
                                        <td class="text-right dense-num-bold dense-num" style="white-space: nowrap;">
                                            <?php echo number_format($row['total_gross_amount'], 0); ?>
                                            <?php if ($taxTreat === 'PLUS_VAT'): ?>
                                                <span class="dense-badge dense-badge-plusvat" title="Plus VAT (+18% statutory breakdown)" style="font-size: 8.5px; padding: 1px 3px; margin-left: 2px;">+VAT</span>
                                            <?php elseif ($taxTreat === 'VAT_INCLUSIVE'): ?>
                                                <span class="dense-badge dense-badge-inclusive" title="VAT Inclusive pricing (no separate VAT breakdown)" style="font-size: 8.5px; padding: 1px 3px; margin-left: 2px;">Inc</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="dense-badge <?php echo $isSettled ? 'dense-badge-settled' : 'dense-badge-unpaid'; ?>">
                                                <?php echo $isSettled ? 'Paid' : 'Unpaid'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;" onclick="event.stopPropagation(); selectInvoiceRow('<?php echo htmlspecialchars($row['invoice_number']); ?>', this.closest('tr'))">
                                                Audit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Split Table Pagination Footer -->
                        <div class="split-pagination">
                            <div>
                                Showing <strong><?php echo number_format(min($invoiceTotal, ($p - 1) * $limit + 1)); ?></strong>–<strong><?php echo number_format(min($invoiceTotal, $p * $limit)); ?></strong> of <strong><?php echo number_format($invoiceTotal); ?></strong>
                            </div>
                            <?php if ($invoicePages > 1): ?>
                            <div style="display: flex; gap: 3px; align-items: center;">
                                <?php 
                                $baseUrl = "reports.php?type=invoices&year=" . urlencode($year) . "&month=" . urlencode($month) . "&status=" . urlencode($status) . "&search=" . urlencode($search) . "&brand=" . urlencode($brand ?? '') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&sort=" . urlencode($sort ?? '');
                                
                                if ($p > 1): ?>
                                    <a href="<?php echo $baseUrl; ?>&p=<?php echo ($p - 1); ?>" class="cmd-btn" style="height: 22px; padding: 0 6px;">« Prev</a>
                                <?php endif; ?>

                                <?php 
                                $startPage = max(1, $p - 1);
                                $endPage = min($invoicePages, $p + 1);
                                for($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="<?php echo $baseUrl; ?>&p=<?php echo $i; ?>" class="cmd-btn <?php echo $i === $p ? 'cmd-btn-primary' : ''; ?>" style="height: 22px; padding: 0 6px;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($p < $invoicePages): ?>
                                    <a href="<?php echo $baseUrl; ?>&p=<?php echo ($p + 1); ?>" class="cmd-btn" style="height: 22px; padding: 0 6px;">Next »</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Drawer: Master-Detail Side Audit Inspector -->
                    <div class="split-drawer drawer-collapsed" id="sideAuditDrawer">
                        <div class="drawer-header">
                            <div class="drawer-title-area">
                                <i class="icon-file-text" style="color: #818cf8; font-size: 13px;"></i>
                                <span class="drawer-title" id="drawerTitle">Invoice Audit</span>
                            </div>
                            <div class="drawer-controls">
                                <button type="button" class="drawer-ctrl-btn" onclick="toggleDrawerFullscreen()" title="Toggle Fullscreen Inspector">
                                    <i class="icon-maximize-2" id="drawerExpandIcon"></i> Full
                                </button>
                                <button type="button" class="drawer-ctrl-btn" onclick="closeDrawer()" title="Close Drawer">
                                    <i class="icon-x"></i>
                                </button>
                            </div>
                        </div>
                        <div class="drawer-body" id="drawerBody">
                            <div style="text-align: center; padding: 40px 15px; color: var(--text-muted); font-size: 12px;">
                                Select an invoice row to inspect line items, serial numbers, and payment reconciliation.
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($type === 'warranties'): ?>
                <!-- Warranty KPI Ribbon -->
                <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
                    <div class="metric-card">
                        <div class="metric-label">Total Serialized Assets</div>
                        <div class="metric-value"><?php echo number_format($warrantyKpis['total_assets'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Registered Hardware Units
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Active Warranty</div>
                        <div class="metric-value" style="color: #15803d;"><?php echo number_format($warrantyKpis['active_assets'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Currently Under Protection
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expiring ≤ 30 Days</div>
                        <div class="metric-value" style="color: #b45309;"><?php echo number_format($warrantyKpis['expiring_30d'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Urgent Renewal / SLA Alert
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expiring ≤ 90 Days</div>
                        <div class="metric-value" style="color: #6366f1;"><?php echo number_format(($warrantyKpis['expiring_30d'] ?? 0) + ($warrantyKpis['expiring_60d'] ?? 0) + ($warrantyKpis['expiring_90d'] ?? 0)); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Upcoming Renewal Pipeline
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expired Warranties</div>
                        <div class="metric-value" style="color: #b91c1c;"><?php echo number_format($warrantyKpis['expired_assets'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Post-Warranty Support Potential
                        </div>
                    </div>
                </div>

                <!-- Hardware Assets Registry Card -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2 style="margin: 0; font-size: 22px;">Hardware Asset & Warranty Registry</h2>
                            <p style="color: var(--text-muted); font-size: 14px; margin: 4px 0 0 0;">
                                Serial-level inventory tracking with warranty duration, expiry dates, and parent-chassis relationships.
                            </p>
                        </div>
                    </div>

                    <!-- Filter Controls -->
                    <form method="GET" action="reports.php" class="filter-controls" style="margin-bottom: 25px; background: #f8fafc; padding: 18px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                        <input type="hidden" name="type" value="warranties">

                        <div class="filter-group" style="flex: 1; min-width: 220px; margin: 0;">
                            <span class="filter-label">Search Serial / Product / Customer / Invoice</span>
                            <input type="text" name="search" class="filter-select" placeholder="Serial number, product, customer..." value="<?php echo htmlspecialchars($warrantySearch ?? ''); ?>" style="width: 100%;">
                        </div>

                        <div class="filter-group" style="margin: 0;">
                            <span class="filter-label">Brand</span>
                            <select name="brand" class="filter-select" style="min-width: 140px;">
                                <option value="all">All Brands</option>
                                <?php foreach($uniqueBrands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo ($warrantyBrand ?? 'all') === $b['product_category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['product_category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group" style="margin: 0;">
                            <span class="filter-label">Warranty Status</span>
                            <select name="status" class="filter-select" style="min-width: 160px;">
                                <option value="all" <?php echo ($warrantyStatus ?? 'all') === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="ACTIVE" <?php echo ($warrantyStatus ?? '') === 'ACTIVE' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="EXPIRING_30D" <?php echo ($warrantyStatus ?? '') === 'EXPIRING_30D' ? 'selected' : ''; ?>>Expiring in 30 Days</option>
                                <option value="EXPIRING_60D" <?php echo ($warrantyStatus ?? '') === 'EXPIRING_60D' ? 'selected' : ''; ?>>Expiring in 60 Days</option>
                                <option value="EXPIRING_90D" <?php echo ($warrantyStatus ?? '') === 'EXPIRING_90D' ? 'selected' : ''; ?>>Expiring in 90 Days</option>
                                <option value="EXPIRED" <?php echo ($warrantyStatus ?? '') === 'EXPIRED' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-view" style="padding: 10px 18px;"><i class="icon-filter"></i> Filter</button>
                            <?php if (!empty($warrantySearch) || ($warrantyStatus ?? 'all') !== 'all' || ($warrantyBrand ?? 'all') !== 'all'): ?>
                                <a href="reports.php?type=warranties" class="btn-view" style="background: #e2e8f0; color: #475569; text-decoration: none; padding: 10px 14px;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Assets Table -->
                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="min-width: 140px;">Serial Number</th>
                                    <th style="min-width: 220px;">Product Name & Model</th>
                                    <th>Brand</th>
                                    <th style="min-width: 180px;">Customer</th>
                                    <th>Invoice #</th>
                                    <th class="text-center">Warranty Term</th>
                                    <th>Expiry Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($warrantyAssets)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                                        <i class="icon-shield" style="font-size: 36px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                                        No hardware assets found. Run the AI Entity Extractor from <a href="settings.php" style="color: var(--primary); font-weight: 700;">Settings</a> to normalize legacy and active invoice lines into discrete serialized units.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach($warrantyAssets as $asset): 
                                    $st = $asset['dynamic_status'];
                                    $stBadgeBg = '#ecfdf5';
                                    $stBadgeColor = '#15803d';
                                    $stLabel = 'Active (' . $asset['days_remaining'] . 'd)';

                                    if ($st === 'EXPIRED') {
                                        $stBadgeBg = '#fee2e2';
                                        $stBadgeColor = '#b91c1c';
                                        $stLabel = 'Expired';
                                    } elseif ($st === 'EXPIRING_30D') {
                                        $stBadgeBg = '#fef3c7';
                                        $stBadgeColor = '#b45309';
                                        $stLabel = 'Expiring (' . $asset['days_remaining'] . 'd)';
                                    } elseif ($st === 'EXPIRING_60D' || $st === 'EXPIRING_90D') {
                                        $stBadgeBg = '#e0e7ff';
                                        $stBadgeColor = '#4338ca';
                                        $stLabel = 'Expiring (' . $asset['days_remaining'] . 'd)';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 800; font-size: 13px; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; color: #1e293b; border: 1px solid #e2e8f0;">
                                            <?php echo htmlspecialchars($asset['serial_number']); ?>
                                        </span>
                                        <?php if (!empty($asset['parent_serial_number'])): ?>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 3px;">
                                                Chassis S/N: <span style="font-family: monospace;"><?php echo htmlspecialchars($asset['parent_serial_number']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 13px;">
                                            <?php echo htmlspecialchars($asset['product_name']); ?>
                                        </div>
                                        <?php if (!empty($asset['model_sku'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;">
                                                SKU: <?php echo htmlspecialchars($asset['model_sku']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; background: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 700;">
                                            <?php echo htmlspecialchars($asset['brand'] ?: 'Hardware'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="customer_report.php?name=<?php echo urlencode($asset['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600; font-size: 13px;">
                                            <?php echo htmlspecialchars($asset['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 13px;">
                                            <?php echo htmlspecialchars($asset['invoice_number']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center" style="font-size: 12px; font-weight: 700; color: #475569;">
                                        <?php echo $asset['warranty_months'] ? ($asset['warranty_months'] . ' Months') : 'Standard'; ?>
                                    </td>
                                    <td style="font-size: 13px; font-weight: 600; color: #334155;">
                                        <?php echo htmlspecialchars($asset['warranty_expiry_date'] ?: '—'); ?>
                                    </td>
                                    <td class="text-center">
                                        <span style="display: inline-block; padding: 3px 10px; border-radius: 14px; background: <?php echo $stBadgeBg; ?>; color: <?php echo $stBadgeColor; ?>; font-size: 10px; font-weight: 800; text-transform: uppercase;">
                                            <?php echo $stLabel; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($asset['invoice_number']); ?>')" style="padding: 5px 10px; font-size: 11px;">
                                            <i class="icon-file-text"></i> Invoice
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($warrantyPages > 1): 
                        $wbUrl = "reports.php?type=warranties&brand=" . urlencode($warrantyBrand ?? 'all') . "&status=" . urlencode($warrantyStatus ?? 'all') . "&search=" . urlencode($warrantySearch ?? '');
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; flex-wrap: wrap; gap: 15px;">
                        <span style="font-size: 13px; color: var(--text-muted);">
                            Showing page <?php echo $p; ?> of <?php echo $warrantyPages; ?> (<?php echo number_format($warrantyTotal); ?> total assets)
                        </span>
                        <div style="display: flex; gap: 5px;">
                            <?php if ($p > 1): ?>
                                <a href="<?php echo $wbUrl; ?>&p=<?php echo ($p - 1); ?>" class="btn-view" style="padding: 6px 12px; background: white; color: var(--text-main); border: 1px solid var(--border-color);">« Prev</a>
                            <?php endif; ?>
                            <span class="btn-view" style="padding: 6px 14px; background: var(--primary); color: white; border: none;"><?php echo $p; ?></span>
                            <?php if ($p < $warrantyPages): ?>
                                <a href="<?php echo $wbUrl; ?>&p=<?php echo ($p + 1); ?>" class="btn-view" style="padding: 6px 12px; background: white; color: var(--text-main); border: 1px solid var(--border-color);">Next »</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($type === 'renewals'): ?>
                <!-- Renewals KPI Ribbon -->
                <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
                    <div class="metric-card">
                        <div class="metric-label">Subscriptions & Licenses</div>
                        <div class="metric-value"><?php echo number_format($renewalKpis['total_subscriptions'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Managed Recurring Offerings
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total Licensed Seats</div>
                        <div class="metric-value" style="color: var(--primary);"><?php echo number_format($renewalKpis['total_seats'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            User / Endpoint Licenses
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Renewals Due ≤ 60 Days</div>
                        <div class="metric-value" style="color: #b45309;"><?php echo number_format($renewalKpis['due_soon_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Actionable Pipeline
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Upcoming Pipeline Value</div>
                        <div class="metric-value price-tag"><?php echo htmlspecialchars($currency) . number_format($renewalKpis['pipeline_value'] ?? 0, 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Recurring ARR at Stake
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expired / Lapsed</div>
                        <div class="metric-value" style="color: #b91c1c;"><?php echo number_format($renewalKpis['expired_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Win-back Opportunities
                        </div>
                    </div>
                </div>

                <!-- Monthly Renewal Calendar Timeline -->
                <?php if (!empty($renewalCalendar)): ?>
                <div class="card" style="margin-bottom: 25px;">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <i class="icon-calendar" style="color: var(--primary);"></i> 12-Month SaaS & Subscription Renewal Outlook
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px;">
                        <?php foreach($renewalCalendar as $cal): ?>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
                            <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">
                                <?php echo date('M Y', strtotime($cal['renewal_month'] . '-01')); ?>
                            </div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--primary); margin: 4px 0;">
                                <?php echo $cal['count']; ?> <span style="font-size: 10px; color: var(--text-muted); font-weight: 500;">contracts</span>
                            </div>
                            <div style="font-size: 11px; font-weight: 700; color: #15803d;">
                                <?php echo htmlspecialchars($currency) . number_format($cal['renewal_value'] ?? 0, 0); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Subscriptions Pipeline Table -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2 style="margin: 0; font-size: 22px;">Software Licenses & SaaS Renewals Pipeline</h2>
                            <p style="color: var(--text-muted); font-size: 14px; margin: 4px 0 0 0;">
                                Track license seats, service start/end dates, and estimated contract renewal opportunity values.
                            </p>
                        </div>
                    </div>

                    <!-- Filter Controls -->
                    <form method="GET" action="reports.php" class="filter-controls" style="margin-bottom: 25px; background: #f8fafc; padding: 18px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                        <input type="hidden" name="type" value="renewals">

                        <div class="filter-group" style="flex: 1; min-width: 220px; margin: 0;">
                            <span class="filter-label">Search Software / Customer / Invoice</span>
                            <input type="text" name="search" class="filter-select" placeholder="Acronis, ESET, customer..." value="<?php echo htmlspecialchars($renewalSearch ?? ''); ?>" style="width: 100%;">
                        </div>

                        <div class="filter-group" style="margin: 0;">
                            <span class="filter-label">Renewal Status</span>
                            <select name="status" class="filter-select" style="min-width: 160px;">
                                <option value="all" <?php echo ($renewalStatus ?? 'all') === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="ACTIVE" <?php echo ($renewalStatus ?? '') === 'ACTIVE' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="DUE_SOON" <?php echo ($renewalStatus ?? '') === 'DUE_SOON' ? 'selected' : ''; ?>>Due Soon (≤ 60 Days)</option>
                                <option value="EXPIRED" <?php echo ($renewalStatus ?? '') === 'EXPIRED' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-view" style="padding: 10px 18px;"><i class="icon-filter"></i> Filter</button>
                            <?php if (!empty($renewalSearch) || ($renewalStatus ?? 'all') !== 'all'): ?>
                                <a href="reports.php?type=renewals" class="btn-view" style="background: #e2e8f0; color: #475569; text-decoration: none; padding: 10px 14px;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Table -->
                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">Software / Service Offering</th>
                                    <th>Edition / Tier</th>
                                    <th style="min-width: 180px;">Customer</th>
                                    <th>Invoice #</th>
                                    <th class="text-center">Seats</th>
                                    <th>Coverage Period</th>
                                    <th class="text-right">Opportunity Value</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($renewalSubs)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                                        <i class="icon-refresh-cw" style="font-size: 36px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                                        No subscription or SaaS contracts found. Run the AI Entity Extractor from <a href="settings.php" style="color: var(--primary); font-weight: 700;">Settings</a> to identify software and recurring service periods.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach($renewalSubs as $sub): 
                                    $st = $sub['dynamic_status'];
                                    $stBadgeBg = '#ecfdf5';
                                    $stBadgeColor = '#15803d';
                                    $stLabel = 'Active (' . $sub['days_remaining'] . 'd)';

                                    if ($st === 'EXPIRED') {
                                        $stBadgeBg = '#fee2e2';
                                        $stBadgeColor = '#b91c1c';
                                        $stLabel = 'Expired';
                                    } elseif ($st === 'DUE_SOON') {
                                        $stBadgeBg = '#fef3c7';
                                        $stBadgeColor = '#b45309';
                                        $stLabel = 'Due in ' . $sub['days_remaining'] . 'd';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 13px;">
                                            <?php echo htmlspecialchars($sub['software_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569;">
                                            <?php echo htmlspecialchars($sub['edition_tier'] ?: 'Standard'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="customer_report.php?name=<?php echo urlencode($sub['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600; font-size: 13px;">
                                            <?php echo htmlspecialchars($sub['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 13px;">
                                            <?php echo htmlspecialchars($sub['invoice_number']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center" style="font-size: 13px; font-weight: 800; color: #334155;">
                                        <?php echo number_format($sub['license_seats'] ?? 1); ?>
                                    </td>
                                    <td style="font-size: 12px; color: #475569;">
                                        <?php echo htmlspecialchars($sub['period_start_date'] ?: '—'); ?> → <strong><?php echo htmlspecialchars($sub['period_end_date'] ?: '—'); ?></strong>
                                    </td>
                                    <td class="text-right price-tag" style="font-size: 13px; font-weight: 800;">
                                        <?php echo htmlspecialchars($currency) . number_format($sub['renewal_opportunity_value'] ?? 0, 0); ?>
                                    </td>
                                    <td class="text-center">
                                        <span style="display: inline-block; padding: 3px 10px; border-radius: 14px; background: <?php echo $stBadgeBg; ?>; color: <?php echo $stBadgeColor; ?>; font-size: 10px; font-weight: 800; text-transform: uppercase;">
                                            <?php echo $stLabel; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($sub['invoice_number']); ?>')" style="padding: 5px 10px; font-size: 11px;">
                                            <i class="icon-file-text"></i> Invoice
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($renewalPages > 1): 
                        $rbUrl = "reports.php?type=renewals&status=" . urlencode($renewalStatus ?? 'all') . "&search=" . urlencode($renewalSearch ?? '');
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; flex-wrap: wrap; gap: 15px;">
                        <span style="font-size: 13px; color: var(--text-muted);">
                            Showing page <?php echo $p; ?> of <?php echo $renewalPages; ?> (<?php echo number_format($renewalTotal); ?> total subscriptions)
                        </span>
                        <div style="display: flex; gap: 5px;">
                            <?php if ($p > 1): ?>
                                <a href="<?php echo $rbUrl; ?>&p=<?php echo ($p - 1); ?>" class="btn-view" style="padding: 6px 12px; background: white; color: var(--text-main); border: 1px solid var(--border-color);">« Prev</a>
                            <?php endif; ?>
                            <span class="btn-view" style="padding: 6px 14px; background: var(--primary); color: white; border: none;"><?php echo $p; ?></span>
                            <?php if ($p < $renewalPages): ?>
                                <a href="<?php echo $rbUrl; ?>&p=<?php echo ($p + 1); ?>" class="btn-view" style="padding: 6px 12px; background: white; color: var(--text-main); border: 1px solid var(--border-color);">Next »</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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

    <!-- Invoice Details Modal -->
    <div id="invoiceModalOverlay" class="modal-overlay" onclick="if(event.target === this) closeInvoiceDetails()">
        <div class="modal" style="max-width: 1050px; width: 95%;">
            <div class="modal-header">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <h2 id="invModalTitle" style="margin: 0; font-size: 22px; font-family: monospace; font-weight: 800; color: var(--primary);">Invoice #</h2>
                        <span id="invModalStatusBadge" style="padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 800; text-transform: uppercase;"></span>
                        <span id="invModalDate" style="font-size: 13px; color: var(--text-muted); font-weight: 600;"></span>
                    </div>
                    <p id="invModalCustomer" style="color: var(--text-main); font-size: 15px; font-weight: 700; margin: 6px 0 0 0;"></p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="window.print()" class="btn-view" style="background: #f1f5f9; color: #334155; border: 1px solid var(--border-color); padding: 8px 14px; font-size: 13px;">
                        <i class="icon-printer"></i> Print
                    </button>
                    <a id="invModalCustomerReportLink" href="#" class="btn-view" style="background: var(--text-main); text-decoration: none; padding: 8px 16px; font-size: 13px;">Customer Dossier</a>
                    <button class="modal-close" onclick="closeInvoiceDetails()" style="font-size: 28px; line-height: 1;">×</button>
                </div>
            </div>
            
            <div class="modal-body" id="invModalBody">
                <!-- Loaded dynamically via openInvoiceDetails(invoiceNumber) -->
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text, btn) {
            if (!text || text === 'UNASSIGNED') return;
            navigator.clipboard.writeText(text).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = '✓ Copied';
                btn.style.background = '#dcfce7';
                btn.style.color = '#15803d';
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.style.background = '';
                    btn.style.color = '';
                }, 1500);
            }).catch(() => {
                prompt('Copy serial number:', text);
            });
        }

        /* ── Side Audit Drawer & Row Selection ── */
        let activeSelectedRow = null;

        function selectInvoiceRow(invoiceNumber, rowEl) {
            if (activeSelectedRow) {
                activeSelectedRow.classList.remove('row-selected');
            }
            if (rowEl) {
                activeSelectedRow = rowEl;
                rowEl.classList.add('row-selected');
            }

            const drawer = document.getElementById('sideAuditDrawer');
            const drawerTitle = document.getElementById('drawerTitle');
            const drawerBody = document.getElementById('drawerBody');

            if (!drawer || !drawerBody) return;

            drawer.classList.remove('drawer-collapsed');
            drawerTitle.innerText = 'Audit: #' + invoiceNumber;
            drawerBody.innerHTML = '<div style="text-align: center; padding: 40px 15px;"><div style="display: inline-block; width: 26px; height: 26px; border: 3px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div><p style="margin-top: 10px; color: var(--text-muted); font-size: 11.5px;">Loading audit records, serials, and ledger...</p></div>';

            fetch(`reports.php?ajax_invoice_details=${encodeURIComponent(invoiceNumber)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.error) {
                        drawerBody.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--error); font-size: 12px;">${res.error}</div>`;
                        return;
                    }
                    drawerBody.innerHTML = buildInvoiceAuditHtml(res);
                })
                .catch(err => {
                    drawerBody.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--error); font-size: 12px;">Error retrieving invoice details: ${err.message}</div>`;
                    console.error(err);
                });
        }

        function closeDrawer() {
            const drawer = document.getElementById('sideAuditDrawer');
            if (drawer) {
                drawer.classList.add('drawer-collapsed');
                drawer.classList.remove('drawer-fullscreen');
                const expIcon = document.getElementById('drawerExpandIcon');
                if (expIcon) expIcon.className = 'icon-maximize-2';
            }
            if (activeSelectedRow) {
                activeSelectedRow.classList.remove('row-selected');
                activeSelectedRow = null;
            }
        }

        function toggleDrawerFullscreen() {
            const drawer = document.getElementById('sideAuditDrawer');
            const expIcon = document.getElementById('drawerExpandIcon');
            if (!drawer) return;
            const isFull = drawer.classList.toggle('drawer-fullscreen');
            if (expIcon) {
                expIcon.className = isFull ? 'icon-minimize-2' : 'icon-maximize-2';
            }
        }

        /* ── Modal Full View ── */
        function openInvoiceDetails(invoiceNumber) {
            const overlay = document.getElementById('invoiceModalOverlay');
            const title = document.getElementById('invModalTitle');
            const statusBadge = document.getElementById('invModalStatusBadge');
            const dateSpan = document.getElementById('invModalDate');
            const customerP = document.getElementById('invModalCustomer');
            const body = document.getElementById('invModalBody');
            const customerReportLink = document.getElementById('invModalCustomerReportLink');

            title.innerText = 'Invoice #' + invoiceNumber;
            statusBadge.innerText = 'Loading...';
            statusBadge.style.background = '#f1f5f9';
            statusBadge.style.color = '#64748b';
            statusBadge.style.border = 'none';
            dateSpan.innerText = '';
            customerP.innerText = 'Fetching invoice records...';
            customerReportLink.style.display = 'none';

            body.innerHTML = '<div style="text-align: center; padding: 60px 20px;"><div style="display: inline-block; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div><p style="margin-top: 15px; color: var(--text-muted); font-size: 14px;">Loading invoice line items, serial numbers, and payment reconciliation...</p></div>';

            overlay.style.display = 'flex';

            fetch(`reports.php?ajax_invoice_details=${encodeURIComponent(invoiceNumber)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.error) {
                        body.innerHTML = `<div style="text-align: center; padding: 50px; color: var(--error);">${res.error}</div>`;
                        return;
                    }

                    const h = res.header || {};
                    const recon = res.reconciliation || {};
                    const isSettled = recon.status === 'Settled';

                    statusBadge.innerText = recon.status;
                    statusBadge.style.background = isSettled ? '#ecfdf5' : '#fffbeb';
                    statusBadge.style.color = isSettled ? '#10b981' : '#b45309';
                    statusBadge.style.border = `1px solid ${isSettled ? '#10b981' : '#b45309'}30`;

                    dateSpan.innerText = `• Invoiced: ${h.invoice_date || 'N/A'}` + (h.paid_date ? ` (Paid: ${h.paid_date}${h.days_to_pay ? ', ' + h.days_to_pay + ' days DSO' : ''})` : '');
                    customerP.innerText = h.customer_name || 'N/A';

                    customerReportLink.href = `customer_report.php?name=${encodeURIComponent(h.customer_name || '')}`;
                    customerReportLink.style.display = 'inline-block';

                    body.innerHTML = buildInvoiceAuditHtml(res);
                })
                .catch(err => {
                    body.innerHTML = `<div style="text-align: center; padding: 50px; color: var(--error);">Error retrieving invoice details: ${err.message}</div>`;
                    console.error(err);
                });
        }

        function buildInvoiceAuditHtml(res) {
            const h = res.header || {};
            const c = res.customer || {};
            const items = res.items || [];
            const lines = res.lines || [];
            const assets = res.assets || [];
            const subs = res.subscriptions || [];
            const payments = res.payments || [];
            const recon = res.reconciliation || {};

            const isSettled = recon.status === 'Settled';
            const nf = new Intl.NumberFormat();
            const serializedAssetCount = assets.filter(a => a.serial_number && a.serial_number !== 'UNASSIGNED').length;

            const taxTreatment = h.vat_treatment || (items.length > 0 ? items[0].vat_treatment : 'VAT_INCLUSIVE');
            const isVatReg = parseInt(c.is_vat_registered || 0) === 1;
            const vatNo = c.vat_number || '';
            const tinNo = c.tin_number || '';

            let taxBadge = '';
            if (isVatReg) {
                taxBadge = `<span class="dense-badge dense-badge-plusvat" title="Registered VAT Entity under IRD Sri Lanka">VAT No: ${escapeHtml(vatNo || 'Registered')}</span>`;
            } else if (tinNo) {
                taxBadge = `<span class="dense-badge dense-badge-inclusive" title="Business TIN (Non-VAT Registered under IRD)">TIN: ${escapeHtml(tinNo)} (Non-VAT)</span>`;
            } else {
                taxBadge = `<span class="dense-badge dense-badge-inclusive" title="Non-VAT Registered / Retail Client">Non-VAT Reg</span>`;
            }

            const treatBadge = (taxTreatment === 'PLUS_VAT')
                ? `<span class="dense-badge dense-badge-plusvat" style="font-size: 8.5px; margin-left: auto;">+VAT (18%)</span>`
                : `<span class="dense-badge dense-badge-inclusive" style="font-size: 8.5px; margin-left: auto;">VAT INC</span>`;

            let html = '';

            // 1. Financial summary cards
            html += `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 12px;">
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px;">
                        <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: flex; justify-content: space-between;">
                            <span>Net Base</span>
                            ${taxTreatment === 'PLUS_VAT' ? '<span style="color:#6d28d9; font-size:9px;">Subtotal</span>' : ''}
                        </div>
                        <div style="font-size: 14px; font-weight: 800; color: var(--text-main); margin-top: 2px;">LKR ${nf.format(Math.round(h.total_base_value || 0))}</div>
                    </div>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px;">
                        <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: flex; align-items: center;">
                            <span>18% VAT</span>
                            ${treatBadge}
                        </div>
                        <div style="font-size: 14px; font-weight: 800; color: var(--primary); margin-top: 2px;">LKR ${nf.format(Math.round(h.total_vat || 0))}</div>
                    </div>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px;">
                        <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Gross Total</div>
                        <div style="font-size: 14px; font-weight: 800; color: var(--text-main); margin-top: 2px;">LKR ${nf.format(Math.round(h.total_gross_amount || 0))}</div>
                    </div>
                    <div style="background: ${isSettled ? '#f0fdf4' : '#fffbeb'}; border: 1px solid ${isSettled ? '#bbf7d0' : '#fde68a'}; border-radius: 6px; padding: 8px 10px;">
                        <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: ${isSettled ? '#15803d' : '#b45309'};">
                            ${isSettled ? 'Settled' : 'Balance Due'}
                        </div>
                        <div style="font-size: 14px; font-weight: 800; color: ${isSettled ? '#15803d' : '#b91c1c'}; margin-top: 2px;">
                            LKR ${isSettled ? nf.format(Math.round(recon.total_paid || h.total_gross_amount || 0)) : nf.format(Math.round(recon.balance_due || h.total_gross_amount || 0))}
                        </div>
                    </div>
                </div>
            `;

            // 2. Metadata Context Strip
            html += `
                <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px; margin-bottom: 12px; font-size: 11px; display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <strong style="color: var(--text-main); font-size: 12px;">${escapeHtml(h.customer_name || 'N/A')}</strong>
                            ${taxBadge}
                        </div>
                        <a href="customer_report.php?name=${encodeURIComponent(h.customer_name || '')}" target="_blank" style="font-size: 10.5px; color: var(--primary); text-decoration: none; font-weight: 600;">Dossier &rarr;</a>
                    </div>
                    <div style="color: var(--text-muted); font-size: 10.5px; display: flex; gap: 8px; flex-wrap: wrap;">
                        <span>Date: <strong>${h.invoice_date || 'N/A'}</strong></span>
                        <span>PO: <strong>${escapeHtml(h.po_number || 'None')}</strong></span>
                        <span>Rep: <strong>${escapeHtml(h.rep_name || h.sales_rep_code || '—')}</strong></span>
                        ${h.paid_date ? `<span style="color: #15803d;">Paid: <strong>${h.paid_date}</strong></span>` : '<span style="color: #b91c1c;">Unsettled</span>'}
                    </div>
                </div>
            `;

            // 3. Normalized Commercial Items Table
            if (items.length > 0) {
                html += `
                    <div style="margin-bottom: 14px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                            <i class="icon-package" style="color: var(--primary); font-size: 12px;"></i>
                            Identified Items (${items.length})
                        </div>
                        <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 5px; background: white;">
                            <table class="rational-table" style="font-size: 11px;">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center" style="width: 35px;">Qty</th>
                                        <th class="text-right" style="width: 75px;">Unit Price</th>
                                        <th class="text-right" style="width: 80px;">Gross</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                items.forEach((it) => {
                    html += `
                        <tr>
                            <td style="font-weight: 600; color: var(--text-main);">
                                ${escapeHtml(it.clean_product_name)}
                                ${it.brand_category ? `<span style="font-size: 9px; color: var(--text-muted); display: block;">${escapeHtml(it.brand_category)}</span>` : ''}
                            </td>
                            <td class="text-center dense-num">${it.quantity}</td>
                            <td class="text-right dense-num" style="color: #475569;">${nf.format(Math.round(it.unit_price || 0))}</td>
                            <td class="text-right dense-num-bold dense-num">${nf.format(Math.round(it.total_amount || 0))}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table></div></div>`;
            }

            // 4. Normalized Hardware Assets Registry
            if (assets.length > 0) {
                html += `
                    <div style="margin-bottom: 14px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 5px;">
                                <i class="icon-shield" style="color: #2563eb; font-size: 12px;"></i>
                                Hardware Assets & S/N (${assets.length})
                            </span>
                        </div>
                        <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 5px; background: white;">
                            <table class="rational-table" style="font-size: 11px;">
                                <thead>
                                    <tr>
                                        <th>Serial Number</th>
                                        <th>Asset</th>
                                        <th>Expiry</th>
                                        <th class="text-center" style="width: 55px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                assets.forEach(a => {
                    const isUnassigned = (!a.serial_number || a.serial_number === 'UNASSIGNED');
                    const snDisplay = isUnassigned ? '<span style="color: #94a3b8; font-style: italic;">UNASSIGNED</span>' : escapeHtml(a.serial_number);
                    const copyBtn = isUnassigned ? '' : `
                        <button type="button" onclick="copyToClipboard('${escapeHtml(a.serial_number)}', this)" title="Copy serial number" style="background: none; border: 1px solid #cbd5e1; border-radius: 3px; padding: 0 4px; font-size: 9.5px; cursor: pointer; color: #475569; margin-left: 4px;">
                            📋
                        </button>
                    `;
                    let statusBadge = '<span class="dense-badge dense-badge-settled">ACTIVE</span>';
                    if (a.warranty_status === 'EXPIRED' || (a.warranty_expiry_date && a.warranty_expiry_date < new Date().toISOString().slice(0, 10))) {
                        statusBadge = '<span class="dense-badge dense-badge-credit">EXPIRED</span>';
                    }

                    html += `
                        <tr>
                            <td class="dense-doc-num">${snDisplay}${copyBtn}</td>
                            <td style="font-weight: 500;">${escapeHtml(a.product_name)}</td>
                            <td class="dense-num" style="font-size: 10px;">${escapeHtml(a.warranty_expiry_date || '—')}</td>
                            <td class="text-center">${statusBadge}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table></div></div>`;
            }

            // 5. Software Subscriptions & Maintenance Agreements
            if (subs.length > 0) {
                html += `
                    <div style="margin-bottom: 14px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                            <i class="icon-refresh-cw" style="color: #0284c7; font-size: 12px;"></i>
                            Software / Maintenance Agreements (${subs.length})
                        </div>
                        <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 5px; background: white;">
                            <table class="rational-table" style="font-size: 11px;">
                                <thead>
                                    <tr>
                                        <th>Contract Offering</th>
                                        <th class="text-center" style="width: 40px;">Seats</th>
                                        <th>Coverage</th>
                                        <th class="text-right" style="width: 75px;">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                subs.forEach(s => {
                    html += `
                        <tr>
                            <td style="font-weight: 600;">${escapeHtml(s.software_name)}</td>
                            <td class="text-center dense-num">${s.license_seats || 1}</td>
                            <td style="font-size: 10px;">${escapeHtml(s.period_end_date || '—')}</td>
                            <td class="text-right dense-num-bold dense-num">${nf.format(Math.round(s.renewal_opportunity_value || 0))}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table></div></div>`;
            }

            // 6. Verbatim QuickBooks Raw Line Items
            html += `
                <div style="margin-bottom: 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #ffffff; padding: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: #475569;">
                            QuickBooks Raw Sales Lines (${lines.length})
                        </span>
                        <span style="font-size: 9.5px; color: var(--text-muted);">Verbatim Ledger Text</span>
                    </div>
                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <table class="rational-table" style="font-size: 10.5px;">
                            <thead>
                                <tr>
                                    <th style="width: 25px;">#</th>
                                    <th>Raw Description & Serial Detection</th>
                                    <th class="text-center" style="width: 35px;">Qty</th>
                                    <th class="text-right" style="width: 70px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            lines.forEach((l, idx) => {
                let descText = l.item_description || 'Item Description';
                let formattedDesc = escapeHtml(descText)
                    .replace(/(S\/N[:\s]*[A-Za-z0-9\-\,\s]+)/gi, '<span style="display:inline-block; background:#e0e7ff; color:#3730a3; padding:1px 4px; border-radius:3px; font-weight:700; font-size:10px;">$1</span>')
                    .replace(/\n/g, '<br>');

                html += `
                    <tr>
                        <td style="color: var(--text-muted); font-size: 10px;">${idx + 1}</td>
                        <td style="line-height: 1.35;">${formattedDesc}</td>
                        <td class="text-center dense-num">${l.quantity || 1}</td>
                        <td class="text-right dense-num">${nf.format(Math.round(l.total_amount || 0))}</td>
                    </tr>
                `;
            });

            html += `</tbody></table></div></div>`;

            // 7. Settlement & Payment Reconciliation
            html += `
                <div>
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 5px;">
                        Payment Reconciliation
                    </div>
            `;

            if (payments.length > 0) {
                html += `
                    <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 5px; background: white;">
                        <table class="rational-table" style="font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ref / Cheque</th>
                                    <th class="text-right">Cleared</th>
                                    <th class="text-center" style="width: 55px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                payments.forEach(p => {
                    html += `
                        <tr>
                            <td class="dense-num">${p.payment_date || 'N/A'}</td>
                            <td class="dense-doc-num">${p.reference_num || 'Direct'}</td>
                            <td class="text-right dense-num" style="color: #15803d; font-weight: 700;">${nf.format(Math.round(p.amount || 0))}</td>
                            <td class="text-center"><span class="dense-badge dense-badge-settled">Applied</span></td>
                        </tr>
                    `;
                });
                html += `</tbody></table></div>`;
            } else if (isSettled) {
                html += `
                    <div style="background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px; color: #15803d; font-size: 11.5px; display: flex; align-items: center; gap: 8px;">
                        <i class="icon-shield-check" style="font-size: 16px;"></i>
                        <div>
                            <strong>Settled via QuickBooks Reconciliation</strong><br>
                            Cleared on <strong>${h.paid_date || 'N/A'}</strong> (${h.days_to_pay || 0} days DSO).
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px; color: #b45309; font-size: 11.5px; display: flex; align-items: center; gap: 8px;">
                        <i class="icon-clock" style="font-size: 16px;"></i>
                        <div>
                            <strong>Awaiting Commercial Settlement</strong><br>
                            Open balance: <strong>LKR ${nf.format(Math.round(recon.balance_due || h.total_gross_amount || 0))}</strong>.
                        </div>
                    </div>
                `;
            }

            html += `</div>`;
            return html;
        }

        function closeInvoiceDetails() {
            document.getElementById('invoiceModalOverlay').style.display = 'none';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDrawer();
                closeInvoiceDetails();
                closeCustomerDetails();
            }
        });

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

        function toggleMethodology(id) {
            const body = document.getElementById('body_' + id);
            const icon = document.getElementById('icon_' + id);
            const text = document.getElementById('text_' + id);
            if (!body) return;
            const isCollapsed = body.classList.contains('collapsed');
            if (isCollapsed) {
                body.classList.remove('collapsed');
                if (icon) {
                    icon.classList.add('expanded');
                    icon.textContent = '▼';
                }
                if (text) text.textContent = 'Hide Logic & Formulas';
            } else {
                body.classList.add('collapsed');
                if (icon) {
                    icon.classList.remove('expanded');
                    icon.textContent = '▶';
                }
                if (text) text.textContent = 'Show Logic & Formulas';
            }
        }
    </script>
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
