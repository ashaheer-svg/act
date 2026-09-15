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

// AJAX Handler for Customer Details
if (isset($_GET['ajax_customer_history'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $name = $_GET['ajax_customer_history'];
    echo json_encode($reports->getCustomerHistory($name), JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// AJAX Handler for Invoice Details (Full Line Items, Serials, Payments)
if (isset($_GET['ajax_invoice_details'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $inv = $_GET['ajax_invoice_details'];
    try {
        echo json_encode($reports->getInvoiceDetails($inv), JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// AJAX Handler for Real-Time Warranty & Serial Number Lookup
if (isset($_GET['ajax_warranty_lookup'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $q = $_GET['ajax_warranty_lookup'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
    try {
        $results = $reports->lookupWarrantySerial($q, $status, $limit);
        echo json_encode(['results' => $results], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
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

// CSV Export Handlers for New Active Analytics Reports
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($type === 'invoices') {
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
    } elseif ($type === 'ltv') {
        $res = $reports->getCustomerLTVReport(['search' => $_GET['search'] ?? '', 'customer_type' => $customer_type, 'rep_code' => $rep_code, 'tier' => $_GET['tier'] ?? '', 'sort' => $_GET['sort'] ?? 'ltv_desc'], 1, 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=customer_ltv_matrix_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Customer Name', 'Category', 'Sales Rep', 'First Invoice', 'Last Invoice', 'Tenure (Yrs)', 'Invoices', 'Base Net (LKR)', '18% VAT (LKR)', 'Lifetime Gross (LKR)', 'Avg Order Value (LKR)', 'Tier']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['customer_name'], $r['customer_type'] ?? '', $r['sales_rep'] ?? '', $r['first_invoice'], $r['last_invoice'], $r['tenure_years'], $r['total_invoices'], $r['lifetime_base'], $r['lifetime_vat'], $r['lifetime_gross'], $r['avg_order_value'], $r['ltv_tier']]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'churn') {
        $res = $reports->getAccountChurnReport(['search' => $_GET['search'] ?? '', 'rep_code' => $rep_code, 'risk' => $_GET['risk'] ?? 'all'], 1, 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=account_churn_pipeline_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Customer Name', 'Channel', 'Sales Rep', 'Last Order Date', 'Days Inactive', 'Invoices', 'Base Spend (LKR)', 'VAT Paid (LKR)', 'Historical Gross (LKR)', 'Churn Risk Tier']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['customer_name'], $r['customer_type'] ?? '', $r['sales_rep'] ?? '', $r['last_order_date'], $r['days_inactive'], $r['historical_invoices'], $r['historical_base'], $r['historical_vat'], $r['historical_gross'], $r['churn_risk']]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'eol') {
        $res = $reports->getHardwareEOLReport(['search' => $_GET['search'] ?? '', 'brand' => $brand, 'status' => $_GET['status'] ?? 'all'], 1, 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hardware_eol_forecast_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Serial Number', 'Product Model / SKU', 'Product Name', 'Brand', 'Customer Name', 'Invoice #', 'Warranty Months', 'Start Date', 'Expiry Date', 'Days Remaining', 'Fleet Status']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['serial_number'], $r['model_sku'], $r['product_name'], $r['brand'], $r['customer_name'], $r['invoice_number'], $r['warranty_months'], $r['warranty_start_date'], $r['warranty_expiry_date'], $r['days_remaining'], $r['eol_status']]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'contracts' || $type === 'expiring_contracts') {
        $exportFilters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? 'all',
            'range' => $_GET['range'] ?? 'pm_90d',
            'category' => $_GET['category'] ?? 'all',
            'sort' => $_GET['sort'] ?? 'expiry_asc',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        $res = $reports->getContractsRenewalReport($exportFilters, 1, 10000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=expiring_contracts_renewals_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice Number', 'Customer Name', 'End Customer', 'Service / Software Offering', 'Category', 'Term (Months)', 'Period Start Date', 'Expiring Date', 'Days Remaining', 'Opportunity Value (LKR)', 'Status']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [
                $r['invoice_number'],
                $r['customer_name'],
                $r['end_customer'] ?? '',
                $r['software_name'],
                $r['category_label'],
                $r['term_months'] ?? '',
                $r['period_start_date'],
                $r['period_end_date'],
                $r['days_remaining'],
                round($r['renewal_opportunity_value'], 2),
                $r['dynamic_status']
            ]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'rental_roi') {
        $res = $reports->getRentalROIReport(['search' => $_GET['search'] ?? '', 'status' => $_GET['status'] ?? 'all'], 1, 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=rental_fleet_roi_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice #', 'Invoice Date', 'Customer Name', 'Product Description', 'Brand', 'Qty', 'Unit Price', 'Base Net (LKR)', '18% VAT (LKR)', 'Gross (LKR)', 'Days Since Billed', 'Rental Status', 'Serials']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['invoice_number'], $r['invoice_date'], $r['customer_name'], $r['clean_product_name'], $r['brand_category'], $r['quantity'], $r['unit_price'], $r['base_value'], $r['vat_component'], $r['total_amount'], $r['days_since_billed'], $r['rental_status'], $r['serial_numbers']]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'brand_growth') {
        $viewMode = $_GET['view_mode'] ?? 'brand';
        $bgFilters = [
            'brand' => !empty($_GET['brand']) ? $_GET['brand'] : null,
            'category' => !empty($_GET['category']) ? $_GET['category'] : null
        ];
        header('Content-Type: text/csv; charset=utf-8');
        $out = fopen('php://output', 'w');

        if ($viewMode === 'category') {
            $res = $reports->getCategoryPerformanceReport($bgFilters);
            header('Content-Disposition: attachment; filename=category_performance_' . date('Ymd_His') . '.csv');
            fputcsv($out, ['Category Name', 'Invoices', 'Client Reach', 'Units Sold', 'Lifetime Base (LKR)', 'Lifetime VAT (LKR)', 'Lifetime Gross (LKR)', 'Historical Pre-2023 (LKR)', 'Modern Post-2023 (LKR)', 'Avg Yield (LKR)', 'Revenue Share %', 'Growth Trajectory']);
            foreach ($res['rows'] as $r) {
                fputcsv($out, [$r['category_name'], $r['total_invoices'], $r['client_reach'], $r['total_units_sold'], $r['lifetime_base_revenue'], $r['lifetime_vat_revenue'], $r['lifetime_gross_revenue'], $r['historical_pre_2023'], $r['modern_post_2023'], $r['avg_line_yield'], $r['revenue_share_pct'], $r['growth_trajectory']]);
            }
        } elseif ($viewMode === 'matrix') {
            $matrix = $reports->getBrandCategoryMatrixReport($bgFilters);
            header('Content-Disposition: attachment; filename=brand_category_matrix_' . date('Ymd_His') . '.csv');
            fputcsv($out, ['Brand', 'Category', 'Invoices', 'Units Sold', 'Gross Revenue (LKR)']);
            foreach ($matrix as $m) {
                fputcsv($out, [$m['brand_name'], $m['category_name'], $m['invoice_count'], $m['units_sold'], $m['gross_revenue']]);
            }
        } else {
            $res = $reports->getBrandGrowthReport($bgFilters);
            header('Content-Disposition: attachment; filename=brand_performance_' . date('Ymd_His') . '.csv');
            fputcsv($out, ['Brand Name', 'Invoices', 'Client Reach', 'Units Sold', 'Lifetime Base (LKR)', 'Lifetime VAT (LKR)', 'Lifetime Gross (LKR)', 'Historical Pre-2023 (LKR)', 'Modern Post-2023 (LKR)', 'Avg Order Yield (LKR)', 'Revenue Share %', 'Growth Trajectory']);
            foreach ($res['rows'] as $r) {
                fputcsv($out, [$r['brand_name'], $r['total_invoices'], $r['client_reach'], $r['total_units_sold'], $r['lifetime_base_revenue'], $r['lifetime_vat_revenue'], $r['lifetime_gross_revenue'], $r['historical_pre_2023'], $r['modern_post_2023'], $r['avg_line_yield'], $r['revenue_share_pct'], $r['growth_trajectory']]);
            }
        }
        fclose($out);
        exit;
    } elseif ($type === 'dso_trends') {
        $res = $reports->getDSOTrendsReport($year, ['search' => $_GET['search'] ?? '']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=dso_working_capital_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Customer Name', 'Channel', 'Sales Rep', 'Invoices', 'Gross Billed (LKR)', 'Collected (LKR)', 'Outstanding (LKR)', 'Collection Rate %', 'Avg DSO (Days)', 'Max Delay (Days)', 'Risk Status']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['customer_name'], $r['customer_type'] ?? '', $r['sales_rep'] ?? '', $r['invoice_count'], $r['gross_billed'], $r['collected_amount'], $r['outstanding_amount'], $r['collection_rate_pct'], $r['avg_dso_days'], $r['max_dso_days'], $r['risk_badge']]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'tax_audit') {
        $res = $reports->getTaxAuditReport($year, $month, 1, 10000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=statutory_tax_ird_audit_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice Number', 'Type', 'Invoice Date', 'Customer Name', 'VAT Treatment', 'Taxable Base (LKR)', 'Statutory 18% VAT (LKR)', 'Gross Total (LKR)', 'Settlement Date']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['invoice_number'], $r['invoice_type'], $r['invoice_date'], $r['customer_name'], $r['vat_treatment'], $r['taxable_base'], $r['vat_18_component'], $r['gross_total'], $r['paid_date'] ?? '']);
        }
        fclose($out);
        exit;
    } elseif ($type === 'warranties') {
        $res = $reports->lookupWarrantySerial($_GET['search'] ?? '', $_GET['status'] ?? 'all', 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=warranty_serial_lookup_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Serial Number', 'Product Name', 'Brand', 'Model / SKU', 'Customer', 'Warranty Months', 'Start Date', 'Expiry Date', 'Status', 'Days Remaining / Expired', 'Invoices Count', 'Invoices', 'Maintenance Contracts Count']);
        foreach ($res as $r) {
            $invNumbers = implode('; ', array_column($r['invoices'], 'invoice_number'));
            fputcsv($out, [
                $r['serial_number'],
                $r['product_name'],
                $r['brand'],
                $r['model_sku'],
                $r['current_customer'],
                $r['warranty_months'],
                $r['latest_start_date'] ?: $r['initial_start_date'],
                $r['computed_expiry_date'],
                $r['computed_status'],
                $r['days_diff'],
                count($r['invoices']),
                $invNumbers,
                count($r['maintenance_contracts'])
            ]);
        }
        fclose($out);
        exit;
    } elseif ($type === 'unpaid_invoices') {
        $res = $reports->getUnpaidInvoicesByCustomerReport([
            'search' => $_GET['search'] ?? '',
            'rep_code' => $rep_code,
            'customer_type' => $customer_type,
            'aging_bracket' => $_GET['aging_bracket'] ?? 'all',
            'sort' => $_GET['sort'] ?? 'customer_asc'
        ], 1, 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=unpaid_invoices_by_customer_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Customer Name', 'Channel', 'Sales Rep', 'Invoice Number', 'Invoice Date', 'Aging Days', 'Aging Bracket', 'PO Number', 'Line Items Count', 'Base Net (LKR)', '18% VAT (LKR)', 'Gross Balance Due (LKR)']);
        foreach ($res['customers'] as $cust) {
            foreach ($cust['invoices'] as $inv) {
                fputcsv($out, [
                    $cust['customer_name'],
                    $cust['customer_type'],
                    $cust['rep_name'] ?: $cust['sales_rep_code'],
                    $inv['invoice_number'],
                    $inv['invoice_date'],
                    $inv['aging_days'],
                    $inv['aging_label'],
                    $inv['po_number'] ?? '',
                    $inv['line_count'],
                    $inv['base_value'],
                    $inv['vat_component'],
                    $inv['total_amount']
                ]);
            }
        }
        fclose($out);
        exit;
    }
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
        case 'ltv':
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $search = $_GET['search'] ?? '';
            $tier = $_GET['tier'] ?? 'all';
            $sort = $_GET['sort'] ?? 'ltv_desc';
            $ltvFilters = [
                'search' => $search,
                'customer_type' => $customer_type,
                'rep_code' => $rep_code,
                'tier' => $tier,
                'sort' => $sort
            ];
            $ltvResult = $reports->getCustomerLTVReport($ltvFilters, $p, $limit);
            $ltvData = $ltvResult['rows'];
            $ltvTotal = $ltvResult['total'];
            $ltvPages = $ltvResult['pages'];
            $ltvSummary = $ltvResult['summary'];
            $reportTitle = 'Customer Lifetime Value (LTV) & Loyalty Matrix';
            break;
        case 'churn':
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $search = $_GET['search'] ?? '';
            $risk = $_GET['risk'] ?? 'all';
            $churnFilters = [
                'search' => $search,
                'rep_code' => $rep_code,
                'risk' => $risk
            ];
            $churnResult = $reports->getAccountChurnReport($churnFilters, $p, $limit);
            $churnData = $churnResult['rows'];
            $churnTotal = $churnResult['total'];
            $churnPages = $churnResult['pages'];
            $churnSummary = $churnResult['summary'];
            $reportTitle = 'Account Churn & Reactivation Pipeline';
            break;
        case 'eol':
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? 'all';
            $eolFilters = [
                'search' => $search,
                'brand' => $brand,
                'status' => $status
            ];
            $eolResult = $reports->getHardwareEOLReport($eolFilters, $p, $limit);
            $eolData = $eolResult['rows'];
            $eolTotal = $eolResult['total'];
            $eolPages = $eolResult['pages'];
            $eolSummary = $eolResult['summary'];
            $reportTitle = 'Hardware End-of-Life (EOL) & Refresh Forecast';
            break;
        case 'contracts':
        case 'expiring_contracts':
            $type = 'contracts';
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? 'all';
            $range = $_GET['range'] ?? 'pm_90d';
            $category = $_GET['category'] ?? 'all';
            $sort = $_GET['sort'] ?? 'expiry_asc';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';
            $contractsFilters = [
                'search' => $search,
                'status' => $status,
                'range' => $range,
                'category' => $category,
                'sort' => $sort,
                'date_from' => $date_from,
                'date_to' => $date_to
            ];
            $contractsResult = $reports->getContractsRenewalReport($contractsFilters, $p, $limit);
            $contractsData = $contractsResult['rows'];
            $contractsTotal = $contractsResult['total'];
            $contractsPages = $contractsResult['pages'];
            $contractsSummary = $contractsResult['summary'];
            $reportTitle = 'Time-Based Expiring Contracts & Invoices';
            break;
        case 'rental_roi':
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? 'all';
            $rentalFilters = [
                'search' => $search,
                'status' => $status
            ];
            $rentalResult = $reports->getRentalROIReport($rentalFilters, $p, $limit);
            $rentalData = $rentalResult['rows'];
            $rentalTotal = $rentalResult['total'];
            $rentalPages = $rentalResult['pages'];
            $rentalSummary = $rentalResult['summary'];
            $reportTitle = 'Rental Fleet Utilization & Commercial Yield';
            break;
        case 'brand_growth':
            $viewMode = $_GET['view_mode'] ?? 'brand';
            if (!in_array($viewMode, ['brand', 'category', 'matrix'])) $viewMode = 'brand';
            $catFilter = $_GET['category'] ?? null;
            $brandFilter = $_GET['brand'] ?? null;
            $bgFilters = [
                'brand' => $brandFilter,
                'category' => $catFilter,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            $brandGrowthResult = $reports->getBrandGrowthReport($bgFilters);
            $brandGrowthData = $brandGrowthResult['rows'];
            $brandGrowthSummary = $brandGrowthResult['summary'];

            $categoryPerfResult = $reports->getCategoryPerformanceReport($bgFilters);
            $categoryPerfData = $categoryPerfResult['rows'];
            $categoryPerfSummary = $categoryPerfResult['summary'];

            $matrixData = ($viewMode === 'matrix') ? $reports->getBrandCategoryMatrixReport($bgFilters) : [];
            
            $masterBrandsList = $db->getBrands(true);
            $masterCategoriesList = $db->getCategories(true);

            if ($viewMode === 'category') {
                $reportTitle = 'Product Category Performance & Growth Trajectory (2009–2026)';
            } elseif ($viewMode === 'matrix') {
                $reportTitle = 'Brand × Category Portfolio Cross-Matrix';
            } else {
                $reportTitle = 'Brand Performance & Growth Trajectory (2009–2026)';
            }
            break;
        case 'dso_trends':
            $search = $_GET['search'] ?? '';
            $dsoResult = $reports->getDSOTrendsReport($year, ['search' => $search]);
            $dsoData = $dsoResult['rows'];
            $dsoSummary = $dsoResult['summary'];
            $reportTitle = 'Working Capital & DSO Collection Velocity';
            break;
        case 'tax_audit':
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $taxResult = $reports->getTaxAuditReport($year, $month, $p, $limit);
            $taxData = $taxResult['rows'];
            $taxTotal = $taxResult['total'];
            $taxPages = $taxResult['pages'];
            $taxSummary = $taxResult['summary'];
            $reportTitle = 'Statutory Tax & IRD Audit Ledger (18% VAT)';
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
            $warrantyLimit = max(10, min(100, (int)($_GET['limit'] ?? 50)));
            $warrantyKpis = $reports->getWarrantySummaryMetrics();
            $warrantyResults = $reports->lookupWarrantySerial($warrantySearch, $warrantyStatus, $warrantyLimit);
            $reportTitle = 'Hardware Warranty & Maintenance Contract Lookup';
            break;
        case 'unpaid_invoices':
            $p = max(1, (int)($_GET['p'] ?? 1));
            $limit = 25;
            $search = $_GET['search'] ?? '';
            $agingBracket = $_GET['aging_bracket'] ?? 'all';
            $sort = $_GET['sort'] ?? 'customer_asc';
            $unpaidFilters = [
                'search' => $search,
                'rep_code' => $rep_code,
                'customer_type' => $customer_type,
                'aging_bracket' => $agingBracket,
                'sort' => $sort
            ];
            $unpaidResult = $reports->getUnpaidInvoicesByCustomerReport($unpaidFilters, $p, $limit);
            $unpaidCustomers = $unpaidResult['customers'];
            $unpaidTotal = $unpaidResult['total'];
            $unpaidPages = $unpaidResult['pages'];
            $unpaidSummary = $unpaidResult['summary'];
            $reportTitle = 'Unpaid Invoices Ledger (Sorted by Customer)';
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
    <link rel="stylesheet" href="layout.css?v=2.4.0">
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
                                    <option value="invoices" <?php echo $type === 'invoices' ? 'selected' : ''; ?>>Commercial Invoices</option>
                                    <option value="unpaid_invoices" <?php echo $type === 'unpaid_invoices' ? 'selected' : ''; ?>>Unpaid Invoices</option>
                                    <option value="warranties" <?php echo $type === 'warranties' ? 'selected' : ''; ?>>Warranty & Serials</option>
                                    <option value="ltv" <?php echo $type === 'ltv' ? 'selected' : ''; ?>>Customer LTV</option>
                                    <option value="churn" <?php echo $type === 'churn' ? 'selected' : ''; ?>>Account Churn</option>
                                    <option value="eol" <?php echo $type === 'eol' ? 'selected' : ''; ?>>Hardware EOL</option>
                                    <option value="contracts" <?php echo $type === 'contracts' ? 'selected' : ''; ?>>Expiring Contracts & Subscriptions</option>
                                    <option value="rental_roi" <?php echo $type === 'rental_roi' ? 'selected' : ''; ?>>Rental Fleet ROI</option>
                                    <option value="brand_growth" <?php echo $type === 'brand_growth' ? 'selected' : ''; ?>>Brand & Category Performance</option>
                                    <option value="dso_trends" <?php echo $type === 'dso_trends' ? 'selected' : ''; ?>>DSO Trends</option>
                                    <option value="tax_audit" <?php echo $type === 'tax_audit' ? 'selected' : ''; ?>>Tax & IRD Audit (18%)</option>
                                </optgroup>
                                <optgroup label="── Temporarily Archived Reports ──">
                                    <option value="renewals" <?php echo $type === 'renewals' ? 'selected' : ''; ?>>SaaS Renewals (Archived)</option>
                                    <option value="yearly" <?php echo $type === 'yearly' ? 'selected' : ''; ?>>Yearly Sales (Archived)</option>
                                    <option value="monthly" <?php echo $type === 'monthly' ? 'selected' : ''; ?>>Monthly Sales (Archived)</option>
                                    <option value="quarterly" <?php echo $type === 'quarterly' ? 'selected' : ''; ?>>Quarterly Sales (Archived)</option>
                                    <option value="matrix" <?php echo $type === 'matrix' ? 'selected' : ''; ?>>Customer Matrix (Archived)</option>
                                    <option value="stock" <?php echo $type === 'stock' ? 'selected' : ''; ?>>Stock Movement (Archived)</option>
                                    <option value="rfm" <?php echo $type === 'rfm' ? 'selected' : ''; ?>>RFM / Churn (Archived)</option>
                                    <option value="partners" <?php echo $type === 'partners' ? 'selected' : ''; ?>>Partner Cohorts (Archived)</option>
                                    <option value="reps" <?php echo $type === 'reps' ? 'selected' : ''; ?>>Sales Reps (Archived)</option>
                                    <option value="credit" <?php echo $type === 'credit' ? 'selected' : ''; ?>>Credit Health (Archived)</option>
                                    <option value="aging" <?php echo $type === 'aging' ? 'selected' : ''; ?>>Aging (Archived)</option>
                                </optgroup>
                            </select>
                        </div>

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

                        <?php if (in_array($type, ['invoices', 'dso_trends', 'tax_audit', 'yearly', 'monthly', 'quarterly', 'matrix', 'reps'])): ?>
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
                        $activeReportsList = ['invoices', 'unpaid_invoices', 'warranties', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit'];
                        if (in_array($type, $activeReportsList)): 
                            if ($type === 'contracts') {
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
            $activeTypes = ['invoices', 'unpaid_invoices', 'warranties', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit'];
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

                    <?php 
                    $stockBaseUrl = "reports.php?type=stock&brand=" . urlencode($brand ?? '') . "&fsn=" . urlencode($fsn ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $stockPages, $stockTotal, $limit, $stockBaseUrl, 'products'); 
                    ?>
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
                                        <th class="text-right" style="width: 108px;"><div style="display: flex; justify-content: flex-end; align-items: center; gap: 4px;"><span>Gross Total</span><span style="width: 30px; flex-shrink: 0;"></span></div></th>
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
                                            <?php if (!empty($row['end_customer'])): ?>
                                                <div style="font-size: 10.5px; color: #047857; font-weight: 600; display: flex; align-items: center; gap: 3px; margin-top: 1px;" title="End Client: <?php echo htmlspecialchars($row['end_customer']); ?>">
                                                    <i class="icon-briefcase" style="font-size: 9px;"></i>
                                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px;"><?php echo htmlspecialchars($row['end_customer']); ?></span>
                                                </div>
                                            <?php endif; ?>
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
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                                <span style="font-variant-numeric: tabular-nums;"><?php echo number_format($row['total_gross_amount'], 0); ?></span>
                                                <span style="display: inline-block; width: 30px; text-align: center; flex-shrink: 0;">
                                                    <?php if ($taxTreat === 'PLUS_VAT' || $taxTreat === 'VAT_INCLUSIVE'): ?>
                                                        <span class="dense-badge dense-badge-plusvat" title="Plus VAT (Statutory breakdown)" style="font-size: 8px; padding: 1px 2px; width: 100%; box-sizing: border-box; text-align: center; display: inline-block;">+VAT</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
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

                        <!-- Split Table Pagination Footer (Concept B: Modern Floating Rail) -->
                        <?php 
                        $baseUrl = "reports.php?type=invoices&year=" . urlencode($year) . "&month=" . urlencode($month) . "&status=" . urlencode($status) . "&search=" . urlencode($search) . "&brand=" . urlencode($brand ?? '') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&sort=" . urlencode($sort ?? '');
                        echo renderPaginationRail($p, $invoicePages, $invoiceTotal, $limit, $baseUrl, 'invoices');
                        ?>
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

            <?php elseif ($type === 'ltv'): ?>
                <!-- 1. Customer Lifetime Value (LTV) & Loyalty Matrix -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Analyzed Clients</span>
                        <span class="metric-pill-val"><?php echo number_format($ltvTotal); ?></span>
                        <span class="metric-pill-sub">Total Historical Accounts</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Lifetime Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($ltvSummary['grand_lifetime_revenue'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Gross Billed Realization</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Core Net Base</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($ltvSummary['grand_lifetime_base'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Pre-VAT Recognized Revenue</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Avg Order (AOV)</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo htmlspecialchars($currency) . number_format($ltvSummary['grand_aov'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Mean Transaction Yield</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tier Distribution</span>
                        <span class="metric-pill-val" style="font-size: 13px; color: #4f46e5;">
                            Plat: <?php echo number_format($ltvSummary['platinum_count'] ?? 0); ?> | Gold: <?php echo number_format($ltvSummary['gold_count'] ?? 0); ?>
                        </span>
                        <span class="metric-pill-sub">
                            Silver: <?php echo number_format($ltvSummary['silver_count'] ?? 0); ?> | Bronze: <?php echo number_format($ltvSummary['bronze_count'] ?? 0); ?>
                        </span>
                    </div>
                </div>

                <!-- Secondary Filters -->
                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="ltv">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Tier:</span>
                        <select name="tier" class="cmd-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($tier ?? 'all') === 'all' ? 'selected' : ''; ?>>All Tiers</option>
                            <option value="PLATINUM" <?php echo ($tier ?? '') === 'PLATINUM' ? 'selected' : ''; ?>>Platinum (≥ 20M)</option>
                            <option value="GOLD" <?php echo ($tier ?? '') === 'GOLD' ? 'selected' : ''; ?>>Gold (5M–20M)</option>
                            <option value="SILVER" <?php echo ($tier ?? '') === 'SILVER' ? 'selected' : ''; ?>>Silver (1M–5M)</option>
                            <option value="BRONZE" <?php echo ($tier ?? '') === 'BRONZE' ? 'selected' : ''; ?>>Bronze (< 1M)</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Channel:</span>
                        <select name="customer_type" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <option value="Partner" <?php echo ($customer_type ?? '') === 'Partner' ? 'selected' : ''; ?>>Partners</option>
                            <option value="End Customer" <?php echo ($customer_type ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customers</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Sort:</span>
                        <select name="sort" class="cmd-select" onchange="this.form.submit()">
                            <option value="ltv_desc" <?php echo ($sort ?? '') === 'ltv_desc' ? 'selected' : ''; ?>>Gross Spend (High to Low)</option>
                            <option value="invoices_desc" <?php echo ($sort ?? '') === 'invoices_desc' ? 'selected' : ''; ?>>Order Frequency (High to Low)</option>
                            <option value="tenure_desc" <?php echo ($sort ?? '') === 'tenure_desc' ? 'selected' : ''; ?>>Account Tenure (Longest First)</option>
                            <option value="aov_desc" <?php echo ($sort ?? '') === 'aov_desc' ? 'selected' : ''; ?>>Avg Order Value (High to Low)</option>
                            <option value="name_asc" <?php echo ($sort ?? '') === 'name_asc' ? 'selected' : ''; ?>>Customer Name (A-Z)</option>
                        </select>
                    </div>
                </form>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Customer Organization</th>
                                    <th style="width: 80px;">Channel</th>
                                    <th style="width: 50px;">Rep</th>
                                    <th style="width: 80px;">First Order</th>
                                    <th style="width: 80px;">Last Order</th>
                                    <th class="text-right" style="width: 60px;">Tenure</th>
                                    <th class="text-right" style="width: 55px;">Invoices</th>
                                    <th class="text-right" style="width: 100px;">Base Net</th>
                                    <th class="text-right" style="width: 90px;">18% VAT</th>
                                    <th class="text-right" style="width: 110px;">Lifetime Gross</th>
                                    <th class="text-right" style="width: 95px;">Avg Order</th>
                                    <th class="text-center" style="width: 75px;">Loyalty Tier</th>
                                    <th class="text-center" style="width: 60px;">Dossier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ltvData)): ?>
                                    <tr><td colspan="13" style="text-align: center; padding: 40px; color: var(--text-muted);">No customer accounts found for the active criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ltvData as $row): 
                                        $tierColor = '#64748b';
                                        $tierBg = '#f1f5f9';
                                        if ($row['ltv_tier'] === 'PLATINUM') { $tierColor = '#4338ca'; $tierBg = '#e0e7ff'; }
                                        elseif ($row['ltv_tier'] === 'GOLD') { $tierColor = '#b45309'; $tierBg = '#fef3c7'; }
                                        elseif ($row['ltv_tier'] === 'SILVER') { $tierColor = '#0f766e'; $tierBg = '#ccfbf1'; }
                                    ?>
                                    <tr onclick="window.location.href='customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>'">
                                        <td style="font-weight: 600; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['customer_type'] ?: 'Direct'); ?></span></td>
                                        <td><span style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['sales_rep'] ?: '—'); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['first_invoice']); ?></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['last_invoice']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo $row['tenure_years']; ?>y</td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['total_invoices']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['lifetime_base'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['lifetime_vat'], 0); ?></td>
                                        <td class="text-right dense-num-bold dense-num" style="color: var(--text-main);"><?php echo number_format($row['lifetime_gross'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['avg_order_value'], 0); ?></td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $tierBg; ?>; color: <?php echo $tierColor; ?>; font-weight: 800;">
                                                <?php echo $row['ltv_tier']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;">Dossier</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=ltv&tier=" . urlencode($tier ?? '') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&search=" . urlencode($search ?? '') . "&sort=" . urlencode($sort ?? '');
                    echo renderPaginationRail($p, $ltvPages, $ltvTotal, $limit, $baseUrl, 'accounts');
                    ?>
                </div>

            <?php elseif ($type === 'churn'): ?>
                <!-- 2. Account Churn & Reactivation Pipeline -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">At-Risk Accounts</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($churnTotal); ?></span>
                        <span class="metric-pill-sub">Inactive > 90 Days</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Historical Revenue at Risk</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($churnSummary['at_risk_historical_spend'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Cumulative Client Value</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Dormant (> 365d)</span>
                        <span class="metric-pill-val" style="color: #64748b;"><?php echo number_format($churnSummary['dormant_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Reactivation Campaign Candidates</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Critical Churn (180-365d)</span>
                        <span class="metric-pill-val" style="color: #ea580c;"><?php echo number_format($churnSummary['critical_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Immediate Outreach Window</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Average Inactivity</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo round($churnSummary['avg_inactivity_days'] ?? 0); ?> Days</span>
                        <span class="metric-pill-sub">Mean Portfolio Idle Days</span>
                    </div>
                </div>

                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="churn">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Inactivity Severity:</span>
                        <select name="risk" class="cmd-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($risk ?? 'all') === 'all' ? 'selected' : ''; ?>>All Inactive (≥ 90 Days)</option>
                            <option value="WATCHLIST" <?php echo ($risk ?? '') === 'WATCHLIST' ? 'selected' : ''; ?>>Watchlist (90–180 Days)</option>
                            <option value="CRITICAL" <?php echo ($risk ?? '') === 'CRITICAL' ? 'selected' : ''; ?>>Critical Alert (180–365 Days)</option>
                            <option value="DORMANT" <?php echo ($risk ?? '') === 'DORMANT' ? 'selected' : ''; ?>>Dormant (> 1 Year)</option>
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
                </form>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th style="width: 80px;">Channel</th>
                                    <th style="width: 50px;">Rep</th>
                                    <th style="width: 90px;">Last Order Date</th>
                                    <th class="text-right" style="width: 80px;">Days Inactive</th>
                                    <th class="text-right" style="width: 60px;">Orders</th>
                                    <th class="text-right" style="width: 100px;">Base Spend</th>
                                    <th class="text-right" style="width: 90px;">VAT Paid</th>
                                    <th class="text-right" style="width: 110px;">Historical Gross</th>
                                    <th class="text-center" style="width: 90px;">Churn Risk</th>
                                    <th class="text-center" style="width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($churnData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No inactive accounts match the active filter criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($churnData as $row): 
                                        $riskColor = '#b91c1c';
                                        $riskBg = '#fee2e2';
                                        if ($row['churn_risk'] === 'CRITICAL') { $riskColor = '#c2410c'; $riskBg = '#ffedd5'; }
                                        elseif ($row['churn_risk'] === 'WATCHLIST') { $riskColor = '#b45309'; $riskBg = '#fef3c7'; }
                                        elseif ($row['churn_risk'] === 'DORMANT') { $riskColor = '#475569'; $riskBg = '#f1f5f9'; }
                                    ?>
                                    <tr onclick="window.location.href='customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>'">
                                        <td style="font-weight: 600; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['customer_type'] ?: 'Direct'); ?></span></td>
                                        <td><span style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['sales_rep'] ?: '—'); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['last_order_date']); ?></td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $riskColor; ?>;"><?php echo number_format($row['days_inactive']); ?>d</td>
                                        <td class="text-right dense-num"><?php echo number_format($row['historical_invoices']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['historical_base'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['historical_vat'], 0); ?></td>
                                        <td class="text-right dense-num-bold dense-num" style="color: var(--text-main);"><?php echo number_format($row['historical_gross'], 0); ?></td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $riskBg; ?>; color: <?php echo $riskColor; ?>; font-weight: 800;">
                                                <?php echo $row['churn_risk']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;">Engage</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=churn&risk=" . urlencode($risk ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $churnPages, $churnTotal, $limit, $baseUrl, 'accounts');
                    ?>
                </div>

            <?php elseif ($type === 'eol'): ?>
                <!-- 3. Hardware End-of-Life (EOL) & Refresh Forecast -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Serialized Units</span>
                        <span class="metric-pill-val"><?php echo number_format($eolSummary['total_tracked_assets'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware Fleet Inventory</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Expired Warranties (EOL)</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($eolSummary['total_expired'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Primary Hardware Refresh Pipeline</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Expiring ≤ 90 Days</span>
                        <span class="metric-pill-val" style="color: #6366f1;"><?php echo number_format($eolSummary['expiring_90d'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Warranty Extension Opportunities</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Expiring ≤ 30 Days</span>
                        <span class="metric-pill-val" style="color: #ea580c;"><?php echo number_format($eolSummary['expiring_30d'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Urgent Renewal Alerts</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Under Coverage</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo number_format($eolSummary['active_assets'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Healthy Hardware Base</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Serial Number</th>
                                    <th style="width: 140px;">Model SKU</th>
                                    <th>Product Description</th>
                                    <th style="width: 80px;">Brand</th>
                                    <th>Customer Organization</th>
                                    <th style="width: 90px;">Invoice #</th>
                                    <th style="width: 75px;">Start Date</th>
                                    <th style="width: 75px;">Expiry Date</th>
                                    <th class="text-right" style="width: 70px;">Warranty</th>
                                    <th class="text-right" style="width: 70px;">Remaining</th>
                                    <th class="text-center" style="width: 85px;">Fleet Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($eolData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No hardware assets found matching the filter criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($eolData as $row): 
                                        $statusColor = '#15803d';
                                        $statusBg = '#ecfdf5';
                                        if ($row['eol_status'] === 'EXPIRED') { $statusColor = '#b91c1c'; $statusBg = '#fee2e2'; }
                                        elseif ($row['eol_status'] === 'EXPIRING_30D') { $statusColor = '#c2410c'; $statusBg = '#ffedd5'; }
                                        elseif ($row['eol_status'] === 'EXPIRING_90D') { $statusColor = '#4338ca'; $statusBg = '#e0e7ff'; }
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 11px;">
                                                <?php echo htmlspecialchars($row['serial_number']); ?>
                                            </span>
                                        </td>
                                        <td style="font-family: monospace; font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['model_sku'] ?: '—'); ?></td>
                                        <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500;" title="<?php echo htmlspecialchars($row['product_name']); ?>">
                                            <?php echo htmlspecialchars($row['product_name']); ?>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #334155;"><?php echo htmlspecialchars($row['brand'] ?: 'Unassigned'); ?></span></td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-doc-num"><?php echo htmlspecialchars($row['invoice_number']); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['warranty_start_date'] ?: '—'); ?></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['warranty_expiry_date']); ?></td>
                                        <td class="text-right dense-num"><?php echo $row['warranty_months']; ?>m</td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $statusColor; ?>;"><?php echo $row['days_remaining']; ?>d</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; font-weight: 800;">
                                                <?php echo $row['eol_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=eol&status=" . urlencode($status ?? '') . "&brand=" . urlencode($brand ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $eolPages, $eolTotal, $limit, $baseUrl, 'units');
                    ?>
                </div>

            <?php elseif ($type === 'contracts'): ?>
                <!-- 4. Time-Based Expiring Contracts & Recurring Invoices -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Contracts In Scope</span>
                        <span class="metric-pill-val"><?php echo number_format($contractsSummary['total_contracts'] ?? 0); ?></span>
                        <span class="metric-pill-sub">In selected expiration window</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Renewal Pipeline</span>
                        <span class="metric-pill-val" style="color: #2563eb;"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['total_opportunity_value'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Total contract recurring base</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Post Due (Overdue)</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($contractsSummary['overdue_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['overdue_value'] ?? 0, 0); ?> (Grace Period)</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Due Soon (≤ 30 Days)</span>
                        <span class="metric-pill-val" style="color: #b45309;"><?php echo number_format($contractsSummary['due_30d_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['due_30d_value'] ?? 0, 0); ?> (Urgent Outreach)</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Due 31 to 90 Days</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo number_format($contractsSummary['due_90d_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['due_90d_value'] ?? 0, 0); ?> (Upcoming Pipeline)</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 95px;">Invoice #</th>
                                    <th>Customer Organization &amp; End Client</th>
                                    <th>Service Offering / Scope</th>
                                    <th style="width: 80px;">Category</th>
                                    <th style="width: 70px;">Term</th>
                                    <th style="width: 85px;">Period Start</th>
                                    <th style="width: 95px; background: #eff6ff; color: #1e40af;">Expiring Date ▾</th>
                                    <th class="text-center" style="width: 120px;">Days Remaining</th>
                                    <th class="text-right" style="width: 110px;">Opportunity (LKR)</th>
                                    <th class="text-center" style="width: 55px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contractsData)): ?>
                                    <tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-muted);">No expiring contracts or time-based invoices found matching criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($contractsData as $row): 
                                        $days = (int)($row['days_remaining'] ?? 0);
                                        $cColor = '#15803d'; $cBg = '#ecfdf5'; $daysLabel = "Due in {$days}d";
                                        if ($days < 0) {
                                            $cColor = '#b91c1c'; $cBg = '#fee2e2';
                                            $daysLabel = "Expired " . abs($days) . "d ago";
                                        } elseif ($days === 0) {
                                            $cColor = '#b45309'; $cBg = '#fef3c7';
                                            $daysLabel = "Due Today";
                                        } elseif ($days <= 30) {
                                            $cColor = '#b45309'; $cBg = '#fef3c7';
                                            $daysLabel = "Due in {$days}d";
                                        }

                                        $catLabel = $row['category_label'] ?? 'General';
                                        $catStyle = "background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;";
                                        if ($catLabel === 'Acronis') {
                                            $catStyle = "background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe;";
                                        } elseif ($catLabel === 'MA / SLA') {
                                            $catStyle = "background: #f5f3ff; color: #6d28d9; border: 1px solid #ede9fe;";
                                        } elseif ($catLabel === 'Hosting') {
                                            $catStyle = "background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;";
                                        } elseif ($catLabel === 'License') {
                                            $catStyle = "background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5;";
                                        }
                                    ?>
                                    <tr style="<?php echo $days < 0 ? 'background: #fffafa;' : ($days <= 30 ? 'background: #fffdf5;' : ''); ?>">
                                        <td>
                                            <span class="dense-doc-num" style="cursor: pointer; color: #2563eb; font-weight: 600;" onclick="openInvoiceDetails('<?php echo htmlspecialchars($row['invoice_number']); ?>')" title="Open invoice details">
                                                <?php echo htmlspecialchars($row['invoice_number']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                            <?php if (!empty($row['end_customer'])): ?>
                                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                    <i class="icon-arrow-right" style="font-size: 10px;"></i> End Client: <?php echo htmlspecialchars($row['end_customer']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #334155; font-size: 12px;">
                                            <?php echo htmlspecialchars($row['software_name']); ?>
                                        </td>
                                        <td>
                                            <span class="dense-badge" style="<?php echo $catStyle; ?> font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                                <?php echo htmlspecialchars($catLabel); ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo !empty($row['term_months']) ? $row['term_months'] . ' Mo' : '—'; ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo htmlspecialchars($row['period_start_date'] ?? '—'); ?>
                                        </td>
                                        <td style="background: #eff6ff; font-family: monospace; font-size: 11px; font-weight: 700; color: <?php echo $cColor; ?>;">
                                            <?php echo htmlspecialchars($row['period_end_date']); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $cBg; ?>; color: <?php echo $cColor; ?>; font-weight: 800; font-size: 11px;">
                                                <?php echo $daysLabel; ?>
                                            </span>
                                        </td>
                                        <td class="text-right dense-num-bold">
                                            <?php echo number_format($row['renewal_opportunity_value'] ?? 0, 0); ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="cmd-btn" style="padding: 2px 6px;" onclick="openInvoiceDetails('<?php echo htmlspecialchars($row['invoice_number']); ?>')" title="Inspect Invoice Line Items &amp; Serials">
                                                <i class="icon-file-text"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $contractsBaseUrl = "reports.php?type=contracts&range=" . urlencode($range ?? 'pm_90d') . "&category=" . urlencode($category ?? 'all') . "&status=" . urlencode($status ?? 'all') . "&sort=" . urlencode($sort ?? 'expiry_asc') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $contractsPages, $contractsTotal, $limit, $contractsBaseUrl, 'contracts');
                    ?>
                </div>

            <?php elseif ($type === 'rental_roi'): ?>
                <!-- 5. Rental Fleet Utilization & Commercial Yield -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Rental Deployments</span>
                        <span class="metric-pill-val"><?php echo number_format($rentalSummary['total_deployments'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo number_format($rentalSummary['unique_clients'] ?? 0); ?> Unique Clients</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Rental Revenue</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($rentalSummary['total_rental_volume'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Cumulative Billed Yield</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Monthly Run-Rate (MRR)</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($rentalSummary['active_mrr'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Active Base Run-Rate</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Deployments</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($rentalSummary['active_deployments'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Billed ≤ 35 Days</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Delinquent Rentals</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($rentalSummary['delinquent_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Asset Recovery Candidates</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 75px;">Date</th>
                                    <th style="width: 90px;">Invoice #</th>
                                    <th>Client / Lessee</th>
                                    <th>Equipment / Rental Description</th>
                                    <th style="width: 80px;">Brand</th>
                                    <th class="text-right" style="width: 45px;">Qty</th>
                                    <th class="text-right" style="width: 85px;">Monthly Base</th>
                                    <th class="text-right" style="width: 75px;">18% VAT</th>
                                    <th class="text-right" style="width: 95px;">Gross Amount</th>
                                    <th class="text-right" style="width: 75px;">Last Billed</th>
                                    <th class="text-center" style="width: 80px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rentalData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No rental records found matching criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rentalData as $row): 
                                        $rColor = '#15803d'; $rBg = '#ecfdf5';
                                        if ($row['rental_status'] === 'SUSPENDED') { $rColor = '#b91c1c'; $rBg = '#fee2e2'; }
                                        elseif ($row['rental_status'] === 'OVERDUE') { $rColor = '#b45309'; $rBg = '#fef3c7'; }
                                    ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                                        <td><span class="dense-doc-num"><?php echo htmlspecialchars($row['invoice_number']); ?></span></td>
                                        <td style="font-weight: 600;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['clean_product_name']); ?>">
                                            <?php echo htmlspecialchars($row['clean_product_name']); ?>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #334155;"><?php echo htmlspecialchars($row['brand_category']); ?></span></td>
                                        <td class="text-right dense-num"><?php echo $row['quantity']; ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['base_value'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['vat_component'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['total_amount'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $rColor; ?>;"><?php echo $row['days_since_billed']; ?>d ago</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $rBg; ?>; color: <?php echo $rColor; ?>; font-weight: 800;">
                                                <?php echo $row['rental_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $rentalBaseUrl = "reports.php?type=rental_roi&status=" . urlencode($status ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $rentalPages, $rentalTotal, $limit, $rentalBaseUrl, 'deployments');
                    ?>
                </div>

            <?php elseif ($type === 'brand_growth'): ?>
                <!-- 6. Brand & Category Performance (2009–2026) -->
                
                <?php if (($viewMode ?? 'brand') === 'category'): ?>
                <!-- Category Performance View -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Categories</span>
                        <span class="metric-pill-val" style="color: #059669;"><?php echo number_format($categoryPerfSummary['total_categories'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Commercial Verticals</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Category Portfolio Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($categoryPerfSummary['total_gross_portfolio'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Consolidated Extracted Revenue</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Units Dispatched</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($categoryPerfSummary['total_units_dispatched'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware, Services & Licenses</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Modern Era Sales (2023+)</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($categoryPerfSummary['modern_sales_volume'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Post-Crisis Expansion</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Market Penetration</span>
                        <span class="metric-pill-val"><?php echo number_format($categoryPerfSummary['total_client_reach'] ?? 0); ?> Accounts</span>
                        <span class="metric-pill-sub">Category-Customer Touchpoints</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Strategic Category</th>
                                    <th class="text-right" style="width: 70px;">Invoices</th>
                                    <th class="text-right" style="width: 80px;">Client Reach</th>
                                    <th class="text-right" style="width: 70px;">Units Sold</th>
                                    <th class="text-right" style="width: 105px;">Base Revenue</th>
                                    <th class="text-right" style="width: 90px;">18% VAT</th>
                                    <th class="text-right" style="width: 115px;">Lifetime Gross</th>
                                    <th class="text-right" style="width: 100px;">Pre-2023 Era</th>
                                    <th class="text-right" style="width: 100px;">Post-2023 Era</th>
                                    <th class="text-right" style="width: 75px;">Share %</th>
                                    <th class="text-center" style="width: 85px;">Trajectory</th>
                                    <th class="text-center" style="width: 45px;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categoryPerfData)): ?>
                                    <tr><td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">No category sales records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categoryPerfData as $row): 
                                        $isExpanding = $row['growth_trajectory'] === 'EXPANDING';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($row['category_color'] ?: '#059669'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 700; color: var(--text-main); font-size: 12px;"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                                <?php if (!empty($row['category_code'])): ?>
                                                    <span style="font-size: 9.5px; padding: 1px 4px; background: #f1f5f9; border-radius: 3px; color: #64748b; font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($row['category_code']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_invoices']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['client_reach']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_units_sold']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['lifetime_base_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['lifetime_vat_revenue'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['lifetime_gross_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['historical_pre_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: #15803d;"><?php echo number_format($row['modern_post_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: #059669;"><?php echo $row['revenue_share_pct']; ?>%</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $isExpanding ? '#ecfdf5' : '#fee2e2'; ?>; color: <?php echo $isExpanding ? '#15803d' : '#b91c1c'; ?>; font-weight: 800;">
                                                <?php echo $row['growth_trajectory']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="product_mapping.php?tab=products&status=ALL&category=<?php echo urlencode($row['category_name']); ?>" class="action-icon-btn" title="Inspect Products in this Category" target="_blank">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif (($viewMode ?? 'brand') === 'matrix'): ?>
                <!-- Brand x Category Matrix View -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Combinations</span>
                        <span class="metric-pill-val" style="color: #7c3aed;"><?php echo count($matrixData); ?></span>
                        <span class="metric-pill-sub">Brand &times; Category Intersections</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Portfolio Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format(array_sum(array_column($matrixData, 'gross_revenue')), 0); ?></span>
                        <span class="metric-pill-sub">All Intersections</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Units Sold</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format(array_sum(array_column($matrixData, 'units_sold'))); ?></span>
                        <span class="metric-pill-sub">Hardware, licenses & contracts</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Product Category</th>
                                    <th class="text-right" style="width: 100px;">Invoices</th>
                                    <th class="text-right" style="width: 100px;">Units Sold</th>
                                    <th class="text-right" style="width: 140px;">Gross Revenue</th>
                                    <th class="text-right" style="width: 90px;">Share %</th>
                                    <th class="text-center" style="width: 55px;">Audit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($matrixData)): ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No cross-portfolio records found.</td></tr>
                                <?php else: 
                                    $totalMatrixGross = array_sum(array_column($matrixData, 'gross_revenue'));
                                    foreach ($matrixData as $m): 
                                        $sharePct = $totalMatrixGross > 0 ? round(($m['gross_revenue'] / $totalMatrixGross) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($m['brand_color'] ?: '#2563eb'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($m['brand_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($m['category_color'] ?: '#059669'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($m['category_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num"><?php echo number_format($m['invoice_count']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($m['units_sold']); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo htmlspecialchars($currency) . number_format($m['gross_revenue'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: var(--primary);"><?php echo $sharePct; ?>%</td>
                                        <td class="text-center">
                                            <a href="product_mapping.php?tab=products&status=ALL&brand=<?php echo urlencode($m['brand_name']); ?>&category=<?php echo urlencode($m['category_name']); ?>" class="action-icon-btn" title="View Products in this Brand/Category" target="_blank">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php else: ?>
                <!-- Brand Performance View (Default) -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Brands</span>
                        <span class="metric-pill-val"><?php echo number_format($brandGrowthSummary['total_brands'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware & Software Makers</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Portfolio Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($brandGrowthSummary['total_gross_portfolio'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">All-Time 17-Year Volume</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Units Dispatched</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($brandGrowthSummary['total_units_dispatched'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware & Licenses</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Modern Era Sales (2023+)</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($brandGrowthSummary['modern_sales_volume'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Post-Crisis Expansion</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Market Penetration</span>
                        <span class="metric-pill-val"><?php echo number_format($brandGrowthSummary['total_client_reach'] ?? 0); ?> Accounts</span>
                        <span class="metric-pill-sub">Brand-Customer Touchpoints</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Brand / Strategic Product Line</th>
                                    <th class="text-right" style="width: 70px;">Invoices</th>
                                    <th class="text-right" style="width: 80px;">Client Reach</th>
                                    <th class="text-right" style="width: 70px;">Units Sold</th>
                                    <th class="text-right" style="width: 105px;">Base Revenue</th>
                                    <th class="text-right" style="width: 90px;">18% VAT</th>
                                    <th class="text-right" style="width: 115px;">Lifetime Gross</th>
                                    <th class="text-right" style="width: 100px;">Pre-2023 Era</th>
                                    <th class="text-right" style="width: 100px;">Post-2023 Era</th>
                                    <th class="text-right" style="width: 75px;">Share %</th>
                                    <th class="text-center" style="width: 85px;">Trajectory</th>
                                    <th class="text-center" style="width: 45px;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($brandGrowthData)): ?>
                                    <tr><td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">No brand sales records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($brandGrowthData as $row): 
                                        $isExpanding = $row['growth_trajectory'] === 'EXPANDING';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($row['brand_color'] ?: '#2563eb'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 700; color: var(--text-main); font-size: 12px;"><?php echo htmlspecialchars($row['brand_name']); ?></span>
                                                <?php if (!empty($row['brand_code'])): ?>
                                                    <span style="font-size: 9.5px; padding: 1px 4px; background: #f1f5f9; border-radius: 3px; color: #64748b; font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($row['brand_code']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_invoices']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['client_reach']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_units_sold']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['lifetime_base_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['lifetime_vat_revenue'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['lifetime_gross_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['historical_pre_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: #15803d;"><?php echo number_format($row['modern_post_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: var(--primary);"><?php echo $row['revenue_share_pct']; ?>%</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $isExpanding ? '#ecfdf5' : '#fee2e2'; ?>; color: <?php echo $isExpanding ? '#15803d' : '#b91c1c'; ?>; font-weight: 800;">
                                                <?php echo $row['growth_trajectory']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="product_mapping.php?tab=products&status=ALL&brand=<?php echo urlencode($row['brand_name']); ?>" class="action-icon-btn" title="Inspect Products for this Brand" target="_blank">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($type === 'dso_trends'): ?>
                <!-- 7. Working Capital & DSO Collection Velocity -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Accounts Billed</span>
                        <span class="metric-pill-val"><?php echo number_format($dsoSummary['total_accounts'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Year: <?php echo htmlspecialchars($year === 'all' ? 'All Time (2009–2026)' : $year); ?></span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Invoiced</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($dsoSummary['grand_gross_billed'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Total Working Capital Demand</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Realized Collections</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($dsoSummary['grand_collected'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Cash Converted to Bank</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Outstanding Receivables</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo htmlspecialchars($currency) . number_format($dsoSummary['grand_outstanding'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Working Capital Trapped</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Average DSO</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo $dsoSummary['grand_avg_dso'] ?? 0; ?> Days</span>
                        <span class="metric-pill-sub">Mean Days Sales Outstanding</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Client / Enterprise Account</th>
                                    <th style="width: 80px;">Channel</th>
                                    <th style="width: 50px;">Rep</th>
                                    <th class="text-right" style="width: 55px;">Invoices</th>
                                    <th class="text-right" style="width: 105px;">Gross Billed</th>
                                    <th class="text-right" style="width: 105px;">Cash Collected</th>
                                    <th class="text-right" style="width: 105px;">Outstanding</th>
                                    <th class="text-right" style="width: 75px;">Collection %</th>
                                    <th class="text-right" style="width: 75px;">Avg DSO</th>
                                    <th class="text-right" style="width: 75px;">Max Delay</th>
                                    <th class="text-center" style="width: 80px;">Liquidity Risk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dsoData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No accounts found for the active filter.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dsoData as $row): 
                                        $dsoColor = '#15803d'; $dsoBg = '#ecfdf5';
                                        if ($row['risk_badge'] === 'CRITICAL') { $dsoColor = '#b91c1c'; $dsoBg = '#fee2e2'; }
                                        elseif ($row['risk_badge'] === 'DELAYED') { $dsoColor = '#b45309'; $dsoBg = '#fef3c7'; }
                                        elseif ($row['risk_badge'] === 'NORMAL') { $dsoColor = '#4338ca'; $dsoBg = '#e0e7ff'; }
                                    ?>
                                    <tr onclick="window.location.href='customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>'">
                                        <td style="font-weight: 600;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['customer_type'] ?: 'Direct'); ?></span></td>
                                        <td><span style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['sales_rep'] ?: '—'); ?></span></td>
                                        <td class="text-right dense-num"><?php echo $row['invoice_count']; ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['gross_billed'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #15803d;"><?php echo number_format($row['collected_amount'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $row['outstanding_amount'] > 0 ? '#b91c1c' : '#64748b'; ?>;"><?php echo number_format($row['outstanding_amount'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo $row['collection_rate_pct']; ?>%</td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $dsoColor; ?>;"><?php echo $row['avg_dso_days']; ?>d</td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo $row['max_dso_days']; ?>d</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $dsoBg; ?>; color: <?php echo $dsoColor; ?>; font-weight: 800;">
                                                <?php echo $row['risk_badge']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($type === 'tax_audit'): ?>
                <!-- 8. Statutory Tax & IRD Audit Ledger -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Audited Invoices</span>
                        <span class="metric-pill-val"><?php echo number_format($taxTotal); ?></span>
                        <span class="metric-pill-sub">Total Statutory Filings</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Taxable Base</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($taxSummary['total_taxable_base'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Net Assessed Revenue</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Statutory 18% VAT Assessed</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo htmlspecialchars($currency) . number_format($taxSummary['total_vat_assessed'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Total IRD Tax Obligation</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Billed Total</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($taxSummary['grand_gross_total'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Includes Inclusive & Plus VAT</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Breakdown Integrity</span>
                        <span class="metric-pill-val" style="font-size: 13px; color: #15803d;">
                            +VAT: <?php echo htmlspecialchars($currency) . number_format($taxSummary['statutory_plus_vat'] ?? 0, 0); ?>
                        </span>
                        <span class="metric-pill-sub" style="color: #64748b;">
                            Inc Sales: <?php echo htmlspecialchars($currency) . number_format($taxSummary['inclusive_sales_volume'] ?? 0, 0); ?>
                        </span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 75px;">Date</th>
                                    <th style="width: 95px;">Invoice #</th>
                                    <th style="width: 80px;">Type</th>
                                    <th>Customer / Enterprise</th>
                                    <th style="width: 85px;">VAT Treatment</th>
                                    <th class="text-right" style="width: 105px;">Taxable Base</th>
                                    <th class="text-right" style="width: 95px;">18% VAT</th>
                                    <th class="text-right" style="width: 110px;">Gross Total</th>
                                    <th class="text-center" style="width: 75px;">Settled Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($taxData)): ?>
                                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No invoices found for the selected period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($taxData as $row): 
                                        $isPlus = $row['vat_treatment'] === 'PLUS_VAT';
                                    ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                                        <td><span class="dense-doc-num"><?php echo htmlspecialchars($row['invoice_number']); ?></span></td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['invoice_type']); ?></span></td>
                                        <td style="font-weight: 600; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                        </td>
                                        <td>
                                            <span class="dense-badge <?php echo ($isPlus || $row['vat_treatment'] === 'VAT_INCLUSIVE') ? 'dense-badge-plusvat' : 'dense-badge-exempt'; ?>">
                                                <?php echo ($isPlus || $row['vat_treatment'] === 'VAT_INCLUSIVE') ? '+VAT' : 'EXEMPT'; ?>
                                            </span>
                                        </td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['taxable_base'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: var(--primary);"><?php echo number_format($row['vat_18_component'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['gross_total'], 0); ?></td>
                                        <td class="text-center" style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo htmlspecialchars($row['paid_date'] ?: '—'); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=tax_audit&year=" . urlencode($year) . "&month=" . urlencode($month);
                    echo renderPaginationRail($p, $taxPages, $taxTotal, $limit, $baseUrl, 'records');
                    ?>
                </div>

            <?php elseif ($type === 'unpaid_invoices'): ?>
                <!-- Unpaid Invoices Compact KPI Ribbon (Matches Invoice List Density) -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Receivables Due</span>
                        <span class="metric-pill-val" style="color: #0f172a;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['grand_gross_due'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Active Receivables Balance</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Unpaid Invoices</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($unpaidSummary['total_unpaid_invoices'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Across <?php echo number_format($unpaidSummary['total_customers'] ?? 0); ?> Debtor Accounts</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Debtor Accounts</span>
                        <span class="metric-pill-val" style="color: #6366f1;"><?php echo number_format($unpaidSummary['total_customers'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Clients with Open Balance</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Critical Overdue (&gt; 90d)</span>
                        <span class="metric-pill-val" style="color: #dc2626;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['critical_amount'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub" style="color: #dc2626; font-weight: 600;"><?php echo number_format($unpaidSummary['critical_count'] ?? 0); ?> High-Risk Invoices</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Current Normal (&le; 30d)</span>
                        <span class="metric-pill-val" style="color: #16a34a;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['current_amount'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub" style="color: #16a34a;"><?php echo number_format($unpaidSummary['current_count'] ?? 0); ?> Standard Cycle Invoices</span>
                    </div>
                </div>

                <!-- Unpaid Invoices Header & Filter Card -->
                <div class="card" style="margin-bottom: 25px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-main);"><i class="icon-clock" style="color: #f59e0b;"></i> Unpaid Invoices Ledger (Sorted by Customer)</h2>
                            <span class="sb-badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 11px; padding: 2px 8px;">Post-2021 Receivables</span>
                        </div>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 0 0;">
                            Commercial receivables from 2022 onwards, grouped and sorted by customer account with risk aging tiers, individual invoice breakdowns, and line-item auditing.
                        </p>
                    </div>

                    <!-- Filter Controls Form -->
                    <form method="GET" action="reports.php" style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                        <input type="hidden" name="type" value="unpaid_invoices">

                        <!-- Search -->
                        <div style="flex: 1; min-width: 220px;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px; text-transform: uppercase;">Search Customer or Invoice #</label>
                            <input type="text" name="search" class="form-control" placeholder="Type customer name or invoice number..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="width: 100%; height: 38px; padding: 0 12px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1; box-sizing: border-box;">
                        </div>

                        <!-- Aging Bracket Filter -->
                        <div style="min-width: 160px;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px; text-transform: uppercase;">Aging Bracket</label>
                            <select name="aging_bracket" class="form-control" style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1; box-sizing: border-box;">
                                <option value="all" <?php echo ($agingBracket ?? 'all') === 'all' ? 'selected' : ''; ?>>All Aging Periods</option>
                                <option value="current" <?php echo ($agingBracket ?? '') === 'current' ? 'selected' : ''; ?>>Current (≤ 30 Days)</option>
                                <option value="30_60" <?php echo ($agingBracket ?? '') === '30_60' ? 'selected' : ''; ?>>31–60 Days</option>
                                <option value="60_90" <?php echo ($agingBracket ?? '') === '60_90' ? 'selected' : ''; ?>>61–90 Days</option>
                                <option value="over_90" <?php echo ($agingBracket ?? '') === 'over_90' ? 'selected' : ''; ?>>Critical (> 90 Days)</option>
                            </select>
                        </div>

                        <!-- Sort Options -->
                        <div style="min-width: 180px;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px; text-transform: uppercase;">Sort Customers By</label>
                            <select name="sort" class="form-control" style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1; box-sizing: border-box;">
                                <option value="customer_asc" <?php echo ($sort ?? 'customer_asc') === 'customer_asc' ? 'selected' : ''; ?>>Customer Name (A &rarr; Z)</option>
                                <option value="customer_desc" <?php echo ($sort ?? '') === 'customer_desc' ? 'selected' : ''; ?>>Customer Name (Z &rarr; A)</option>
                                <option value="balance_desc" <?php echo ($sort ?? '') === 'balance_desc' ? 'selected' : ''; ?>>Outstanding (Highest First)</option>
                                <option value="balance_asc" <?php echo ($sort ?? '') === 'balance_asc' ? 'selected' : ''; ?>>Outstanding (Lowest First)</option>
                                <option value="aging_desc" <?php echo ($sort ?? '') === 'aging_desc' ? 'selected' : ''; ?>>Max Overdue Days (Oldest First)</option>
                                <option value="count_desc" <?php echo ($sort ?? '') === 'count_desc' ? 'selected' : ''; ?>>Unpaid Invoices Count</option>
                            </select>
                        </div>

                        <!-- Buttons -->
                        <div style="display: flex; gap: 6px;">
                            <button type="submit" class="btn-view" style="height: 38px; padding: 0 16px; font-weight: 700; background: var(--primary); color: white; border: none; border-radius: 6px;">
                                <i class="icon-filter"></i> Apply
                            </button>
                            <?php if (!empty($search) || ($agingBracket ?? 'all') !== 'all' || ($sort ?? 'customer_asc') !== 'customer_asc'): ?>
                                <a href="reports.php?type=unpaid_invoices" class="btn-view" style="height: 38px; line-height: 36px; padding: 0 12px; background: #e2e8f0; color: #475569; border: none; border-radius: 6px; text-decoration: none;">
                                    Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Unpaid Customers List -->
                <div id="unpaidInvoicesContainer">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                            Showing <strong><?php echo number_format($unpaidTotal); ?></strong> debtor accounts 
                            (Page <?php echo $p; ?> of <?php echo $unpaidPages; ?>)
                            <?php if (!empty($search)): ?>
                                matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                        </span>
                        <span style="font-size: 13px; color: var(--text-muted);">
                            Active Receivables: <strong style="color: #0f172a;">LKR <?php echo number_format($unpaidSummary['grand_gross_due'] ?? 0, 2); ?></strong>
                        </span>
                    </div>

                    <?php if (empty($unpaidCustomers)): ?>
                        <div class="card" style="text-align: center; padding: 60px 20px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: #ecfdf5; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                <i class="icon-check-circle" style="font-size: 28px; color: #10b981;"></i>
                            </div>
                            <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: var(--text-main);">No unpaid invoices found</h3>
                            <p style="color: var(--text-muted); font-size: 13px; max-width: 500px; margin: 0 auto;">
                                All accounts for the selected filters are settled, or no matching records were found.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($unpaidCustomers as $cust): 
                            $badgeClass = $cust['badge_class'] ?? 'secondary';
                            $riskBadge = $cust['risk_badge'] ?? 'CURRENT';
                            $invoices = $cust['invoices'] ?? [];
                        ?>
                        <div class="card" style="margin-bottom: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.03); overflow: hidden; padding: 0;">
                            <!-- Customer Header Banner -->
                            <div style="padding: 16px 22px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">
                                        <a href="customer_report.php?name=<?php echo urlencode($cust['customer_name']); ?>" style="color: inherit; text-decoration: none;" title="View customer profile and complete billing history">
                                            <i class="icon-building-2" style="font-size: 15px; color: var(--primary); margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($cust['customer_name']); ?>
                                        </a>
                                    </h3>
                                    <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #e0e7ff; color: #4338ca;">
                                        <?php echo htmlspecialchars($cust['customer_type']); ?>
                                    </span>
                                    <?php if (!empty($cust['rep_name'])): ?>
                                        <span style="font-size: 11px; color: #64748b;">
                                            Rep: <strong><?php echo htmlspecialchars($cust['rep_name']); ?></strong>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <!-- Invoices Count Badge -->
                                    <span style="font-size: 12px; font-weight: 700; background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <?php echo $cust['unpaid_invoices_count']; ?> <?php echo $cust['unpaid_invoices_count'] === 1 ? 'Invoice' : 'Invoices'; ?>
                                    </span>

                                    <!-- Aging Risk Badge -->
                                    <span class="warranty-status-badge badge-<?php echo $badgeClass; ?>" style="font-size: 10px; padding: 3px 10px;">
                                        <?php echo htmlspecialchars($riskBadge); ?> • <?php echo $cust['max_aging_days']; ?>d Overdue
                                    </span>

                                    <!-- Outstanding Amount Badge -->
                                    <div style="background: #0f172a; color: #f8fafc; font-size: 13px; font-weight: 800; padding: 5px 12px; border-radius: 6px; font-family: monospace;">
                                        Due: LKR <?php echo number_format($cust['total_gross_due'], 2); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Embedded Invoices Table -->
                            <div style="overflow-x: auto;">
                                <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                    <thead style="background: #ffffff; border-bottom: 1px solid #e2e8f0;">
                                        <tr>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569; width: 110px;">Invoice #</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569; width: 95px;">Date</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569; width: 115px;">Aging / Status</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569; width: 100px;">PO Number</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569;">Billed Items Preview</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569;" class="text-right">Net Base (LKR)</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569;" class="text-right">18% VAT (LKR)</th>
                                            <th style="padding: 10px 14px; font-weight: 800; color: #0f172a;" class="text-right">Gross Total (LKR)</th>
                                            <th style="padding: 10px 14px; font-weight: 700; color: #475569;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $inv): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 8px 14px; font-family: monospace; font-weight: 800; color: var(--primary);">
                                                <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                            </td>
                                            <td style="padding: 8px 14px; color: #475569; white-space: nowrap;">
                                                <?php echo htmlspecialchars($inv['invoice_date']); ?>
                                            </td>
                                            <td style="padding: 8px 14px;">
                                                <span class="warranty-status-badge badge-<?php echo $inv['aging_badge']; ?>" style="font-size: 10px; padding: 2px 8px;">
                                                    <?php echo htmlspecialchars($inv['aging_label']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 8px 14px; color: #64748b; font-size: 11px;">
                                                <?php echo htmlspecialchars($inv['po_number'] ?: '—'); ?>
                                            </td>
                                            <td style="padding: 8px 14px; color: #334155; font-size: 11px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($inv['items_summary']); ?>">
                                                <span style="font-weight: 700; color: #64748b; margin-right: 4px;">(<?php echo $inv['line_count']; ?>)</span>
                                                <?php echo htmlspecialchars($inv['items_summary']); ?>
                                            </td>
                                            <td style="padding: 8px 14px; color: #475569;" class="text-right">
                                                <?php echo number_format((float)$inv['base_value'], 2); ?>
                                            </td>
                                            <td style="padding: 8px 14px; color: var(--primary); font-weight: 600;" class="text-right">
                                                <?php echo number_format((float)$inv['vat_component'], 2); ?>
                                            </td>
                                            <td style="padding: 8px 14px; font-weight: 800; color: #0f172a;" class="text-right">
                                                <?php echo number_format((float)$inv['total_amount'], 2); ?>
                                            </td>
                                            <td style="padding: 8px 14px; text-align: center;">
                                                <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($inv['invoice_number']); ?>')" style="padding: 4px 8px; font-size: 11px;">
                                                    <i class="icon-eye"></i> Audit
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Pagination (Concept B: Modern Floating Rail) -->
                        <?php 
                        $unpaidBaseUrl = "reports.php?type=unpaid_invoices&search=" . urlencode($search ?? '') . "&aging_bracket=" . urlencode($agingBracket ?? 'all') . "&sort=" . urlencode($sort ?? 'customer_asc');
                        echo renderPaginationRail($p, $unpaidPages, $unpaidTotal, $limit, $unpaidBaseUrl, 'debtor accounts');
                        ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($type === 'warranties'): ?>
                <!-- Warranty KPI Ribbon -->
                <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
                    <div class="metric-card">

                        <div class="metric-label">Tracked Serial Numbers</div>
                        <div class="metric-value"><?php echo number_format($warrantyKpis['total_serials'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Registered Serialized Units
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Active Warranty</div>
                        <div class="metric-value" style="color: #10b981;"><?php echo number_format($warrantyKpis['active_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Under Manufacturer Warranty
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expiring Soon (≤ 60 Days)</div>
                        <div class="metric-value" style="color: #f59e0b;"><?php echo number_format($warrantyKpis['expiring_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Immediate SLA / Renewal Target
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expired Warranties</div>
                        <div class="metric-value" style="color: #ef4444;"><?php echo number_format($warrantyKpis['expired_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Out of Warranty / Refresh Candidates
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Maintenance Agreements</div>
                        <div class="metric-value" style="color: #6366f1;"><?php echo number_format($warrantyKpis['maintenance_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            SLA & Maintenance Contracts
                        </div>
                    </div>
                </div>

                <!-- Warranty Lookup Hero & Interactive Search -->
                <div class="card" style="margin-bottom: 25px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-main);"><i class="icon-shield-check" style="color: #10b981;"></i> Serial Number &amp; Warranty Lifecycle Intelligence</h2>
                            <span class="sb-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 11px; padding: 2px 8px;">Live Lookup</span>
                        </div>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 0 0;">
                            Search by full or partial serial number to reveal all historical invoice distributions, active warranty countdowns, and customer maintenance contracts.
                        </p>
                    </div>

                    <!-- Search Input Box with Typeahead & Quick Chips -->
                    <form method="GET" action="reports.php" id="warrantySearchForm" onsubmit="return handleWarrantySubmit(event);" style="margin-bottom: 15px;">
                        <input type="hidden" name="type" value="warranties">
                        <input type="hidden" name="status" id="warrantyStatusField" value="<?php echo htmlspecialchars($warrantyStatus ?? 'all'); ?>">

                        <div style="position: relative; display: flex; gap: 10px; align-items: center;">
                            <div style="position: relative; flex: 1;">
                                <i class="icon-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 18px; color: #94a3b8;"></i>
                                <input type="text" 
                                       id="warrantySearchInput" 
                                       name="search" 
                                       class="form-control" 
                                       placeholder="Enter full or partial S/N (e.g. 2110RXRC, 20C0SKRC, 0W200116, SYN) or customer name..." 
                                       value="<?php echo htmlspecialchars($warrantySearch ?? ''); ?>" 
                                       autocomplete="off"
                                       style="width: 100%; height: 48px; padding-left: 48px; padding-right: 40px; font-size: 15px; font-weight: 600; border-radius: 8px; border: 2px solid #cbd5e1; transition: all 0.2s; box-sizing: border-box;">
                                <button type="button" id="warrantyClearBtn" onclick="clearWarrantySearch()" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; display: <?php echo !empty($warrantySearch) ? 'block' : 'none'; ?>;" title="Clear Search">×</button>
                            </div>
                            <button type="submit" class="btn-view" style="height: 48px; padding: 0 24px; font-size: 14px; font-weight: 700; background: var(--primary); color: white; border: none; border-radius: 8px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <i class="icon-search"></i> Search S/N
                            </button>
                        </div>
                    </form>

                    <!-- Quick Sample Chips & Status Filters -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                        <!-- Sample Chips -->
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Quick Examples:</span>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('2110RXRC')">2110RXRC <span style="opacity: 0.7; font-size: 10px;">(RS1221RP+)</span></button>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('20C0SKRCXAQ44')">20C0SKRCXAQ44 <span style="opacity: 0.7; font-size: 10px;">(Avian SLA)</span></button>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('0W200116')">0W200116 <span style="opacity: 0.7; font-size: 10px;">(Multi-Inv Switch)</span></button>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('0P32014')">0P32014 <span style="opacity: 0.7; font-size: 10px;">(BDCOM AP)</span></button>
                        </div>

                        <!-- Status Filter Tabs -->
                        <div style="display: flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 8px;">
                            <?php 
                            $currSt = $warrantyStatus ?? 'all';
                            $statuses = [
                                'all' => 'All Units',
                                'active' => 'Active',
                                'expiring_soon' => 'Expiring Soon',
                                'expired' => 'Expired',
                                'has_maintenance' => 'Has SLA / Contract'
                            ];
                            foreach ($statuses as $stKey => $stLabel):
                                $isActive = ($currSt === $stKey);
                            ?>
                            <button type="button" 
                                    class="st-tab-btn <?php echo $isActive ? 'active' : ''; ?>" 
                                    onclick="filterWarrantyStatus('<?php echo $stKey; ?>')">
                                <?php echo $stLabel; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Results Container (Populated SSR and Updated via AJAX) -->
                <div id="warrantyResultsContainer">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                            Showing <span id="warrantyCountSpan"><?php echo count($warrantyResults); ?></span> matching serialized equipment records
                            <?php if (!empty($warrantySearch)): ?>
                                for "<strong><?php echo htmlspecialchars($warrantySearch); ?></strong>"
                            <?php endif; ?>
                        </span>
                        <span id="warrantyLiveNotice" style="font-size: 12px; color: #10b981; font-weight: 600; display: none;">
                            <i class="icon-refresh-cw" style="animation: spin 1s linear infinite; display: inline-block;"></i> Searching live...
                        </span>
                    </div>

                    <?php if (empty($warrantyResults)): ?>
                        <div class="card" style="text-align: center; padding: 60px 20px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                <i class="icon-shield" style="font-size: 28px; color: #94a3b8;"></i>
                            </div>
                            <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: var(--text-main);">No matching serialized equipment found</h3>
                            <p style="color: var(--text-muted); font-size: 13px; max-width: 500px; margin: 0 auto 20px;">
                                No records matched your search query. Try searching with a partial serial number (e.g. the first 6–8 characters), or click one of the quick examples above.
                            </p>
                            <button type="button" class="btn-view" onclick="quickSearchSerial('2110RXRC')" style="padding: 8px 16px; margin: 0 auto;">
                                View Sample Serial: 2110RXRC
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($warrantyResults as $asset): 
                            $badgeClass = $asset['status_badge_class'] ?? 'secondary';
                            $statusLabel = $asset['status_label'] ?? 'Unknown';
                            $pct = $asset['progress_pct'] ?? 100;
                            $invoices = $asset['invoices'] ?? [];
                            $contracts = $asset['maintenance_contracts'] ?? [];
                        ?>
                        <div class="card warranty-asset-card" style="margin-bottom: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.03); overflow: hidden; padding: 0;">
                            <!-- Asset Header Bar -->
                            <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                                <div style="flex: 1; min-width: 280px;">
                                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                                        <span class="serial-tag" onclick="copyToClipboard('<?php echo htmlspecialchars($asset['serial_number']); ?>', this)" title="Click to copy serial number">
                                            <i class="icon-hash" style="font-size: 11px; opacity: 0.6;"></i>
                                            <strong><?php echo htmlspecialchars($asset['serial_number']); ?></strong>
                                            <i class="icon-copy" style="font-size: 11px; margin-left: 4px; opacity: 0.5;"></i>
                                        </span>
                                        <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0369a1; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($asset['brand'] ?: 'Hardware'); ?>
                                        </span>
                                        <?php if (!empty($asset['model_sku'])): ?>
                                        <span style="font-size: 11px; font-weight: 700; color: #64748b; font-family: monospace;">
                                            SKU: <?php echo htmlspecialchars($asset['model_sku']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['parent_serial_number'])): ?>
                                        <span style="font-size: 11px; color: #64748b;">
                                            Chassis S/N: <strong style="font-family: monospace;"><?php echo htmlspecialchars($asset['parent_serial_number']); ?></strong>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: var(--text-main);">
                                        <?php echo htmlspecialchars($asset['product_name']); ?>
                                    </h3>
                                    <div style="font-size: 13px; color: var(--text-muted);">
                                        Primary Account: 
                                        <a href="customer_report.php?name=<?php echo urlencode($asset['current_customer']); ?>" style="color: var(--primary); font-weight: 700; text-decoration: none;">
                                            <?php echo htmlspecialchars($asset['current_customer']); ?>
                                        </a>
                                        <?php if (!empty($asset['end_customer'])): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; background: #ecfdf5; color: #047857; font-weight: 700; font-size: 11.5px; padding: 2px 8px; border-radius: 4px; border: 1px solid #a7f3d0;">
                                                <i class="icon-briefcase" style="font-size: 10px;"></i> End Client: <?php echo htmlspecialchars($asset['end_customer']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['all_customers']) && $asset['all_customers'] !== $asset['current_customer']): ?>
                                            <span style="font-size: 11px; color: #64748b; margin-left: 6px;">(Also associated: <?php echo htmlspecialchars($asset['all_customers']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Status Badge & Lifecycle Gauge -->
                                <div style="min-width: 220px; text-align: right;">
                                    <div>
                                        <span class="warranty-status-badge badge-<?php echo $badgeClass; ?>">
                                            <span class="pulse-dot"></span>
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                        Warranty: <strong><?php echo $asset['warranty_months'] ? ($asset['warranty_months'] . ' Months') : 'Standard'; ?></strong>
                                        <?php if (!empty($asset['computed_expiry_date'])): ?>
                                            &bull; Expiry: <strong style="color: #334155;"><?php echo htmlspecialchars($asset['computed_expiry_date']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Lifecycle bar -->
                                    <div style="margin-top: 8px; width: 100%; max-width: 220px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-left: auto;" title="<?php echo $pct; ?>% of warranty lifecycle elapsed">
                                        <div style="width: <?php echo $pct; ?>%; height: 100%; background: <?php echo $badgeClass === 'success' ? '#10b981' : ($badgeClass === 'warning' ? '#f59e0b' : '#ef4444'); ?>; border-radius: 3px;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Body: Two Detailed Panels (Invoices + Maintenance Contracts) -->
                            <div style="padding: 20px 22px;">
                                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                    <!-- Invoices List Table -->
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <i class="icon-file-text" style="color: var(--primary); margin-right: 4px;"></i> Invoice Distribution (<?php echo count($invoices); ?> <?php echo count($invoices) === 1 ? 'Invoice' : 'Invoices'; ?>)
                                            </h4>
                                            <span style="font-size: 11px; color: var(--text-muted);">Where this serial number appears in billing records</span>
                                        </div>

                                        <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                            <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                    <tr>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Invoice #</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Date</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Billed Customer</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Line Item Description</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Total (LKR)</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Settlement</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($invoices)): ?>
                                                    <tr>
                                                        <td colspan="7" style="padding: 15px; text-align: center; color: var(--text-muted);">
                                                            No discrete sales invoice linked.
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($invoices as $inv): 
                                                        $isSettled = !empty($inv['paid_date']);
                                                    ?>
                                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                                        <td style="padding: 8px 12px; font-family: monospace; font-weight: 800; color: var(--primary);">
                                                            <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">
                                                            <?php echo htmlspecialchars($inv['invoice_date'] ?: '—'); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; font-weight: 600; color: #1e293b;">
                                                            <?php echo htmlspecialchars($inv['customer_name']); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; color: #334155; font-size: 11px; max-width: 320px; line-height: 1.4;">
                                                            <?php 
                                                            $desc = $inv['matching_line_desc'] ?: $asset['product_name'];
                                                            // Highlight serial
                                                            $snHighlight = htmlspecialchars($asset['serial_number']);
                                                            $safeDesc = htmlspecialchars($desc);
                                                            if (!empty($snHighlight)) {
                                                                $safeDesc = str_ireplace($snHighlight, '<mark style="background: #fef08a; padding: 1px 4px; border-radius: 3px; font-weight: 800;">' . $snHighlight . '</mark>', $safeDesc);
                                                            }
                                                            echo $safeDesc;
                                                            ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">
                                                            <?php echo number_format((float)($inv['total_invoice_amount'] ?? 0), 2); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; text-align: center;">
                                                            <?php if ($isSettled): ?>
                                                                <span class="dense-badge dense-badge-settled" title="Settled on <?php echo $inv['paid_date']; ?><?php echo !empty($inv['days_to_pay']) ? ' (' . $inv['days_to_pay'] . 'd)' : ''; ?>">
                                                                    Settled
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="dense-badge dense-badge-unpaid">
                                                                    Unpaid
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; text-align: center;">
                                                            <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($inv['invoice_number']); ?>')" style="padding: 4px 8px; font-size: 11px;">
                                                                <i class="icon-eye"></i> Audit
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Maintenance Contracts Section -->
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <i class="icon-shield-check" style="color: #6366f1; margin-right: 4px;"></i> Customer Maintenance Agreements & SLAs (<?php echo count($contracts); ?>)
                                            </h4>
                                            <span style="font-size: 11px; color: var(--text-muted);">Active and historical maintenance contracts for this account</span>
                                        </div>

                                        <?php if (empty($contracts)): ?>
                                            <div style="padding: 14px 18px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 10px;">
                                                <i class="icon-info" style="font-size: 16px; color: #d97706;"></i>
                                                <div>
                                                    <strong>No active Maintenance Agreement on file for this account.</strong> This equipment is an immediate candidate for post-warranty SLA or annual maintenance contract (MA) proposal.
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                                <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                        <tr>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Service / Agreement Title</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Edition Tier</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Coverage Period</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Contract Status</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Opportunity Value (LKR)</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Origin Invoice</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($contracts as $mc): ?>
                                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                                            <td style="padding: 8px 12px; font-weight: 700; color: #1e293b;">
                                                                <?php echo htmlspecialchars($mc['contract_title']); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; color: #64748b; font-size: 11px;">
                                                                <?php echo htmlspecialchars($mc['edition_tier']); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">
                                                                <?php echo htmlspecialchars($mc['period_start_date'] ?: '—'); ?> &rarr; <?php echo htmlspecialchars($mc['period_end_date'] ?: 'Ongoing'); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; text-align: center;">
                                                                <span class="warranty-status-badge badge-<?php echo $mc['badge_class'] ?? 'secondary'; ?>" style="font-size: 10px; padding: 2px 8px;">
                                                                    <?php echo htmlspecialchars($mc['status_label']); ?>
                                                                </span>
                                                            </td>
                                                            <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">
                                                                <?php echo number_format((float)($mc['contract_value'] ?? 0), 2); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; text-align: center;">
                                                                <?php if (!empty($mc['invoice_number'])): ?>
                                                                    <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($mc['invoice_number']); ?>')" style="padding: 4px 8px; font-size: 11px;">
                                                                        <i class="icon-eye"></i> <?php echo htmlspecialchars($mc['invoice_number']); ?>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span style="color: var(--text-muted);">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <style>
                    .serial-tag {
                        display: inline-flex;
                        align-items: center;
                        background: #0f172a;
                        color: #f8fafc;
                        font-family: monospace;
                        font-size: 13px;
                        padding: 4px 10px;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.2s;
                        user-select: all;
                    }
                    .serial-tag:hover {
                        background: #1e293b;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
                    }
                    .btn-chip {
                        background: #ffffff;
                        border: 1px solid #cbd5e1;
                        border-radius: 6px;
                        padding: 3px 10px;
                        font-size: 12px;
                        font-family: monospace;
                        font-weight: 700;
                        color: #334155;
                        cursor: pointer;
                        transition: all 0.15s;
                    }
                    .btn-chip:hover {
                        background: #f1f5f9;
                        border-color: #94a3b8;
                        color: var(--primary);
                    }
                    .st-tab-btn {
                        background: transparent;
                        border: none;
                        padding: 5px 12px;
                        font-size: 12px;
                        font-weight: 700;
                        color: #64748b;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.15s;
                    }
                    .st-tab-btn.active {
                        background: #ffffff;
                        color: var(--primary);
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    }
                    .warranty-status-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 11px;
                        font-weight: 800;
                        letter-spacing: 0.3px;
                        text-transform: uppercase;
                    }
                    .warranty-status-badge.badge-success {
                        background: #ecfdf5;
                        color: #065f46;
                        border: 1px solid #a7f3d0;
                    }
                    .warranty-status-badge.badge-warning {
                        background: #fffbeb;
                        color: #92400e;
                        border: 1px solid #fde68a;
                    }
                    .warranty-status-badge.badge-danger {
                        background: #fef2f2;
                        color: #991b1b;
                        border: 1px solid #fecaca;
                    }
                    .warranty-status-badge.badge-secondary {
                        background: #f1f5f9;
                        color: #475569;
                        border: 1px solid #e2e8f0;
                    }
                    .pulse-dot {
                        width: 7px;
                        height: 7px;
                        border-radius: 50%;
                        background: currentColor;
                        display: inline-block;
                    }
                    .badge-success .pulse-dot {
                        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.4);
                        animation: pulseDot 2s infinite;
                    }
                    @keyframes pulseDot {
                        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                        70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
                        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                    }
                </style>

                <script>
                    let warrantyDebounceTimer = null;

                    function handleWarrantySubmit(e) {
                        e.preventDefault();
                        const q = document.getElementById('warrantySearchInput').value.trim();
                        const st = document.getElementById('warrantyStatusField').value;
                        window.location.href = 'reports.php?type=warranties&search=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(st);
                        return false;
                    }

                    function quickSearchSerial(sn) {
                        const input = document.getElementById('warrantySearchInput');
                        input.value = sn;
                        document.getElementById('warrantyClearBtn').style.display = 'block';
                        performWarrantyLookup(sn, document.getElementById('warrantyStatusField').value);
                    }

                    function clearWarrantySearch() {
                        const input = document.getElementById('warrantySearchInput');
                        input.value = '';
                        document.getElementById('warrantyClearBtn').style.display = 'none';
                        performWarrantyLookup('', document.getElementById('warrantyStatusField').value);
                    }

                    function filterWarrantyStatus(st) {
                        document.getElementById('warrantyStatusField').value = st;
                        document.querySelectorAll('.st-tab-btn').forEach(btn => btn.classList.remove('active'));
                        event.target.classList.add('active');
                        const q = document.getElementById('warrantySearchInput').value.trim();
                        performWarrantyLookup(q, st);
                    }

                    function copyToClipboard(text, el) {
                        navigator.clipboard.writeText(text).then(() => {
                            const orig = el.innerHTML;
                            el.innerHTML = '<i class="icon-check" style="color: #10b981;"></i> Copied!';
                            setTimeout(() => { el.innerHTML = orig; }, 1500);
                        });
                    }

                    // Live Typeahead with Debounce
                    document.getElementById('warrantySearchInput')?.addEventListener('input', function(e) {
                        const val = e.target.value;
                        document.getElementById('warrantyClearBtn').style.display = val ? 'block' : 'none';
                        clearTimeout(warrantyDebounceTimer);
                        warrantyDebounceTimer = setTimeout(() => {
                            performWarrantyLookup(val, document.getElementById('warrantyStatusField').value);
                        }, 280);
                    });

                    function performWarrantyLookup(query, status) {
                        const notice = document.getElementById('warrantyLiveNotice');
                        if (notice) notice.style.display = 'inline-block';

                        // Update browser URL without reloading
                        const newUrl = 'reports.php?type=warranties&search=' + encodeURIComponent(query) + '&status=' + encodeURIComponent(status);
                        window.history.replaceState({path: newUrl}, '', newUrl);

                        fetch(`reports.php?ajax_warranty_lookup=${encodeURIComponent(query)}&status=${encodeURIComponent(status)}&limit=50`)
                            .then(r => r.json())
                            .then(data => {
                                if (notice) notice.style.display = 'none';
                                renderWarrantyResults(data.results || [], query);
                            })
                            .catch(err => {
                                if (notice) notice.style.display = 'none';
                                console.error('Warranty lookup error:', err);
                            });
                    }

                    function renderWarrantyResults(results, query) {
                        const container = document.getElementById('warrantyResultsContainer');
                        const countSpan = document.getElementById('warrantyCountSpan');
                        if (countSpan) countSpan.innerText = results.length;

                        if (results.length === 0) {
                            container.innerHTML = `
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                                        Showing <strong>0</strong> matching serialized equipment records for "<strong>${escapeHtml(query)}</strong>"
                                    </span>
                                </div>
                                <div class="card" style="text-align: center; padding: 60px 20px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                                    <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                        <i class="icon-shield" style="font-size: 28px; color: #94a3b8;"></i>
                                    </div>
                                    <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: var(--text-main);">No matching serialized equipment found</h3>
                                    <p style="color: var(--text-muted); font-size: 13px; max-width: 500px; margin: 0 auto 20px;">
                                        No records matched your search query. Try searching with a partial serial number or click a quick example.
                                    </p>
                                </div>
                            `;
                            return;
                        }

                        let html = `
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                                    Showing <span id="warrantyCountSpan">${results.length}</span> matching serialized equipment records
                                    ${query ? `for "<strong>${escapeHtml(query)}</strong>"` : ''}
                                </span>
                            </div>
                        `;

                        results.forEach(asset => {
                            const badgeClass = asset.status_badge_class || 'secondary';
                            const statusLabel = asset.status_label || 'Unknown';
                            const pct = asset.progress_pct || 100;
                            const invoices = asset.invoices || [];
                            const contracts = asset.maintenance_contracts || [];
                            const barColor = badgeClass === 'success' ? '#10b981' : (badgeClass === 'warning' ? '#f59e0b' : '#ef4444');

                            let invoiceRows = '';
                            if (invoices.length === 0) {
                                invoiceRows = `<tr><td colspan="7" style="padding: 15px; text-align: center; color: var(--text-muted);">No discrete sales invoice linked.</td></tr>`;
                            } else {
                                invoices.forEach(inv => {
                                    const isSettled = !!inv.paid_date;
                                    let desc = inv.matching_line_desc || asset.product_name || '';
                                    let safeDesc = escapeHtml(desc);
                                    if (asset.serial_number) {
                                        const re = new RegExp(escapeRegExp(asset.serial_number), 'gi');
                                        safeDesc = safeDesc.replace(re, m => `<mark style="background: #fef08a; padding: 1px 4px; border-radius: 3px; font-weight: 800;">${m}</mark>`);
                                    }
                                    invoiceRows += `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 8px 12px; font-family: monospace; font-weight: 800; color: var(--primary);">${escapeHtml(inv.invoice_number)}</td>
                                            <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">${escapeHtml(inv.invoice_date || '—')}</td>
                                            <td style="padding: 8px 12px; font-weight: 600; color: #1e293b;">${escapeHtml(inv.customer_name || '—')}</td>
                                            <td style="padding: 8px 12px; color: #334155; font-size: 11px; max-width: 320px; line-height: 1.4;">${safeDesc}</td>
                                            <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">${formatCurrency(inv.total_invoice_amount || 0)}</td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                <span class="dense-badge ${isSettled ? 'dense-badge-settled' : 'dense-badge-unpaid'}">
                                                    ${isSettled ? 'Settled' : 'Unpaid'}
                                                </span>
                                            </td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                <button type="button" class="btn-view" onclick="openInvoiceDetails('${escapeHtml(inv.invoice_number)}')" style="padding: 4px 8px; font-size: 11px;">
                                                    <i class="icon-eye"></i> Audit
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                            }

                            let contractHtml = '';
                            if (contracts.length === 0) {
                                contractHtml = `
                                    <div style="padding: 14px 18px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 10px;">
                                        <i class="icon-info" style="font-size: 16px; color: #d97706;"></i>
                                        <div>
                                            <strong>No active Maintenance Agreement on file for this account.</strong> This equipment is an immediate candidate for post-warranty SLA or annual maintenance contract (MA) proposal.
                                        </div>
                                    </div>
                                `;
                            } else {
                                let cRows = '';
                                contracts.forEach(mc => {
                                    cRows += `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 8px 12px; font-weight: 700; color: #1e293b;">${escapeHtml(mc.contract_title || '')}</td>
                                            <td style="padding: 8px 12px; color: #64748b; font-size: 11px;">${escapeHtml(mc.edition_tier || '')}</td>
                                            <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">${escapeHtml(mc.period_start_date || '—')} &rarr; ${escapeHtml(mc.period_end_date || 'Ongoing')}</td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                <span class="warranty-status-badge badge-${mc.badge_class || 'secondary'}" style="font-size: 10px; padding: 2px 8px;">
                                                    ${escapeHtml(mc.status_label || '')}
                                                </span>
                                            </td>
                                            <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">${formatCurrency(mc.contract_value || 0)}</td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                ${mc.invoice_number ? `<button type="button" class="btn-view" onclick="openInvoiceDetails('${escapeHtml(mc.invoice_number)}')" style="padding: 4px 8px; font-size: 11px;"><i class="icon-eye"></i> ${escapeHtml(mc.invoice_number)}</button>` : '—'}
                                            </td>
                                        </tr>
                                    `;
                                });
                                contractHtml = `
                                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                        <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                <tr>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Service / Agreement Title</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Edition Tier</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Coverage Period</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Contract Status</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Opportunity Value (LKR)</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Origin Invoice</th>
                                                </tr>
                                            </thead>
                                            <tbody>${cRows}</tbody>
                                        </table>
                                    </div>
                                `;
                            }

                            html += `
                                <div class="card warranty-asset-card" style="margin-bottom: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.03); overflow: hidden; padding: 0;">
                                    <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                                        <div style="flex: 1; min-width: 280px;">
                                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                                                <span class="serial-tag" onclick="copyToClipboard('${escapeHtml(asset.serial_number)}', this)" title="Click to copy serial number">
                                                    <i class="icon-hash" style="font-size: 11px; opacity: 0.6;"></i>
                                                    <strong>${escapeHtml(asset.serial_number)}</strong>
                                                    <i class="icon-copy" style="font-size: 11px; margin-left: 4px; opacity: 0.5;"></i>
                                                </span>
                                                <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0369a1; text-transform: uppercase;">
                                                    ${escapeHtml(asset.brand || 'Hardware')}
                                                </span>
                                                ${asset.model_sku ? `<span style="font-size: 11px; font-weight: 700; color: #64748b; font-family: monospace;">SKU: ${escapeHtml(asset.model_sku)}</span>` : ''}
                                                ${asset.parent_serial_number ? `<span style="font-size: 11px; color: #64748b;">Chassis S/N: <strong style="font-family: monospace;">${escapeHtml(asset.parent_serial_number)}</strong></span>` : ''}
                                            </div>
                                            <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: var(--text-main);">
                                                ${escapeHtml(asset.product_name)}
                                            </h3>
                                            <div style="font-size: 13px; color: var(--text-muted);">
                                                Primary Account: 
                                                <a href="customer_report.php?name=${encodeURIComponent(asset.current_customer)}" style="color: var(--primary); font-weight: 700; text-decoration: none;">
                                                    ${escapeHtml(asset.current_customer || '—')}
                                                </a>
                                            </div>
                                        </div>
                                        <div style="min-width: 220px; text-align: right;">
                                            <div>
                                                <span class="warranty-status-badge badge-${badgeClass}">
                                                    <span class="pulse-dot"></span>
                                                    ${escapeHtml(statusLabel)}
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                                Warranty: <strong>${asset.warranty_months ? asset.warranty_months + ' Months' : 'Standard'}</strong>
                                                ${asset.computed_expiry_date ? `&bull; Expiry: <strong style="color: #334155;">${escapeHtml(asset.computed_expiry_date)}</strong>` : ''}
                                            </div>
                                            <div style="margin-top: 8px; width: 100%; max-width: 220px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-left: auto;">
                                                <div style="width: ${pct}%; height: 100%; background: ${barColor}; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding: 20px 22px;">
                                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                    <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        <i class="icon-file-text" style="color: var(--primary); margin-right: 4px;"></i> Invoice Distribution (${invoices.length} ${invoices.length === 1 ? 'Invoice' : 'Invoices'})
                                                    </h4>
                                                </div>
                                                <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                                    <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                                        <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                            <tr>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Invoice #</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Date</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Billed Customer</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Line Item Description</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Total (LKR)</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Settlement</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>${invoiceRows}</tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                    <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        <i class="icon-shield-check" style="color: #6366f1; margin-right: 4px;"></i> Customer Maintenance Agreements & SLAs (${contracts.length})
                                                    </h4>
                                                </div>
                                                ${contractHtml}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        container.innerHTML = html;
                    }

                    function escapeHtml(str) {
                        if (!str) return '';
                        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }

                    function escapeRegExp(string) {
                        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    }

                    function formatCurrency(val) {
                        const n = parseFloat(val) || 0;
                        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                </script>
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

                    <!-- Pagination (Concept B: Modern Floating Rail) -->
                    <?php 
                    $rbUrl = "reports.php?type=renewals&status=" . urlencode($renewalStatus ?? 'all') . "&search=" . urlencode($renewalSearch ?? '');
                    echo renderPaginationRail($p, $renewalPages, $renewalTotal, $limit, $rbUrl, 'subscriptions');
                    ?>
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
                    <a id="invModalCustomerReportLink" href="#" class="btn-view" style="background: #2563eb; color: #ffffff !important; border: 1px solid #2563eb; text-decoration: none; padding: 8px 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);"><i class="icon-building-2"></i> Customer Dossier</a>
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
            window.print();
        }

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
