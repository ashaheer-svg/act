<?php
/**
 * Sales BI Platform - Data Explorer
 * Allows browsing, searching, and raw inspection of extracted QuickBooks datasets
 * (Sales lines, Payments, Customer profiles, and Sync logs) with Report Ideas discovery.
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$user = $auth->getCurrentUser();

$currency = $db->getSetting('currency_symbol', 'LKR ');

// Dataset Tab Selection: 'sales', 'payments', 'customers', 'audit'
$tab = $_GET['tab'] ?? 'sales';
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$viability = $_GET['viability'] ?? 'all';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = min(250, max(10, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// Export to CSV Function
if (!function_exists('handleCsvExport')) {
    function handleCsvExport($db, $tab, $search, $category, $viability, $dateFrom, $dateTo) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sales_bi_export_' . $tab . '_' . date('Ymd_His') . '.csv');
        
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        if ($tab === 'sales') {
            fputcsv($out, ['ID', 'Type', 'Date', 'Invoice #', 'Customer', 'Description', 'Category', 'Qty', 'Amount', 'Base Value', 'VAT', 'Treatment', 'Rep', 'PO #', 'Paid Date', 'Days to Pay', 'Memo'], ',', '"', "\\");
            $sql = "SELECT id, invoice_type, invoice_date, invoice_number, customer_name, item_description, product_category, quantity, total_amount, base_value, vat_component, vat_treatment, sales_rep_code, po_number, paid_date, days_to_pay, memo FROM sales ORDER BY invoice_date DESC LIMIT 10000";
            $data = $db->fetchAll($sql);
            foreach ($data as $r) fputcsv($out, $r, ',', '"', "\\");
        } elseif ($tab === 'payments') {
            fputcsv($out, ['ID', 'Customer', 'Payment Date', 'Reference #', 'Amount', 'Matched Invoice #', 'Created At'], ',', '"', "\\");
            $sql = "SELECT id, customer_name, payment_date, reference_num, amount, invoice_num, created_at FROM payments ORDER BY payment_date DESC LIMIT 10000";
            $data = $db->fetchAll($sql);
            foreach ($data as $r) fputcsv($out, $r, ',', '"', "\\");
        } elseif ($tab === 'customers') {
            fputcsv($out, ['Customer Name', 'Company', 'Contact', 'Email', 'Phone', 'City', 'Sales Rep', 'Type', 'Terms', 'Credit Limit', 'Current Balance'], ',', '"', "\\");
            $sql = "SELECT customer_name, company_name, contact_name, email, phone, bill_city, sales_rep, customer_type, terms, credit_limit, current_balance FROM customer_profiles ORDER BY customer_name ASC LIMIT 5000";
            $data = $db->fetchAll($sql);
            foreach ($data as $r) fputcsv($out, $r, ',', '"', "\\");
        } elseif ($tab === 'assets') {
            fputcsv($out, ['ID', 'Serial #', 'Product', 'Brand', 'Model SKU', 'Customer', 'Invoice #', 'Warranty Months', 'Start Date', 'Expiry Date', 'Status', 'Notes'], ',', '"', "\\");
            $sql = "SELECT id, serial_number, product_name, brand, model_sku, customer_name, invoice_number, warranty_months, warranty_start_date, warranty_expiry_date, warranty_status, notes FROM hardware_assets ORDER BY warranty_expiry_date ASC LIMIT 5000";
            $data = $db->fetchAll($sql);
            foreach ($data as $r) fputcsv($out, $r, ',', '"', "\\");
        } elseif ($tab === 'subscriptions') {
            fputcsv($out, ['ID', 'Software', 'Tier', 'Seats', 'Customer', 'Invoice #', 'Start Date', 'End Date', 'Term Months', 'Status', 'Opportunity Value'], ',', '"', "\\");
            $sql = "SELECT id, software_name, edition_tier, license_seats, customer_name, invoice_number, period_start_date, period_end_date, term_months, renewal_status, renewal_opportunity_value FROM software_subscriptions ORDER BY period_end_date ASC LIMIT 5000";
            $data = $db->fetchAll($sql);
            foreach ($data as $r) fputcsv($out, $r, ',', '"', "\\");
        }
        fclose($out);
    }
}

// Export to CSV Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    handleCsvExport($db, $tab, $search, $category, $viability, $dateFrom, $dateTo);
    exit;
}

// Database high-level summary counts
$totalSales = (int)$db->fetch("SELECT count(*) as c FROM sales")['c'];
$totalPayments = (int)$db->fetch("SELECT count(*) as c FROM payments")['c'];
$totalCustomers = (int)$db->fetch("SELECT count(*) as c FROM customer_profiles")['c'];
$totalAssets = (int)($db->fetch("SELECT count(*) as c FROM hardware_assets")['c'] ?? 0);
$totalSubscriptions = (int)($db->fetch("SELECT count(*) as c FROM software_subscriptions")['c'] ?? 0);
$lastSync = $db->getSetting('last_qb_sync', 'Never');
$lastSyncSummary = $db->getSetting('last_qb_sync_summary', 'No sync recorded');

// Categories for filter
$categories = $db->fetchAll("
    SELECT DISTINCT 
        CASE WHEN INSTR(product_category, ':') > 0 THEN UPPER(TRIM(SUBSTR(product_category, 1, INSTR(product_category, ':') - 1)))
        ELSE UPPER(TRIM(product_category)) END as cat 
    FROM sales 
    WHERE product_category IS NOT NULL AND product_category != '' 
    ORDER BY cat ASC
");
$categories = array_filter(array_column($categories, 'cat'));

// Data queries per tab
$rows = [];
$totalRows = 0;

if ($tab === 'sales') {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (invoice_number LIKE ? OR customer_name LIKE ? OR item_description LIKE ? OR po_number LIKE ? OR memo LIKE ?)";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
    }

    if (!empty($category)) {
        $where .= " AND (product_category LIKE ? OR item_description LIKE ?)";
        $params[] = "%$category%";
        $params[] = "%$category%";
    }

    if (!empty($dateFrom)) {
        $where .= " AND invoice_date >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $where .= " AND invoice_date <= ?";
        $params[] = $dateTo;
    }

    if ($viability === 'commercial') {
        $where .= " AND total_amount > 0";
    } elseif ($viability === 'serialized') {
        $where .= " AND total_amount = 0 AND (item_description LIKE '%S/N%' OR item_description LIKE '%Serial%')";
    } elseif ($viability === 'placeholders') {
        $where .= " AND total_amount = 0 AND (TRIM(item_description) = 'Item' OR TRIM(item_description) = 'Opening balance' OR (item_description NOT LIKE '%S/N%' AND item_description NOT LIKE '%Serial%'))";
    }

    $countSql = "SELECT COUNT(*) as c FROM sales $where";
    $totalRows = (int)$db->fetch($countSql, $params)['c'];

    $dataSql = "
        SELECT 
            id, invoice_type, invoice_date, invoice_number, customer_name,
            item_description, tax_code, quantity, qb_amount, base_value,
            vat_component, applied_tax_rate, vat_treatment, total_amount, gross_profit, product_category,
            sales_rep_code, paid_date, days_to_pay, po_number, memo, qb_txn_id,
            CASE WHEN item_description LIKE '%S/N%' OR item_description LIKE '%SN:%' OR item_description LIKE '%Serial%' THEN 1 ELSE 0 END as is_serialized
        FROM sales
        $where
        ORDER BY invoice_date DESC, id DESC
        LIMIT ? OFFSET ?
    ";
    $queryParams = array_merge($params, [$limit, $offset]);
    $rows = $db->fetchAll($dataSql, $queryParams);

} elseif ($tab === 'payments') {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (customer_name LIKE ? OR reference_num LIKE ? OR invoice_num LIKE ?)";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm]);
    }

    if (!empty($dateFrom)) {
        $where .= " AND payment_date >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $where .= " AND payment_date <= ?";
        $params[] = $dateTo;
    }

    $countSql = "SELECT COUNT(*) as c FROM payments $where";
    $totalRows = (int)$db->fetch($countSql, $params)['c'];

    $dataSql = "
        SELECT 
            id, customer_name, payment_date, reference_num, amount, invoice_num, created_at
        FROM payments
        $where
        ORDER BY payment_date DESC, id DESC
        LIMIT ? OFFSET ?
    ";
    $queryParams = array_merge($params, [$limit, $offset]);
    $rows = $db->fetchAll($dataSql, $queryParams);

} elseif ($tab === 'customers') {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (customer_name LIKE ? OR company_name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ? OR bill_city LIKE ? OR sales_rep LIKE ?)";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
    }

    $countSql = "SELECT COUNT(*) as c FROM customer_profiles $where";
    $totalRows = (int)$db->fetch($countSql, $params)['c'];

    $dataSql = "
        SELECT 
            customer_name, company_name, contact_name, email, phone, alt_phone,
            bill_address, bill_city, bill_state, bill_zip, bill_country,
            sales_rep, customer_type, terms, credit_limit, current_balance, total_balance,
            account_number, is_active, updated_at
        FROM customer_profiles
        $where
        ORDER BY current_balance DESC, customer_name ASC
        LIMIT ? OFFSET ?
    ";
    $queryParams = array_merge($params, [$limit, $offset]);
    $rows = $db->fetchAll($dataSql, $queryParams);

} elseif ($tab === 'assets') {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (serial_number LIKE ? OR product_name LIKE ? OR customer_name LIKE ? OR invoice_number LIKE ? OR model_sku LIKE ?)";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
    }

    $countSql = "SELECT COUNT(*) as c FROM hardware_assets $where";
    $totalRows = (int)$db->fetch($countSql, $params)['c'];

    $dataSql = "
        SELECT 
            id, invoice_number, customer_name, product_name, brand, model_sku,
            serial_number, warranty_type, warranty_months, warranty_start_date,
            warranty_expiry_date, warranty_status, parent_serial_number, notes, created_at,
            ROUND(julianday(warranty_expiry_date) - julianday('now')) as days_remaining
        FROM hardware_assets
        $where
        ORDER BY warranty_expiry_date ASC, id DESC
        LIMIT ? OFFSET ?
    ";
    $queryParams = array_merge($params, [$limit, $offset]);
    $rows = $db->fetchAll($dataSql, $queryParams);

} elseif ($tab === 'subscriptions') {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (software_name LIKE ? OR customer_name LIKE ? OR invoice_number LIKE ?)";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm]);
    }

    $countSql = "SELECT COUNT(*) as c FROM software_subscriptions $where";
    $totalRows = (int)$db->fetch($countSql, $params)['c'];

    $dataSql = "
        SELECT 
            id, invoice_number, customer_name, software_name, edition_tier,
            license_seats, period_start_date, period_end_date, term_months,
            renewal_status, renewal_opportunity_value, created_at,
            ROUND(julianday(period_end_date) - julianday('now')) as days_remaining
        FROM software_subscriptions
        $where
        ORDER BY period_end_date ASC, id DESC
        LIMIT ? OFFSET ?
    ";
    $queryParams = array_merge($params, [$limit, $offset]);
    $rows = $db->fetchAll($dataSql, $queryParams);

} elseif ($tab === 'audit') {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (action LIKE ? OR description LIKE ? OR ip_address LIKE ?)";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm]);
    }

    $countSql = "SELECT COUNT(*) as c FROM activity_log $where";
    $totalRows = (int)$db->fetch($countSql, $params)['c'];

    $dataSql = "
        SELECT id, user_id, action, description, ip_address, activity_date
        FROM activity_log
        $where
        ORDER BY activity_date DESC
        LIMIT ? OFFSET ?
    ";
    $queryParams = array_merge($params, [$limit, $offset]);
    $rows = $db->fetchAll($dataSql, $queryParams);
}

$totalPages = max(1, (int)ceil($totalRows / $limit));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Explorer - Sales BI Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.3">
    <style>
        .dataset-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
            overflow-x: auto;
        }
        .dataset-tab {
            padding: 12px 20px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .dataset-tab:hover {
            color: var(--primary);
        }
        .dataset-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: #ffffff;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .tab-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            background: #e2e8f0;
            color: #475569;
        }
        .dataset-tab.active .tab-badge {
            background: #e0e7ff;
            color: #3730a3;
        }
        .explorer-toolbar {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }
        .filter-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .filter-input {
            padding: 8px 14px;
            font-size: 13px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #f8fafc;
            color: var(--text-main);
            outline: none;
        }
        .filter-input:focus {
            border-color: var(--primary);
            background: #ffffff;
        }
        .btn-inspect {
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            background: #eef2ff;
            color: var(--primary);
            border: 1px solid #c7d2fe;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s;
        }
        .btn-inspect:hover {
            background: var(--primary);
            color: #ffffff;
        }
        .ideas-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-left: 5px solid #0284c7;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 24px;
        }
        .ideas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 14px;
        }
        .idea-card {
            background: #ffffff;
            border: 1px solid #e0f2fe;
            border-radius: var(--radius-md);
            padding: 14px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .idea-title {
            font-size: 13px;
            font-weight: 800;
            color: #0369a1;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .idea-fields {
            font-family: monospace;
            font-size: 11px;
            background: #f1f5f9;
            color: #334155;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .idea-desc {
            font-size: 12px;
            color: #475569;
            line-height: 1.5;
        }
        /* JSON Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
        }
        .modal-container {
            background: #ffffff;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 750px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: zoomIn 0.2s ease-out;
        }
        @keyframes zoomIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }
        .modal-body {
            padding: 20px 24px;
            overflow-y: auto;
        }
        .json-viewer {
            background: #0b1121;
            color: #38bdf8;
            padding: 16px;
            border-radius: var(--radius-md);
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 50vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Quick search extracted records...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <!-- Page Title -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h1 style="font-family: 'Inter Tight', sans-serif; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">QuickBooks Data Explorer</h1>
                        <p style="color: var(--text-muted); font-size: 13px; margin-top: 2px;">
                            Browse raw records ingested from the desktop sync app, inspect field schemas, and discover report opportunities.
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="explorer.php?tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&viability=<?php echo urlencode($viability); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&export=csv" class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 6px; border: 1px solid var(--border-color); border-radius: 8px; background: #ffffff; color: var(--text-main);">
                            <i class="icon-download"></i> Export Filtered to CSV
                        </a>
                        <button onclick="toggleIdeasBox()" class="btn btn-primary" style="padding: 8px 16px; font-size: 12px; font-weight: 700; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px; background: #0284c7; color: #ffffff; border: none;">
                            <i class="icon-sparkles"></i> <span id="ideasToggleText">Report Ideas & Potential</span>
                        </button>
                    </div>
                </div>

                <!-- Report Ideas & Analytical Potential Accordion Box -->
                <div class="ideas-box" id="ideasBox" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="icon-lightbulb" style="font-size: 18px; color: #0284c7;"></i>
                                <h3 style="font-size: 16px; font-weight: 800; color: #0369a1;">Analytical Potential & Report Ideas from Raw Extracted Fields</h3>
                            </div>
                            <p style="font-size: 12.5px; color: #334155; margin-top: 4px; line-height: 1.5;">
                                By inspecting the raw tables below, we have identified rich QuickBooks fields that can be transformed into actionable business reports:
                            </p>
                        </div>
                        <button onclick="toggleIdeasBox()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #0369a1;">&times;</button>
                    </div>

                    <div class="ideas-grid">
                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-shield-check"></i> 1. Hardware Serial & Warranty RMA Tracker</div>
                            <span class="idea-fields">sales.item_description • invoice_date</span>
                            <p class="idea-desc">Isolates unit serial numbers (e.g. <code>S/N PE-99482</code>) and computes remaining warranty based on 1, 2, or 3-year term clauses to prevent servicing out-of-warranty hardware.</p>
                        </div>

                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-alert-triangle"></i> 2. Credit Limit Over-Utilization Alert</div>
                            <span class="idea-fields">customer_profiles.credit_limit • current_balance</span>
                            <p class="idea-desc">Flags high-risk accounts where active unpaid debt exceeds their QuickBooks approved credit ceiling (e.g. Credit Limit: LKR 1M vs AR: LKR 1.45M = 145% exposure).</p>
                        </div>

                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-calendar-clock"></i> 3. Contractual Payment Terms vs DSO Drift</div>
                            <span class="idea-fields">customer_profiles.terms • sales.days_to_pay</span>
                            <p class="idea-desc">Measures SLA compliance. Identifies clients contracted on 'Net 30' who consistently breach terms by taking 60–90 days to settle invoices.</p>
                        </div>

                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-credit-card"></i> 4. Cheque vs Bank Transfer Reconciler</div>
                            <span class="idea-fields">payments.reference_num • payment_method</span>
                            <p class="idea-desc">Breaks down collections by settlement instrument (cheques vs electronic fund transfers), tracking clearance delays and post-dated cheques.</p>
                        </div>

                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-file-check-2"></i> 5. Customer PO Compliance & Tender Tracker</div>
                            <span class="idea-fields">sales.po_number • invoice_number</span>
                            <p class="idea-desc">Tracks orders with missing Purchase Orders (audit risk) and groups recurring government/corporate tender deliveries under a master PO number.</p>
                        </div>

                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-map-pin"></i> 6. Geographic Territory & Outstation Breakdown</div>
                            <span class="idea-fields">customer_profiles.bill_city • bill_state</span>
                            <p class="idea-desc">Aggregates revenue and collection speed across geographic districts (Colombo, Kandy, Galle, Gampaha) to optimize territory rep deployment.</p>
                        </div>

                        <div class="idea-card">
                            <div class="idea-title"><i class="icon-refresh-cw"></i> 7. RMA & Credit Note Return Rate</div>
                            <span class="idea-fields">sales.invoice_type = 'Credit Memo'</span>
                            <p class="idea-desc">Quantifies product return rates and financial credit note adjustments by product category, revealing defective hardware batches or billing disputes.</p>
                        </div>
                    </div>
                </div>

                <!-- Dataset Navigation Tabs -->
                <div class="dataset-tabs">
                    <a href="explorer.php?tab=sales" class="dataset-tab <?php echo $tab === 'sales' ? 'active' : ''; ?>">
                        <i class="icon-receipt"></i>
                        <span>Sales & Invoices</span>
                        <span class="tab-badge"><?php echo number_format($totalSales); ?></span>
                    </a>
                    <a href="explorer.php?tab=payments" class="dataset-tab <?php echo $tab === 'payments' ? 'active' : ''; ?>">
                        <i class="icon-credit-card"></i>
                        <span>Received Payments</span>
                        <span class="tab-badge"><?php echo number_format($totalPayments); ?></span>
                    </a>
                    <a href="explorer.php?tab=customers" class="dataset-tab <?php echo $tab === 'customers' ? 'active' : ''; ?>">
                        <i class="icon-users"></i>
                        <span>Customer Profiles</span>
                        <span class="tab-badge"><?php echo number_format($totalCustomers); ?></span>
                    </a>
                    <a href="explorer.php?tab=assets" class="dataset-tab <?php echo $tab === 'assets' ? 'active' : ''; ?>">
                        <i class="icon-shield"></i>
                        <span>Hardware Assets (S/N)</span>
                        <span class="tab-badge"><?php echo number_format($totalAssets); ?></span>
                    </a>
                    <a href="explorer.php?tab=subscriptions" class="dataset-tab <?php echo $tab === 'subscriptions' ? 'active' : ''; ?>">
                        <i class="icon-refresh-cw"></i>
                        <span>SaaS Subscriptions</span>
                        <span class="tab-badge"><?php echo number_format($totalSubscriptions); ?></span>
                    </a>
                    <a href="explorer.php?tab=audit" class="dataset-tab <?php echo $tab === 'audit' ? 'active' : ''; ?>">
                        <i class="icon-activity"></i>
                        <span>Sync & Audit Logs</span>
                    </a>
                </div>

                <!-- Filter & Search Toolbar -->
                <form method="GET" action="explorer.php" class="explorer-toolbar">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                    
                    <div class="filter-fields">
                        <input type="text" name="search" class="filter-input" placeholder="Search keywords, #, S/N..." value="<?php echo htmlspecialchars($search); ?>" style="width: 240px;">

                        <?php if ($tab === 'sales'): ?>
                        <select name="category" class="filter-input">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="viability" class="filter-input">
                            <option value="all" <?php echo $viability === 'all' ? 'selected' : ''; ?>>All Line Types</option>
                            <option value="commercial" <?php echo $viability === 'commercial' ? 'selected' : ''; ?>>Commercial Sales (> 0)</option>
                            <option value="serialized" <?php echo $viability === 'serialized' ? 'selected' : ''; ?>>Serialized Assets (0 with S/N)</option>
                            <option value="placeholders" <?php echo $viability === 'placeholders' ? 'selected' : ''; ?>>Headers & Memos (= 0)</option>
                        </select>
                        <?php endif; ?>

                        <?php if ($tab === 'sales' || $tab === 'payments'): ?>
                        <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($dateFrom); ?>" title="Date From">
                        <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($dateTo); ?>" title="Date To">
                        <?php endif; ?>

                        <select name="limit" class="filter-input" onchange="this.form.submit()">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 rows</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 rows</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 rows</option>
                            <option value="250" <?php echo $limit == 250 ? 'selected' : ''; ?>>250 rows</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; border-radius: 8px; background: var(--primary); color: #fff; border: none; cursor: pointer;">
                            <i class="icon-filter"></i> Apply
                        </button>
                        <?php if (!empty($search) || !empty($category) || !empty($dateFrom) || !empty($dateTo) || $viability !== 'all'): ?>
                        <a href="explorer.php?tab=<?php echo urlencode($tab); ?>" class="btn" style="padding: 8px 12px; font-size: 13px; color: var(--text-muted); text-decoration: none;">
                            Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Data Table Container -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px; font-weight: 700; color: var(--text-main);">
                            Showing <?php echo number_format(min($totalRows, $offset + 1)); ?>–<?php echo number_format(min($totalRows, $offset + count($rows))); ?> of <?php echo number_format($totalRows); ?> records
                        </span>
                        <span style="font-size: 11px; color: var(--text-muted);">
                            Last Sync: <strong><?php echo htmlspecialchars($lastSync); ?></strong>
                        </span>
                    </div>

                    <div style="overflow-x: auto;">
                        <?php if ($tab === 'sales'): ?>
                        <table class="table" style="margin: 0; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Invoice #</th>
                                    <th>Customer Name</th>
                                    <th>Description / Serials</th>
                                    <th>Category</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Amount (<?php echo $currency; ?>)</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-muted);">No sales records found matching your filters.</td></tr>
                                <?php else: foreach ($rows as $r): 
                                    $isPaid = $r['paid_date'] !== null;
                                    $isSerialized = $r['is_serialized'] == 1;
                                    $isZero = $r['total_amount'] == 0;
                                ?>
                                <tr>
                                    <td style="white-space: nowrap; color: var(--text-muted);"><?php echo htmlspecialchars($r['invoice_date']); ?></td>
                                    <td>
                                        <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: <?php echo $r['invoice_type'] === 'Invoice' ? '#e2e8f0' : '#fee2e2'; ?>; color: <?php echo $r['invoice_type'] === 'Invoice' ? '#334155' : '#991b1b'; ?>;">
                                            <?php echo htmlspecialchars($r['invoice_type']); ?>
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                        <?php echo htmlspecialchars($r['invoice_number']); ?>
                                        <?php 
                                            $treatment = $r['vat_treatment'] ?? '';
                                            $ratePct = round(($r['applied_tax_rate'] ?? 0.18) * 100);
                                            if ($treatment === 'VAT_INCLUSIVE') {
                                                echo '<span style="display:inline-block; font-size: 9px; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 1px 4px; border-radius: 3px; margin-left: 3px;" title="Statutory ' . $ratePct . '% VAT Inclusive">' . $ratePct . '% Inc</span>';
                                            } elseif ($treatment === 'VAT_EXEMPT' || ($r['applied_tax_rate'] ?? 0) == 0) {
                                                echo '<span style="display:inline-block; font-size: 9px; font-weight: 800; background: #f1f5f9; color: #475569; padding: 1px 4px; border-radius: 3px; margin-left: 3px;" title="Statutory VAT Exempt (0%)">0% Exempt</span>';
                                            } elseif ($treatment === 'VAT_EXCLUSIVE_BREAKUP') {
                                                echo '<span style="display:inline-block; font-size: 9px; font-weight: 800; background: #fef3c7; color: #b45309; padding: 1px 4px; border-radius: 3px; margin-left: 3px;" title="' . $ratePct . '% VAT Exclusive Breakup">' . $ratePct . '% Excl</span>';
                                            }
                                        ?>
                                    </td>
                                    <td style="font-weight: 600;" title="<?php echo htmlspecialchars($r['customer_name']); ?>">
                                        <a href="customer_report.php?name=<?php echo urlencode($r['customer_name']); ?>" style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars(substr($r['customer_name'], 0, 30)); ?>
                                        </a>
                                    </td>
                                    <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($r['item_description']); ?>">
                                        <?php echo htmlspecialchars($r['item_description']); ?>
                                        <?php if ($isSerialized): ?>
                                            <span style="background: #e0e7ff; color: #3730a3; font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 3px; margin-left: 4px;">S/N</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; color: var(--text-muted); background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo htmlspecialchars($r['product_category'] ?: 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td class="text-right"><?php echo (float)$r['quantity']; ?></td>
                                    <td class="text-right" style="font-weight: 700; <?php echo $isZero ? 'color: var(--text-muted);' : 'color: var(--text-main);'; ?>">
                                        <?php if ($isZero && $isSerialized): ?>
                                            <span style="font-size: 10px; color: #3730a3; background: #e0e7ff; padding: 2px 6px; border-radius: 4px;">Warranty (0.00)</span>
                                        <?php else: ?>
                                            <?php echo number_format($r['total_amount'], 2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: <?php echo $isPaid ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $isPaid ? '#15803d' : '#991b1b'; ?>;">
                                            <?php echo $isPaid ? 'Settled' : 'Unpaid'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-inspect" onclick='openRawModal(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="icon-code"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <?php elseif ($tab === 'payments'): ?>
                        <table class="table" style="margin: 0; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer Name</th>
                                    <th>Matched Invoice #</th>
                                    <th>Reference / Cheque #</th>
                                    <th class="text-right">Amount (<?php echo $currency; ?>)</th>
                                    <th>Recorded At</th>
                                    <th class="text-center">Raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No payment records found.</td></tr>
                                <?php else: foreach ($rows as $r): ?>
                                <tr>
                                    <td style="white-space: nowrap; color: var(--text-muted);"><?php echo htmlspecialchars($r['payment_date']); ?></td>
                                    <td style="font-weight: 600;">
                                        <a href="customer_report.php?name=<?php echo urlencode($r['customer_name']); ?>" style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars($r['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                        <?php echo htmlspecialchars($r['invoice_num'] ?: 'Unmatched'); ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($r['reference_num'] ?: '-'); ?></td>
                                    <td class="text-right" style="font-weight: 700; color: #15803d;"><?php echo number_format($r['amount'], 2); ?></td>
                                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($r['created_at']); ?></td>
                                    <td class="text-center">
                                        <button class="btn-inspect" onclick='openRawModal(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="icon-code"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <?php elseif ($tab === 'customers'): ?>
                        <table class="table" style="margin: 0; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Type</th>
                                    <th>Primary Contact</th>
                                    <th>Phone / Email</th>
                                    <th>City</th>
                                    <th>Rep</th>
                                    <th>Terms</th>
                                    <th class="text-right">Credit Limit</th>
                                    <th class="text-right">Balance</th>
                                    <th class="text-center">Raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-muted);">No customer profiles found.</td></tr>
                                <?php else: foreach ($rows as $r): 
                                    $isOverLimit = $r['credit_limit'] > 0 && $r['current_balance'] > $r['credit_limit'];
                                ?>
                                <tr>
                                    <td style="font-weight: 700;">
                                        <a href="customer_report.php?name=<?php echo urlencode($r['customer_name']); ?>" style="color: var(--primary); text-decoration: none;">
                                            <?php echo htmlspecialchars($r['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: <?php echo $r['customer_type'] === 'Partner' ? '#e0e7ff' : '#f1f5f9'; ?>; color: <?php echo $r['customer_type'] === 'Partner' ? '#3730a3' : '#475569'; ?>;">
                                            <?php echo htmlspecialchars($r['customer_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['contact_name'] ?: '-'); ?></td>
                                    <td style="font-size: 12px; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($r['phone'] ?: ($r['email'] ?: '-')); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['bill_city'] ?: '-'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($r['sales_rep'] ?: '-'); ?></strong></td>
                                    <td><span style="font-size: 11px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($r['terms'] ?: 'N/A'); ?></span></td>
                                    <td class="text-right"><?php echo number_format($r['credit_limit'], 0); ?></td>
                                    <td class="text-right" style="font-weight: 800; color: <?php echo $isOverLimit ? '#dc2626' : 'var(--text-main)'; ?>;">
                                        <?php echo number_format($r['current_balance'], 0); ?>
                                        <?php if ($isOverLimit): ?>
                                            <span style="font-size: 9px; background: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 3px; margin-left: 2px;" title="Exceeds Credit Limit!">OVER</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-inspect" onclick='openRawModal(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="icon-code"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <?php elseif ($tab === 'assets'): ?>
                        <table class="table" style="margin: 0; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Product Name & Model</th>
                                    <th>Brand</th>
                                    <th>Customer Name</th>
                                    <th>Invoice #</th>
                                    <th class="text-center">Warranty</th>
                                    <th>Expiry Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No hardware assets found. Run the AI entity extractor from Settings to normalize invoice lines into discrete serialized units.</td></tr>
                                <?php else: foreach ($rows as $r): 
                                    $days = $r['days_remaining'] ?? 0;
                                    $isExp = $days < 0;
                                    $is30 = $days >= 0 && $days <= 30;
                                    $badgeColor = $isExp ? '#dc2626' : ($is30 ? '#d97706' : '#16a34a');
                                    $badgeBg = $isExp ? '#fee2e2' : ($is30 ? '#fef3c7' : '#dcfce7');
                                    $badgeText = $isExp ? 'Expired' : ($is30 ? "Due in {$days}d" : "Active ({$days}d)");
                                ?>
                                <tr>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 800; font-size: 13px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #1e293b;">
                                            <?php echo htmlspecialchars($r['serial_number']); ?>
                                        </span>
                                        <?php if (!empty($r['parent_serial_number'])): ?>
                                            <div style="font-size: 10px; color: var(--text-muted);">Chassis: <?php echo htmlspecialchars($r['parent_serial_number']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($r['product_name']); ?>
                                        <?php if (!empty($r['model_sku'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($r['model_sku']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; background: #eff6ff; color: #2563eb; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                            <?php echo htmlspecialchars($r['brand'] ?: 'Hardware'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="customer_report.php?name=<?php echo urlencode($r['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($r['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                        <?php echo htmlspecialchars($r['invoice_number']); ?>
                                    </td>
                                    <td class="text-center" style="font-size: 12px; font-weight: 600;">
                                        <?php echo $r['warranty_months'] ? ($r['warranty_months'] . 'm') : 'Standard'; ?>
                                    </td>
                                    <td style="font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($r['warranty_expiry_date'] ?: '—'); ?></td>
                                    <td class="text-center">
                                        <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>;">
                                            <?php echo $badgeText; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-inspect" onclick='openRawModal(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="icon-code"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <?php elseif ($tab === 'subscriptions'): ?>
                        <table class="table" style="margin: 0; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Software / Service Offering</th>
                                    <th>Edition / Tier</th>
                                    <th class="text-center">Seats</th>
                                    <th>Customer Name</th>
                                    <th>Invoice #</th>
                                    <th>Coverage Period</th>
                                    <th class="text-right">Opportunity Value</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No subscription or SaaS records found. Run the AI entity extractor from Settings to normalize software and SaaS service periods.</td></tr>
                                <?php else: foreach ($rows as $r): 
                                    $days = $r['days_remaining'] ?? 0;
                                    $isExp = $days < 0;
                                    $is60 = $days >= 0 && $days <= 60;
                                    $badgeColor = $isExp ? '#dc2626' : ($is60 ? '#d97706' : '#16a34a');
                                    $badgeBg = $isExp ? '#fee2e2' : ($is60 ? '#fef3c7' : '#dcfce7');
                                    $badgeText = $isExp ? 'Expired' : ($is60 ? "Due in {$days}d" : "Active ({$days}d)");
                                ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($r['software_name']); ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569;">
                                            <?php echo htmlspecialchars($r['edition_tier'] ?: 'Standard'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center" style="font-weight: 800; font-size: 13px;">
                                        <?php echo number_format($r['license_seats'] ?? 1); ?>
                                    </td>
                                    <td>
                                        <a href="customer_report.php?name=<?php echo urlencode($r['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($r['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                        <?php echo htmlspecialchars($r['invoice_number']); ?>
                                    </td>
                                    <td style="font-size: 12px; color: #475569;">
                                        <?php echo htmlspecialchars($r['period_start_date'] ?: '—'); ?> → <strong><?php echo htmlspecialchars($r['period_end_date'] ?: '—'); ?></strong>
                                    </td>
                                    <td class="text-right" style="font-weight: 800;">
                                        <?php echo htmlspecialchars($currency) . number_format($r['renewal_opportunity_value'] ?? 0, 0); ?>
                                    </td>
                                    <td class="text-center">
                                        <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>;">
                                            <?php echo $badgeText; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-inspect" onclick='openRawModal(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="icon-code"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <?php elseif ($tab === 'audit'): ?>
                        <table class="table" style="margin: 0; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th class="text-center">Raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No audit logs recorded yet.</td></tr>
                                <?php else: foreach ($rows as $r): ?>
                                <tr>
                                    <td style="white-space: nowrap; color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($r['activity_date']); ?></td>
                                    <td><span style="font-weight: 700; font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($r['action']); ?></span></td>
                                    <td style="font-size: 12.5px;"><?php echo htmlspecialchars($r['description']); ?></td>
                                    <td style="font-family: monospace; font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($r['ip_address']); ?></td>
                                    <td class="text-center">
                                        <button class="btn-inspect" onclick='openRawModal(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="icon-code"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div style="padding: 16px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <span style="font-size: 13px; color: var(--text-muted);">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <?php if ($page > 1): ?>
                                <a href="explorer.php?tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&viability=<?php echo urlencode($viability); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&limit=<?php echo $limit; ?>&p=<?php echo $page - 1; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; text-decoration: none; color: var(--text-main);">Previous</a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="explorer.php?tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&viability=<?php echo urlencode($viability); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&limit=<?php echo $limit; ?>&p=<?php echo $page + 1; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; text-decoration: none; color: var(--text-main);">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- .content-body -->
        </main>
    </div>

    <!-- Raw JSON Inspector Modal -->
    <div class="modal-overlay" id="rawModalOverlay" onclick="closeRawModal(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="icon-code" style="color: var(--primary);"></i>
                    <h3 style="font-size: 15px; font-weight: 800;">Raw Record Attributes (QuickBooks Schema)</h3>
                </div>
                <button onclick="closeRawModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-size: 12px; color: var(--text-muted);">Exact key-value dictionary extracted from QuickBooks Desktop:</span>
                    <button onclick="copyRawJson()" class="btn" style="padding: 4px 10px; font-size: 11px; font-weight: 700; border: 1px solid var(--border-color); border-radius: 6px; background: #f8fafc; cursor: pointer;">
                        Copy JSON
                    </button>
                </div>
                <div class="json-viewer" id="rawJsonViewer"></div>
            </div>
        </div>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
        function toggleIdeasBox() {
            const box = document.getElementById('ideasBox');
            const text = document.getElementById('ideasToggleText');
            if (box.style.display === 'none') {
                box.style.display = 'block';
                text.innerText = 'Hide Report Ideas';
            } else {
                box.style.display = 'none';
                text.innerText = 'Report Ideas & Potential';
            }
        }

        let currentModalData = null;
        function openRawModal(data) {
            currentModalData = data;
            document.getElementById('rawJsonViewer').innerText = JSON.stringify(data, null, 2);
            document.getElementById('rawModalOverlay').style.display = 'flex';
        }

        function closeRawModal(e) {
            document.getElementById('rawModalOverlay').style.display = 'none';
        }

        function copyRawJson() {
            if (currentModalData) {
                navigator.clipboard.writeText(JSON.stringify(currentModalData, null, 2))
                    .then(() => alert('Raw JSON copied to clipboard!'))
                    .catch(() => alert('Failed to copy.'));
            }
        }
    </script>
</body>
</html>
