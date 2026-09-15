<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

function testMonthlyMatrix($db, $mode = 'rolling', $selectedYear = null) {
    // 1. Determine months list
    $latestRow = $db->fetch("SELECT MAX(invoice_date) as max_date, MIN(invoice_date) as min_date FROM sales WHERE invoice_date IS NOT NULL AND invoice_date != ''");
    $maxDate = $latestRow['max_date'] ?? date('Y-m-d');
    $latestYear = date('Y', strtotime($maxDate));
    $currentYm = date('Y-m');

    if ($mode === 'calendar') {
        if (!$selectedYear) {
            $selectedYear = $latestYear;
        }
        $monthKeys = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthKeys[] = sprintf('%04d-%02d', $selectedYear, $m);
        }
        // Previous month for MoM growth of the first month (Dec of previous year)
        $prevMonthKey = sprintf('%04d-12', $selectedYear - 1);
    } else {
        // Rolling 12 months up to the latest month with data (or current month)
        $latestYm = date('Y-m', strtotime($maxDate));
        $monthKeys = [];
        $ts = strtotime($latestYm . '-01');
        for ($i = 11; $i >= 0; $i--) {
            $monthKeys[] = date('Y-m', strtotime("-$i month", $ts));
        }
        $prevMonthKey = date('Y-m', strtotime("-12 month", $ts));
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

    // Also get distinct customers across the entire period
    $periodPlaceholders = implode(',', array_fill(0, count($monthKeys), '?'));
    $distinctCustRow = $db->fetch("
        SELECT COUNT(DISTINCT customer_name) as total_unique_customers
        FROM sales
        WHERE strftime('%Y-%m', invoice_date) IN ($periodPlaceholders)
          AND invoice_type = 'Invoice'
    ", $monthKeys);
    $totalUniqueCustomers = (int)($distinctCustRow['total_unique_customers'] ?? 0);

    echo "Mode: $mode, Year: $selectedYear\n";
    echo "Months: " . implode(', ', $monthKeys) . "\n";
    echo "Total unique customers in period: $totalUniqueCustomers\n";

    $prevGross = isset($dataByYm[$prevMonthKey]) ? (float)$dataByYm[$prevMonthKey]['gross_sales'] : 0;
    foreach ($monthKeys as $ym) {
        $r = $dataByYm[$ym] ?? [];
        $gross = (float)($r['gross_sales'] ?? 0);
        $mom = ($prevGross > 0) ? (($gross - $prevGross) / $prevGross * 100) : null;
        $prevGross = $gross;
        echo sprintf("%s: Gross: %12.2f | MoM: %s\n", $ym, $gross, $mom !== null ? sprintf('%+6.1f%%', $mom) : '  N/A ');
    }
}

testMonthlyMatrix($db, 'rolling');
echo "\n----------------------------------------\n";
testMonthlyMatrix($db, 'calendar', '2026');
