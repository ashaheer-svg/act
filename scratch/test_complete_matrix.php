<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

function getMonthlySalesMatrix($db, $mode = 'rolling', $selectedYear = null) {
    $latestRow = $db->fetch("SELECT MAX(invoice_date) as max_date FROM sales WHERE invoice_date IS NOT NULL AND invoice_date != ''");
    $maxDate = $latestRow['max_date'] ?? date('Y-m-d');
    $latestYear = (int)date('Y', strtotime($maxDate));
    $latestYm = date('Y-m', strtotime($maxDate));
    $currentYm = date('Y-m');

    $availableYearsRows = $db->fetchAll("
        SELECT DISTINCT strftime('%Y', invoice_date) as yr 
        FROM sales 
        WHERE invoice_date IS NOT NULL AND invoice_date != '' 
        ORDER BY yr DESC
    ");
    $availableYears = array_values(array_filter(array_column($availableYearsRows, 'yr')));
    if (empty($availableYears)) {
        $availableYears = [(string)$latestYear];
    }

    if ($mode === 'calendar') {
        if (!$selectedYear || !in_array((string)$selectedYear, $availableYears)) {
            $selectedYear = (string)$latestYear;
        }
        $monthKeys = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthKeys[] = sprintf('%04d-%02d', (int)$selectedYear, $m);
        }
        $prevMonthKey = sprintf('%04d-12', (int)$selectedYear - 1);
        $periodTitle = "Calendar Year " . $selectedYear;
    } else {
        $mode = 'rolling';
        $selectedYear = (string)$latestYear;
        $monthKeys = [];
        $ts = strtotime($latestYm . '-01');
        for ($i = 11; $i >= 0; $i--) {
            $monthKeys[] = date('Y-m', strtotime("-$i month", $ts));
        }
        $prevMonthKey = date('Y-m', strtotime("-12 month", $ts));
        $firstLabel = date('M Y', strtotime($monthKeys[0] . '-01'));
        $lastLabel = date('M Y', strtotime($monthKeys[11] . '-01'));
        $periodTitle = "Rolling 12 Months ($firstLabel – $lastLabel)";
    }

    $allMonthsToFetch = array_merge([$prevMonthKey], $monthKeys);
    $placeholders = implode(',', array_fill(0, count($allMonthsToFetch), '?'));

    $sql = "
        SELECT 
            strftime('%Y-%m', invoice_date) as ym,
            COUNT(DISTINCT invoice_number) as invoice_count,
            COUNT(DISTINCT customer_name) as customer_count,
            SUM(quantity) as total_units,
            SUM(base_value) as net_base,
            SUM(vat_component) as vat_component,
            SUM(total_amount) as gross_sales,
            SUM(CASE WHEN (is_paid = 1 OR (paid_date IS NOT NULL AND paid_date != '')) THEN total_amount ELSE 0 END) as collected_amount,
            SUM(CASE WHEN (paid_date IS NULL OR paid_date = '') THEN total_amount ELSE 0 END) as outstanding_amount
        FROM sales
        WHERE strftime('%Y-%m', invoice_date) IN ($placeholders)
          AND invoice_type = 'Invoice'
        GROUP BY ym
    ";

    $rows = $db->fetchAll($sql, $allMonthsToFetch);
    $dataByYm = [];
    foreach ($rows as $r) {
        $dataByYm[$r['ym']] = $r;
    }

    // Unique customers across the displayed months
    $periodPlaceholders = implode(',', array_fill(0, count($monthKeys), '?'));
    $distinctCustRow = $db->fetch("
        SELECT COUNT(DISTINCT customer_name) as total_unique_customers
        FROM sales
        WHERE strftime('%Y-%m', invoice_date) IN ($periodPlaceholders)
          AND invoice_type = 'Invoice'
    ", $monthKeys);
    $totalUniqueCustomers = (int)($distinctCustRow['total_unique_customers'] ?? 0);

    // Build Month Columns metadata
    $months = [];
    $activeMonthsCount = 0;
    $prevGross = isset($dataByYm[$prevMonthKey]) ? (float)$dataByYm[$prevMonthKey]['gross_sales'] : 0;

    foreach ($monthKeys as $ym) {
        $dt = strtotime($ym . '-01');
        $isFuture = ($ym > $latestYm);
        $hasData = isset($dataByYm[$ym]) && ((float)$dataByYm[$ym]['gross_sales'] > 0 || (int)$dataByYm[$ym]['invoice_count'] > 0);
        if ($hasData) {
            $activeMonthsCount++;
        }

        $r = $dataByYm[$ym] ?? [];
        $gross = (float)($r['gross_sales'] ?? 0);
        $base = (float)($r['net_base'] ?? 0);
        $vat = (float)($r['vat_component'] ?? 0);
        $invCount = (int)($r['invoice_count'] ?? 0);
        $units = (float)($r['total_units'] ?? 0);
        $custCount = (int)($r['customer_count'] ?? 0);
        $collected = (float)($r['collected_amount'] ?? 0);
        $outstanding = (float)($r['outstanding_amount'] ?? 0);
        $aov = ($invCount > 0) ? ($gross / $invCount) : 0;
        $colRate = ($gross > 0) ? ($collected / $gross * 100) : 0;

        $mom = null;
        if (!$isFuture) {
            if ($prevGross > 0) {
                $mom = (($gross - $prevGross) / $prevGross) * 100;
            } elseif ($prevGross == 0 && $gross > 0) {
                $mom = 100.0;
            }
        }
        $prevGross = $gross;

        $months[$ym] = [
            'key' => $ym,
            'label' => date('M Y', $dt),
            'short_label' => date('M \'y', $dt),
            'month_name' => date('M', $dt),
            'year' => date('Y', $dt),
            'is_future' => $isFuture,
            'is_current' => ($ym === $currentYm),
            'has_data' => $hasData,
            'metrics' => [
                'gross_sales' => $gross,
                'net_base' => $base,
                'vat_component' => $vat,
                'invoice_count' => $invCount,
                'total_units' => $units,
                'customer_count' => $custCount,
                'aov' => $aov,
                'collected_amount' => $collected,
                'outstanding_amount' => $outstanding,
                'collection_rate' => $colRate,
                'mom_growth' => $mom
            ]
        ];
    }

    // Calculate Period Totals and Averages
    $totalGross = 0;
    $totalBase = 0;
    $totalVat = 0;
    $totalInvoices = 0;
    $totalUnits = 0;
    $totalCollected = 0;
    $totalOutstanding = 0;
    $momSum = 0;
    $momCount = 0;
    $monthlyCustSum = 0;

    foreach ($months as $m) {
        if ($m['has_data']) {
            $met = $m['metrics'];
            $totalGross += $met['gross_sales'];
            $totalBase += $met['net_base'];
            $totalVat += $met['vat_component'];
            $totalInvoices += $met['invoice_count'];
            $totalUnits += $met['total_units'];
            $totalCollected += $met['collected_amount'];
            $totalOutstanding += $met['outstanding_amount'];
            $monthlyCustSum += $met['customer_count'];
            if ($met['mom_growth'] !== null) {
                $momSum += $met['mom_growth'];
                $momCount++;
            }
        }
    }

    $div = max(1, $activeMonthsCount);
    $periodAov = ($totalInvoices > 0) ? ($totalGross / $totalInvoices) : 0;
    $periodColRate = ($totalGross > 0) ? ($totalCollected / $totalGross * 100) : 0;
    $avgMom = ($momCount > 0) ? ($momSum / $momCount) : 0;

    $totals = [
        'gross_sales' => $totalGross,
        'net_base' => $totalBase,
        'vat_component' => $totalVat,
        'invoice_count' => $totalInvoices,
        'total_units' => $totalUnits,
        'customer_count' => $totalUniqueCustomers,
        'aov' => $periodAov,
        'collected_amount' => $totalCollected,
        'outstanding_amount' => $totalOutstanding,
        'collection_rate' => $periodColRate,
        'mom_growth' => null
    ];

    $averages = [
        'gross_sales' => $totalGross / $div,
        'net_base' => $totalBase / $div,
        'vat_component' => $totalVat / $div,
        'invoice_count' => $totalInvoices / $div,
        'total_units' => $totalUnits / $div,
        'customer_count' => $monthlyCustSum / $div,
        'aov' => $periodAov,
        'collected_amount' => $totalCollected / $div,
        'outstanding_amount' => $totalOutstanding / $div,
        'collection_rate' => $periodColRate,
        'mom_growth' => $avgMom
    ];

    // Build Row Definitions
    $metricRows = [
        'gross_sales' => [
            'code' => 'gross_sales',
            'label' => 'Gross Billed Sales',
            'description' => 'Total invoiced revenue inclusive of statutory taxes',
            'icon' => 'icon-dollar-sign',
            'format' => 'currency',
            'highlight' => true
        ],
        'net_base' => [
            'code' => 'net_base',
            'label' => 'Net Base Sales',
            'description' => 'Pre-tax revenue recognizable by business',
            'icon' => 'icon-trending-up',
            'format' => 'currency',
            'highlight' => false
        ],
        'vat_component' => [
            'code' => 'vat_component',
            'label' => '18% VAT Component',
            'description' => 'Statutory Inland Revenue Department tax portion',
            'icon' => 'icon-percent',
            'format' => 'currency',
            'highlight' => false
        ],
        'invoice_count' => [
            'code' => 'invoice_count',
            'label' => 'Invoices Issued',
            'description' => 'Number of commercial transactions finalized',
            'icon' => 'icon-file-text',
            'format' => 'integer',
            'highlight' => false
        ],
        'total_units' => [
            'code' => 'total_units',
            'label' => 'Units Dispatched',
            'description' => 'Total physical devices, software packs & service units sold',
            'icon' => 'icon-package',
            'format' => 'integer',
            'highlight' => false
        ],
        'customer_count' => [
            'code' => 'customer_count',
            'label' => 'Active Customer Reach',
            'description' => 'Unique corporate accounts billed in period',
            'icon' => 'icon-users',
            'format' => 'integer',
            'highlight' => false
        ],
        'aov' => [
            'code' => 'aov',
            'label' => 'Average Order Value (AOV)',
            'description' => 'Yield per finalized invoice transaction',
            'icon' => 'icon-target',
            'format' => 'currency',
            'highlight' => false
        ],
        'collected_amount' => [
            'code' => 'collected_amount',
            'label' => 'Collected / Settled',
            'description' => 'Total cash realization received against billings',
            'icon' => 'icon-check-circle',
            'format' => 'currency',
            'highlight' => false
        ],
        'outstanding_amount' => [
            'code' => 'outstanding_amount',
            'label' => 'Outstanding Receivables',
            'description' => 'Debtor balances remaining unpaid in period',
            'icon' => 'icon-alert-circle',
            'format' => 'currency',
            'highlight' => false
        ],
        'collection_rate' => [
            'code' => 'collection_rate',
            'label' => 'Collection Realization %',
            'description' => 'Percentage of invoiced revenue successfully settled',
            'icon' => 'icon-pie-chart',
            'format' => 'percentage',
            'highlight' => false
        ],
        'mom_growth' => [
            'code' => 'mom_growth',
            'label' => 'MoM Revenue Growth',
            'description' => 'Month-over-month trajectory in gross billings',
            'icon' => 'icon-activity',
            'format' => 'growth_rate',
            'highlight' => true
        ]
    ];

    return [
        'mode' => $mode,
        'selected_year' => $selectedYear,
        'available_years' => $availableYears,
        'period_title' => $periodTitle,
        'active_months_count' => $activeMonthsCount,
        'months' => $months,
        'totals' => $totals,
        'averages' => $averages,
        'metric_rows' => $metricRows
    ];
}

$matrix = getMonthlySalesMatrix($db, 'rolling');
echo "Matrix Title: " . $matrix['period_title'] . "\n";
echo "Active months: " . $matrix['active_months_count'] . "\n";
echo "Totals Gross: " . number_format($matrix['totals']['gross_sales'], 2) . "\n";
echo "Average Monthly Gross: " . number_format($matrix['averages']['gross_sales'], 2) . "\n";
echo "Total Unique Clients: " . $matrix['totals']['customer_count'] . "\n";
echo "Collection Rate: " . number_format($matrix['totals']['collection_rate'], 1) . "%\n";

$matrixCal = getMonthlySalesMatrix($db, 'calendar', '2026');
echo "\n================ CALENDAR 2026 ================\n";
echo "Matrix Title: " . $matrixCal['period_title'] . "\n";
echo "Active months: " . $matrixCal['active_months_count'] . "\n";
echo "Totals Gross: " . number_format($matrixCal['totals']['gross_sales'], 2) . "\n";
echo "Average Monthly Gross: " . number_format($matrixCal['averages']['gross_sales'], 2) . "\n";
echo "Total Unique Clients: " . $matrixCal['totals']['customer_count'] . "\n";
echo "Collection Rate: " . number_format($matrixCal['totals']['collection_rate'], 1) . "%\n";


echo "\n--- ROW VALUES VERIFICATION ---\n";
foreach ($matrix['metric_rows'] as $k => $row) {
    echo str_pad($row['label'], 28);
    foreach ($matrix['months'] as $m) {
        $val = $m['metrics'][$k];
        if ($m['is_future']) {
            echo str_pad('-', 12, ' ', STR_PAD_LEFT);
        } elseif ($row['format'] === 'currency') {
            echo str_pad(number_format($val, 0), 12, ' ', STR_PAD_LEFT);
        } elseif ($row['format'] === 'integer') {
            echo str_pad(number_format($val, 0), 12, ' ', STR_PAD_LEFT);
        } elseif ($row['format'] === 'percentage') {
            echo str_pad(number_format($val, 1) . '%', 12, ' ', STR_PAD_LEFT);
        } elseif ($row['format'] === 'growth_rate') {
            echo str_pad($val !== null ? sprintf('%+.1f%%', $val) : 'N/A', 12, ' ', STR_PAD_LEFT);
        }
    }
    // Total
    $tot = $matrix['totals'][$k];
    if ($row['format'] === 'currency') {
        echo ' | ' . str_pad(number_format($tot, 0), 14, ' ', STR_PAD_LEFT);
    } elseif ($row['format'] === 'integer') {
        echo ' | ' . str_pad(number_format($tot, 0), 14, ' ', STR_PAD_LEFT);
    } elseif ($row['format'] === 'percentage') {
        echo ' | ' . str_pad(number_format($tot, 1) . '%', 14, ' ', STR_PAD_LEFT);
    } else {
        echo ' | ' . str_pad('-', 14, ' ', STR_PAD_LEFT);
    }
    // Avg
    $avg = $matrix['averages'][$k];
    if ($row['format'] === 'currency') {
        echo ' | ' . str_pad(number_format($avg, 0), 14, ' ', STR_PAD_LEFT);
    } elseif ($row['format'] === 'integer') {
        echo ' | ' . str_pad(number_format($avg, 0), 14, ' ', STR_PAD_LEFT);
    } elseif ($row['format'] === 'percentage') {
        echo ' | ' . str_pad(number_format($avg, 1) . '%', 14, ' ', STR_PAD_LEFT);
    } elseif ($row['format'] === 'growth_rate') {
        echo ' | ' . str_pad(sprintf('%+.1f%%', $avg), 14, ' ', STR_PAD_LEFT);
    }
    echo "\n";
}
