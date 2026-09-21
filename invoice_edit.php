<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$auth->requireReportAccess('edit_invoices');
$user = $auth->getCurrentUser();
$reports = new Reports($db);

$currency = $db->getSetting('currency_symbol', 'LKR ');

// ── AJAX Save Handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $auth->requireReportAccess('edit_invoices');
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        if ($action === 'switch_invoice_vat_mode') {
            $invoiceNumber = trim($_POST['invoice_number'] ?? '');
            $targetMode = trim($_POST['target_mode'] ?? 'VAT_INCLUSIVE');
            $res = $db->switchInvoiceVatMode($invoiceNumber, $targetMode);
            echo json_encode($res);
            exit;
        }

        if ($action === 'save_invoice_full') {
            $payload = json_decode($_POST['payload'] ?? '{}', true);
            $invoiceNumber = trim($payload['invoice_number'] ?? '');
            if (empty($invoiceNumber)) {
                throw new Exception('Invoice number is required.');
            }

            // 1. Update Header
            $headerData = $payload['header'] ?? [];
            $db->updateInvoiceHeader($invoiceNumber, $headerData);

            // 2. Update Commercial Line Items
            $items = $payload['items'] ?? [];
            foreach ($items as $item) {
                $itemId = (int)($item['id'] ?? 0);
                if ($itemId > 0) {
                    $itemUpdate = [
                        'clean_product_name' => trim($item['clean_product_name'] ?? ''),
                        'brand'              => trim($item['brand'] ?? ''),
                        'category'           => trim($item['category'] ?? ''),
                        'product_type'       => trim($item['product_type'] ?? 'HARDWARE'),
                        'unit_cost'          => isset($item['unit_cost']) && $item['unit_cost'] !== '' ? (float)$item['unit_cost'] : null,
                        'gross_profit'       => isset($item['gross_profit']) && $item['gross_profit'] !== '' ? (float)$item['gross_profit'] : null,
                        'vat_treatment'      => trim($item['vat_treatment'] ?? ''),
                        'end_customer'       => trim($item['end_customer'] ?? '')
                    ];
                    $db->updateInvoiceItemDetails($itemId, $itemUpdate);
                }
            }

            // 3. Update Hardware Assets & Warranties
            $assets = $payload['assets'] ?? [];
            foreach ($assets as $asset) {
                $assetId = (int)($asset['id'] ?? 0);
                if ($assetId > 0) {
                    $assetUpdate = [
                        'serial_number'        => trim($asset['serial_number'] ?? ''),
                        'model_sku'            => trim($asset['model_sku'] ?? ''),
                        'brand'                => trim($asset['brand'] ?? 'Synology'),
                        'warranty_type'        => trim($asset['warranty_type'] ?? 'Standard Hardware Warranty'),
                        'warranty_months'      => !empty($asset['warranty_months']) ? (int)$asset['warranty_months'] : 36,
                        'warranty_start_date'  => !empty($asset['warranty_start_date']) ? trim($asset['warranty_start_date']) : null,
                        'warranty_expiry_date' => !empty($asset['warranty_expiry_date']) ? trim($asset['warranty_expiry_date']) : null,
                        'warranty_status'      => trim($asset['warranty_status'] ?? 'Active'),
                        'notes'                => trim($asset['notes'] ?? ''),
                        'end_customer'         => trim($asset['end_customer'] ?? '')
                    ];
                    $db->updateHardwareAsset($assetId, $assetUpdate);
                }
            }

            // 4. Add Newly Created Assets
            $newAssets = $payload['new_assets'] ?? [];
            foreach ($newAssets as $newA) {
                $serial = trim($newA['serial_number'] ?? '');
                if (!empty($serial)) {
                    $newA['invoice_number'] = $invoiceNumber;
                    $db->addHardwareAsset($newA);
                }
            }

            // 5. Delete Marked Assets
            $deleteAssets = $payload['delete_assets'] ?? [];
            foreach ($deleteAssets as $delId) {
                $delId = (int)$delId;
                if ($delId > 0) {
                    $db->deleteHardwareAsset($delId);
                }
            }

            // 6. Update Software Subscriptions
            $subscriptions = $payload['subscriptions'] ?? [];
            foreach ($subscriptions as $sub) {
                $subId = (int)($sub['id'] ?? 0);
                if ($subId > 0) {
                    $subUpdate = [
                        'software_name'             => trim($sub['software_name'] ?? ''),
                        'edition_tier'              => trim($sub['edition_tier'] ?? ''),
                        'license_seats'             => !empty($sub['license_seats']) ? (int)$sub['license_seats'] : 1,
                        'period_start_date'         => !empty($sub['period_start_date']) ? trim($sub['period_start_date']) : null,
                        'period_end_date'           => !empty($sub['period_end_date']) ? trim($sub['period_end_date']) : null,
                        'term_months'               => !empty($sub['term_months']) ? (int)$sub['term_months'] : 12,
                        'renewal_status'            => trim($sub['renewal_status'] ?? 'Active'),
                        'renewal_opportunity_value' => isset($sub['renewal_opportunity_value']) ? (float)$sub['renewal_opportunity_value'] : 0,
                        'end_customer'              => trim($sub['end_customer'] ?? '')
                    ];
                    $db->updateSoftwareSubscription($subId, $subUpdate);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Invoice changes saved successfully!']);
            exit;
        }

        throw new Exception("Unknown action '$action'");
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ── GET Mode: Load Invoice Details ──
$inv = trim($_GET['inv'] ?? '');
if (empty($inv)) {
    header('Location: reports.php?type=invoices');
    exit;
}

$invoiceData = $reports->getInvoiceDetails($inv);
if (empty($invoiceData) || !empty($invoiceData['error'])) {
    $errorMsg = $invoiceData['error'] ?? "Invoice '$inv' not found.";
}

$header = $invoiceData['header'] ?? [];
$customer = $invoiceData['customer'] ?? [];
$items = $invoiceData['items'] ?? [];
$rawLines = $invoiceData['lines'] ?? [];
$assets = $invoiceData['assets'] ?? [];
$subscriptions = $invoiceData['subscriptions'] ?? [];
$reconciliation = $invoiceData['reconciliation'] ?? [];

// Taxonomy master lists for dropdowns
$masterBrands = $db->getBrands();
$masterCategories = $db->getCategories();
$salesReps = $db->getSalesReps();

// Known end-customers for autocomplete datalist
$knownEndCustomers = $db->fetchAll("
    SELECT DISTINCT end_customer FROM (
        SELECT DISTINCT end_customer FROM sales WHERE end_customer IS NOT NULL AND TRIM(end_customer) != ''
        UNION
        SELECT DISTINCT end_customer FROM invoice_items WHERE end_customer IS NOT NULL AND TRIM(end_customer) != ''
    ) ORDER BY end_customer ASC
");

$title = "Edit Commercial Invoice: " . htmlspecialchars($inv);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title); ?> - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Inter+Tight:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=2.6.0">
    <style>
        .edit-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
        }
        .edit-card-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-divider);
        }
        .form-grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .field-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
        }
        .field-input, .field-select {
            height: 28px;
            font-size: 12px;
            font-family: inherit;
            font-weight: 500;
            color: var(--text-main);
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0 8px;
            outline: none;
            transition: border-color 0.15s, background 0.15s;
        }
        .field-input:focus, .field-select:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        .field-input-tabular {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum" 1;
            text-align: right;
            font-weight: 600;
        }
        .table-edit-input {
            width: 100%;
            height: 24px;
            font-size: 11px;
            font-family: inherit;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            padding: 0 6px;
            outline: none;
            box-sizing: border-box;
        }
        .table-edit-input:focus {
            border-color: var(--primary);
            background: #fdfefe;
        }
        .table-edit-select {
            width: 100%;
            height: 24px;
            font-size: 11px;
            font-family: inherit;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            padding: 0 4px;
            outline: none;
        }
        .gp-pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 10.5px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .gp-high { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .gp-med { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .gp-low { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .toast-notify {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0f172a;
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-lg);
            z-index: 999999;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.25s, transform 0.25s;
            pointer-events: none;
        }
        .toast-notify.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search invoices or serials...'; require_once 'includes/header.php'; ?>

            <div class="content-body">

                <?php if (!empty($errorMsg)): ?>
                    <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 6px; padding: 20px; text-align: center; margin-top: 20px;">
                        <h2 style="font-size: 16px; color: #b91c1c; margin-bottom: 8px;">Invoice Not Found</h2>
                        <p style="color: #64748b; font-size: 13px;"><?= htmlspecialchars($errorMsg); ?></p>
                        <a href="reports.php?type=invoices" class="cmd-btn" style="margin-top: 12px; display: inline-flex;">&larr; Return to Invoices</a>
                    </div>
                <?php else: ?>

                <!-- 1. 38px Unified Command Bar -->
                <div class="command-bar">
                    <div class="cmd-left">
                        <a href="reports.php?type=invoices" class="cmd-btn" title="Back to Invoices Ledger">
                            <i class="icon-arrow-left"></i> Back
                        </a>
                        <span style="font-size: 12px; font-weight: 800; color: var(--text-main); margin-left: 4px;">
                            Invoice #<?= htmlspecialchars($inv); ?>
                        </span>
                        <span class="dense-badge dense-badge-inclusive" style="font-size: 10px;">
                            <?= htmlspecialchars($header['customer_name'] ?? ''); ?>
                        </span>
                        <?php if (!empty($header['paid_date'])): ?>
                            <span class="dense-badge dense-badge-settled" style="font-size: 10px;">
                                <i class="icon-check"></i> Settled (<?= htmlspecialchars($header['paid_date']); ?>)
                            </span>
                        <?php else: ?>
                            <span class="dense-badge dense-badge-overdue" style="font-size: 10px;">
                                <i class="icon-clock"></i> Unpaid
                            </span>
                        <?php endif; ?>

                        <?php 
                        $currentVatTreatment = strtoupper(trim($header['vat_treatment'] ?? 'VAT_INCLUSIVE'));
                        $appliedRatePct = !empty($header['applied_tax_rate']) ? (floatval($header['applied_tax_rate']) * 100) : 18;
                        ?>
                        <div style="display: inline-flex; align-items: center; gap: 6px; margin-left: 8px; border-left: 1px solid var(--border-color); padding-left: 8px;">
                            <?php if ($currentVatTreatment === 'VAT_INCLUSIVE'): ?>
                                <span class="dense-badge" style="background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; font-size: 10px; font-weight: 700;" title="Invoice price includes VAT">
                                    ⚡ VAT-Inclusive (<?= $appliedRatePct; ?>%)
                                </span>
                                <button type="button" class="cmd-btn" style="height: 22px; padding: 0 8px; font-size: 10.5px; color: #1e40af; border-color: #93c5fd; background: #eff6ff;" onclick="quickSwitchVat('PLUS_VAT')" title="Switch to calculate VAT on top of base (+<?= $appliedRatePct; ?>%)">
                                    Switch to VAT+
                                </button>
                            <?php elseif ($currentVatTreatment === 'PLUS_VAT'): ?>
                                <span class="dense-badge" style="background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; font-size: 10px; font-weight: 700;" title="VAT is charged on top of pre-tax subtotal">
                                    ⚡ VAT+ Pre-Tax (<?= $appliedRatePct; ?>%)
                                </span>
                                <button type="button" class="cmd-btn" style="height: 22px; padding: 0 8px; font-size: 10.5px; color: #065f46; border-color: #a7f3d0; background: #ecfdf5;" onclick="quickSwitchVat('VAT_INCLUSIVE')" title="Switch so total amount includes VAT">
                                    Switch to VAT-Inclusive
                                </button>
                            <?php else: ?>
                                <span class="dense-badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 10px; font-weight: 700;">
                                    VAT-Exempt (0%)
                                </span>
                                <button type="button" class="cmd-btn" style="height: 22px; padding: 0 8px; font-size: 10.5px; color: #065f46; border-color: #a7f3d0; background: #ecfdf5;" onclick="quickSwitchVat('VAT_INCLUSIVE')">
                                    Set VAT-Inclusive
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cmd-right">
                        <button type="button" class="cmd-btn cmd-btn-primary" onclick="saveAllChanges()" id="btnSaveTop">
                            <i class="icon-save"></i> Save Changes
                        </button>
                        <button type="button" class="cmd-btn" onclick="openAuditModalPreview()" title="Quick Preview Audit Modal">
                            <i class="icon-eye"></i> Audit Preview
                        </button>
                        <button type="button" class="cmd-btn" onclick="location.reload()" title="Discard changes and reload">
                            <i class="icon-rotate-cw"></i> Reload
                        </button>
                    </div>
                </div>

                <!-- 2. Live Financial KPI Ribbon -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Net Base Value</span>
                        <span class="metric-pill-val" id="kpiNetBase"><?= $currency; ?><?= number_format($header['total_base_value'] ?? 0, 2); ?></span>
                        <span class="metric-pill-sub">Total subtotal (Pre-tax)</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">18% VAT Component</span>
                        <span class="metric-pill-val" id="kpiVat"><?= $currency; ?><?= number_format($header['total_vat'] ?? 0, 2); ?></span>
                        <span class="metric-pill-sub" id="kpiTaxTreatmentTag"><?= htmlspecialchars($header['vat_treatment'] ?? 'PLUS_VAT'); ?></span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Billed</span>
                        <span class="metric-pill-val" id="kpiGross"><?= $currency; ?><?= number_format($header['total_gross_amount'] ?? 0, 2); ?></span>
                        <span class="metric-pill-sub">Customer invoice total</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Cost Price</span>
                        <span class="metric-pill-val" id="kpiTotalCost" style="color: #64748b;"><?= $currency; ?>0.00</span>
                        <span class="metric-pill-sub">Direct inventory &amp; service cost</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Profit (GP)</span>
                        <span class="metric-pill-val" id="kpiGrossProfit" style="color: #10b981;"><?= $currency; ?><?= number_format($header['total_gross_profit'] ?? 0, 2); ?></span>
                        <span class="metric-pill-sub" id="kpiGpMargin">Margin: 0.0%</span>
                    </div>
                </div>

                <!-- 3. Form Container -->
                <form id="invoiceEditForm" onsubmit="return false;">

                    <!-- SECTION 1: Commercial Header & Customer Attribution -->
                    <div class="edit-card">
                        <div class="edit-card-title">
                            <span><i class="icon-file-text" style="color: var(--primary);"></i> Commercial Attribution &amp; Header Controls</span>
                            <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">
                                Invoiced on <?= htmlspecialchars($header['invoice_date'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div class="form-grid-4">
                            <!-- Customer (Read-only reference) -->
                            <div class="field-group">
                                <label class="field-label">Bill-To Customer</label>
                                <input type="text" class="field-input" value="<?= htmlspecialchars($header['customer_name'] ?? ''); ?>" readonly style="background: #f1f5f9; color: #475569; font-weight: 600;">
                            </div>

                            <!-- End Customer (Editable) -->
                            <div class="field-group">
                                <label class="field-label">End Customer (End-Client Entity)</label>
                                <input type="text" id="hdr_end_customer" name="end_customer" class="field-input" list="endCustomerList" value="<?= htmlspecialchars($header['end_customer'] ?? ''); ?>" placeholder="e.g. Elephant House, Lanka IOC, Dilmah...">
                                <datalist id="endCustomerList">
                                    <?php foreach ($knownEndCustomers as $kec): ?>
                                        <option value="<?= htmlspecialchars($kec['end_customer']); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <!-- Sales Rep (Editable) -->
                            <div class="field-group">
                                <label class="field-label">Sales Representative</label>
                                <select id="hdr_sales_rep_code" name="sales_rep_code" class="field-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($salesReps as $sr): ?>
                                        <option value="<?= htmlspecialchars($sr['rep_code']); ?>" <?= ($header['sales_rep_code'] ?? '') === $sr['rep_code'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($sr['rep_name']); ?> (<?= htmlspecialchars($sr['rep_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- PO Number (Editable) -->
                            <div class="field-group">
                                <label class="field-label">PO / Customer Ref Number</label>
                                <input type="text" id="hdr_po_number" name="po_number" class="field-input" value="<?= htmlspecialchars($header['po_number'] ?? ''); ?>" placeholder="e.g. PO-2026-091">
                            </div>

                            <!-- Settlement & Paid Date -->
                            <div class="field-group">
                                <label class="field-label">Settlement Date (Paid Date)</label>
                                <input type="date" id="hdr_paid_date" name="paid_date" class="field-input" value="<?= htmlspecialchars($header['paid_date'] ?? ''); ?>">
                            </div>

                            <!-- VAT Treatment Tag Switcher -->
                            <div class="field-group">
                                <label class="field-label">VAT Treatment Tag</label>
                                <select id="hdr_vat_treatment" name="vat_treatment" class="field-select" onchange="onVatTreatmentChange()">
                                    <option value="PLUS_VAT" <?= ($header['vat_treatment'] ?? '') === 'PLUS_VAT' ? 'selected' : ''; ?>>+VAT (18% Standard Rated Exclusive)</option>
                                    <option value="VAT_INCLUSIVE" <?= ($header['vat_treatment'] ?? '') === 'VAT_INCLUSIVE' ? 'selected' : ''; ?>>VAT Inclusive (18% Inclusive)</option>
                                    <option value="VAT_EXEMPT" <?= ($header['vat_treatment'] ?? '') === 'VAT_EXEMPT' ? 'selected' : ''; ?>>VAT Exempt (0% IRD Exempt)</option>
                                    <option value="NON_VAT" <?= ($header['vat_treatment'] ?? '') === 'NON_VAT' ? 'selected' : ''; ?>>Non-VAT Registered</option>
                                </select>
                            </div>

                            <!-- Auto-Recalculate VAT & Net Base -->
                            <div class="field-group" style="justify-content: flex-end;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; color: var(--primary); cursor: pointer;">
                                    <input type="checkbox" id="hdr_recalc_vat" name="recalc_vat" value="1" checked>
                                    Recalculate Net Base &amp; VAT on save
                                </label>
                            </div>

                            <!-- Internal Memo / Notes -->
                            <div class="field-group">
                                <label class="field-label">Commercial Memo / Delivery Remarks</label>
                                <input type="text" id="hdr_memo" name="memo" class="field-input" value="<?= htmlspecialchars($header['memo'] ?? ''); ?>" placeholder="Internal commercial notes...">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Normalized Commercial Line Items & Profitability Grid -->
                    <div class="edit-card">
                        <div class="edit-card-title">
                            <span><i class="icon-package" style="color: var(--primary);"></i> Extracted Products, Taxonomy &amp; Gross Profit (GP)</span>
                            <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">
                                <?= count($items); ?> Commercial Item(s)
                            </span>
                        </div>

                        <?php if (empty($items)): ?>
                            <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 12px;">
                                No normalized extracted products found. Showing QuickBooks raw lines below.
                            </div>
                        <?php else: ?>
                            <!-- Invoice-Level Quick Profit / Cost Setter -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; flex-wrap: wrap;">
                                    <span style="font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 5px;">
                                        <i class="icon-zap" style="color: #f59e0b;"></i> Quick-Set Invoice Profit:
                                    </span>
                                    <select id="quickProfitMode" class="table-edit-select" style="width: auto; padding: 4px 8px; font-weight: 600; background: #ffffff;">
                                        <option value="cost">Enter Total Cost Price (excl. VAT)</option>
                                        <option value="gp">Enter Total Gross Profit (excl. VAT)</option>
                                    </select>
                                    <input type="number" step="0.01" id="quickProfitInput" class="table-edit-input" placeholder="0.00" style="width: 140px; padding: 4px 8px; font-weight: 700; background: #ffffff; text-align: right;">
                                    <button type="button" class="cmd-btn" onclick="applyQuickInvoiceProfit()" style="background: #0f172a; color: #ffffff; border: none; font-weight: 600; padding: 4px 12px; font-size: 11.5px; border-radius: 5px; cursor: pointer;">
                                        Apply Pro-Rata to Items
                                    </button>
                                </div>
                                <div style="font-size: 11px; color: #64748b;">
                                    Values strictly <strong>excluding 18% VAT</strong> &bull; Pro-rata allocated across items by Base Net share
                                </div>
                            </div>

                            <div style="overflow-x: auto;">
                                <table class="rational-table" id="itemsEditTable" style="margin-bottom: 4px;">
                                    <thead>
                                        <tr>
                                            <th style="width: 30px;">#</th>
                                            <th style="min-width: 220px;">Clean Catalog Product Name</th>
                                            <th style="width: 140px;">Brand</th>
                                            <th style="width: 180px;">Category</th>
                                            <th style="width: 110px;">Type</th>
                                            <th class="text-right" style="width: 45px;">Qty</th>
                                            <th class="text-right" style="width: 95px;">Unit Price</th>
                                            <th class="text-right" style="width: 100px;">Base Net</th>
                                            <th class="text-right" style="width: 105px; background: #faf5ff;">Unit Cost</th>
                                            <th class="text-right" style="width: 110px; background: #faf5ff;">Gross Profit (GP)</th>
                                            <th class="text-right" style="width: 75px; background: #faf5ff;">Margin %</th>
                                            <th style="width: 130px;">Line End Client</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $lineIdx = 0;
                                        foreach ($items as $item): 
                                            $lineIdx++;
                                            $qty = max(1, (float)($item['quantity'] ?? 1));
                                            $baseVal = (float)($item['base_value'] ?? 0);
                                            $unitCost = isset($item['unit_cost']) && $item['unit_cost'] !== null ? (float)$item['unit_cost'] : 0.0;
                                            $gp = isset($item['gross_profit']) && $item['gross_profit'] !== null ? (float)$item['gross_profit'] : 0.0;
                                            
                                            // Calculate margin %
                                            $marginPct = ($baseVal > 0 && $gp > 0) ? round(($gp / $baseVal) * 100, 1) : 0.0;
                                        ?>
                                        <tr data-item-id="<?= (int)$item['id']; ?>" class="item-row">
                                            <td class="text-muted" style="font-size: 10px; font-weight: 700;"><?= $lineIdx; ?></td>
                                            <td>
                                                <input type="text" class="table-edit-input item-clean-name" value="<?= htmlspecialchars($item['clean_product_name'] ?? ''); ?>" style="font-weight: 600;">
                                            </td>
                                            <td>
                                                <select class="table-edit-select item-brand">
                                                    <option value="Other">Other</option>
                                                    <?php foreach ($masterBrands as $mb): ?>
                                                        <option value="<?= htmlspecialchars($mb['name']); ?>" <?= ($item['brand'] ?? '') === $mb['name'] ? 'selected' : ''; ?>>
                                                            <?= htmlspecialchars($mb['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="table-edit-select item-category">
                                                    <option value="Other / Unassigned">Other / Unassigned</option>
                                                    <?php foreach ($masterCategories as $mc): ?>
                                                        <option value="<?= htmlspecialchars($mc['name']); ?>" <?= ($item['category'] ?? '') === $mc['name'] ? 'selected' : ''; ?>>
                                                            <?= htmlspecialchars($mc['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="table-edit-select item-type">
                                                    <option value="HARDWARE" <?= ($item['product_type'] ?? '') === 'HARDWARE' ? 'selected' : ''; ?>>HARDWARE</option>
                                                    <option value="SERVICE" <?= ($item['product_type'] ?? '') === 'SERVICE' ? 'selected' : ''; ?>>SERVICE</option>
                                                    <option value="MAINTENANCE" <?= ($item['product_type'] ?? '') === 'MAINTENANCE' ? 'selected' : ''; ?>>MAINTENANCE</option>
                                                    <option value="RENTAL" <?= ($item['product_type'] ?? '') === 'RENTAL' ? 'selected' : ''; ?>>RENTAL</option>
                                                    <option value="LICENSE" <?= ($item['product_type'] ?? '') === 'LICENSE' ? 'selected' : ''; ?>>LICENSE</option>
                                                    <option value="COMMERCIAL_ADJUSTMENT" <?= ($item['product_type'] ?? '') === 'COMMERCIAL_ADJUSTMENT' ? 'selected' : ''; ?>>ADJUSTMENT</option>
                                                </select>
                                            </td>
                                            <td class="text-right item-qty" data-qty="<?= $qty; ?>" style="font-weight: 700; font-size: 11px;">
                                                <?= $qty; ?>
                                            </td>
                                            <td class="text-right text-muted dense-num" style="font-size: 11px;">
                                                <?= number_format($item['unit_price'] ?? 0, 2); ?>
                                            </td>
                                            <td class="text-right dense-num item-base-value" data-base="<?= $baseVal; ?>" style="font-weight: 700; color: var(--text-main);">
                                                <?= number_format($baseVal, 2); ?>
                                            </td>
                                            <!-- Editable Unit Cost -->
                                            <td style="background: #faf5ff;">
                                                <input type="number" step="0.01" class="table-edit-input table-edit-input-num item-unit-cost" value="<?= $unitCost > 0 ? $unitCost : ''; ?>" placeholder="0.00" oninput="onCostChange(this)" style="color: #581c87; font-weight: 700;">
                                            </td>
                                            <!-- Editable Total GP -->
                                            <td style="background: #faf5ff;">
                                                <input type="number" step="0.01" class="table-edit-input table-edit-input-num item-gp" value="<?= $gp > 0 ? $gp : ''; ?>" placeholder="0.00" oninput="onGpChange(this)" style="color: #047857; font-weight: 700;">
                                            </td>
                                            <!-- Editable Margin % -->
                                            <td style="background: #faf5ff;" class="text-right">
                                                <input type="number" step="0.1" class="table-edit-input table-edit-input-num item-margin" value="<?= $marginPct > 0 ? $marginPct : ''; ?>" placeholder="0.0%" oninput="onMarginChange(this)" style="width: 55px; font-weight: 700;">
                                            </td>
                                            <td>
                                                <input type="text" class="table-edit-input item-end-customer" list="endCustomerList" value="<?= htmlspecialchars($item['end_customer'] ?? ''); ?>" placeholder="Inherit header">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- SECTION 3: Hardware Assets & Warranty Tracking -->
                    <div class="edit-card">
                        <div class="edit-card-title">
                            <span><i class="icon-shield-check" style="color: var(--primary);"></i> Hardware Units, Serial Numbers &amp; Warranty Lifecycles</span>
                            <button type="button" class="cmd-btn" onclick="addNewAssetRow()" style="height: 24px; padding: 0 8px; font-size: 11px;">
                                <i class="icon-plus"></i> Add Serial Unit
                            </button>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="rational-table" id="assetsEditTable">
                                <thead>
                                    <tr>
                                        <th style="min-width: 160px;">Serial Number</th>
                                        <th style="min-width: 140px;">Model / SKU</th>
                                        <th style="width: 120px;">Brand</th>
                                        <th style="width: 100px;">Duration</th>
                                        <th style="width: 120px;">Start Date</th>
                                        <th style="width: 120px;">Expiry Date</th>
                                        <th style="width: 110px;">Status</th>
                                        <th style="min-width: 140px;">Notes / RMA</th>
                                        <th style="width: 40px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="assetsTableBody">
                                    <?php if (empty($assets)): ?>
                                        <tr id="noAssetsRow">
                                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 15px;">
                                                No hardware serials registered for this invoice. Click "Add Serial Unit" to track hardware warranties.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($assets as $ast): ?>
                                            <tr data-asset-id="<?= (int)$ast['id']; ?>" class="asset-row">
                                                <td>
                                                    <input type="text" class="table-edit-input asset-serial" value="<?= htmlspecialchars($ast['serial_number'] ?? ''); ?>" style="font-weight: 700; color: var(--primary); font-family: monospace;">
                                                </td>
                                                <td>
                                                    <input type="text" class="table-edit-input asset-model" value="<?= htmlspecialchars($ast['model_sku'] ?? $ast['product_name'] ?? ''); ?>">
                                                </td>
                                                <td>
                                                    <select class="table-edit-select asset-brand">
                                                        <?php foreach ($masterBrands as $mb): ?>
                                                            <option value="<?= htmlspecialchars($mb['name']); ?>" <?= ($ast['brand'] ?? '') === $mb['name'] ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($mb['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="table-edit-select asset-months" onchange="onAssetDurationChange(this)">
                                                        <option value="12" <?= (int)($ast['warranty_months'] ?? 36) === 12 ? 'selected' : ''; ?>>12 Months (1Y)</option>
                                                        <option value="24" <?= (int)($ast['warranty_months'] ?? 36) === 24 ? 'selected' : ''; ?>>24 Months (2Y)</option>
                                                        <option value="36" <?= (int)($ast['warranty_months'] ?? 36) === 36 ? 'selected' : ''; ?>>36 Months (3Y)</option>
                                                        <option value="60" <?= (int)($ast['warranty_months'] ?? 36) === 60 ? 'selected' : ''; ?>>60 Months (5Y)</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="date" class="table-edit-input asset-start" value="<?= htmlspecialchars($ast['warranty_start_date'] ?? ''); ?>" onchange="onAssetStartChange(this)">
                                                </td>
                                                <td>
                                                    <input type="date" class="table-edit-input asset-expiry" value="<?= htmlspecialchars($ast['warranty_expiry_date'] ?? ''); ?>">
                                                </td>
                                                <td>
                                                    <select class="table-edit-select asset-status">
                                                        <option value="Active" <?= ($ast['warranty_status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="Expired" <?= ($ast['warranty_status'] ?? '') === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                                                        <option value="Void" <?= ($ast['warranty_status'] ?? '') === 'Void' ? 'selected' : ''; ?>>Void</option>
                                                        <option value="RMA Replaced" <?= ($ast['warranty_status'] ?? '') === 'RMA Replaced' ? 'selected' : ''; ?>>RMA Replaced</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="table-edit-input asset-notes" value="<?= htmlspecialchars($ast['notes'] ?? ''); ?>" placeholder="Optional notes...">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="cmd-btn" style="height: 22px; padding: 0 5px; color: var(--danger);" onclick="deleteExistingAsset(<?= (int)$ast['id']; ?>, this)" title="Remove Serial Unit">
                                                        <i class="icon-trash-2"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SECTION 4: Software Subscriptions & Recurring Contracts -->
                    <?php if (!empty($subscriptions)): ?>
                    <div class="edit-card">
                        <div class="edit-card-title">
                            <span><i class="icon-refresh-cw" style="color: var(--primary);"></i> Software Subscriptions, Cloud Licenses &amp; SLA Contracts</span>
                            <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);"><?= count($subscriptions); ?> Contract Record(s)</span>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="rational-table">
                                <thead>
                                    <tr>
                                        <th style="min-width: 180px;">Service / Software Title</th>
                                        <th style="width: 100px;">Tier / Seats</th>
                                        <th style="width: 120px;">Period Start</th>
                                        <th style="width: 120px;">Period End</th>
                                        <th style="width: 90px;">Term (Mo)</th>
                                        <th style="width: 110px;">Status</th>
                                        <th class="text-right" style="width: 130px;">Renewal Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $sub): ?>
                                    <tr data-sub-id="<?= (int)$sub['id']; ?>" class="sub-row">
                                        <td>
                                            <input type="text" class="table-edit-input sub-name" value="<?= htmlspecialchars($sub['software_name'] ?? ''); ?>" style="font-weight: 600;">
                                        </td>
                                        <td>
                                            <input type="number" class="table-edit-input sub-seats" value="<?= (int)($sub['license_seats'] ?? 1); ?>">
                                        </td>
                                        <td>
                                            <input type="date" class="table-edit-input sub-start" value="<?= htmlspecialchars($sub['period_start_date'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="date" class="table-edit-input sub-end" value="<?= htmlspecialchars($sub['period_end_date'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="number" class="table-edit-input sub-term" value="<?= (int)($sub['term_months'] ?? 12); ?>">
                                        </td>
                                        <td>
                                            <select class="table-edit-select sub-status">
                                                <option value="Active" <?= ($sub['renewal_status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="Expiring Soon" <?= ($sub['renewal_status'] ?? '') === 'Expiring Soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                                                <option value="Renewed" <?= ($sub['renewal_status'] ?? '') === 'Renewed' ? 'selected' : ''; ?>>Renewed</option>
                                                <option value="Lapsed" <?= ($sub['renewal_status'] ?? '') === 'Lapsed' ? 'selected' : ''; ?>>Lapsed</option>
                                            </select>
                                        </td>
                                        <td class="text-right">
                                            <input type="number" step="0.01" class="table-edit-input table-edit-input-num sub-opp-val" value="<?= (float)($sub['renewal_opportunity_value'] ?? 0); ?>" style="font-weight: 700; color: var(--primary);">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Bottom Floating Save Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; margin-bottom: 30px;">
                        <a href="reports.php?type=invoices" class="cmd-btn">&larr; Return to Invoices</a>
                        <button type="button" class="cmd-btn cmd-btn-primary" onclick="saveAllChanges()" style="height: 32px; padding: 0 16px; font-size: 13px;">
                            <i class="icon-save"></i> Save All Invoice Changes
                        </button>
                    </div>

                </form>

                <?php endif; ?>

            </div><!-- .content-body -->
        </main>
    </div><!-- .app-container -->

    <!-- Toast Notification -->
    <div id="toastNotification" class="toast-notify">
        <i class="icon-check-circle" style="color: #10b981;"></i>
        <span id="toastMsg">Changes saved</span>
    </div>

    <!-- Hidden Master Brands & Categories Options for dynamic row creation -->
    <select id="brandTemplateOptions" style="display: none;">
        <?php foreach ($masterBrands as $mb): ?>
            <option value="<?= htmlspecialchars($mb['name']); ?>"><?= htmlspecialchars($mb['name']); ?></option>
        <?php endforeach; ?>
    </select>

    <?php require_once 'includes/layout_js.php'; ?>

    <script>
        const INVOICE_NUMBER = <?= json_encode($inv); ?>;
        const CURRENCY = <?= json_encode($currency); ?>;
        const deletedAssetIds = [];

        // ── Quick Set Invoice-Level Profit (Excl. VAT) ──
        function applyQuickInvoiceProfit() {
            const mode = document.getElementById('quickProfitMode')?.value || 'cost';
            const valInput = document.getElementById('quickProfitInput');
            const val = parseFloat(valInput?.value);

            if (isNaN(val) || val < 0) {
                alert('Please enter a valid amount.');
                return;
            }

            const rows = document.querySelectorAll('#itemsEditTable tbody tr.item-row');
            if (rows.length === 0) {
                alert('No commercial items available to distribute profit.');
                return;
            }

            let totalBase = 0;
            rows.forEach(r => {
                totalBase += parseFloat(r.querySelector('.item-base-value').getAttribute('data-base')) || 0;
            });

            let totalCost = 0;
            let totalGp = 0;
            if (mode === 'cost') {
                totalCost = val;
                totalGp = totalBase - totalCost;
            } else {
                totalGp = val;
                totalCost = totalBase - totalGp;
            }

            let runningGp = 0;
            let runningCost = 0;

            rows.forEach((r, idx) => {
                const qty = parseFloat(r.querySelector('.item-qty').getAttribute('data-qty')) || 1;
                const itemBase = parseFloat(r.querySelector('.item-base-value').getAttribute('data-base')) || 0;

                let itemGp, itemCost;
                if (idx === rows.length - 1) {
                    itemGp = totalGp - runningGp;
                    itemCost = totalCost - runningCost;
                } else {
                    const ratio = totalBase > 0 ? (itemBase / totalBase) : (1 / rows.length);
                    itemGp = totalGp * ratio;
                    itemCost = totalCost * ratio;
                    runningGp += itemGp;
                    runningCost += itemCost;
                }

                const unitCost = qty > 0 ? (itemCost / qty) : 0;
                const margin = itemBase > 0 ? ((itemGp / itemBase) * 100) : 0;

                r.querySelector('.item-unit-cost').value = unitCost > 0 ? unitCost.toFixed(2) : '0.00';
                r.querySelector('.item-gp').value = itemGp.toFixed(2);
                r.querySelector('.item-margin').value = margin.toFixed(1);
            });

            recalculateSummaryRibbon();
            showToast('✓ Pro-rata distributed ' + (mode === 'cost' ? 'cost' : 'gross profit') + ' across ' + rows.length + ' item(s)');
        }

        // ── Profit & Cost 3-Way Calculators ──
        function onCostChange(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').getAttribute('data-qty')) || 1;
            const baseVal = parseFloat(row.querySelector('.item-base-value').getAttribute('data-base')) || 0;
            const unitCost = parseFloat(input.value) || 0;

            const totalCost = unitCost * qty;
            const gp = baseVal - totalCost;
            const margin = baseVal > 0 ? ((gp / baseVal) * 100) : 0;

            row.querySelector('.item-gp').value = gp !== 0 ? gp.toFixed(2) : '';
            row.querySelector('.item-margin').value = margin !== 0 ? margin.toFixed(1) : '';
            recalculateSummaryRibbon();
        }

        function onGpChange(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').getAttribute('data-qty')) || 1;
            const baseVal = parseFloat(row.querySelector('.item-base-value').getAttribute('data-base')) || 0;
            const gp = parseFloat(input.value) || 0;

            const totalCost = baseVal - gp;
            const unitCost = qty > 0 ? (totalCost / qty) : 0;
            const margin = baseVal > 0 ? ((gp / baseVal) * 100) : 0;

            row.querySelector('.item-unit-cost').value = unitCost > 0 ? unitCost.toFixed(2) : '';
            row.querySelector('.item-margin').value = margin !== 0 ? margin.toFixed(1) : '';
            recalculateSummaryRibbon();
        }

        function onMarginChange(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').getAttribute('data-qty')) || 1;
            const baseVal = parseFloat(row.querySelector('.item-base-value').getAttribute('data-base')) || 0;
            const margin = parseFloat(input.value) || 0;

            const gp = baseVal * (margin / 100);
            const totalCost = baseVal - gp;
            const unitCost = qty > 0 ? (totalCost / qty) : 0;

            row.querySelector('.item-gp').value = gp !== 0 ? gp.toFixed(2) : '';
            row.querySelector('.item-unit-cost').value = unitCost > 0 ? unitCost.toFixed(2) : '';
            recalculateSummaryRibbon();
        }

        function recalculateSummaryRibbon() {
            let totalCost = 0;
            let totalGp = 0;
            let totalBase = 0;

            document.querySelectorAll('#itemsEditTable tbody tr.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').getAttribute('data-qty')) || 1;
                const baseVal = parseFloat(row.querySelector('.item-base-value').getAttribute('data-base')) || 0;
                const unitCost = parseFloat(row.querySelector('.item-unit-cost').value) || 0;
                const gp = parseFloat(row.querySelector('.item-gp').value) || 0;

                totalBase += baseVal;
                totalCost += (unitCost * qty);
                totalGp += gp;
            });

            const overallMargin = totalBase > 0 ? ((totalGp / totalBase) * 100) : 0;
            const nf = new Intl.NumberFormat();

            document.getElementById('kpiTotalCost').innerText = CURRENCY + nf.format(Math.round(totalCost));
            document.getElementById('kpiGrossProfit').innerText = CURRENCY + nf.format(Math.round(totalGp));
            document.getElementById('kpiGpMargin').innerText = `Margin: ${overallMargin.toFixed(1)}%`;
        }

        // ── VAT Treatment Switcher ──
        function onVatTreatmentChange() {
            const select = document.getElementById('hdr_vat_treatment');
            const treatment = select.value;
            const tag = document.getElementById('kpiTaxTreatmentTag');
            tag.innerText = treatment;

            const grossStr = document.getElementById('kpiGross').innerText.replace(/[^0-9.]/g, '');
            const gross = parseFloat(grossStr) || 0;

            if (treatment === 'VAT_EXEMPT' || treatment === 'NON_VAT') {
                document.getElementById('kpiVat').innerText = CURRENCY + '0.00';
                document.getElementById('kpiNetBase').innerText = CURRENCY + new Intl.NumberFormat().format(Math.round(gross));
            } else if (treatment === 'PLUS_VAT' || treatment === 'VAT_INCLUSIVE') {
                const base = Math.round(gross / 1.18);
                const vat = gross - base;
                document.getElementById('kpiNetBase').innerText = CURRENCY + new Intl.NumberFormat().format(base);
                document.getElementById('kpiVat').innerText = CURRENCY + new Intl.NumberFormat().format(vat);
            }
        }

        // ── Warranty Asset Dates Auto-Computation ──
        function onAssetDurationChange(select) {
            const row = select.closest('tr');
            const months = parseInt(select.value) || 36;
            const startInput = row.querySelector('.asset-start');
            const expiryInput = row.querySelector('.asset-expiry');

            const startDate = startInput.value ? new Date(startInput.value) : new Date();
            if (!isNaN(startDate.getTime())) {
                const expDate = new Date(startDate);
                expDate.setMonth(expDate.getMonth() + months);
                expiryInput.value = expDate.toISOString().split('T')[0];
            }
        }

        function onAssetStartChange(input) {
            const row = input.closest('tr');
            const durationSelect = row.querySelector('.asset-months');
            onAssetDurationChange(durationSelect);
        }

        // ── Add New Asset Row ──
        function addNewAssetRow() {
            const noRow = document.getElementById('noAssetsRow');
            if (noRow) noRow.remove();

            const tbody = document.getElementById('assetsTableBody');
            const brandOpts = document.getElementById('brandTemplateOptions').innerHTML;
            const today = new Date().toISOString().split('T')[0];
            const defaultExp = new Date();
            defaultExp.setFullYear(defaultExp.getFullYear() + 3);
            const expStr = defaultExp.toISOString().split('T')[0];

            const tr = document.createElement('tr');
            tr.className = 'asset-row new-asset-row';
            tr.innerHTML = `
                <td>
                    <input type="text" class="table-edit-input asset-serial" placeholder="Enter S/N..." style="font-weight: 700; color: var(--primary); font-family: monospace;">
                </td>
                <td>
                    <input type="text" class="table-edit-input asset-model" placeholder="Model SKU (e.g. DS923+)">
                </td>
                <td>
                    <select class="table-edit-select asset-brand">
                        ${brandOpts}
                    </select>
                </td>
                <td>
                    <select class="table-edit-select asset-months" onchange="onAssetDurationChange(this)">
                        <option value="12">12 Months (1Y)</option>
                        <option value="24">24 Months (2Y)</option>
                        <option value="36" selected>36 Months (3Y)</option>
                        <option value="60">60 Months (5Y)</option>
                    </select>
                </td>
                <td>
                    <input type="date" class="table-edit-input asset-start" value="${today}" onchange="onAssetStartChange(this)">
                </td>
                <td>
                    <input type="date" class="table-edit-input asset-expiry" value="${expStr}">
                </td>
                <td>
                    <select class="table-edit-select asset-status">
                        <option value="Active" selected>Active</option>
                        <option value="Expired">Expired</option>
                        <option value="Void">Void</option>
                        <option value="RMA Replaced">RMA Replaced</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="table-edit-input asset-notes" placeholder="Optional notes...">
                </td>
                <td class="text-center">
                    <button type="button" class="cmd-btn" style="height: 22px; padding: 0 5px; color: var(--danger);" onclick="this.closest('tr').remove()" title="Cancel Unit">
                        <i class="icon-x"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            tr.querySelector('.asset-serial').focus();
        }

        function deleteExistingAsset(assetId, btn) {
            if (confirm('Are you sure you want to remove this hardware serial record?')) {
                deletedAssetIds.push(assetId);
                btn.closest('tr').remove();
            }
        }

        // ── SAVE ALL CHANGES ──
        function saveAllChanges() {
            const btn = document.getElementById('btnSaveTop');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="icon-loader" style="animation: spin 0.8s linear infinite;"></i> Saving...';

            const payload = {
                invoice_number: INVOICE_NUMBER,
                header: {
                    end_customer: document.getElementById('hdr_end_customer')?.value || '',
                    sales_rep_code: document.getElementById('hdr_sales_rep_code')?.value || '',
                    po_number: document.getElementById('hdr_po_number')?.value || '',
                    paid_date: document.getElementById('hdr_paid_date')?.value || '',
                    vat_treatment: document.getElementById('hdr_vat_treatment')?.value || 'PLUS_VAT',
                    recalc_vat: document.getElementById('hdr_recalc_vat')?.checked ? 1 : 0,
                    memo: document.getElementById('hdr_memo')?.value || ''
                },
                items: [],
                assets: [],
                new_assets: [],
                delete_assets: deletedAssetIds,
                subscriptions: []
            };

            // Gather items
            document.querySelectorAll('#itemsEditTable tbody tr.item-row').forEach(row => {
                const id = parseInt(row.getAttribute('data-item-id'));
                payload.items.push({
                    id: id,
                    clean_product_name: row.querySelector('.item-clean-name')?.value || '',
                    brand: row.querySelector('.item-brand')?.value || 'Other',
                    category: row.querySelector('.item-category')?.value || 'Other / Unassigned',
                    product_type: row.querySelector('.item-type')?.value || 'HARDWARE',
                    unit_cost: row.querySelector('.item-unit-cost')?.value || null,
                    gross_profit: row.querySelector('.item-gp')?.value || null,
                    end_customer: row.querySelector('.item-end-customer')?.value || ''
                });
            });

            // Gather existing assets
            document.querySelectorAll('#assetsEditTable tbody tr.asset-row:not(.new-asset-row)').forEach(row => {
                const id = parseInt(row.getAttribute('data-asset-id'));
                payload.assets.push({
                    id: id,
                    serial_number: row.querySelector('.asset-serial')?.value || '',
                    model_sku: row.querySelector('.asset-model')?.value || '',
                    brand: row.querySelector('.asset-brand')?.value || 'Synology',
                    warranty_months: parseInt(row.querySelector('.asset-months')?.value) || 36,
                    warranty_start_date: row.querySelector('.asset-start')?.value || '',
                    warranty_expiry_date: row.querySelector('.asset-expiry')?.value || '',
                    warranty_status: row.querySelector('.asset-status')?.value || 'Active',
                    notes: row.querySelector('.asset-notes')?.value || '',
                    end_customer: document.getElementById('hdr_end_customer')?.value || ''
                });
            });

            // Gather new assets
            document.querySelectorAll('#assetsEditTable tbody tr.new-asset-row').forEach(row => {
                payload.new_assets.push({
                    serial_number: row.querySelector('.asset-serial')?.value || '',
                    model_sku: row.querySelector('.asset-model')?.value || '',
                    brand: row.querySelector('.asset-brand')?.value || 'Synology',
                    warranty_months: parseInt(row.querySelector('.asset-months')?.value) || 36,
                    warranty_start_date: row.querySelector('.asset-start')?.value || '',
                    warranty_expiry_date: row.querySelector('.asset-expiry')?.value || '',
                    warranty_status: row.querySelector('.asset-status')?.value || 'Active',
                    notes: row.querySelector('.asset-notes')?.value || '',
                    end_customer: document.getElementById('hdr_end_customer')?.value || ''
                });
            });

            // Gather subscriptions
            document.querySelectorAll('tr.sub-row').forEach(row => {
                const id = parseInt(row.getAttribute('data-sub-id'));
                payload.subscriptions.push({
                    id: id,
                    software_name: row.querySelector('.sub-name')?.value || '',
                    license_seats: parseInt(row.querySelector('.sub-seats')?.value) || 1,
                    period_start_date: row.querySelector('.sub-start')?.value || '',
                    period_end_date: row.querySelector('.sub-end')?.value || '',
                    term_months: parseInt(row.querySelector('.sub-term')?.value) || 12,
                    renewal_status: row.querySelector('.sub-status')?.value || 'Active',
                    renewal_opportunity_value: parseFloat(row.querySelector('.sub-opp-val')?.value) || 0,
                    end_customer: document.getElementById('hdr_end_customer')?.value || ''
                });
            });

            const formData = new FormData();
            formData.append('action', 'save_invoice_full');
            formData.append('payload', JSON.stringify(payload));

            fetch('invoice_edit.php?inv=' + encodeURIComponent(INVOICE_NUMBER), {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = origHtml;

                if (res.success) {
                    showToast('✓ ' + (res.message || 'Invoice changes saved successfully!'));
                } else {
                    alert('Error saving changes: ' + (res.error || 'Unknown error'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                alert('Network or server error: ' + err.message);
            });
        }

        function showToast(msg) {
            const toast = document.getElementById('toastNotification');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.add('active');
            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }

        function quickSwitchVat(targetMode) {
            const modeName = targetMode.replace('_', ' ');
            if (!confirm('Switch invoice #' + INVOICE_NUMBER + ' to ' + modeName + '?\n\nLine items and invoice totals will be recalculated immediately.')) {
                return;
            }
            const fd = new FormData();
            fd.append('action', 'switch_invoice_vat_mode');
            fd.append('invoice_number', INVOICE_NUMBER);
            fd.append('target_mode', targetMode);
            
            fetch('invoice_edit.php?inv=' + encodeURIComponent(INVOICE_NUMBER), {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res && res.success) {
                    showToast('✓ Invoice switched to ' + modeName + ' successfully!');
                    setTimeout(() => location.reload(), 600);
                } else {
                    alert('Error: ' + (res.error || res.message || 'Failed to switch VAT mode'));
                }
            })
            .catch(err => {
                alert('Network error: ' + err.message);
            });
        }

        // Ctrl+S / Cmd+S shortcut to save
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveAllChanges();
            }
        });

        // Initialize cost calculations on page load
        document.addEventListener('DOMContentLoaded', () => {
            recalculateSummaryRibbon();
        });
    </script>
</body>
</html>
