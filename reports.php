<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';
require_once 'includes/report_methodology.php';
require_once 'includes/pagination.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$reports = new Reports($db);

if (!function_exists('getReportPaginationParams')) {
    /**
     * Helper to resolve pagination parameters for business reports
     */
    function getReportPaginationParams($defaultLimit = 25) {
        $isAll = (isset($_GET['limit']) && $_GET['limit'] === 'all') || !empty($_GET['show_all']);
        $limit = $isAll ? 999999 : (isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : $defaultLimit);
        $p = $isAll ? 1 : max(1, (int)($_GET['p'] ?? 1));
        return [$p, $limit, $isAll];
    }
}

// AJAX Handlers
require_once __DIR__ . '/modules/reports/ajax.php';

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
$uniqueBrands = $reports->getUniqueBrands();

// Enforce RBAC Report Permissions
$currentView = $_GET['view'] ?? '';
if (!$auth->canAccessReport($type, null, $currentView)) {
    // If accessing default 'invoices' without explicit parameter, redirect to first permitted report if available
    if (!isset($_GET['type']) || $_GET['type'] === 'invoices') {
        $catalog = Auth::getReportDefinitions();
        foreach ($catalog as $k => $def) {
            if (strpos($def['url'], 'reports.php?type=') === 0 && $auth->canAccessReport($k)) {
                header('Location: ' . $def['url']);
                exit;
            }
        }
    }
    $auth->requireReportAccess($type, $currentView);
}

// Modular Report Controller Dispatcher
$controllerMap = [
    'monthly_overview'   => 'monthly',
    'monthly_customer'   => 'monthly',
    'monthly_rep'        => 'monthly',
    'expiring_contracts' => 'contracts',
    'dso'                => 'dso_trends'
];
$controllerKey = $controllerMap[$type] ?? $type;
$controllerFile = __DIR__ . "/modules/reports/{$controllerKey}.php";

$reportData = [];
$reportTitle = 'Report Details';
if (file_exists($controllerFile)) {
    require $controllerFile;
} else {
    require __DIR__ . "/modules/reports/default.php";
}

