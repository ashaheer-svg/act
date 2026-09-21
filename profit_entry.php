<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireReportAccess('profit_entry');

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', 'LKR ');

// Handle AJAX Save Request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_invoice_gp') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $invNumber = trim($_POST['invoice_number'] ?? '');
    $gp = isset($_POST['gp']) && $_POST['gp'] !== '' ? (float)$_POST['gp'] : null;
    $cost = isset($_POST['cost']) && $_POST['cost'] !== '' ? (float)$_POST['cost'] : null;
    $year = $_POST['year'] ?? date('Y');
    $month = $_POST['month'] ?? date('m');

    $result = $db->updateInvoiceGrossProfit($invNumber, $gp, $cost);
    if ($result && !empty($result['success'])) {
        $summary = $db->getProfitEntrySummary($year, $month);
        echo json_encode([
            'success' => true,
            'invoice' => $result,
            'summary' => $summary
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to update invoice profit'
        ]);
    }
    exit;
}

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$status = $_GET['status'] ?? 'all';
$customerType = $_GET['customer_type'] ?? '';
$repCode = $_GET['rep_code'] ?? '';
$search = $_GET['search'] ?? '';

// Fetch Invoices and KPI Summary
$filters = [
    'status' => $status,
    'customer_type' => $customerType,
    'rep_code' => $repCode,
    'search' => $search
];

$invoices = $db->getInvoicesForProfitEntry($year, $month, $filters);
$summary = $db->getProfitEntrySummary($year, $month);

