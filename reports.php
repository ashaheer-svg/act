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

// AJAX Handler for Customer Details
if (isset($_GET['ajax_customer_history'])) {
    $auth->requireReportAccess('customer_report');
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $name = $_GET['ajax_customer_history'];
    echo json_encode($reports->getCustomerHistory($name), JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// AJAX Handler for Invoice Details (Full Line Items, Serials, Payments)
if (isset($_GET['ajax_invoice_details'])) {
    $auth->requireReportAccess('invoices');
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
    $auth->requireReportAccess('warranties');
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
    } elseif ($type === 'monthly') {
        $exportMode = $_GET['mode'] ?? 'rolling';
        $exportYear = $_GET['year'] ?? date('Y');
        $exportView = $_GET['view'] ?? 'overview';

        $exportFilters = [
            'search' => $_GET['search'] ?? '',
            'brand' => $_GET['brand'] ?? '',
            'customer_type' => $_GET['customer_type'] ?? '',
            'rep_code' => $_GET['rep_code'] ?? ''
        ];

        if ($exportView === 'customer') {
            $matrix = $reports->getCustomerMonthlyMatrix($exportMode, $exportYear, $exportFilters);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=customer_monthly_sales_matrix_' . $exportMode . '_' . date('Ymd_His') . '.csv');
            $out = fopen('php://output', 'w');

            $headers = ['Customer Name', 'Customer Type', 'Top Category', 'Invoices', 'Volume (Units)'];
            foreach ($matrix['months'] as $m) {
                $headers[] = $m['label'];
            }
            $headers[] = 'Period Total Revenue';
            $headers[] = 'Monthly Average';
            fputcsv($out, $headers, ',', '"', "\\");

            foreach ($matrix['rows'] as $r) {
                $line = [
                    $r['customer_name'],
                    $r['customer_type'],
                    $r['top_brand'] ?? '-',
                    $r['total_invoices'],
                    $r['total_units']
                ];
                for ($i = 1; $i <= 12; $i++) {
                    $line[] = round((float)($r['m_' . $i] ?? 0), 2);
                }
                $line[] = round((float)$r['total_revenue'], 2);
                $line[] = round((float)$r['monthly_avg'], 2);
                fputcsv($out, $line, ',', '"', "\\");
            }

            // Summary Totals row
            $sLine = [
                'PORTFOLIO TOTAL',
                '',
                '',
                $matrix['summary']['total_invoices'],
                $matrix['summary']['total_units']
            ];
            for ($i = 1; $i <= 12; $i++) {
                $sLine[] = round((float)($matrix['summary']['monthly_totals'][$i] ?? 0), 2);
            }
            $sLine[] = round((float)$matrix['summary']['total_revenue'], 2);
            $sLine[] = round((float)$matrix['summary']['monthly_average'], 2);
            fputcsv($out, $sLine, ',', '"', "\\");

            fclose($out);
            exit;
        } elseif ($exportView === 'rep') {
            $matrix = $reports->getSalesRepMonthlyMatrix($exportMode, $exportYear, $exportFilters);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=sales_rep_monthly_matrix_' . $exportMode . '_' . date('Ymd_His') . '.csv');
            $out = fopen('php://output', 'w');

            $headers = ['Rep Code', 'Sales Rep Name', 'Client Reach', 'Invoices', 'Volume (Units)'];
            foreach ($matrix['months'] as $m) {
                $headers[] = $m['label'];
            }
            $headers[] = 'Period Total Revenue';
            $headers[] = 'Monthly Average';
            fputcsv($out, $headers, ',', '"', "\\");

            foreach ($matrix['rows'] as $r) {
                $line = [
                    $r['rep_code'],
                    $r['rep_name'],
                    $r['customer_reach'],
                    $r['total_invoices'],
                    $r['total_units']
                ];
                for ($i = 1; $i <= 12; $i++) {
                    $line[] = round((float)($r['m_' . $i] ?? 0), 2);
                }
                $line[] = round((float)$r['total_revenue'], 2);
                $line[] = round((float)$r['monthly_avg'], 2);
                fputcsv($out, $line, ',', '"', "\\");
            }

            // Summary Totals row
            $sLine = [
                'SALES TEAM TOTAL',
                '',
                $matrix['summary']['total_reach'],
                $matrix['summary']['total_invoices'],
                $matrix['summary']['total_units']
            ];
            for ($i = 1; $i <= 12; $i++) {
                $sLine[] = round((float)($matrix['summary']['monthly_totals'][$i] ?? 0), 2);
            }
            $sLine[] = round((float)$matrix['summary']['total_revenue'], 2);
            $sLine[] = round((float)$matrix['summary']['monthly_average'], 2);
            fputcsv($out, $sLine, ',', '"', "\\");

            fclose($out);
            exit;
        } else {
            // Overview Macro KPI Matrix
            $matrix = $reports->getMonthlySalesMatrix($exportMode, $exportYear);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=monthly_sales_matrix_' . $exportMode . '_' . date('Ymd_His') . '.csv');
            $out = fopen('php://output', 'w');
            
            // Header row: Financial Metric, each month, Period Total, Monthly Average
            $headers = ['Financial Metric'];
            foreach ($matrix['months'] as $m) {
                $headers[] = $m['label'];
            }
            $headers[] = 'Period Total';
            $headers[] = 'Monthly Average';
            fputcsv($out, $headers, ',', '"', "\\");
            
            foreach ($matrix['metric_rows'] as $k => $row) {
                $line = [$row['label']];
                foreach ($matrix['months'] as $m) {
                    if ($m['is_future']) {
                        $line[] = '-';
                    } else {
                        $val = $m['metrics'][$k];
                        if ($row['format'] === 'percentage') {
                            $line[] = number_format($val, 2) . '%';
                        } elseif ($row['format'] === 'growth_rate') {
                            $line[] = ($val !== null) ? sprintf('%+.2f%%', $val) : 'N/A';
                        } else {
                            $line[] = round($val, 2);
                        }
                    }
                }
                // Total
                $tot = $matrix['totals'][$k];
                if ($row['format'] === 'percentage') {
                    $line[] = number_format($tot, 2) . '%';
                } elseif ($row['format'] === 'growth_rate') {
                    $line[] = '-';
                } else {
                    $line[] = round($tot, 2);
                }
                // Average
                $avg = $matrix['averages'][$k];
                if ($row['format'] === 'percentage') {
                    $line[] = number_format($avg, 2) . '%';
                } elseif ($row['format'] === 'growth_rate') {
                    $line[] = sprintf('%+.2f%%', $avg);
                } else {
                    $line[] = round($avg, 2);
                }
                fputcsv($out, $line, ',', '"', "\\");
            }
            fclose($out);
            exit;
        }
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
            $taxResult = $reports->getTaxAuditReport($year, $month, $p, $limit);
            $taxData = $taxResult['rows'];
            $taxTotal = $taxResult['total'];
            $taxPages = $taxResult['pages'];
            $taxSummary = $taxResult['summary'];
            $reportTitle = 'Statutory Tax & IRD Audit Ledger (18% VAT)';
            break;
        case 'monthly':
            $monthlyMode = $_GET['mode'] ?? 'rolling';
            if (!in_array($monthlyMode, ['rolling', 'calendar'])) {
                $monthlyMode = 'rolling';
            }
            $selectedYear = $_GET['year'] ?? (!empty($availableYears) ? $availableYears[0] : date('Y'));
            $monthlyView = $_GET['view'] ?? 'overview';
            if (!in_array($monthlyView, ['overview', 'customer', 'rep'])) {
                $monthlyView = 'overview';
            }

            $monthlyFilters = [
                'search' => $_GET['search'] ?? '',
                'brand' => $_GET['brand'] ?? '',
                'customer_type' => $_GET['customer_type'] ?? '',
                'rep_code' => $_GET['rep_code'] ?? ''
            ];

            if ($monthlyView === 'customer') {
                $matrixData = $reports->getCustomerMonthlyMatrix($monthlyMode, $selectedYear, $monthlyFilters);
                $reportTitle = 'Customer Monthly Sales Matrix — ' . $matrixData['period_title'];
            } elseif ($monthlyView === 'rep') {
                $matrixData = $reports->getSalesRepMonthlyMatrix($monthlyMode, $selectedYear, $monthlyFilters);
                $reportTitle = 'Sales Rep Monthly Sales Matrix — ' . $matrixData['period_title'];
            } else {
                $matrixData = $reports->getMonthlySalesMatrix($monthlyMode, $selectedYear);
                $reportTitle = 'Monthly Sales Performance Matrix — ' . $matrixData['period_title'];
            }
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
            list($p, $limit, $isAll) = getReportPaginationParams(50);
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
            list($p, $limit, $isAll) = getReportPaginationParams(25);
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
            list($p, $limit, $isAll) = getReportPaginationParams(50);
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
                        $activeReportsList = ['monthly', 'invoices', 'unpaid_invoices', 'warranties', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit'];
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
            $activeTypes = ['monthly', 'invoices', 'unpaid_invoices', 'warranties', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit'];
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
            $reportViewFile = __DIR__ . "/views/reports/{$type}.php";
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
                editLink.href = 'invoice_edit.php?inv=' + encodeURIComponent(invoiceNumber);
                editLink.style.display = 'inline-flex';
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
                editLink.href = 'invoice_edit.php?inv=' + encodeURIComponent(invoiceNumber);
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