$summary = $reportData['summary'] ?? ($invoiceSummary ?? ($unpaidSummary ?? ($contractsSummary ?? [])));
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
    <link rel="stylesheet" href="layout.css?v=2.6.2">
    <style>
        .command-bar {
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .command-bar::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        <?php if ($type === 'monthly'): ?>
        @page {
            size: A4 landscape;
            margin: 6mm 6mm 8mm 6mm;
        }
        @media print {
            body, .main-wrapper, .main-content, .content-body {
                background: #ffffff !important;
                color: #0f172a !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .monthly-matrix-wrapper {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                gap: 6px !important;
            }
            .monthly-matrix-wrapper .card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 0 6px 0 !important;
                background: transparent !important;
            }
            .monthly-matrix-wrapper .card > div:first-child .cmd-btn,
            .monthly-matrix-wrapper a[href*="export=csv"],
            .monthly-matrix-wrapper button[onclick*="print"] {
                display: none !important;
            }
            .monthly-matrix-wrapper .metrics-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 6px !important;
                margin-bottom: 6px !important;
            }
            .monthly-matrix-wrapper .metric-card {
                padding: 4px 6px !important;
                border: 1px solid #cbd5e1 !important;
                border-top: 2.5px solid #4f46e5 !important;
                border-radius: 4px !important;
                background: #f8fafc !important;
                min-width: 0 !important;
            }
            .monthly-matrix-wrapper .metric-label {
                font-size: 8px !important;
            }
            .monthly-matrix-wrapper .metric-value {
                font-size: 11px !important;
                margin: 1px 0 !important;
            }
            .monthly-matrix-wrapper .metric-card div[style*="font-size: 11px"] {
                font-size: 8px !important;
            }
            .monthly-matrix-wrapper svg {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                height: 52px !important;
            }
            .monthly-matrix-wrapper .matrix-table-wrap,
            .monthly-matrix-wrapper div[style*="overflow-x: auto"] {
                overflow: visible !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .matrix-sales-table {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
                font-size: 7.5pt !important;
            }
            .matrix-sales-table tr {
                page-break-inside: avoid !important;
            }
            .matrix-sales-table th,
            .matrix-sales-table td {
                min-width: 0 !important;
                box-sizing: border-box !important;
                padding: 2px 2px !important;
                font-size: 7pt !important;
                line-height: 1.15 !important;
                border: 0.5pt solid #cbd5e1 !important;
                overflow: hidden !important;
            }
            .matrix-sales-table th:first-child,
            .matrix-sales-table td:first-child {
                position: static !important;
                width: 20% !important;
                min-width: 0 !important;
                max-width: 20% !important;
                padding: 2px 4px !important;
                border-right: 1.5pt solid #94a3b8 !important;
                white-space: normal !important;
                word-break: break-word !important;
            }
            .matrix-sales-table td:first-child div[style*="font-size: 11.5px"] {
                font-size: 7.5pt !important;
                font-weight: 700 !important;
                line-height: 1.1 !important;
            }
            .matrix-sales-table td:first-child div[style*="font-size: 9.5px"],
            .matrix-sales-table td:first-child i {
                display: none !important;
            }
            .matrix-sales-table th:not(:first-child):not(:nth-last-child(2)):not(:last-child),
            .matrix-sales-table td:not(:first-child):not(:nth-last-child(2)):not(:last-child) {
                width: 5.5% !important;
                max-width: 5.5% !important;
                min-width: 0 !important;
                padding: 2px 1px !important;
                text-align: right !important;
                font-size: 6.8pt !important;
                letter-spacing: -0.3px !important;
                white-space: nowrap !important;
            }
            .matrix-sales-table th:nth-last-child(2),
            .matrix-sales-table td:nth-last-child(2) {
                width: 7.5% !important;
                max-width: 7.5% !important;
                min-width: 0 !important;
                padding: 2px 2px !important;
                font-weight: 800 !important;
                font-size: 7pt !important;
                background: #f1f5f9 !important;
                border-left: 1.5pt solid #94a3b8 !important;
                text-align: right !important;
                white-space: nowrap !important;
            }
            .matrix-sales-table th:last-child,
            .matrix-sales-table td:last-child {
                width: 6.5% !important;
                max-width: 6.5% !important;
                min-width: 0 !important;
                padding: 2px 2px !important;
                font-weight: 700 !important;
                font-size: 7pt !important;
                background: #f8fafc !important;
                text-align: right !important;
                white-space: nowrap !important;
            }
            .matrix-sales-table span[style*="border-radius"] {
                font-size: 6.5pt !important;
                padding: 0 1px !important;
                background: transparent !important;
            }
        }
        <?php endif; ?>
    </style>
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
                            <select class="cmd-select cmd-select-report" onchange="window.location.href='reports.php?type='+this.value">
                                <optgroup label="── Active Strategic Analytics ──">
                                    <?php if ($auth->canAccessReport('invoices')): ?><option value="invoices" <?php echo $type === 'invoices' ? 'selected' : ''; ?>>Commercial Invoices</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('unpaid_invoices')): ?><option value="unpaid_invoices" <?php echo $type === 'unpaid_invoices' ? 'selected' : ''; ?>>Unpaid Invoices</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('monthly_overview') || $auth->canAccessReport('monthly_customer') || $auth->canAccessReport('monthly_rep')): ?><option value="monthly" <?php echo $type === 'monthly' ? 'selected' : ''; ?>>Monthly Sales Matrix</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('warranties')): ?><option value="warranties" <?php echo $type === 'warranties' ? 'selected' : ''; ?>>Warranty & Serials</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('ltv')): ?><option value="ltv" <?php echo $type === 'ltv' ? 'selected' : ''; ?>>Customer LTV</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('churn')): ?><option value="churn" <?php echo $type === 'churn' ? 'selected' : ''; ?>>Account Churn</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('eol')): ?><option value="eol" <?php echo $type === 'eol' ? 'selected' : ''; ?>>Hardware EOL</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('contracts')): ?><option value="contracts" <?php echo $type === 'contracts' ? 'selected' : ''; ?>>Expiring Contracts & Subscriptions</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('rental_roi')): ?><option value="rental_roi" <?php echo $type === 'rental_roi' ? 'selected' : ''; ?>>Rental Fleet ROI</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('brand_growth')): ?><option value="brand_growth" <?php echo $type === 'brand_growth' ? 'selected' : ''; ?>>Brand & Category Performance</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('dso_trends')): ?><option value="dso_trends" <?php echo $type === 'dso_trends' ? 'selected' : ''; ?>>DSO Trends</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('tax_audit')): ?><option value="tax_audit" <?php echo $type === 'tax_audit' ? 'selected' : ''; ?>>Tax & IRD Audit (18%)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('unlinked_payments')): ?><option value="unlinked_payments" <?php echo $type === 'unlinked_payments' ? 'selected' : ''; ?>>Unlinked Payments Audit</option><?php endif; ?>
                                </optgroup>
                                <optgroup label="── Temporarily Archived Reports ──">
                                    <?php if ($auth->canAccessReport('renewals')): ?><option value="renewals" <?php echo $type === 'renewals' ? 'selected' : ''; ?>>SaaS Renewals (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('yearly')): ?><option value="yearly" <?php echo $type === 'yearly' ? 'selected' : ''; ?>>Yearly Sales (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('quarterly')): ?><option value="quarterly" <?php echo $type === 'quarterly' ? 'selected' : ''; ?>>Quarterly Sales (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('matrix')): ?><option value="matrix" <?php echo $type === 'matrix' ? 'selected' : ''; ?>>Customer Matrix (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('stock')): ?><option value="stock" <?php echo $type === 'stock' ? 'selected' : ''; ?>>Stock Movement (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('partners')): ?><option value="partners" <?php echo $type === 'partners' ? 'selected' : ''; ?>>Partner Cohorts (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('credit')): ?><option value="credit" <?php echo $type === 'credit' ? 'selected' : ''; ?>>Credit Health (Archived)</option><?php endif; ?>
                                    <?php if ($auth->canAccessReport('aging')): ?><option value="aging" <?php echo $type === 'aging' ? 'selected' : ''; ?>>Aging (Archived)</option><?php endif; ?>
                                </optgroup>
                            </select>
                        </div>

                        <?php if ($type === 'monthly'): ?>
                        <!-- Monthly Sales Matrix View Filters -->
                        <div class="cmd-group">
                            <span class="cmd-label"><i class="icon-layers" style="font-size: 11px;"></i> Matrix View:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=monthly&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>&customer_type=<?php echo urlencode($monthlyFilters['customer_type'] ?? ''); ?>&view='+this.value">
                                <option value="overview" <?php echo $monthlyView === 'overview' ? 'selected' : ''; ?>>Macro KPI Matrix</option>
                                <option value="customer" <?php echo $monthlyView === 'customer' ? 'selected' : ''; ?>>By Customer Breakdown</option>
                                <option value="rep" <?php echo $monthlyView === 'rep' ? 'selected' : ''; ?>>By Sales Rep Breakdown</option>
                            </select>
                        </div>

                        <div class="cmd-group">
                            <span class="cmd-label"><i class="icon-sliders" style="font-size: 11px;"></i> Period Mode:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=monthly&view=<?php echo urlencode($monthlyView); ?>&year=<?php echo urlencode($selectedYear); ?>&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>&customer_type=<?php echo urlencode($monthlyFilters['customer_type'] ?? ''); ?>&mode='+this.value">
                                <option value="rolling" <?php echo ($monthlyMode ?? 'rolling') === 'rolling' ? 'selected' : ''; ?>>Rolling 12 Months</option>
                                <option value="calendar" <?php echo ($monthlyMode ?? '') === 'calendar' ? 'selected' : ''; ?>>Calendar Year (Jan–Dec)</option>
                            </select>
                        </div>

                        <?php if (($monthlyMode ?? 'rolling') === 'calendar'): ?>
                        <div class="cmd-group">
                            <span class="cmd-label"><i class="icon-calendar" style="font-size: 11px;"></i> Year:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=monthly&view=<?php echo urlencode($monthlyView); ?>&mode=calendar&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>&customer_type=<?php echo urlencode($monthlyFilters['customer_type'] ?? ''); ?>&year='+this.value">
                                <?php foreach ($availableYears as $y): ?>
                                    <option value="<?php echo htmlspecialchars($y); ?>" <?php echo (string)($selectedYear ?? $year) === (string)$y ? 'selected' : ''; ?>><?php echo htmlspecialchars($y); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($monthlyView === 'customer'): ?>
                        <div class="cmd-group">
                            <span class="cmd-label">Customer Type:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=monthly&view=customer&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>&search=<?php echo urlencode($monthlyFilters['search'] ?? ''); ?>&customer_type='+encodeURIComponent(this.value)">
                                <option value="">All Types</option>
                                <option value="Partner" <?php echo ($monthlyFilters['customer_type'] ?? '') === 'Partner' ? 'selected' : ''; ?>>Partners Only</option>
                                <option value="End Customer" <?php echo ($monthlyFilters['customer_type'] ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customers Only</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array($monthlyView, ['customer', 'rep'])): ?>
                        <div class="cmd-group">
                            <span class="cmd-label">Brand:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=monthly&view=<?php echo urlencode($monthlyView); ?>&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>&customer_type=<?php echo urlencode($monthlyFilters['customer_type'] ?? ''); ?>&search=<?php echo urlencode($monthlyFilters['search'] ?? ''); ?>&brand='+encodeURIComponent(this.value)">
                                <option value="">All Brands</option>
                                <?php foreach ($uniqueBrands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo ($monthlyFilters['brand'] ?? '') === $b['product_category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['product_category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($type === 'brand_growth'): ?>
                        <!-- Brand & Category Subview Switcher -->
                        <div class="cmd-group">
                            <span class="cmd-label">Breakdown:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=brand_growth&brand=<?php echo urlencode($brandFilter ?? ''); ?>&category=<?php echo urlencode($catFilter ?? ''); ?>&view_mode='+this.value">
                                <option value="brand" <?php echo ($viewMode ?? 'brand') === 'brand' ? 'selected' : ''; ?>>By Brand</option>
                                <option value="category" <?php echo ($viewMode ?? '') === 'category' ? 'selected' : ''; ?>>By Category</option>
                                <option value="matrix" <?php echo ($viewMode ?? '') === 'matrix' ? 'selected' : ''; ?>>Brand &times; Category Matrix</option>
                            </select>
                        </div>

                        <!-- Brand Filter -->
                        <div class="cmd-group">
                            <span class="cmd-label">Brand:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=brand_growth&view_mode=<?php echo urlencode($viewMode ?? 'brand'); ?>&category=<?php echo urlencode($catFilter ?? ''); ?>&brand='+this.value">
                                <option value="ALL">All Brands</option>
                                <?php foreach ($masterBrandsList as $mb): ?>
                                    <option value="<?php echo htmlspecialchars($mb['name']); ?>" <?php echo ($brandFilter === $mb['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mb['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div class="cmd-group">
                            <span class="cmd-label">Category:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=brand_growth&view_mode=<?php echo urlencode($viewMode ?? 'brand'); ?>&brand=<?php echo urlencode($brandFilter ?? ''); ?>&category='+this.value">
                                <option value="ALL">All Categories</option>
                                <?php foreach ($masterCategoriesList as $mc): ?>
                                    <option value="<?php echo htmlspecialchars($mc['name']); ?>" <?php echo ($catFilter === $mc['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mc['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array($type, ['invoices', 'dso_trends', 'tax_audit', 'yearly', 'quarterly', 'matrix', 'reps'])): ?>
                        <div class="cmd-group">
                            <span class="cmd-label">Year:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=<?php echo $type; ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&status=<?php echo urlencode($status ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&month=<?php echo urlencode($month ?? ''); ?>&year='+this.value">
                                <option value="all" <?php echo $year === 'all' ? 'selected' : ''; ?>>All Years</option>
                                <?php foreach ($availableYears as $y): ?>
                                    <option value="<?php echo htmlspecialchars($y); ?>" <?php echo (string)$year === (string)$y ? 'selected' : ''; ?>><?php echo htmlspecialchars($y); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array($type, ['invoices', 'tax_audit'])): ?>
                        <div class="cmd-group">
                            <span class="cmd-label">Month:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=<?php echo $type; ?>&year=<?php echo urlencode($year); ?>&status=<?php echo urlencode($status ?? ''); ?>&search=<?php echo urlencode($search ?? ''); ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&month='+this.value">
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
                        <?php endif; ?>

                        <?php if (in_array($type, ['invoices', 'warranties', 'eol', 'rental_roi'])): ?>
                        <div class="cmd-group">
                            <span class="cmd-label">Status:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=<?php echo $type; ?>&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month ?? ''); ?>&search=<?php echo urlencode($search ?? $warrantySearch ?? ''); ?>&brand=<?php echo urlencode($brand ?? ''); ?>&customer_type=<?php echo urlencode($customer_type ?? ''); ?>&rep_code=<?php echo urlencode($rep_code ?? ''); ?>&status='+this.value">
                                <option value="all" <?php echo ($status ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <?php if ($type === 'invoices'): ?>
                                    <option value="settled" <?php echo ($status ?? '') === 'settled' ? 'selected' : ''; ?>>Settled</option>
                                    <option value="unpaid" <?php echo ($status ?? '') === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                <?php elseif ($type === 'warranties'): ?>
                                    <option value="active" <?php echo ($warrantyStatus ?? '') === 'active' ? 'selected' : ''; ?>>Active Warranty</option>
                                    <option value="expiring_soon" <?php echo ($warrantyStatus ?? '') === 'expiring_soon' ? 'selected' : ''; ?>>Expiring ≤ 60d</option>
                                    <option value="expired" <?php echo ($warrantyStatus ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="has_maintenance" <?php echo ($warrantyStatus ?? '') === 'has_maintenance' ? 'selected' : ''; ?>>With Maintenance SLA</option>
                                <?php elseif ($type === 'eol'): ?>
                                    <option value="ACTIVE" <?php echo ($status ?? '') === 'ACTIVE' ? 'selected' : ''; ?>>Active Warranty</option>
                                    <option value="EXPIRING_30D" <?php echo ($status ?? '') === 'EXPIRING_30D' ? 'selected' : ''; ?>>Expiring ≤ 30d</option>
                                    <option value="EXPIRING_90D" <?php echo ($status ?? '') === 'EXPIRING_90D' ? 'selected' : ''; ?>>Expiring ≤ 90d</option>
                                    <option value="EXPIRED" <?php echo ($status ?? '') === 'EXPIRED' ? 'selected' : ''; ?>>Expired (EOL)</option>
                                <?php elseif ($type === 'rental_roi'): ?>
                                    <option value="ACTIVE" <?php echo ($status ?? '') === 'ACTIVE' ? 'selected' : ''; ?>>Active (≤ 35d)</option>
                                    <option value="OVERDUE" <?php echo ($status ?? '') === 'OVERDUE' ? 'selected' : ''; ?>>Overdue (36–60d)</option>
                                    <option value="SUSPENDED" <?php echo ($status ?? '') === 'SUSPENDED' ? 'selected' : ''; ?>>Suspended (> 60d)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($type === 'contracts'): ?>
                        <!-- 1. Expiration Window Range Selector -->
                        <div class="cmd-group">
                            <span class="cmd-label" style="color: #2563eb;"><i class="icon-calendar" style="font-size: 11px;"></i> Range:</span>
                            <select class="cmd-select" style="font-weight: 600; color: #1e40af; background: #eff6ff;" onchange="window.location.href='reports.php?type=contracts&category=<?php echo urlencode($category ?? 'all'); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&sort=<?php echo urlencode($sort ?? 'expiry_asc'); ?>&search=<?php echo urlencode($search ?? ''); ?>&range='+this.value">
                                <option value="pm_90d" <?php echo ($range ?? 'pm_90d') === 'pm_90d' ? 'selected' : ''; ?>>±3 Months (3m Due to 3m Post Due) [Default]</option>
                                <option value="due_30d" <?php echo ($range ?? '') === 'due_30d' ? 'selected' : ''; ?>>Due ≤ 30 Days (Urgent)</option>
                                <option value="due_60d" <?php echo ($range ?? '') === 'due_60d' ? 'selected' : ''; ?>>Due ≤ 60 Days</option>
                                <option value="due_90d" <?php echo ($range ?? '') === 'due_90d' ? 'selected' : ''; ?>>Due ≤ 90 Days (Next 3 Months)</option>
                                <option value="due_180d" <?php echo ($range ?? '') === 'due_180d' ? 'selected' : ''; ?>>Due ≤ 180 Days (Next 6 Months)</option>
                                <option value="post_30d" <?php echo ($range ?? '') === 'post_30d' ? 'selected' : ''; ?>>Post Due (Past 30 Days)</option>
                                <option value="post_90d" <?php echo ($range ?? '') === 'post_90d' ? 'selected' : ''; ?>>Post Due (Past 90 Days)</option>
                                <option value="overdue" <?php echo ($range ?? '') === 'overdue' ? 'selected' : ''; ?>>All Overdue (&lt; Today)</option>
                                <option value="upcoming" <?php echo ($range ?? '') === 'upcoming' ? 'selected' : ''; ?>>All Upcoming (≥ Today)</option>
                                <option value="all" <?php echo ($range ?? '') === 'all' ? 'selected' : ''; ?>>All Registered Contracts (All Time)</option>
                            </select>
                        </div>

                        <!-- 2. Category Filter -->
                        <div class="cmd-group">
                            <span class="cmd-label">Category:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=contracts&range=<?php echo urlencode($range ?? 'pm_90d'); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&sort=<?php echo urlencode($sort ?? 'expiry_asc'); ?>&search=<?php echo urlencode($search ?? ''); ?>&category='+this.value">
                                <option value="all" <?php echo ($category ?? 'all') === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="ma" <?php echo ($category ?? '') === 'ma' ? 'selected' : ''; ?>>Maintenance (MAs/SLAs)</option>
                                <option value="acronis" <?php echo ($category ?? '') === 'acronis' ? 'selected' : ''; ?>>Acronis Cloud/Backup</option>
                                <option value="hosting" <?php echo ($category ?? '') === 'hosting' ? 'selected' : ''; ?>>Web &amp; Email Hosting</option>
                                <option value="licenses" <?php echo ($category ?? '') === 'licenses' ? 'selected' : ''; ?>>Software Licenses</option>
                            </select>
                        </div>

                        <!-- 3. Status Filter -->
                        <div class="cmd-group">
                            <span class="cmd-label">Status:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=contracts&range=<?php echo urlencode($range ?? 'pm_90d'); ?>&category=<?php echo urlencode($category ?? 'all'); ?>&sort=<?php echo urlencode($sort ?? 'expiry_asc'); ?>&search=<?php echo urlencode($search ?? ''); ?>&status='+this.value">
                                <option value="all" <?php echo ($status ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="ACTIVE" <?php echo ($status ?? '') === 'ACTIVE' ? 'selected' : ''; ?>>Active (&gt; 30d)</option>
                                <option value="DUE_SOON" <?php echo ($status ?? '') === 'DUE_SOON' ? 'selected' : ''; ?>>Due Soon (≤ 30d)</option>
                                <option value="OVERDUE" <?php echo ($status ?? '') === 'OVERDUE' ? 'selected' : ''; ?>>Post Due / Overdue</option>
                            </select>
                        </div>

                        <!-- 4. Sort Filter -->
                        <div class="cmd-group">
                            <span class="cmd-label">Sort:</span>
                            <select class="cmd-select" onchange="window.location.href='reports.php?type=contracts&range=<?php echo urlencode($range ?? 'pm_90d'); ?>&category=<?php echo urlencode($category ?? 'all'); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&search=<?php echo urlencode($search ?? ''); ?>&sort='+this.value">
                                <option value="expiry_asc" <?php echo ($sort ?? 'expiry_asc') === 'expiry_asc' ? 'selected' : ''; ?>>Expiring Date (Earliest First)</option>
                                <option value="expiry_desc" <?php echo ($sort ?? '') === 'expiry_desc' ? 'selected' : ''; ?>>Expiring Date (Latest First)</option>
                                <option value="value_desc" <?php echo ($sort ?? '') === 'value_desc' ? 'selected' : ''; ?>>Renewal Value (Highest First)</option>
                                <option value="customer_asc" <?php echo ($sort ?? '') === 'customer_asc' ? 'selected' : ''; ?>>Customer (A-Z)</option>
                            </select>
                        </div>

                        <!-- 5. Contracts Search Form -->
                        <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 4px; margin: 0;">
                            <input type="hidden" name="type" value="contracts">
                            <input type="hidden" name="range" value="<?php echo htmlspecialchars($range ?? 'pm_90d'); ?>">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category ?? 'all'); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? 'all'); ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort ?? 'expiry_asc'); ?>">
                            <input type="text" name="search" class="cmd-input" placeholder="Search contracts..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="width: 140px;">
                            <button type="submit" class="cmd-btn" title="Search"><i class="icon-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="reports.php?type=contracts&range=<?php echo urlencode($range ?? 'pm_90d'); ?>&category=<?php echo urlencode($category ?? 'all'); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&sort=<?php echo urlencode($sort ?? 'expiry_asc'); ?>" class="cmd-btn" title="Clear search"><i class="icon-x"></i></a>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>

                        <?php if (in_array($type, ['invoices', 'ltv', 'churn', 'eol', 'rental_roi', 'dso_trends'])): ?>
                        <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 4px; margin: 0;">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
                            <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? 'all'); ?>">
                            <?php if (!empty($brand)): ?><input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>"><?php endif; ?>
                            <?php if (!empty($customer_type)): ?><input type="hidden" name="customer_type" value="<?php echo htmlspecialchars($customer_type); ?>"><?php endif; ?>
                            <?php if (!empty($rep_code)): ?><input type="hidden" name="rep_code" value="<?php echo htmlspecialchars($rep_code); ?>"><?php endif; ?>
                            <input type="text" name="search" class="cmd-input" placeholder="Search report..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="width: 130px;">
                            <button type="submit" class="cmd-btn" title="Search"><i class="icon-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="reports.php?type=<?php echo htmlspecialchars($type); ?>&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month); ?>&status=<?php echo urlencode($status ?? 'all'); ?>" class="cmd-btn" title="Clear search"><i class="icon-x"></i></a>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="cmd-right">
                        <button type="button" onclick="toggleReportMethodology()" id="btnReportMethodology" class="cmd-btn" title="View calculation methodology, standards & formulas">
                            <i class="icon-calculator"></i> Method
                        </button>
                        <button type="button" onclick="printCurrentReport()" class="cmd-btn" title="Print this report">
                            <i class="icon-printer"></i> Print
                        </button>
                        <button type="button" onclick="exportReportToPdf()" class="cmd-btn cmd-btn-primary" title="Export this report as PDF">
                            <i class="icon-file-text"></i> PDF
                        </button>
                        <?php 
                        $activeReportsList = ['monthly', 'invoices', 'unpaid_invoices', 'warranties', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit', 'unlinked_payments'];
                        if (in_array($type, $activeReportsList)): 
                            if ($type === 'monthly') {
                                $csvUrl = "reports.php?type=monthly&export=csv&mode=" . urlencode($monthlyMode ?? 'rolling') . "&year=" . urlencode($selectedYear ?? $year);
                            } elseif ($type === 'contracts') {
                                $csvUrl = "reports.php?type=contracts&export=csv&range=" . urlencode($range ?? 'pm_90d') . "&category=" . urlencode($category ?? 'all') . "&status=" . urlencode($status ?? 'all') . "&sort=" . urlencode($sort ?? 'expiry_asc') . "&search=" . urlencode($search ?? '');
                            } elseif ($type === 'brand_growth') {
                                $csvUrl = "reports.php?type=brand_growth&export=csv&view_mode=" . urlencode($viewMode ?? 'brand') . "&brand=" . urlencode($brandFilter ?? '') . "&category=" . urlencode($catFilter ?? '');
                            } else {
                                $csvUrl = "reports.php?type=" . urlencode($type) . "&export=csv&year=" . urlencode($year) . "&month=" . urlencode($month) . "&status=" . urlencode($status ?? 'all') . "&search=" . urlencode($search ?? '') . "&brand=" . urlencode($brand ?? '') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&sort=" . urlencode($sort ?? '') . "&tier=" . urlencode($tier ?? '') . "&risk=" . urlencode($risk ?? '') . "&aging_bracket=" . urlencode($agingBracket ?? 'all');
                            }
                        ?>
                            <a href="<?php echo $csvUrl; ?>" class="cmd-btn" title="Download filtered report as CSV">
                                <i class="icon-download"></i> CSV
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

        <div class="main-content">
            <div class="print-header-banner">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 10px; font-weight: 800; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em;">ACTIVITY | BI &bull; Executive Intelligence</div>
                        <h1 style="margin: 4px 0 0 0; font-size: 20px; font-weight: 800; color: #0f172a;"><?php echo htmlspecialchars($reportTitle); ?></h1>
                        <div style="font-size: 11px; color: #475569; margin-top: 4px;">
                            Generated on <?php echo date('Y-m-d H:i:s'); ?> | Scope: <?php echo htmlspecialchars(strtoupper($type)); ?>
                            <?php if (!empty($search)): ?> | Search: "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                            <?php if (!empty($year)): ?> | Year: <?php echo htmlspecialchars($year); ?><?php endif; ?>
                            <?php if (!empty($month)): ?> | Month: <?php echo htmlspecialchars($month); ?><?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 13px; font-weight: 800; color: #0f172a;">Active Solutions (Pvt) Ltd</div>
                        <div style="font-size: 10px; color: #64748b;">act.active.lk &bull; Confidential &bull; Executive Brief</div>
                    </div>
                </div>
            </div>
            <?php 
            $activeTypes = ['monthly', 'invoices', 'unpaid_invoices', 'warranties', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit', 'unlinked_payments'];
            if (!in_array($type, $activeTypes)): 
            ?>
                <div style="background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #92400e;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="icon-archive" style="font-size: 16px; color: #d97706;"></i>
                        <span><strong>Archived View:</strong> This report has been temporarily archived while the master invoice audit is active. You can return to the active <a href="reports.php?type=invoices" style="color: #4f46e5; font-weight: 600; text-decoration: underline;">Commercial Invoices View</a> or any of the 8 new strategic reports at any time.</span>
                    </div>
                </div>
            <?php endif; ?>
            <?php renderReportMethodology($type, $currency); ?>

            <?php
            // Modular Report View Dispatcher
            $viewKey = $controllerKey ?? $type;
            $reportViewFile = __DIR__ . "/views/reports/{$viewKey}.php";
            if (file_exists($reportViewFile)) {
                require $reportViewFile;
            } else {
                require __DIR__ . "/views/reports/default.php";
            }
            ?>
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
                    <a id="invModalEditLink" href="#" class="btn-view" style="background: #4f46e5; color: #ffffff !important; border: 1px solid #4f46e5; text-decoration: none; padding: 8px 14px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);"><i class="icon-edit-3"></i> Edit Invoice</a>
                    <button type="button" onclick="window.print()" class="btn-view" style="background: #f1f5f9; color: #334155; border: 1px solid var(--border-color); padding: 8px 14px; font-size: 13px;">
                        <i class="icon-printer"></i> Print
                    </button>
                    <a id="invModalCustomerReportLink" href="#" class="btn-view" style="background: #2563eb; color: #ffffff !important; border: 1px solid #2563eb; text-decoration: none; padding: 8px 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);"><i class="icon-building-2"></i> Customer Dossier</a>
                    <button class="modal-close" onclick="closeInvoiceDetails()" style="font-size: 28px; line-height: 1;">×</button>
                </div>
            </div>
            
            <div class="modal-body" id="invModalBody">
                <!-- Loaded dynamically via openInvoiceDetails(invoiceNumber) -->
            </div>
        </div>
    </div>

    <!-- Customer Payment Analytics & Turnaround Modal -->
    <div id="customerPaymentModalOverlay" class="modal-overlay" onclick="if(event.target === this) closeCustomerPaymentModal()" style="display:none; align-items:flex-start; padding: 30px 15px; overflow-y:auto; z-index:9999;">
        <div class="modal" style="max-width: 960px; width: 100%; margin: auto; background: #ffffff; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #cbd5e1; overflow:hidden;">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h2 id="payModalCustomerTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <i class="icon-clock" style="color: var(--primary);"></i>
                            <span id="payModalCustomerName">Customer Analytics</span>
                        </h2>
                        <span id="payModalTypeBadge" class="dense-badge" style="background: #e0e7ff; color: #4338ca; font-weight: 700;">Account</span>
                        <span id="payModalTermsBadge" class="dense-badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;">Terms: Net 30</span>
                    </div>
                    <p id="payModalSubtitle" style="color: #64748b; font-size: 12px; margin: 4px 0 0 0;">
                        360° Historical Payment Turnaround Analysis & 3-Year Purchase Profile
                    </p>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <a id="payModalDossierLink" href="#" class="btn-view" style="background: #2563eb; color: #ffffff !important; border: 1px solid #2563eb; text-decoration: none; padding: 6px 12px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; border-radius: 6px;" target="_blank">
                        <i class="icon-building-2"></i> Full Dossier
                    </a>
                    <button class="modal-close" onclick="closeCustomerPaymentModal()" style="font-size: 24px; line-height: 1; border: none; background: transparent; cursor: pointer; color: #64748b; padding: 2px 6px;">×</button>
                </div>
            </div>
            
            <div class="modal-body" id="payModalBody" style="padding: 20px;">
                <!-- Dynamically populated by renderCustomerPaymentModal(data) -->
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
            const editLink = document.getElementById('drawerEditLink');

            if (!drawer || !drawerBody) return;

            if (editLink) {
                <?php if (!isset($auth) || $auth->canAccessReport('edit_invoices')): ?>
                editLink.href = 'invoice_edit.php?inv=' + encodeURIComponent(invoiceNumber);
                editLink.style.display = 'inline-flex';
                <?php else: ?>
                editLink.style.display = 'none';
                <?php endif; ?>
            }

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
            const editLink = document.getElementById('invModalEditLink');
            if (editLink) {
                <?php if (!isset($auth) || $auth->canAccessReport('edit_invoices')): ?>
                editLink.href = 'invoice_edit.php?inv=' + encodeURIComponent(invoiceNumber);
                editLink.style.display = 'inline-flex';
                <?php else: ?>
                editLink.style.display = 'none';
                <?php endif; ?>
            }

            body.innerHTML = '<div style="text-align: center; padding: 60px 20px;"><div style="display: inline-block; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div><p style="margin-top: 15px; color: var(--text-muted); font-size: 14px;">Loading invoice line items, serial numbers, and payment reconciliation...</p></div>';

            overlay.style.display = 'flex';

            fetch(`reports.php?ajax_invoice_details=${encodeURIComponent(invoiceNumber)}`)
                .then(async r => {
                    const text = await r.text();
                    let res;
                    try {
                        res = JSON.parse(text);
                    } catch (e) {
                        if (text.includes('<!DOCTYPE') || text.includes('<html') || text.includes('login.php') || text.includes('Sign in')) {
                            throw new Error('Your session has timed out. Please refresh the page and log in again.');
                        }
                        const cleanSnippet = text.replace(/<[^>]*>?/gm, '').trim().substring(0, 120);
                        throw new Error(cleanSnippet || 'Invalid response from server.');
                    }
                    return res;
                })
                .then(res => {
                    if (res.error) {
                        body.innerHTML = `<div style="text-align: center; padding: 50px; color: var(--error); font-weight: 500;">${escapeHtml(res.error)}</div>`;
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
                    customerReportLink.style.display = 'inline-flex';

                    body.innerHTML = buildInvoiceAuditHtml(res);
                })
                .catch(err => {
                    body.innerHTML = `<div style="text-align: center; padding: 50px; color: var(--error);"><div style="font-size: 14px; font-weight: 600; margin-bottom: 6px;">Error Retrieving Invoice</div><div style="font-size: 12px; color: #64748b;">${escapeHtml(err.message)}</div><button onclick="selectInvoiceRow('${escapeHtml(invoiceNumber)}', null)" class="cmd-btn" style="margin-top: 15px; height: 28px; padding: 0 12px; font-size: 11px;">Retry</button></div>`;
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

            const effRate = h.applied_tax_rate ? Math.round(h.applied_tax_rate * 100) : 18;
            const isPlusVat = (taxTreatment === 'PLUS_VAT' || taxTreatment === 'VAT_INCLUSIVE');
            const treatBadge = isPlusVat
                ? `<span class="dense-badge dense-badge-plusvat" style="font-size: 8.5px; margin-left: auto;">+VAT (${effRate}%)</span>`
                : `<span class="dense-badge dense-badge-exempt" style="font-size: 8.5px; margin-left: auto;">Exempt</span>`;

            let html = '';

            // 1. Financial summary cards
            html += `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 12px;">
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px;">
                        <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: flex; justify-content: space-between;">
                            <span>Net Base</span>
                            ${isPlusVat ? '<span style="color:#6d28d9; font-size:9px;">Subtotal</span>' : ''}
                        </div>
                        <div style="font-size: 14px; font-weight: 800; color: var(--text-main); margin-top: 2px;">LKR ${nf.format(Math.round(h.total_base_value || 0))}</div>
                    </div>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px;">
                        <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: flex; align-items: center;">
                            <span>${effRate}% VAT</span>
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
                            ${h.end_customer ? `<span style="background: #ecfdf5; color: #047857; font-weight: 700; font-size: 11px; padding: 1px 7px; border-radius: 4px; border: 1px solid #a7f3d0;"><i class="icon-briefcase"></i> End Client: ${escapeHtml(h.end_customer)}</span>` : ''}
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
                                <div style="margin-top: 3px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                    ${it.brand ? `<span style="display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: 600; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;"><i class="icon-tag" style="font-size: 8px; margin-right: 3px;"></i>${escapeHtml(it.brand)}</span>` : ''}
                                    ${it.category ? `<span style="display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: 600; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;"><i class="icon-folder" style="font-size: 8px; margin-right: 3px;"></i>${escapeHtml(it.category)}</span>` : ''}
                                    <a href="product_mapping.php?tab=products&search=${encodeURIComponent(it.invoice_number)}" target="_blank" style="font-size: 9px; color: var(--text-muted); text-decoration: none; margin-left: 2px;" title="Reassign in Product Mapping"><i class="icon-edit-2" style="font-size: 8.5px;"></i> Edit</a>
                                </div>
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

        function closeCustomerPaymentModal() {
            const overlay = document.getElementById('customerPaymentModalOverlay');
            if (overlay) overlay.style.display = 'none';
        }

        function openCustomerPaymentModal(customerName) {
            const overlay = document.getElementById('customerPaymentModalOverlay');
            const nameSpan = document.getElementById('payModalCustomerName');
            const typeBadge = document.getElementById('payModalTypeBadge');
            const termsBadge = document.getElementById('payModalTermsBadge');
            const subtitle = document.getElementById('payModalSubtitle');
            const dossierLink = document.getElementById('payModalDossierLink');
            const body = document.getElementById('payModalBody');

            if (!overlay) return;

            nameSpan.innerText = customerName;
            typeBadge.innerText = 'Loading...';
            typeBadge.style.background = '#f1f5f9';
            typeBadge.style.color = '#64748b';
            termsBadge.style.display = 'none';
            subtitle.innerText = 'Fetching 360° payment turnaround history and yearly purchases...';
            dossierLink.href = 'customer_report.php?name=' + encodeURIComponent(customerName);
            
            body.innerHTML = `
                <div style="text-align: center; padding: 50px 20px;">
                    <div style="display: inline-block; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                    <p style="margin-top: 15px; color: #64748b; font-size: 13.5px; font-weight: 500;">Computing 7 payment turnaround metrics & 3-year purchase aggregates...</p>
                </div>
            `;

            overlay.style.display = 'flex';

            fetch(`reports.php?ajax_customer_payment_profile=${encodeURIComponent(customerName)}`)
                .then(async r => {
                    const text = await r.text();
                    let res;
                    try {
                        res = JSON.parse(text);
                    } catch (e) {
                        if (text.includes('<!DOCTYPE') || text.includes('<html') || text.includes('login.php')) {
                            throw new Error('Your session has timed out. Please refresh and log in again.');
                        }
                        throw new Error(text.replace(/<[^>]*>?/gm, '').trim().substring(0, 120) || 'Invalid server response');
                    }
                    return res;
                })
                .then(res => {
                    if (res.error) {
                        body.innerHTML = `<div style="text-align: center; padding: 40px; color: #ef4444; font-weight: 600;">${escapeHtml(res.error)}</div>`;
                        return;
                    }
                    renderCustomerPaymentModal(res);
                })
                .catch(err => {
                    body.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #ef4444;">
                            <div style="font-weight: 700; font-size: 14px; margin-bottom: 6px;">Failed to Load Payment Profile</div>
                            <div style="font-size: 12px; color: #64748b;">${escapeHtml(err.message)}</div>
                        </div>
                    `;
                    console.error(err);
                });
        }

        function renderCustomerPaymentModal(data) {
            const typeBadge = document.getElementById('payModalTypeBadge');
            const termsBadge = document.getElementById('payModalTermsBadge');
            const subtitle = document.getElementById('payModalSubtitle');
            const body = document.getElementById('payModalBody');

            if (typeBadge) {
                typeBadge.innerText = data.customer_type || 'Account';
                typeBadge.style.background = '#e0e7ff';
                typeBadge.style.color = '#4338ca';
            }
            if (termsBadge) {
                termsBadge.innerText = 'Terms: ' + (data.terms || 'Net 30');
                termsBadge.style.display = 'inline-block';
            }
            if (subtitle) {
                const totalGrossStr = new Intl.NumberFormat().format(Math.round(data.lifetime_summary?.total_purchases || 0));
                const totalInvs = data.lifetime_summary?.total_invoices || 0;
                subtitle.innerHTML = `Rep: <strong>${escapeHtml(data.sales_rep || 'Unassigned')}</strong> &bull; Lifetime Gross Billed: <strong>LKR ${totalGrossStr}</strong> across <strong>${totalInvs}</strong> invoices`;
            }

            const nf = new Intl.NumberFormat();
            const m = data.metrics_7 || {};

            function getDaysDisplay(days, status) {
                let text = '#15803d';
                if (status === 'extended' || days > 60) {
                    text = '#b91c1c';
                } else if (status === 'moderate' || days > 30) {
                    text = '#b45309';
                }
                return `
                    <div style="display:flex; align-items:baseline; gap:5px; margin: 4px 0;">
                        <span style="font-size: 26px; font-weight: 800; color: ${text}; font-family: monospace; letter-spacing: -0.5px;">${days}</span>
                        <span style="font-size: 13px; font-weight: 700; color: ${text};">days</span>
                    </div>
                `;
            }

            let html = `
                <!-- High Level Balance Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px; display:flex; justify-content:space-between;">
                            <span>Settled Invoices</span>
                            <span style="color: #10b981; font-weight: 800;">✓ Settled</span>
                        </div>
                        <div style="font-size: 18px; font-weight: 800; color: #0f172a;">LKR ${nf.format(Math.round(data.total_settled_amount || 0))}</div>
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">${data.settled_invoices_count || 0} historical settled bills</div>
                    </div>

                    <div style="background: #fff7ed; border: 1px solid #ffedd5; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #c2410c; margin-bottom: 4px; display:flex; justify-content:space-between;">
                            <span>Active Open Receivables</span>
                            <span style="color: #ea580c; font-weight: 800;">⏳ Open</span>
                        </div>
                        <div style="font-size: 18px; font-weight: 800; color: #c2410c;">LKR ${nf.format(Math.round(data.total_open_amount || 0))}</div>
                        <div style="font-size: 11.5px; color: #9a3412; margin-top: 2px;">${data.open_invoices_count || 0} bills currently awaiting payment</div>
                    </div>

                    <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #15803d; margin-bottom: 4px; display:flex; justify-content:space-between;">
                            <span>Commercial Status</span>
                            <span style="color: #16a34a; font-weight: 800;">Profile</span>
                        </div>
                        <div style="font-size: 15px; font-weight: 700; color: #166534; margin-top: 2px;">${escapeHtml(data.terms || 'Standard Net 30')}</div>
                        <div style="font-size: 11.5px; color: #15803d; margin-top: 3px;">Weighted turnaround: <strong>${m.weighted_avg ? m.weighted_avg.days + ' days' : 'N/A'}</strong></div>
                    </div>
                </div>

                <!-- Section 1: The 7 Payment Days Calculations -->
                <div style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                        <h3 style="margin: 0; font-size: 13.5px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;">
                            <i class="icon-activity" style="color: #2563eb;"></i> The 7 Payment Days Calculations
                        </h3>
                        <span style="font-size: 11px; color: #64748b; font-weight: 500;">Based on ${data.settled_invoices_count || 0} settled transactions</span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(215px, 1fr)); gap: 10px;">
            `;

            const metricsConfig = [
                { key: 'weighted_avg', badge: 'Core Cash Flow', highlight: true },
                { key: 'simple_avg', badge: 'Arithmetic', highlight: false },
                { key: 'median_days', badge: 'Dispute Proof', highlight: false },
                { key: 'recent_weighted', badge: 'Trailing 12M', highlight: true },
                { key: 'min_days', badge: 'Best Case', highlight: false },
                { key: 'max_days', badge: 'Tail Risk', highlight: false },
                { key: 'open_backlog_avg', badge: 'Current Unpaid', highlight: false }
            ];

            metricsConfig.forEach(cfg => {
                const item = m[cfg.key];
                if (!item) return;
                const isHighlight = cfg.highlight;
                html += `
                    <div style="background: ${isHighlight ? '#f5f3ff' : '#ffffff'}; border: 1px solid ${isHighlight ? '#c4b5fd' : '#e2e8f0'}; border-radius: 8px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                                <span style="font-size: 10.5px; font-weight: 700; color: ${isHighlight ? '#6d28d9' : '#475569'}; text-transform: uppercase;">${escapeHtml(item.short_label || '')}</span>
                                <span style="font-size: 9.5px; padding: 2px 6px; border-radius: 4px; background: ${isHighlight ? '#ede9fe' : '#f1f5f9'}; color: ${isHighlight ? '#5b21b6' : '#64748b'}; font-weight: 700;">${cfg.badge}</span>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 4px; line-height: 1.3;">${escapeHtml(item.label)}</div>
                            ${getDaysDisplay(item.days, item.status)}
                        </div>
                        <div style="font-size: 10.5px; color: #64748b; margin-top: 6px; border-top: 1px dashed #e2e8f0; padding-top: 6px; line-height: 1.35;">
                            ${escapeHtml(item.desc)}
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>

                <!-- Section 2: Yearly Purchases Breakdown for Last 3 Years + YTD -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                        <h3 style="margin: 0; font-size: 13.5px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;">
                            <i class="icon-calendar" style="color: #2563eb;"></i> Yearly Total Purchases (Last 3 Years + Current YTD)
                        </h3>
                        <span style="font-size: 11px; color: #64748b; font-weight: 500;">Billed Gross Revenue & Settlement Rate</span>
                    </div>

                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10.5px;">
                                    <th style="padding: 10px 14px;">Fiscal / Calendar Year</th>
                                    <th style="padding: 10px 14px; text-align: center;">Invoices</th>
                                    <th style="padding: 10px 14px; text-align: right;">Total Gross Billed</th>
                                    <th style="padding: 10px 14px; text-align: right;">Settled Amount</th>
                                    <th style="padding: 10px 14px; text-align: right;">Currently Open</th>
                                    <th style="padding: 10px 14px; text-align: center; width: 140px;">Collection Rate</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            const yearly = data.yearly_purchases || [];
            let sumTotal = 0, sumSettled = 0, sumOpen = 0, sumInvoices = 0;

            if (yearly.length === 0) {
                html += `<tr><td colspan="6" style="padding: 24px; text-align: center; color: #64748b;">No billing records found for this period.</td></tr>`;
            } else {
                yearly.forEach(yr => {
                    sumTotal += yr.total_gross;
                    sumSettled += yr.settled_gross;
                    sumOpen += yr.open_gross;
                    sumInvoices += yr.invoice_count;

                    const rate = yr.total_gross > 0 ? Math.round((yr.settled_gross / yr.total_gross) * 100) : 0;
                    const rateColor = rate >= 90 ? '#10b981' : (rate >= 50 ? '#f59e0b' : '#ef4444');

                    html += `
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 14px; font-weight: 700; color: #0f172a;">
                                ${yr.year}
                                ${yr.is_ytd ? '<span class="dense-badge" style="background:#e0f2fe; color:#0369a1; margin-left:6px; font-size:9.5px; padding:1px 5px;">Current YTD</span>' : ''}
                            </td>
                            <td style="padding: 10px 14px; text-align: center; font-weight: 600; color: #334155;">${yr.invoice_count}</td>
                            <td style="padding: 10px 14px; text-align: right; font-weight: 800; color: #0f172a; font-family: monospace;">LKR ${nf.format(Math.round(yr.total_gross))}</td>
                            <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: #16a34a; font-family: monospace;">LKR ${nf.format(Math.round(yr.settled_gross))}</td>
                            <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: ${yr.open_gross > 0 ? '#ea580c' : '#94a3b8'}; font-family: monospace;">LKR ${nf.format(Math.round(yr.open_gross))}</td>
                            <td style="padding: 10px 14px; text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <div style="flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; max-width: 60px;">
                                        <div style="width: ${rate}%; height: 100%; background: ${rateColor}; border-radius: 3px;"></div>
                                    </div>
                                    <span style="font-weight: 700; color: ${rateColor}; font-size: 11px; width: 34px; text-align: right;">${rate}%</span>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }

            const overallRate = sumTotal > 0 ? Math.round((sumSettled / sumTotal) * 100) : 0;
            const overallColor = overallRate >= 90 ? '#10b981' : (overallRate >= 50 ? '#f59e0b' : '#ef4444');

            html += `
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 800;">
                                    <td style="padding: 12px 14px; color: #0f172a;">Total (Analyzed 4-Year Period)</td>
                                    <td style="padding: 12px 14px; text-align: center; color: #0f172a;">${sumInvoices}</td>
                                    <td style="padding: 12px 14px; text-align: right; color: #0f172a; font-family: monospace;">LKR ${nf.format(Math.round(sumTotal))}</td>
                                    <td style="padding: 12px 14px; text-align: right; color: #16a34a; font-family: monospace;">LKR ${nf.format(Math.round(sumSettled))}</td>
                                    <td style="padding: 12px 14px; text-align: right; color: #ea580c; font-family: monospace;">LKR ${nf.format(Math.round(sumOpen))}</td>
                                    <td style="padding: 12px 14px; text-align: center;">
                                        <span style="font-weight: 800; color: ${overallColor}; font-size: 12px;">${overallRate}% Overall</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            `;

            body.innerHTML = html;
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
                closeCustomerPaymentModal();
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

        function toggleReportMethodology() {
            const panel = document.getElementById('reportMethodologyPanel');
            const btn = document.getElementById('btnReportMethodology');
            if (!panel) return;
            const isHidden = (panel.style.display === 'none' || !panel.style.display);
            if (isHidden) {
                panel.style.display = 'block';
                if (btn) {
                    btn.classList.add('active');
                    btn.style.background = '#eff6ff';
                    btn.style.borderColor = '#2563eb';
                    btn.style.color = '#1d4ed8';
                }
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                panel.style.display = 'none';
                if (btn) {
                    btn.classList.remove('active');
                    btn.style.background = '';
                    btn.style.borderColor = '';
                    btn.style.color = '';
                }
            }
        }

        function printCurrentReport() {
            const printAllBtn = document.querySelector('.pg-btn-print-all-link');
            if (printAllBtn && printAllBtn.href) {
                const total = printAllBtn.getAttribute('data-total') || '';
                const msg = total ? `Print all ${total} entries, or only the current page view?` : 'Print all entries across all pages, or only the current page view?';
                if (confirm(`${msg}\n\n• Click OK to Print All (${total} entries)\n• Click Cancel to Print Current View only`)) {
                    window.open(printAllBtn.href, '_blank');
                    return;
                }
            }
            window.print();
        }

        <?php if (!empty($_GET['print'])): ?>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 450);
        });
        <?php endif; ?>

        function exportReportToPdf() {
            const originalTitle = document.title;
            const reportName = "<?php echo addslashes(preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportTitle ?? 'Activity_BI_Report')); ?>";
            const dateStr = new Date().toISOString().slice(0, 10);
            document.title = `Activity_BI_${reportName}_${dateStr}`;
            window.print();
            setTimeout(() => {
                document.title = originalTitle;
            }, 2500);
        }

        window.addEventListener('beforeprint', () => {
            const modal = document.getElementById('invoiceModalOverlay');
            if (modal && window.getComputedStyle(modal).display !== 'none') {
                document.body.classList.add('printing-modal');
            } else {
                document.body.classList.remove('printing-modal');
            }
        });

        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-modal');
        });
    </script>
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