$uniqueReps = $db->fetchAll("SELECT rep_code, rep_name FROM sales_rep_mapping ORDER BY rep_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gross Profit & Cost Entry Ledger - Active Solutions BI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=2.6.5">
    <style>
        .profit-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .profit-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .profit-kpi-card:hover {
            box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
        }
        .profit-kpi-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .profit-kpi-val {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            font-feature-settings: "tnum";
            line-height: 1.2;
        }
        .profit-kpi-sub {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Toolbar & Controls */
        .profit-toolbar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .profit-toolbar-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Selectable Entry Mode Pills */
        .mode-pill-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .mode-pill {
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .mode-pill:hover {
            color: #0f172a;
        }
        .mode-pill.active {
            background: #ffffff;
            color: #1e40af;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .mode-pill.active.mode-cost {
            color: #7c2d12;
            background: #ffedd5;
        }
        .mode-pill.active.mode-gp {
            color: #065f46;
            background: #d1fae5;
        }

        /* Status Tabs */
        .status-tab-group {
            display: inline-flex;
            gap: 6px;
        }
        .status-tab {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .status-tab:hover {
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .status-tab.active {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
        }
        .status-tab.active .tab-badge {
            background: #334155;
            color: #ffffff;
        }
        .tab-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #475569;
        }

        /* Data Table */
        .profit-ledger-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12.5px;
        }
        .profit-ledger-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #cbd5e1;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .profit-ledger-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            background: #ffffff;
        }
        .profit-ledger-table tr:hover td {
            background: #f8fafc;
        }
        .profit-ledger-table tr.row-completed td {
            background: #ffffff;
        }
        .profit-ledger-table tr.row-pending td {
            background: #fffdfa;
        }

        /* Input Controls in Table */
        .gp-ledger-input {
            width: 120px;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
            font-weight: 700;
            text-align: right;
            font-family: inherit;
            font-feature-settings: "tnum";
            color: #0f172a;
            transition: all 0.15s;
            background: #ffffff;
        }
        .gp-ledger-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .gp-ledger-input.highlight-mode {
            border-color: #f97316;
            background: #fffaf0;
        }
        .gp-ledger-input.highlight-gp {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .gp-ledger-input.saving {
            background: #fef3c7 !important;
            border-color: #f59e0b !important;
        }
        .gp-ledger-input.saved {
            background: #ecfdf5 !important;
            border-color: #10b981 !important;
        }

        .margin-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            font-feature-settings: "tnum";
        }
        .margin-high { background: #dcfce7; color: #15803d; }
        .margin-mid { background: #eff6ff; color: #1d4ed8; }
        .margin-low { background: #fef3c7; color: #b45309; }
        .margin-neg { background: #fee2e2; color: #b91c1c; }
        .margin-zero { background: #f1f5f9; color: #94a3b8; }

        .progress-bar-wrap {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            background: #10b981;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search invoices or clients...'; require_once 'includes/header.php'; ?>

            <div class="content-body">

                <!-- Page Header -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                            <h1 style="font-size: 26px; font-weight: 800; letter-spacing: -0.5px; color: #0f172a; margin: 0;">Gross Profit &amp; Cost Ledger</h1>
                            <span class="badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                                Invoice-Level Fast Entry
                            </span>
                        </div>
                        <p style="color: #64748b; font-size: 13px; margin: 0;">
                            Batch gross profit &amp; direct cost management across commercial invoices. In accordance with IRD standards, all figures are strictly <strong>excluding 18% VAT</strong>.
                        </p>
                    </div>

                    <!-- Entry Mode Toggle (User Requirement) -->
                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Active Entry Mode</span>
                        <div class="mode-pill-group" id="entryModePills">
                            <button type="button" class="mode-pill active mode-cost" id="btnModeCost" onclick="setEntryMode('cost')">
                                <i class="icon-tag"></i> Enter by Cost Price
                            </button>
                            <button type="button" class="mode-pill" id="btnModeGp" onclick="setEntryMode('gp')">
                                <i class="icon-dollar-sign"></i> Enter by Gross Profit
                            </button>
                            <button type="button" class="mode-pill" id="btnModeBoth" onclick="setEntryMode('both')">
                                <i class="icon-sliders"></i> Both Fields
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KPI Summary Cards -->
                <div class="profit-kpi-grid">
                    <!-- KPI 1: Invoices Completion -->
                    <div class="profit-kpi-card">
                        <div>
                            <div class="profit-kpi-label"><i class="icon-check-circle" style="color: #10b981;"></i> Invoice GP Completion</div>
                            <div class="profit-kpi-val" id="kpiCompletion">
                                <?= $summary['completed_count']; ?> <span style="font-size: 15px; color: #64748b; font-weight: 600;">/ <?= $summary['total_invoices']; ?></span>
                            </div>
                        </div>
                        <div>
                            <?php 
                            $pctComplete = $summary['total_invoices'] > 0 ? round(($summary['completed_count'] / $summary['total_invoices']) * 100) : 0;
                            ?>
                            <div class="progress-bar-wrap">
                                <div class="progress-bar-fill" id="kpiProgressFill" style="width: <?= $pctComplete; ?>%;"></div>
                            </div>
                            <div class="profit-kpi-sub" id="kpiCompletionSub">
                                <span style="color: #b45309; font-weight: 700;"><?= $summary['pending_count']; ?> Pending Entry</span> &bull; <span><?= $pctComplete; ?>% Completed</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: Net Base Revenue -->
                    <div class="profit-kpi-card">
                        <div>
                            <div class="profit-kpi-label"><i class="icon-file-text" style="color: #2563eb;"></i> Period Base Net Revenue</div>
                            <div class="profit-kpi-val" id="kpiBaseRevenue">
                                <?= $currency; ?><?= number_format($summary['total_base_revenue'], 2); ?>
                            </div>
                        </div>
                        <div class="profit-kpi-sub">
                            <span>Excluding statutory 18% VAT</span>
                        </div>
                    </div>

                    <!-- KPI 3: Realized Gross Profit -->
                    <div class="profit-kpi-card">
                        <div>
                            <div class="profit-kpi-label"><i class="icon-trending-up" style="color: #059669;"></i> Realized Gross Profit</div>
                            <div class="profit-kpi-val" id="kpiTotalGp" style="color: #059669;">
                                <?= $currency; ?><?= number_format($summary['total_gross_profit'], 2); ?>
                            </div>
                        </div>
                        <div class="profit-kpi-sub" id="kpiTotalCostSub">
                            <span>Total Cost: <?= $currency; ?><?= number_format($summary['total_cost'], 2); ?></span>
                        </div>
                    </div>

                    <!-- KPI 4: Overall Gross Margin % -->
                    <div class="profit-kpi-card">
                        <div>
                            <div class="profit-kpi-label"><i class="icon-percent" style="color: #7c3aed;"></i> Overall Gross Margin</div>
                            <div class="profit-kpi-val" id="kpiMarginPct" style="color: <?= $summary['overall_margin_pct'] >= 20 ? '#059669' : ($summary['overall_margin_pct'] >= 10 ? '#2563eb' : '#b45309'); ?>;">
                                <?= number_format($summary['overall_margin_pct'], 1); ?>%
                            </div>
                        </div>
                        <div class="profit-kpi-sub">
                            <span>Benchmark: Net Margin on Invoiced Base</span>
                        </div>
                    </div>
                </div>

                <!-- Toolbar & Filter Controls -->
                <div class="profit-toolbar">
                    <div class="profit-toolbar-group">
                        <!-- Year Selector -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569;">Year:</label>
                            <select onchange="updateFilters('year', this.value)" class="filter-select" style="padding: 5px 10px; font-size: 12.5px;">
                                <?php for($y = 2023; $y <= 2026; $y++): ?>
                                    <option value="<?= $y; ?>" <?= $year == $y ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Month Selector -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569;">Month:</label>
                            <select onchange="updateFilters('month', this.value)" class="filter-select" style="padding: 5px 10px; font-size: 12.5px;">
                                <?php for($m = 1; $m <= 12; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $mStr; ?>" <?= $month == $mStr ? 'selected' : ''; ?>><?= date('F', mktime(0,0,0,$m,1)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Customer Type Filter -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569;">Customer Type:</label>
                            <select onchange="updateFilters('customer_type', this.value)" class="filter-select" style="padding: 5px 10px; font-size: 12.5px;">
                                <option value="">All Types</option>
                                <option value="Partner" <?= $customerType === 'Partner' ? 'selected' : ''; ?>>Partners</option>
                                <option value="End Customer" <?= $customerType === 'End Customer' ? 'selected' : ''; ?>>End Customers</option>
                            </select>
                        </div>

                        <!-- Sales Rep Filter -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569;">Rep:</label>
                            <select onchange="updateFilters('rep_code', this.value)" class="filter-select" style="padding: 5px 10px; font-size: 12.5px;">
                                <option value="">All Reps</option>
                                <?php foreach($uniqueReps as $ur): ?>
                                    <option value="<?= htmlspecialchars($ur['rep_code']); ?>" <?= $repCode === $ur['rep_code'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($ur['rep_name']); ?> (<?= htmlspecialchars($ur['rep_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="profit-toolbar-group">
                        <!-- Status Filter Tabs -->
                        <div class="status-tab-group">
                            <a href="javascript:void(0)" onclick="updateFilters('status', 'all')" class="status-tab <?= $status === 'all' ? 'active' : ''; ?>">
                                All <span class="tab-badge"><?= $summary['total_invoices']; ?></span>
                            </a>
                            <a href="javascript:void(0)" onclick="updateFilters('status', 'pending')" class="status-tab <?= $status === 'pending' ? 'active' : ''; ?>" style="color: #b45309;">
                                <i class="icon-clock" style="font-size: 12px;"></i> Pending GP <span class="tab-badge" style="background: #fef3c7; color: #92400e;"><?= $summary['pending_count']; ?></span>
                            </a>
                            <a href="javascript:void(0)" onclick="updateFilters('status', 'completed')" class="status-tab <?= $status === 'completed' ? 'active' : ''; ?>" style="color: #15803d;">
                                <i class="icon-check" style="font-size: 12px;"></i> Completed <span class="tab-badge" style="background: #dcfce7; color: #166534;"><?= $summary['completed_count']; ?></span>
                            </a>
                        </div>

                        <!-- Live Text Search -->
                        <div style="position: relative;">
                            <input type="text" id="tableFilterInput" class="form-control" placeholder="Filter customer / invoice..." onkeyup="filterLedgerTable()" style="padding-left: 28px; width: 200px; height: 32px; font-size: 12px;">
                            <i class="icon-search" style="position: absolute; left: 9px; top: 9px; color: #94a3b8; font-size: 13px;"></i>
                        </div>
                    </div>
                </div>

                <!-- Main Data Table Card -->
                <div class="card" style="padding: 0; overflow: hidden; border: 1px solid #e2e8f0; border-radius: 10px;">
                    <div style="padding: 10px 16px; background: #fffbeb; border-bottom: 1px solid #fef3c7; font-size: 12px; color: #92400e; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="icon-info" style="font-size: 15px; color: #d97706;"></i>
                            <span><strong>Fast 10-Key Entry Mode:</strong> Type the value and press <kbd style="background: #ffffff; border: 1px solid #d1d5db; padding: 1px 5px; border-radius: 3px; font-family: monospace;">Enter</kbd> or <kbd style="background: #ffffff; border: 1px solid #d1d5db; padding: 1px 5px; border-radius: 3px; font-family: monospace;">↓</kbd> to automatically jump to the next invoice. Values auto-save instantly.</span>
                        </div>
                        <span id="saveStatusIndicator" style="font-weight: 700; color: #059669; display: none;">
                            <i class="icon-check-circle"></i> Changes auto-saved
                        </span>
                    </div>

                    <div style="overflow-x: auto; max-height: calc(100vh - 340px);">
                        <table class="profit-ledger-table" id="profitLedgerTable">
                            <thead>
                                <tr>
                                    <th style="width: 35px; text-align: center;">#</th>
                                    <th style="width: 85px;">Date</th>
                                    <th style="width: 105px;">Invoice #</th>
                                    <th style="min-width: 220px;">Customer Account</th>
                                    <th style="width: 80px;">Rep</th>
                                    <th style="min-width: 160px;">Products &amp; Brand</th>
                                    <th class="text-right" style="width: 120px;">Base Net (Excl. VAT)</th>
                                    <th class="text-right" style="width: 140px; background: #fffaf0;" id="thCost">
                                        Cost Price (Excl. VAT)
                                    </th>
                                    <th class="text-right" style="width: 140px; background: #f0fdf4;" id="thGp">
                                        Gross Profit (Excl. VAT)
                                    </th>
                                    <th class="text-center" style="width: 90px;">Margin %</th>
                                    <th class="text-center" style="width: 85px;">Status</th>
                                    <th class="text-center" style="width: 45px;" title="Granular line item audit">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                    <tr>
                                        <td colspan="12" style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                                            <i class="icon-inbox" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No commercial invoices found matching the selected period and filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $rowIdx = 0;
                                    foreach ($invoices as $row): 
                                        $rowIdx++;
                                        $baseVal = (float)$row['base_value'];
                                        $costVal = $row['calc_cost'];
                                        $gpVal = $row['calc_gp'];
                                        $margin = (float)$row['margin_pct'];
                                        $isEntered = $row['is_entered'] == 1;

                                        $marginClass = 'margin-zero';
                                        if ($margin >= 25) $marginClass = 'margin-high';
                                        elseif ($margin >= 15) $marginClass = 'margin-mid';
                                        elseif ($margin > 0) $marginClass = 'margin-low';
                                        elseif ($margin < 0) $marginClass = 'margin-neg';
                                    ?>
                                    <tr class="ledger-row <?= $isEntered ? 'row-completed' : 'row-pending'; ?>" 
                                        data-inv="<?= htmlspecialchars($row['invoice_number']); ?>"
                                        data-base="<?= $baseVal; ?>"
                                        data-row-index="<?= $rowIdx; ?>">
                                        <td style="text-align: center; color: #94a3b8; font-weight: 700; font-size: 11px;">
                                            <?= $rowIdx; ?>
                                        </td>
                                        <td style="color: #64748b; font-size: 12px; white-space: nowrap;">
                                            <?= htmlspecialchars($row['invoice_date']); ?>
                                        </td>
                                        <td>
                                            <a href="invoice_edit.php?inv=<?= urlencode($row['invoice_number']); ?>" target="_blank" style="font-family: monospace; font-weight: 700; color: #1e40af; text-decoration: none;" title="Open detailed commercial editor">
                                                <?= htmlspecialchars($row['invoice_number']); ?>
                                                <i class="icon-external-link" style="font-size: 10px; opacity: 0.6;"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a; line-height: 1.3;">
                                                <?= htmlspecialchars($row['customer_name']); ?>
                                            </div>
                                            <div style="margin-top: 2px;">
                                                <span class="badge" style="font-size: 9.5px; padding: 1px 5px; border-radius: 4px; <?= $row['customer_type'] === 'Partner' ? 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;' : 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;'; ?>">
                                                    <?= htmlspecialchars($row['customer_type']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; font-weight: 700; font-size: 10.5px;" title="<?= htmlspecialchars($row['rep_name']); ?>">
                                                <?= htmlspecialchars($row['sales_rep_code']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 11.5px; color: #475569; line-height: 1.3;">
                                                <?= !empty($row['brands_preview']) ? htmlspecialchars($row['brands_preview']) : '<span style="color: #94a3b8;">Standard Items</span>'; ?>
                                            </div>
                                            <div style="font-size: 10.5px; color: #94a3b8;">
                                                <?= (int)$row['items_count']; ?> item<?= (int)$row['items_count'] == 1 ? '' : 's'; ?>
                                            </div>
                                        </td>
                                        <td class="text-right" style="font-weight: 700; color: #0f172a; font-feature-settings: 'tnum';">
                                            <?= number_format($baseVal, 2); ?>
                                        </td>

                                        <!-- EDITABLE COST INPUT -->
                                        <td class="text-right" style="background: #fffaf0;">
                                            <input type="number" step="0.01" 
                                                   class="gp-ledger-input input-cost highlight-mode" 
                                                   data-field="cost"
                                                   data-inv="<?= htmlspecialchars($row['invoice_number']); ?>"
                                                   value="<?= $costVal !== null ? number_format($costVal, 2, '.', '') : ''; ?>" 
                                                   placeholder="0.00"
                                                   oninput="onLedgerCostChange(this)"
                                                   onchange="commitLedgerSave(this)"
                                                   onkeydown="handleLedgerKey(event, this)">
                                        </td>

                                        <!-- EDITABLE GP INPUT -->
                                        <td class="text-right" style="background: #f0fdf4;">
                                            <input type="number" step="0.01" 
                                                   class="gp-ledger-input input-gp" 
                                                   data-field="gp"
                                                   data-inv="<?= htmlspecialchars($row['invoice_number']); ?>"
                                                   value="<?= $gpVal !== null ? number_format($gpVal, 2, '.', '') : ''; ?>" 
                                                   placeholder="0.00"
                                                   oninput="onLedgerGpChange(this)"
                                                   onchange="commitLedgerSave(this)"
                                                   onkeydown="handleLedgerKey(event, this)">
                                        </td>

                                        <!-- CALCULATED MARGIN % -->
                                        <td class="text-center">
                                            <span class="margin-badge <?= $marginClass; ?> cell-margin">
                                                <?= $isEntered ? number_format($margin, 1) . '%' : '&mdash;'; ?>
                                            </span>
                                        </td>

                                        <!-- STATUS BADGE -->
                                        <td class="text-center cell-status">
                                            <?php if ($isEntered): ?>
                                                <span class="badge" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 10px; padding: 2px 6px; border-radius: 4px;">
                                                    <i class="icon-check"></i> Saved
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 10px; padding: 2px 6px; border-radius: 4px;">
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- EDIT LINK -->
                                        <td class="text-center">
                                            <a href="invoice_edit.php?inv=<?= urlencode($row['invoice_number']); ?>" target="_blank" class="cmd-btn" style="height: 24px; padding: 0 6px; color: #475569;" title="Edit granular item lines in invoice editor">
                                                <i class="icon-edit-2" style="font-size: 12px;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>

    <script>
        const ACTIVE_YEAR = <?= json_encode($year); ?>;
        const ACTIVE_MONTH = <?= json_encode($month); ?>;
        const CURRENCY_SYMBOL = <?= json_encode($currency); ?>;

        let currentEntryMode = localStorage.getItem('profit_entry_mode') || 'cost';

        document.addEventListener('DOMContentLoaded', () => {
            setEntryMode(currentEntryMode, false);
        });

        // ── ENTRY MODE SWITCHER (Cost vs GP vs Both) ──
        function setEntryMode(mode, savePref = true) {
            currentEntryMode = mode;
            if (savePref) {
                localStorage.setItem('profit_entry_mode', mode);
            }

            // Update mode pills
            document.querySelectorAll('.mode-pill').forEach(btn => btn.classList.remove('active', 'mode-cost', 'mode-gp'));
            const activeBtn = document.getElementById(mode === 'cost' ? 'btnModeCost' : (mode === 'gp' ? 'btnModeGp' : 'btnModeBoth'));
            if (activeBtn) {
                activeBtn.classList.add('active');
                if (mode === 'cost') activeBtn.classList.add('mode-cost');
                if (mode === 'gp') activeBtn.classList.add('mode-gp');
            }

            // Update column header highlights
            const thCost = document.getElementById('thCost');
            const thGp = document.getElementById('thGp');
            if (thCost && thGp) {
                thCost.style.background = (mode === 'cost' || mode === 'both') ? '#fff7ed' : '#ffffff';
                thGp.style.background = (mode === 'gp' || mode === 'both') ? '#f0fdf4' : '#ffffff';
            }

            // Update input highlights
            document.querySelectorAll('.input-cost').forEach(inp => {
                inp.classList.toggle('highlight-mode', mode === 'cost' || mode === 'both');
            });
            document.querySelectorAll('.input-gp').forEach(inp => {
                inp.classList.toggle('highlight-gp', mode === 'gp' || mode === 'both');
            });
        }

        // ── BIDIRECTIONAL ROW CALCULATIONS (Excl. VAT) ──
        function onLedgerCostChange(costInput) {
            const row = costInput.closest('tr');
            const baseVal = parseFloat(row.getAttribute('data-base')) || 0;
            const cost = costInput.value !== '' ? parseFloat(costInput.value) : null;
            const gpInput = row.querySelector('.input-gp');

            if (cost !== null && !isNaN(cost)) {
                const gp = baseVal - cost;
                gpInput.value = gp.toFixed(2);
                updateRowMarginBadge(row, gp, baseVal);
            } else {
                gpInput.value = '';
                updateRowMarginBadge(row, null, baseVal);
            }
        }

        function onLedgerGpChange(gpInput) {
            const row = gpInput.closest('tr');
            const baseVal = parseFloat(row.getAttribute('data-base')) || 0;
            const gp = gpInput.value !== '' ? parseFloat(gpInput.value) : null;
            const costInput = row.querySelector('.input-cost');

            if (gp !== null && !isNaN(gp)) {
                const cost = baseVal - gp;
                costInput.value = cost.toFixed(2);
                updateRowMarginBadge(row, gp, baseVal);
            } else {
                costInput.value = '';
                updateRowMarginBadge(row, null, baseVal);
            }
        }

        function updateRowMarginBadge(row, gp, baseVal) {
            const badge = row.querySelector('.cell-margin');
            badge.className = 'margin-badge cell-margin';

            if (gp === null || baseVal <= 0) {
                badge.classList.add('margin-zero');
                badge.innerHTML = '&mdash;';
                return;
            }

            const marginPct = (gp / baseVal) * 100;
            if (marginPct >= 25) badge.classList.add('margin-high');
            else if (marginPct >= 15) badge.classList.add('margin-mid');
            else if (marginPct > 0) badge.classList.add('margin-low');
            else badge.classList.add('margin-neg');
            badge.innerText = marginPct.toFixed(1) + '%';
        }

        // ── AJAX AUTO-SAVE ──
        function commitLedgerSave(input) {
            const row = input.closest('tr');
            const invNumber = row.getAttribute('data-inv');
            const costVal = row.querySelector('.input-cost').value;
            const gpVal = row.querySelector('.input-gp').value;

            input.classList.add('saving');

            const formData = new FormData();
            formData.append('action', 'save_invoice_gp');
            formData.append('invoice_number', invNumber);
            formData.append('cost', costVal);
            formData.append('gp', gpVal);
            formData.append('year', ACTIVE_YEAR);
            formData.append('month', ACTIVE_MONTH);

            fetch('profit_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                input.classList.remove('saving');
                if (res.success) {
                    input.classList.add('saved');
                    setTimeout(() => input.classList.remove('saved'), 1200);

                    // Update Row Status
                    const isEntered = costVal !== '' || gpVal !== '';
                    row.classList.toggle('row-completed', isEntered);
                    row.classList.toggle('row-pending', !isEntered);

                    const statusCell = row.querySelector('.cell-status');
                    if (isEntered) {
                        statusCell.innerHTML = '<span class="badge" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 10px; padding: 2px 6px; border-radius: 4px;"><i class="icon-check"></i> Saved</span>';
                    } else {
                        statusCell.innerHTML = '<span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 10px; padding: 2px 6px; border-radius: 4px;">Pending</span>';
                    }

                    // Update KPI Summary Cards
                    if (res.summary) {
                        updateKpiCards(res.summary);
                    }

                    // Show top save flash
                    const indicator = document.getElementById('saveStatusIndicator');
                    if (indicator) {
                        indicator.style.display = 'inline-block';
                        setTimeout(() => indicator.style.display = 'none', 2500);
                    }
                } else {
                    alert('Error saving profit for ' + invNumber + ': ' + (res.error || 'Unknown error'));
                }
            })
            .catch(err => {
                input.classList.remove('saving');
                console.error('Network save error:', err);
            });
        }

        // ── UPDATE SUMMARY CARDS DYNAMICALLY ──
        function updateKpiCards(s) {
            const nf = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const total = s.total_invoices || 0;
            const completed = s.completed_count || 0;
            const pending = s.pending_count || 0;
            const pct = total > 0 ? Math.round((completed / total) * 100) : 0;

            document.getElementById('kpiCompletion').innerHTML = `${completed} <span style="font-size: 15px; color: #64748b; font-weight: 600;">/ ${total}</span>`;
            document.getElementById('kpiProgressFill').style.width = pct + '%';
            document.getElementById('kpiCompletionSub').innerHTML = `<span style="color: #b45309; font-weight: 700;">${pending} Pending Entry</span> &bull; <span>${pct}% Completed</span>`;

            document.getElementById('kpiBaseRevenue').innerText = CURRENCY_SYMBOL + nf.format(s.total_base_revenue || 0);
            document.getElementById('kpiTotalGp').innerText = CURRENCY_SYMBOL + nf.format(s.total_gross_profit || 0);
            document.getElementById('kpiTotalCostSub').innerHTML = `<span>Total Cost: ${CURRENCY_SYMBOL}${nf.format(s.total_cost || 0)}</span>`;
            
            const marginEl = document.getElementById('kpiMarginPct');
            const mVal = parseFloat(s.overall_margin_pct) || 0;
            marginEl.innerText = mVal.toFixed(1) + '%';
            marginEl.style.color = mVal >= 20 ? '#059669' : (mVal >= 10 ? '#2563eb' : '#b45309');
        }

        // ── FAST KEYBOARD NAVIGATION (Enter / ArrowDown / ArrowUp) ──
        function handleLedgerKey(e, input) {
            const isDown = (e.key === 'Enter' || e.key === 'ArrowDown');
            const isUp = (e.key === 'ArrowUp');

            if (!isDown && !isUp) return;

            e.preventDefault();
            const row = input.closest('tr');
            const targetRow = isDown ? row.nextElementSibling : row.previousElementSibling;

            if (targetRow && targetRow.classList.contains('ledger-row')) {
                const targetFieldClass = input.classList.contains('input-cost') ? '.input-cost' : '.input-gp';
                const nextInput = targetRow.querySelector(targetFieldClass);
                if (nextInput) {
                    nextInput.focus();
                    nextInput.select();
                }
            }
        }

        // ── CLIENT-SIDE TABLE FILTER ──
        function filterLedgerTable() {
            const filter = document.getElementById('tableFilterInput').value.toLowerCase();
            const rows = document.querySelectorAll('#profitLedgerTable tbody tr.ledger-row');

            rows.forEach(r => {
                const text = r.textContent.toLowerCase();
                r.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        }

        // ── UPDATE URL FILTERS ──
        function updateFilters(key, val) {
            const url = new URL(window.location.href);
            if (val === '' || val === null) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, val);
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
