<?php
/**
 * Report Controller: Monthly Sales Performance Matrix
 * Active Solutions BI Platform (Overview, Customer, and Rep Views)
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$monthlyMode = $_GET['mode'] ?? 'rolling';
if (!in_array($monthlyMode, ['rolling', 'calendar'])) {
    $monthlyMode = 'rolling';
}
$selectedYear = $_GET['year'] ?? (!empty($availableYears) ? $availableYears[0] : date('Y'));

// Normalize view parameter based on route or query
if ($type === 'monthly_customer') {
    $monthlyView = 'customer';
} elseif ($type === 'monthly_rep') {
    $monthlyView = 'rep';
} else {
    $monthlyView = $_GET['view'] ?? 'overview';
}
if (!in_array($monthlyView, ['overview', 'customer', 'rep'])) {
    $monthlyView = 'overview';
}

$monthlyFilters = [
    'search' => $_GET['search'] ?? '',
    'brand' => $_GET['brand'] ?? '',
    'customer_type' => $_GET['customer_type'] ?? '',
    'rep_code' => $_GET['rep_code'] ?? ''
];

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportMode = $monthlyMode;
    $exportYear = $selectedYear;
    $exportView = $monthlyView;
    $exportFilters = $monthlyFilters;
    while (ob_get_level()) { ob_end_clean(); }

    if ($exportView === 'customer') {
        $matrix = $reports->getCustomerMonthlyMatrix($exportMode, $exportYear, $exportFilters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=customer_monthly_matrix_' . $exportMode . '_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');

        $headers = ['Customer Name', 'Channel', 'Sales Rep', 'Invoices', 'Volume (Units)'];
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
                $r['sales_rep'] ?: $r['sales_rep_code'],
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

        $sLine = ['PORTFOLIO TOTAL', '', '', $matrix['summary']['total_invoices'], $matrix['summary']['total_units']];
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

        $sLine = ['SALES TEAM TOTAL', '', $matrix['summary']['total_reach'], $matrix['summary']['total_invoices'], $matrix['summary']['total_units']];
        for ($i = 1; $i <= 12; $i++) {
            $sLine[] = round((float)($matrix['summary']['monthly_totals'][$i] ?? 0), 2);
        }
        $sLine[] = round((float)$matrix['summary']['total_revenue'], 2);
        $sLine[] = round((float)$matrix['summary']['monthly_average'], 2);
        fputcsv($out, $sLine, ',', '"', "\\");
        fclose($out);
        exit;
    } else {
        $matrix = $reports->getMonthlySalesMatrix($exportMode, $exportYear);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=monthly_sales_matrix_' . $exportMode . '_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
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
            $tot = $matrix['totals'][$k];
            if ($row['format'] === 'percentage') {
                $line[] = number_format($tot, 2) . '%';
            } elseif ($row['format'] === 'growth_rate') {
                $line[] = '-';
            } else {
                $line[] = round($tot, 2);
            }
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

// Data Preparation
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
