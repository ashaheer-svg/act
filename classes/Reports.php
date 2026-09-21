<?php
/**
 * Reports Class - Business Analytics & Reporting
 *
 * Generates reports for sales analysis, metrics, trends, etc.
 */

class Reports {
    private $db;
    private $limitDate = null;

    public function __construct(Database $db, $userRole = 'admin') {
        $this->db = $db;
        
        // If not admin, set the visibility limit
        if ($userRole !== 'admin') {
            $ly = $this->db->getSetting('limit_year', date('Y'));
            $lm = $this->db->getSetting('limit_month', date('m'));
            $this->limitDate = "$ly-$lm-31"; // End of that month
        }
    }

    private function getLimitSql($column = 'invoice_date') {
        return $this->limitDate ? " AND $column <= '{$this->limitDate}' " : "";
    }

    /**
     * Get all unique transaction years present in the database
     */
    public function getAvailableYears() {
        try {
            $rows = $this->db->fetchAll("
                SELECT DISTINCT strftime('%Y', invoice_date) as yr 
                FROM sales 
                WHERE invoice_date IS NOT NULL AND invoice_date != ''
                ORDER BY yr DESC
            ");
            $years = array_filter(array_column($rows, 'yr'));
            if (!empty($years)) {
                return array_values($years);
            }
        } catch (Exception $e) {
            // Ignore error if table is empty
        }
        return ['2026', '2025', '2024', '2023', '2022', '2021'];
    }

    /**
     * Dashboard Summary - Key metrics
     */
    public function getDashboardSummary($dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type IN ('Invoice', 'Credit Memo')";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $summary = $this->db->fetch(
            "SELECT
                COUNT(DISTINCT CASE WHEN invoice_type = 'Invoice' THEN invoice_number END) as total_invoices,
                COUNT(DISTINCT customer_name) as unique_customers,
                SUM(base_value) as total_revenue_base,
                SUM(vat_component) as total_vat,
                SUM(total_amount) as total_amount,
                AVG(CASE WHEN invoice_type = 'Invoice' THEN total_amount END) as avg_invoice_value,
                MAX(CASE WHEN invoice_type = 'Invoice' THEN total_amount END) as largest_invoice,
                MIN(CASE WHEN invoice_type = 'Invoice' AND total_amount > 0 THEN total_amount END) as smallest_invoice,
                (SELECT SUM(amount) FROM payments) as total_payments_received
            FROM sales $where", $params
        );

        $grossInvoiced = (float)($this->db->fetch("SELECT SUM(total_amount) as s FROM sales WHERE invoice_type = 'Invoice'")['s'] ?? 0);
        $totalSettled = (float)($summary['total_payments_received'] ?? 0);
        $summary['total_outstanding'] = max(0, $grossInvoiced - $totalSettled);

        // Format numbers
        foreach ($summary as $key => $value) {
            if (is_numeric($value) && strpos($value, '.') !== false) {
                $summary[$key] = round($value, 2);
            }
        }

        return $summary;
    }

    /**
     * Monthly sales report (legacy/single period)
     */
    public function getMonthlySales($year = null, $month = null) {
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');

        $dateFrom = "$year-$month-01";
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        return [
            'period' => "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT),
            'data' => $this->getSalesByPeriod($dateFrom, $dateTo),
            'summary' => $this->getDashboardSummary($dateFrom, $dateTo)
        ];
    }

    /**
     * Executive Monthly Column-Wise Sales Matrix Report
     * Returns 12 months as columns with key financial metrics as rows,
     * plus Period Total and Monthly Average Benchmark columns.
     *
     * @param string $mode 'rolling' (last 12 months) or 'calendar' (Jan-Dec of $selectedYear)
     * @param string|int|null $selectedYear Selected year for calendar mode
     * @return array
     */
    public function getMonthlySalesMatrix($mode = 'rolling', $selectedYear = null) {
        $limitSql = $this->getLimitSql('invoice_date');
        $latestRow = $this->db->fetch("
            SELECT MAX(invoice_date) as max_date 
            FROM sales 
            WHERE invoice_date IS NOT NULL AND invoice_date != '' $limitSql
        ");
        $maxDate = $latestRow['max_date'] ?? date('Y-m-d');
        $latestYear = (int)date('Y', strtotime($maxDate));
        $latestYm = date('Y-m', strtotime($maxDate));
        $currentYm = date('Y-m');

        $availableYears = $this->getAvailableYears();
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
                COUNT(DISTINCT CASE WHEN invoice_type = 'Invoice' THEN invoice_number END) as invoice_count,
                COUNT(DISTINCT customer_name) as customer_count,
                SUM(quantity) as total_units,
                SUM(base_value) as net_base,
                SUM(vat_component) as vat_component,
                SUM(total_amount) as gross_sales,
                SUM(CASE WHEN (is_paid = 1 OR (paid_date IS NOT NULL AND paid_date != '')) THEN total_amount ELSE 0 END) as collected_amount,
                SUM(CASE WHEN (paid_date IS NULL OR paid_date = '') THEN total_amount ELSE 0 END) as outstanding_amount
            FROM sales
            WHERE strftime('%Y-%m', invoice_date) IN ($placeholders)
              AND invoice_type IN ('Invoice', 'Credit Memo')
              $limitSql
            GROUP BY ym
        ";

        $rows = $this->db->fetchAll($sql, $allMonthsToFetch);
        $dataByYm = [];
        foreach ($rows as $r) {
            $dataByYm[$r['ym']] = $r;
        }

        // Distinct customer reach across the displayed period
        $periodPlaceholders = implode(',', array_fill(0, count($monthKeys), '?'));
        $distinctCustRow = $this->db->fetch("
            SELECT COUNT(DISTINCT customer_name) as total_unique_customers
            FROM sales
            WHERE strftime('%Y-%m', invoice_date) IN ($periodPlaceholders)
              AND invoice_type IN ('Invoice', 'Credit Memo')
              $limitSql
        ", $monthKeys);
        $totalUniqueCustomers = (int)($distinctCustRow['total_unique_customers'] ?? 0);

        // Build monthly breakdown objects
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

        // Totals and Averages
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

        // Metric row definitions for display
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

    /**
     * Executive Customer Monthly Sales Performance Matrix
     * Generates a 12-month column-wise pivot per customer (Rolling 12M or Calendar Year).
     */
    public function getCustomerMonthlyMatrix($mode = 'rolling', $selectedYear = null, $filters = []) {
        $limitSql = $this->getLimitSql('s.invoice_date');
        $latestRow = $this->db->fetch("
            SELECT MAX(invoice_date) as max_date 
            FROM sales 
            WHERE invoice_date IS NOT NULL AND invoice_date != '' " . $this->getLimitSql('invoice_date') . "
        ");
        $maxDate = $latestRow['max_date'] ?? date('Y-m-d');
        $latestYear = (int)date('Y', strtotime($maxDate));
        $latestYm = date('Y-m', strtotime($maxDate));
        $currentYm = date('Y-m');

        $availableYears = $this->getAvailableYears();
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
            $periodTitle = "Calendar Year " . $selectedYear;
        } else {
            $mode = 'rolling';
            $selectedYear = (string)$latestYear;
            $monthKeys = [];
            $ts = strtotime($latestYm . '-01');
            for ($i = 11; $i >= 0; $i--) {
                $monthKeys[] = date('Y-m', strtotime("-$i month", $ts));
            }
            $firstLabel = date('M Y', strtotime($monthKeys[0] . '-01'));
            $lastLabel = date('M Y', strtotime($monthKeys[11] . '-01'));
            $periodTitle = "Rolling 12 Months ($firstLabel – $lastLabel)";
        }

        // Build month headers
        $months = [];
        $monthSqlParts = [];
        $idx = 0;
        foreach ($monthKeys as $ym) {
            $idx++;
            $dt = strtotime($ym . '-01');
            $months[] = [
                'key' => $ym,
                'label' => date('M Y', $dt),
                'short_label' => date("M 'y", $dt),
                'col_key' => 'm_' . $idx,
                'is_current' => ($ym === $currentYm),
                'is_future' => ($ym > $latestYm)
            ];
            $monthSqlParts[] = "SUM(CASE WHEN strftime('%Y-%m', s.invoice_date) = '$ym' THEN s.total_amount ELSE 0 END) as m_$idx";
        }
        $monthSelectSql = implode(",\n                    ", $monthSqlParts);

        // Build WHERE clauses and parameters
        $where = ["s.invoice_type IN ('Invoice', 'Credit Memo')"];
        $placeholders = implode(',', array_fill(0, count($monthKeys), '?'));
        $where[] = "strftime('%Y-%m', s.invoice_date) IN ($placeholders)";
        $params = $monthKeys;

        if (!empty($filters['search'])) {
            $where[] = "s.customer_name LIKE ?";
            $params[] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['customer_type'])) {
            $where[] = "p.customer_type = ?";
            $params[] = trim($filters['customer_type']);
        }

        if (!empty($filters['brand'])) {
            $where[] = "(s.product_category = ? OR s.product_category LIKE ?)";
            $params[] = trim($filters['brand']);
            $params[] = trim($filters['brand']) . ':%';
        }

        if (!empty($filters['rep_code'])) {
            $where[] = "s.sales_rep_code = ?";
            $params[] = trim($filters['rep_code']);
        }

        $whereSql = implode(" AND ", $where);

        $sql = "
            SELECT 
                s.customer_name,
                COALESCE(p.customer_type, 'End Customer') as customer_type,
                COUNT(DISTINCT CASE WHEN s.invoice_type = 'Invoice' THEN s.invoice_number END) as total_invoices,
                SUM(s.quantity) as total_units,
                SUM(s.total_amount) as total_revenue,
                SUM(s.base_value) as total_net_base,
                (SELECT 
                    CASE WHEN INSTR(s2.product_category, ':') > 0 THEN SUBSTR(s2.product_category, 1, INSTR(s2.product_category, ':') - 1) ELSE s2.product_category END 
                 FROM sales s2 WHERE s2.customer_name = s.customer_name GROUP BY 1 ORDER BY COUNT(*) DESC LIMIT 1) as top_brand,
                $monthSelectSql
            FROM sales s
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            WHERE $whereSql
              $limitSql
            GROUP BY s.customer_name
            ORDER BY total_revenue DESC
        ";

        $rows = $this->db->fetchAll($sql, $params);

        // Compute summary metrics and averages across rows
        $summary = [
            'total_customers' => count($rows),
            'total_invoices' => 0,
            'total_units' => 0,
            'total_revenue' => 0,
            'total_net_base' => 0,
            'monthly_totals' => array_fill(1, 12, 0.0),
            'monthly_average' => 0,
            'top_customer' => !empty($rows) ? $rows[0]['customer_name'] : '-',
            'top_customer_revenue' => !empty($rows) ? (float)$rows[0]['total_revenue'] : 0
        ];

        $activeMonths = 0;
        foreach ($months as $m) {
            if (!$m['is_future']) {
                $activeMonths++;
            }
        }
        $div = max(1, $activeMonths);

        foreach ($rows as &$row) {
            $summary['total_invoices'] += (int)$row['total_invoices'];
            $summary['total_units'] += (float)$row['total_units'];
            $summary['total_revenue'] += (float)$row['total_revenue'];
            $summary['total_net_base'] += (float)$row['total_net_base'];
            $row['monthly_avg'] = (float)$row['total_revenue'] / $div;

            for ($i = 1; $i <= 12; $i++) {
                $mVal = (float)($row['m_' . $i] ?? 0);
                $summary['monthly_totals'][$i] += $mVal;
            }
        }
        unset($row);

        $summary['monthly_average'] = $summary['total_revenue'] / $div;

        return [
            'mode' => $mode,
            'selected_year' => $selectedYear,
            'available_years' => $availableYears,
            'period_title' => $periodTitle,
            'months' => $months,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters
        ];
    }

    /**
     * Executive Sales Rep Monthly Sales Performance Matrix
     * Generates a 12-month column-wise pivot per sales rep (Rolling 12M or Calendar Year).
     */
    public function getSalesRepMonthlyMatrix($mode = 'rolling', $selectedYear = null, $filters = []) {
        $limitSql = $this->getLimitSql('s.invoice_date');
        $latestRow = $this->db->fetch("
            SELECT MAX(invoice_date) as max_date 
            FROM sales 
            WHERE invoice_date IS NOT NULL AND invoice_date != '' " . $this->getLimitSql('invoice_date') . "
        ");
        $maxDate = $latestRow['max_date'] ?? date('Y-m-d');
        $latestYear = (int)date('Y', strtotime($maxDate));
        $latestYm = date('Y-m', strtotime($maxDate));
        $currentYm = date('Y-m');

        $availableYears = $this->getAvailableYears();
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
            $periodTitle = "Calendar Year " . $selectedYear;
        } else {
            $mode = 'rolling';
            $selectedYear = (string)$latestYear;
            $monthKeys = [];
            $ts = strtotime($latestYm . '-01');
            for ($i = 11; $i >= 0; $i--) {
                $monthKeys[] = date('Y-m', strtotime("-$i month", $ts));
            }
            $firstLabel = date('M Y', strtotime($monthKeys[0] . '-01'));
            $lastLabel = date('M Y', strtotime($monthKeys[11] . '-01'));
            $periodTitle = "Rolling 12 Months ($firstLabel – $lastLabel)";
        }

        // Build month headers
        $months = [];
        $monthSqlParts = [];
        $idx = 0;
        foreach ($monthKeys as $ym) {
            $idx++;
            $dt = strtotime($ym . '-01');
            $months[] = [
                'key' => $ym,
                'label' => date('M Y', $dt),
                'short_label' => date("M 'y", $dt),
                'col_key' => 'm_' . $idx,
                'is_current' => ($ym === $currentYm),
                'is_future' => ($ym > $latestYm)
            ];
            $monthSqlParts[] = "SUM(CASE WHEN strftime('%Y-%m', s.invoice_date) = '$ym' THEN s.total_amount ELSE 0 END) as m_$idx";
        }
        $monthSelectSql = implode(",\n                    ", $monthSqlParts);

        // Build WHERE clauses and parameters
        $where = ["s.invoice_type IN ('Invoice', 'Credit Memo')"];
        $placeholders = implode(',', array_fill(0, count($monthKeys), '?'));
        $where[] = "strftime('%Y-%m', s.invoice_date) IN ($placeholders)";
        $params = $monthKeys;

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $where[] = "(s.sales_rep_code LIKE ? OR m.rep_name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filters['brand'])) {
            $where[] = "(s.product_category = ? OR s.product_category LIKE ?)";
            $params[] = trim($filters['brand']);
            $params[] = trim($filters['brand']) . ':%';
        }

        $whereSql = implode(" AND ", $where);

        $sql = "
            SELECT 
                COALESCE(NULLIF(s.sales_rep_code, ''), 'UNASSIGNED') as rep_code,
                COALESCE(m.rep_name, CASE WHEN s.sales_rep_code IS NOT NULL AND s.sales_rep_code != '' THEN 'Sales Rep ' || s.sales_rep_code ELSE 'Direct / Unassigned' END) as rep_name,
                COUNT(DISTINCT s.customer_name) as customer_reach,
                COUNT(DISTINCT CASE WHEN s.invoice_type = 'Invoice' THEN s.invoice_number END) as total_invoices,
                SUM(s.quantity) as total_units,
                SUM(s.total_amount) as total_revenue,
                SUM(s.base_value) as total_net_base,
                $monthSelectSql
            FROM sales s
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            WHERE $whereSql
              $limitSql
            GROUP BY rep_code
            ORDER BY total_revenue DESC
        ";

        $rows = $this->db->fetchAll($sql, $params);

        $summary = [
            'total_reps' => count($rows),
            'total_reach' => 0,
            'total_invoices' => 0,
            'total_units' => 0,
            'total_revenue' => 0,
            'total_net_base' => 0,
            'monthly_totals' => array_fill(1, 12, 0.0),
            'monthly_average' => 0,
            'top_rep' => !empty($rows) ? $rows[0]['rep_name'] : '-',
            'top_rep_revenue' => !empty($rows) ? (float)$rows[0]['total_revenue'] : 0
        ];

        $activeMonths = 0;
        foreach ($months as $m) {
            if (!$m['is_future']) {
                $activeMonths++;
            }
        }
        $div = max(1, $activeMonths);

        foreach ($rows as &$row) {
            $summary['total_invoices'] += (int)$row['total_invoices'];
            $summary['total_units'] += (float)$row['total_units'];
            $summary['total_revenue'] += (float)$row['total_revenue'];
            $summary['total_net_base'] += (float)$row['total_net_base'];
            $row['monthly_avg'] = (float)$row['total_revenue'] / $div;

            for ($i = 1; $i <= 12; $i++) {
                $mVal = (float)($row['m_' . $i] ?? 0);
                $summary['monthly_totals'][$i] += $mVal;
            }
        }
        unset($row);

        // Overall distinct customer reach across sales team
        $distinctReachRow = $this->db->fetch("
            SELECT COUNT(DISTINCT s.customer_name) as distinct_clients
            FROM sales s
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            WHERE $whereSql $limitSql
        ", $params);
        $summary['total_reach'] = (int)($distinctReachRow['distinct_clients'] ?? 0);
        $summary['monthly_average'] = $summary['total_revenue'] / $div;

        return [
            'mode' => $mode,
            'selected_year' => $selectedYear,
            'available_years' => $availableYears,
            'period_title' => $periodTitle,
            'months' => $months,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters
        ];
    }


    /**
     * Quarterly sales report
     */
    public function getQuarterlySales($year = null, $quarter = null) {
        if (!$year) $year = date('Y');
        if (!$quarter) $quarter = ceil(date('m') / 3);

        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        $dateFrom = "$year-" . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . "-01";
        $dateTo = date('Y-m-t', strtotime("$year-" . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . "-01"));

        return [
            'period' => "Q$quarter $year",
            'data' => $this->getSalesByPeriod($dateFrom, $dateTo),
            'summary' => $this->getDashboardSummary($dateFrom, $dateTo)
        ];
    }

    /**
     * Yearly sales report
     */
    public function getYearlySales($year = null) {
        if (!$year) $year = date('Y');

        $dateFrom = "$year-01-01";
        $dateTo = "$year-12-31";

        return [
            'period' => $year,
            'data' => $this->getSalesByPeriod($dateFrom, $dateTo),
            'summary' => $this->getDashboardSummary($dateFrom, $dateTo),
            'monthly_breakdown' => $this->getMonthlyBreakdown($year)
        ];
    }

    /**
     * Get Customer Yearly Matrix (Pivot)
     * Rows: Customers, Columns: Jan-Dec Sales
     */
    public function getCustomerYearlyPivot($year, $brand = null, $customerType = null, $repCode = null) {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStr = str_pad($m, 2, '0', STR_PAD_LEFT);
            $months[$m] = "SUM(CASE WHEN strftime('%m', invoice_date) = '$monthStr' THEN base_value ELSE 0 END) as month_$m";
        }
        
            $monthSql = implode(", ", $months);
            $params = [$year];
            $where = $this->getLimitSql('invoice_date');
            
            if ($brand) {
                $where .= " AND product_category = ? ";
                $params[] = $brand;
            }

            if ($customerType) {
                $where .= " AND p.customer_type = ? ";
                $params[] = $customerType;
            }

            if ($repCode) {
                $where .= " AND sales.sales_rep_code = ? ";
                $params[] = $repCode;
            }
            
            return $this->db->fetchAll("
                SELECT 
                    sales.customer_name,
                    p.customer_type,
                    COUNT(*) as total_volume,
                    SUM(base_value) as total_revenue,
                    SUM(gross_profit) as total_profit,
                    (SELECT 
                        CASE WHEN INSTR(s2.product_category, ':') > 0 THEN SUBSTR(s2.product_category, 1, INSTR(s2.product_category, ':') - 1) ELSE s2.product_category END 
                     FROM sales s2 WHERE s2.customer_name = sales.customer_name GROUP BY 1 ORDER BY COUNT(*) DESC LIMIT 1) as top_category,
                    $monthSql
                FROM sales
                LEFT JOIN customer_profiles p ON sales.customer_name = p.customer_name
                WHERE strftime('%Y', invoice_date) = ? AND invoice_type IN ('Invoice', 'Credit Memo')
                $where
                GROUP BY sales.customer_name
                ORDER BY total_revenue DESC
            ", $params);
    }

    /**
     * Get unique product categories (brands)
     */
    public function getUniqueBrands() {
        return $this->db->fetchAll("
            SELECT DISTINCT 
                CASE WHEN INSTR(product_category, ':') > 0 THEN SUBSTR(product_category, 1, INSTR(product_category, ':') - 1) ELSE product_category END as product_category 
            FROM sales 
            ORDER BY product_category ASC
        ");
    }

    /**
     * Get Brand/Category breakdown for each customer
     */
    public function getCustomerBrandBreakdown($year) {
        return $this->db->fetchAll("
            SELECT 
                customer_name,
                CASE WHEN INSTR(product_category, ':') > 0 THEN SUBSTR(product_category, 1, INSTR(product_category, ':') - 1) ELSE product_category END as product_category,
                SUM(total_amount) as category_revenue,
                COUNT(*) as purchase_count
            FROM sales
            WHERE strftime('%Y', invoice_date) = ? AND invoice_type IN ('Invoice', 'Credit Memo')
            GROUP BY customer_name, 2
            ORDER BY customer_name ASC, category_revenue DESC
        ", [$year]);
    }

    /**
     * Get sales by period
     */
    private function getSalesByPeriod($dateFrom, $dateTo) {
        return $this->db->fetchAll(
            "SELECT
                invoice_type,
                invoice_date,
                invoice_number,
                customer_name,
                item_description,
                tax_code,
                quantity,
                base_value,
                vat_component,
                total_amount
            FROM sales
            WHERE invoice_type IN ('Invoice', 'Credit Memo') 
              AND invoice_date BETWEEN ? AND ?
              AND TRIM(COALESCE(item_description, '')) != ''
              AND TRIM(COALESCE(item_description, '')) != 'Item'
              AND TRIM(COALESCE(item_description, '')) != 'Opening balance'
              AND (
                  total_amount != 0 
                  OR (
                      quantity > 0 
                      AND (
                          item_description LIKE '%S/N%' 
                          OR item_description LIKE '%SN:%' 
                          OR item_description LIKE '%Serial%' 
                          OR (product_category IS NOT NULL AND TRIM(product_category) != '' AND TRIM(product_category) != 'Uncategorized')
                      )
                  )
              )
            ORDER BY invoice_date DESC",
            [$dateFrom, $dateTo]
        );
    }

    /**
     * Monthly breakdown for yearly report
     */
    private function getMonthlyBreakdown($year) {
        $data = $this->db->fetchAll(
            "SELECT
                strftime('%m', invoice_date) as month,
                COUNT(DISTINCT CASE WHEN invoice_type = 'Invoice' THEN invoice_number END) as invoice_count,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total
            FROM sales
            WHERE invoice_type IN ('Invoice', 'Credit Memo') AND strftime('%Y', invoice_date) = ?
            GROUP BY strftime('%m', invoice_date)
            ORDER BY month ASC",
            [$year]
        );

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($data as &$row) {
            $row['month_name'] = $months[intval($row['month']) - 1];
        }

        return $data;
    }

    /**
     * Top customers report
     */
    public function getTopCustomers($limit = 10, $dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type IN ('Invoice', 'Credit Memo')";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $customers = $this->db->fetchAll(
            "SELECT
                customer_name,
                COUNT(DISTINCT CASE WHEN invoice_type = 'Invoice' THEN invoice_number END) as invoice_count,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total_revenue,
                AVG(CASE WHEN invoice_type = 'Invoice' THEN total_amount END) as avg_invoice,
                MAX(invoice_date) as last_purchase
            FROM sales $where
            GROUP BY customer_name
            ORDER BY total_revenue DESC
            LIMIT ?", array_merge($params, [$limit])
        );

        // Calculate percentage of total
        $totalRevenue = $this->db->fetch(
            "SELECT SUM(total_amount) as total FROM sales $where", $params
        );

        $total = ($totalRevenue['total'] > 0) ? $totalRevenue['total'] : 1;

        foreach ($customers as &$customer) {
            $customer['revenue_percentage'] = round(($customer['total_revenue'] / $total) * 100, 2);
        }

        return $customers;
    }

    /**
     * Top products report
     */
    public function getTopProducts($limit = 10, $dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type = 'Invoice' AND item_description != ''";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        return $this->db->fetchAll(
            "SELECT
                item_description,
                product_category,
                COUNT(*) as times_sold,
                SUM(quantity) as total_quantity,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_sale_price
            FROM sales $where
            GROUP BY item_description
            ORDER BY total_revenue DESC
            LIMIT ?", array_merge($params, [$limit])
        );
    }

    /**
     * Sales by product category
     */
    public function getSalesByCategory($dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type IN ('Invoice', 'Credit Memo')";
        $params = [];
 
        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }
 
        $categories = $this->db->fetchAll(
            "SELECT
                CASE WHEN INSTR(product_category, ':') > 0 THEN SUBSTR(product_category, 1, INSTR(product_category, ':') - 1) ELSE product_category END as category,
                COUNT(*) as transaction_count,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total_revenue,
                ROUND(SUM(total_amount) * 100.0 / NULLIF((SELECT SUM(total_amount) FROM sales $where), 0), 2) as percentage
            FROM sales $where
            GROUP BY 1
            ORDER BY total_revenue DESC", $params
        );
 
        return $categories;
    }

    /**
     * Customer concentration analysis
     */
    public function getCustomerConcentration($dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type IN ('Invoice', 'Credit Memo')";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $totalRevenue = $this->db->fetch(
            "SELECT SUM(total_amount) as total FROM sales $where", $params
        );

        $top3 = $this->db->fetchAll(
            "SELECT
                customer_name,
                SUM(total_amount) as revenue
            FROM sales $where
            GROUP BY customer_name
            ORDER BY revenue DESC
            LIMIT 3", $params
        );

        $top3_total = array_sum(array_column($top3, 'revenue'));
        $top3_percentage = ($totalRevenue['total'] > 0) ? round(($top3_total / $totalRevenue['total']) * 100, 2) : 0;

        return [
            'total_revenue' => $totalRevenue['total'],
            'top_3_revenue' => $top3_total,
            'top_3_percentage' => $top3_percentage,
            'risk_level' => $top3_percentage > 50 ? 'HIGH' : ($top3_percentage > 30 ? 'MEDIUM' : 'LOW'),
            'top_customers' => $top3
        ];
    }

    /**
     * VAT summary
     */
    public function getVATSummary($dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type IN ('Invoice', 'Credit Memo')";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $summary = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN tax_code = 'Taxable Sales' THEN base_value ELSE 0 END) as taxable_base,
                SUM(CASE WHEN tax_code = 'Taxable Sales' THEN vat_component ELSE 0 END) as taxable_vat,
                SUM(CASE WHEN tax_code = 'Taxable Sales' THEN total_amount ELSE 0 END) as taxable_total,
                SUM(CASE WHEN tax_code = 'Non-Taxable Sales' THEN base_value ELSE 0 END) as non_taxable_base,
                SUM(CASE WHEN tax_code = 'Non-Taxable Sales' THEN vat_component ELSE 0 END) as non_taxable_vat,
                SUM(CASE WHEN tax_code = 'Non-Taxable Sales' THEN total_amount ELSE 0 END) as non_taxable_total
            FROM sales $where", $params
        );

        return $summary;
    }

    /**
     * Daily sales trend
     */
    public function getDailySalesTrend($dateFrom = null, $dateTo = null) {
        $where = "WHERE invoice_type IN ('Invoice', 'Credit Memo')";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        return $this->db->fetchAll(
            "SELECT
                invoice_date as date,
                COUNT(DISTINCT CASE WHEN invoice_type = 'Invoice' THEN invoice_number END) as invoice_count,
                SUM(base_value) as revenue_base,
                SUM(total_amount) as total_revenue
            FROM sales $where
            GROUP BY invoice_date
            ORDER BY invoice_date ASC", $params
        );
    }

    /**
     * Get all unique customers
     */
    public function getAllCustomers() {
        return $this->db->fetchAll(
            "SELECT DISTINCT customer_name FROM sales ORDER BY customer_name ASC"
        );
    }

    /**
     * Get all product categories
     */
    public function getAllCategories() {
        return $this->db->fetchAll("
            SELECT DISTINCT 
                CASE 
                    WHEN INSTR(product_category, ':') > 0 THEN UPPER(TRIM(SUBSTR(product_category, 1, INSTR(product_category, ':') - 1)))
                    ELSE UPPER(TRIM(product_category))
                END as category_name
            FROM sales 
            WHERE product_category IS NOT NULL AND product_category != ''
            ORDER BY category_name ASC
        ");
    }

    /**
     * Get Collection Status (Sales vs Payments)
     */
    public function getCollectionStatus() {
        return $this->db->fetchAll("
            SELECT 
                s.customer_name,
                SUM(CASE WHEN s.invoice_type = 'Invoice' THEN s.total_amount ELSE 0 END) as total_invoiced,
                COALESCE(p.total_paid, 0) as total_paid,
                (SUM(CASE WHEN s.invoice_type = 'Invoice' THEN s.total_amount ELSE 0 END) - COALESCE(p.total_paid, 0)) as balance,
                AVG(s.days_to_pay) as avg_days_to_pay,
                COUNT(s.days_to_pay) as paid_invoices_count
            FROM sales s
            LEFT JOIN (
                SELECT customer_name, SUM(amount) as total_paid
                FROM payments
                GROUP BY customer_name
            ) p ON s.customer_name = p.customer_name
            GROUP BY s.customer_name
            HAVING total_invoiced > 0 OR total_paid > 0
            ORDER BY balance DESC, total_invoiced DESC
        ");
    }

    /**
     * Get Customer Credit Scores based on payment history
     */
    public function getCustomerCreditScore($customerName) {
        $today = date('Y-m-d');
        
        // Get base customer stats for one customer
        $row = $this->db->fetch("
            SELECT 
                customer_name,
                COUNT(*) as total_invoices,
                SUM(CASE WHEN paid_date IS NOT NULL THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN paid_date IS NULL THEN 1 ELSE 0 END) as unpaid_count,
                SUM(total_amount) as total_volume,
                SUM(CASE WHEN paid_date IS NULL THEN total_amount ELSE 0 END) as outstanding_amount
            FROM sales
            WHERE customer_name = ? AND invoice_type = 'Invoice'
            GROUP BY customer_name
        ", [$customerName]);

        if (!$row) return null;

        // Fetch all invoice delays for this customer
        $invoices = $this->db->fetchAll("
            SELECT 
                (CASE 
                    WHEN paid_date IS NOT NULL THEN days_to_pay 
                    ELSE CAST((julianday(?) - julianday(invoice_date)) AS INT)
                END) as effective_days
            FROM sales
            WHERE customer_name = ? AND invoice_type = 'Invoice'
        ", [$today, $customerName]);

        $totalDays = 0;
        $maxDays = 0;
        foreach ($invoices as $inv) {
            $days = max(0, $inv['effective_days']);
            $totalDays += $days;
            if ($days > $maxDays) $maxDays = $days;
        }

        $adp = count($invoices) > 0 ? $totalDays / count($invoices) : 0;
        $row['avg_days'] = $adp;
        $row['max_days'] = $maxDays;
        
        if ($adp <= 30) {
            $score = 100;
        } else if ($adp <= 60) {
            $score = 100 - (($adp - 30) * 1.5);
        } else if ($adp <= 90) {
            $score = 55 - (($adp - 60) * 1);
        } else {
            $score = max(0, 25 - (($adp - 90) * 0.5));
        }
        
        if ($maxDays > 120) $score *= 0.5;
        if ($row['unpaid_count'] > 10) $score -= 10;
        
        $row['credit_score'] = max(0, min(100, round($score)));
        $row['risk_level'] = $this->getRiskLevel($row['credit_score']);
        
        return $row;
    }

    public function getCustomerCreditScores() {
        $today = date('Y-m-d');
        
        // Get base customer stats
        $data = $this->db->fetchAll("
            SELECT 
                customer_name,
                COUNT(*) as total_invoices,
                SUM(CASE WHEN paid_date IS NOT NULL THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN paid_date IS NULL THEN 1 ELSE 0 END) as unpaid_count,
                SUM(total_amount) as total_volume,
                SUM(CASE WHEN paid_date IS NULL THEN total_amount ELSE 0 END) as outstanding_amount
            FROM sales
            WHERE invoice_type = 'Invoice'
            GROUP BY customer_name
            ORDER BY total_volume DESC
        ");

        foreach ($data as &$row) {
            // Fetch all invoice delays for this customer, calculating aging for unpaid ones
            $invoices = $this->db->fetchAll("
                SELECT 
                    (CASE 
                        WHEN paid_date IS NOT NULL THEN days_to_pay 
                        ELSE CAST((julianday(?) - julianday(invoice_date)) AS INT)
                    END) as effective_days
                FROM sales
                WHERE customer_name = ? AND invoice_type = 'Invoice'
            ", [$today, $row['customer_name']]);

            $totalDays = 0;
            $maxDays = 0;
            foreach ($invoices as $inv) {
                $days = max(0, $inv['effective_days']);
                $totalDays += $days;
                if ($days > $maxDays) $maxDays = $days;
            }

            $adp = count($invoices) > 0 ? $totalDays / count($invoices) : 0;
            $row['avg_days'] = $adp;
            $row['max_days'] = $maxDays;
            
            // Scoring Logic:
            // 30 days or less = 100 (Perfect)
            // Penalize based on ADP
            if ($adp <= 30) {
                $score = 100;
            } else if ($adp <= 60) {
                $score = 100 - (($adp - 30) * 1.5);
            } else if ($adp <= 90) {
                $score = 55 - (($adp - 60) * 1);
            } else {
                $score = max(0, 25 - (($adp - 90) * 0.5));
            }
            
            // Extra penalties for dangerous behaviors
            if ($maxDays > 120) $score *= 0.5; // Significant penalty for very old debt
            if ($row['unpaid_count'] > 10) $score -= 10;
            
            $row['credit_score'] = max(0, min(100, round($score)));
            $row['risk_level'] = $this->getRiskLevel($row['credit_score']);
        }
        
        return $data;
    }

    private function getRiskLevel($score) {
        if ($score >= 85) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 50) return 'Fair';
        if ($score >= 30) return 'At Risk';
        return 'Critical';
    }

    public function getCustomerSummary($customerName) {
        $today = date('Y-m-d');
        return $this->db->fetch("
            SELECT 
                customer_name,
                COUNT(DISTINCT invoice_number) as total_invoices,
                SUM(total_amount) as total_volume,
                COUNT(DISTINCT CASE WHEN paid_date IS NOT NULL THEN invoice_number ELSE NULL END) as paid_count,
                COUNT(DISTINCT CASE WHEN paid_date IS NULL AND total_amount != 0 THEN invoice_number ELSE NULL END) as unpaid_count,
                SUM(CASE WHEN paid_date IS NULL THEN total_amount ELSE 0 END) as outstanding_amount,
                AVG(CASE WHEN paid_date IS NOT NULL THEN days_to_pay ELSE (julianday(?) - julianday(invoice_date)) END) as avg_days
            FROM sales
            WHERE customer_name = ? AND invoice_type = 'Invoice'
        ", [$today, $customerName]);
    }

    public function getCustomerMonthlyTrend($customerName) {
        return $this->db->fetchAll("
            SELECT 
                strftime('%Y-%m', invoice_date) as month,
                SUM(total_amount) as total
            FROM sales
            WHERE customer_name = ? AND invoice_type = 'Invoice'
            GROUP BY month
            ORDER BY month DESC
            LIMIT 24
        ", [$customerName]);
    }

    public function getCustomerTopProducts($customerName) {
        return $this->db->fetchAll("
            SELECT 
                CASE 
                    WHEN INSTR(product_category, ':') > 0 THEN UPPER(TRIM(SUBSTR(product_category, 1, INSTR(product_category, ':') - 1)))
                    ELSE UPPER(TRIM(product_category))
                END as main_category,
                item_description,
                COUNT(*) as frequency,
                SUM(total_amount) as total_value,
                SUM(quantity) as total_units
            FROM sales
            WHERE customer_name = ? AND invoice_type = 'Invoice'
              AND TRIM(COALESCE(item_description, '')) != ''
              AND TRIM(COALESCE(item_description, '')) != 'Item'
              AND TRIM(COALESCE(item_description, '')) != 'Opening balance'
              AND (
                  total_amount != 0 
                  OR (
                      quantity > 0 
                      AND (
                          item_description LIKE '%S/N%' 
                          OR item_description LIKE '%SN:%' 
                          OR item_description LIKE '%Serial%' 
                          OR (product_category IS NOT NULL AND TRIM(product_category) != '' AND TRIM(product_category) != 'Uncategorized')
                      )
                  )
              )
            GROUP BY item_description
            ORDER BY total_value DESC, frequency DESC
            LIMIT 15
        ", [$customerName]);
    }

    public function getCustomerHistory($customerName) {
        return $this->db->fetchAll("
            SELECT * FROM (
                SELECT 
                    'Invoice' as entry_type,
                    invoice_number,
                    invoice_date,
                    SUM(total_amount) as amount,
                    '' as reference,
                    (CASE WHEN paid_date IS NOT NULL THEN 'Settled' ELSE 'Outstanding' END) as status,
                    paid_date,
                    days_to_pay
                FROM sales
                WHERE customer_name = ? AND invoice_type = 'Invoice'
                GROUP BY invoice_number
                HAVING SUM(total_amount) != 0 
                    OR (
                        SUM(quantity) > 0 
                        AND (
                            MAX(CASE WHEN item_description LIKE '%S/N%' OR item_description LIKE '%Serial%' THEN 1 ELSE 0 END) = 1
                            OR MAX(CASE WHEN product_category IS NOT NULL AND TRIM(product_category) != '' AND TRIM(product_category) != 'Uncategorized' THEN 1 ELSE 0 END) = 1
                        )
                    )

                UNION ALL

                SELECT 
                    'Payment' as entry_type,
                    invoice_num as invoice_number,
                    payment_date as invoice_date,
                    amount,
                    reference_num as reference,
                    'Applied' as status,
                    NULL as paid_date,
                    NULL as days_to_pay
                FROM payments
                WHERE customer_name = ?
            )
            ORDER BY invoice_number DESC, entry_type ASC, invoice_date DESC
        ", [$customerName, $customerName]);
    }

    public function getAgingReport($bracket = 'all', $status = 'all', $sortBy = 'invoice_number') {
        $today = date('Y-m-d');
        $where = "WHERE invoice_type = 'Invoice'";
        
        if ($status === 'unpaid') {
            $where .= " AND paid_date IS NULL";
        } else if ($status === 'paid') {
            $where .= " AND paid_date IS NOT NULL";
        }

        $sql = "
            SELECT 
                invoice_number,
                customer_name,
                invoice_date,
                paid_date,
                SUM(total_amount) as total_amount,
                (CASE 
                    WHEN paid_date IS NOT NULL THEN days_to_pay 
                    ELSE CAST((julianday(?) - julianday(invoice_date)) AS INT)
                END) as aging_days
            FROM sales
            $where
            GROUP BY invoice_number
            HAVING SUM(total_amount) != 0
        ";

        $data = $this->db->fetchAll($sql, [$today]);

        // Filter by bracket in PHP for flexibility
        if ($bracket !== 'all') {
            $data = array_filter($data, function($row) use ($bracket) {
                $days = $row['aging_days'];
                if ($bracket == '30') return $days <= 30;
                if ($bracket == '60') return $days > 30 && $days <= 60;
                if ($bracket == '90') return $days > 60 && $days <= 90;
                if ($bracket == '180') return $days > 90 && $days <= 180;
                if ($bracket == '365') return $days > 180 && $days <= 365;
                if ($bracket == 'old') return $days > 365;
                return true;
            });
        }

        // Apply sorting
        usort($data, function($a, $b) use ($sortBy) {
            if ($sortBy === 'customer_name') {
                $cmp = strcasecmp($a['customer_name'], $b['customer_name']);
                return $cmp !== 0 ? $cmp : strcasecmp($a['invoice_number'], $b['invoice_number']);
            }
            if ($sortBy === 'aging') return $b['aging_days'] <=> $a['aging_days'];
            return strcasecmp($a['invoice_number'], $b['invoice_number']);
        });

        return $data;
    }

    /**
     * RFM Customer Segmentation & Churn Risk Analysis
     */
    public function getRFMAnalysis($segmentFilter = 'all') {
        $sql = "
            SELECT 
                s.customer_name,
                COALESCE(p.customer_type, 'End Customer') as customer_type,
                COALESCE(NULLIF(p.sales_rep, ''), NULLIF(s.sales_rep_code, ''), '-') as sales_rep,
                MAX(s.invoice_date) as last_order_date,
                CAST((julianday('now') - julianday(MAX(s.invoice_date))) AS INT) as recency_days,
                COUNT(DISTINCT s.invoice_number) as frequency,
                SUM(s.base_value) as monetary,
                SUM(s.total_amount) as total_volume
            FROM sales s
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            WHERE s.invoice_type IN ('Invoice', 'Credit Memo')
            GROUP BY s.customer_name
            ORDER BY monetary DESC
        ";

        $rows = $this->db->fetchAll($sql);
        $results = [];

        foreach ($rows as $r) {
            $rec = (int)$r['recency_days'];
            $freq = (int)$r['frequency'];
            $mon = (float)$r['monetary'];

            if ($rec <= 60 && $freq >= 10 && $mon >= 500000) {
                $segment = 'Champion';
                $color = '#10b981';
            } elseif ($rec <= 90 && $freq >= 5) {
                $segment = 'Loyal Account';
                $color = '#6366f1';
            } elseif ($rec <= 90 && $freq < 5) {
                $segment = 'Potential Loyalist';
                $color = '#0284c7';
            } elseif ($rec <= 60) {
                $segment = 'Recent Buyer';
                $color = '#06b6d4';
            } elseif ($rec > 120 && $mon >= 250000) {
                $segment = 'At Risk';
                $color = '#ef4444';
            } elseif ($rec > 90 && $rec <= 180) {
                $segment = 'Needs Attention';
                $color = '#f59e0b';
            } elseif ($rec > 180 && $rec <= 365) {
                $segment = 'Hibernating';
                $color = '#8b5cf6';
            } else {
                $segment = 'Lost / Dormant';
                $color = '#64748b';
            }

            $r['segment'] = $segment;
            $r['segment_color'] = $color;

            if ($segmentFilter === 'all' || strcasecmp($segmentFilter, $segment) === 0) {
                $results[] = $r;
            }
        }

        return $results;
    }

    /**
     * Partner vs End Customer Cohort Breakdown
     */
    public function getPartnerCohortAnalysis() {
        return $this->db->fetchAll("
            SELECT 
                COALESCE(NULLIF(p.customer_type, ''), 'End Customer') as customer_type,
                COUNT(DISTINCT s.customer_name) as total_accounts,
                COUNT(DISTINCT s.invoice_number) as total_orders,
                SUM(s.total_amount) as total_gross,
                SUM(s.base_value) as total_base,
                ROUND(AVG(s.total_amount), 2) as avg_order_value,
                ROUND(AVG(CASE WHEN s.paid_date IS NOT NULL THEN s.days_to_pay ELSE NULL END), 1) as avg_days_to_pay,
                ROUND(SUM(s.total_amount) * 100.0 / NULLIF((SELECT SUM(total_amount) FROM sales WHERE invoice_type = 'Invoice'), 0), 1) as revenue_share_pct
            FROM sales s
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            WHERE s.invoice_type = 'Invoice'
            GROUP BY 1
            ORDER BY total_gross DESC
        ");
    }

    /**
     * Stock Movement & Inventory Velocity (FSN Analysis)
     */
    public function getStockMovementAnalysis($category = null, $fsnFilter = 'all', $search = '', $limit = 50, $offset = 0) {
        $where = "WHERE invoice_type = 'Invoice' 
                  AND item_description IS NOT NULL 
                  AND TRIM(item_description) != '' 
                  AND TRIM(item_description) != 'Item'
                  AND TRIM(item_description) != 'Opening balance'
                  AND (
                      total_amount != 0 
                      OR (
                          quantity > 0 
                          AND (
                              item_description LIKE '%S/N%' 
                              OR item_description LIKE '%SN:%' 
                              OR item_description LIKE '%Serial%' 
                              OR (product_category IS NOT NULL AND TRIM(product_category) != '' AND TRIM(product_category) != 'Uncategorized')
                          )
                      )
                  )";
        $params = [];

        if (!empty($category)) {
            $where .= " AND product_category = ? ";
            $params[] = $category;
        }

        if (!empty($search)) {
            $where .= " AND (item_description LIKE ? OR product_category LIKE ?) ";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "
            SELECT 
                item_description,
                COALESCE(NULLIF(product_category, ''), 'Uncategorized') as category,
                SUM(quantity) as total_units,
                SUM(total_amount) as total_revenue,
                SUM(base_value) as base_revenue,
                COUNT(*) as dispatch_count,
                COUNT(DISTINCT strftime('%Y-%m', invoice_date)) as active_months,
                MIN(invoice_date) as first_dispatch,
                MAX(invoice_date) as last_dispatch,
                CAST((julianday('now') - julianday(MAX(invoice_date))) AS INT) as days_since_dispatch,
                CASE WHEN item_description LIKE '%S/N%' OR item_description LIKE '%SN:%' OR item_description LIKE '%Serial%' THEN 1 ELSE 0 END as is_serialized
            FROM sales
            $where
            GROUP BY item_description
            HAVING SUM(quantity) > 0 OR SUM(total_amount) > 0
            ORDER BY total_units DESC
        ";

        $rows = $this->db->fetchAll($sql, $params);
        $filtered = [];

        foreach ($rows as $r) {
            $daysSince = (int)$r['days_since_dispatch'];
            $activeMonths = (int)$r['active_months'];
            $units = (float)$r['total_units'];

            if ($daysSince <= 60 && ($activeMonths >= 6 || $units >= 20)) {
                $velocity = 'Fast-Moving (F)';
                $velocityCode = 'F';
            } elseif ($daysSince <= 180 && $activeMonths >= 2) {
                $velocity = 'Slow-Moving (S)';
                $velocityCode = 'S';
            } else {
                $velocity = 'Non-Moving / Dormant (N)';
                $velocityCode = 'N';
            }

            $r['velocity'] = $velocity;
            $r['velocity_code'] = $velocityCode;

            if ($fsnFilter === 'all' || strcasecmp($fsnFilter, $velocityCode) === 0) {
                $filtered[] = $r;
            }
        }

        $totalCount = count($filtered);
        $pageItems = array_slice($filtered, (int)$offset, (int)$limit);

        return [
            'total' => $totalCount,
            'items' => $pageItems,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Sales Rep Performance, Quota Contribution & DSO Health
     */
    public function getSalesRepPerformance($year = null) {
        $where = "WHERE s.invoice_type IN ('Invoice', 'Credit Memo') AND s.sales_rep_code IS NOT NULL AND s.sales_rep_code != ''";
        $params = [];

        if (!empty($year)) {
            $where .= " AND strftime('%Y', s.invoice_date) = ? ";
            $params[] = $year;
        }

        return $this->db->fetchAll("
            SELECT 
                s.sales_rep_code,
                COALESCE(m.rep_name, 'Sales Rep ' || s.sales_rep_code) as rep_name,
                COUNT(DISTINCT CASE WHEN s.invoice_type = 'Invoice' THEN s.invoice_number END) as invoice_count,
                COUNT(*) as total_lines,
                COUNT(DISTINCT s.customer_name) as client_count,
                SUM(s.total_amount) as gross_revenue,
                SUM(s.base_value) as base_revenue,
                SUM(CASE WHEN s.paid_date IS NOT NULL THEN s.total_amount ELSE 0 END) as collected_revenue,
                SUM(CASE WHEN s.paid_date IS NULL THEN s.total_amount ELSE 0 END) as outstanding_revenue,
                ROUND(AVG(CASE WHEN s.paid_date IS NOT NULL THEN s.days_to_pay ELSE NULL END), 1) as avg_dso,
                ROUND(SUM(CASE WHEN s.paid_date IS NOT NULL THEN s.total_amount ELSE 0 END) * 100.0 / NULLIF(SUM(s.total_amount), 0), 1) as collection_rate_pct
            FROM sales s
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            $where
            GROUP BY s.sales_rep_code
            ORDER BY gross_revenue DESC
        ", $params);
    }

    /**
     * Invoice Summary Report
     * Aggregates line items by invoice_number with financial totals, settlement status, and multi-faceted filtering
     */
    public function getInvoiceSummaryReport($filters = [], $page = 1, $limit = 25) {
        $whereConditions = ["s.invoice_number IS NOT NULL AND s.invoice_number != ''"];
        $params = [];

        // Skip non-viable placeholder 0-value items
        $whereConditions[] = "(s.total_amount != 0 OR s.quantity != 0 OR s.item_description LIKE '%S/N%' OR s.item_description LIKE '%Serial%')";

        // Limit date for non-admins
        if ($this->limitDate) {
            $whereConditions[] = "s.invoice_date <= '{$this->limitDate}'";
        }

        // Year filter
        if (!empty($filters['year']) && $filters['year'] !== 'all') {
            $whereConditions[] = "strftime('%Y', s.invoice_date) = ?";
            $params[] = $filters['year'];
        }

        // Month filter
        if (!empty($filters['month']) && $filters['month'] !== 'all') {
            $whereConditions[] = "strftime('%m', s.invoice_date) = ?";
            $params[] = str_pad((string)$filters['month'], 2, '0', STR_PAD_LEFT);
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "s.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "s.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }

        // Search query (invoice_number, customer_name, po_number, item_description)
        if (!empty($filters['search'])) {
            $searchWild = '%' . trim($filters['search']) . '%';
            $whereConditions[] = "(s.invoice_number LIKE ? OR s.customer_name LIKE ? OR s.po_number LIKE ? OR s.item_description LIKE ?)";
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
        }

        // Brand / Category filter
        if (!empty($filters['brand'])) {
            $whereConditions[] = "s.product_category = ?";
            $params[] = $filters['brand'];
        }

        // Customer Type filter
        if (!empty($filters['customer_type'])) {
            $whereConditions[] = "p.customer_type = ?";
            $params[] = $filters['customer_type'];
        }

        // Sales Rep filter
        if (!empty($filters['rep_code'])) {
            $whereConditions[] = "s.sales_rep_code = ?";
            $params[] = $filters['rep_code'];
        }

        // Status filter: all, settled, unpaid
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'settled') {
                $whereConditions[] = "(s.paid_date IS NOT NULL AND s.paid_date != '')";
            } elseif ($filters['status'] === 'unpaid') {
                $whereConditions[] = "(s.paid_date IS NULL OR s.paid_date = '')";
            }
        }

        $whereSql = "WHERE " . implode(" AND ", $whereConditions);

        // Sorting
        $allowedSorts = [
            'invoice_date_desc' => 'MIN(s.invoice_date) DESC, s.invoice_number DESC',
            'invoice_date_asc' => 'MIN(s.invoice_date) ASC, s.invoice_number ASC',
            'invoice_number_asc' => 's.invoice_number ASC',
            'invoice_number_desc' => 's.invoice_number DESC',
            'amount_desc' => 'total_gross_amount DESC',
            'amount_asc' => 'total_gross_amount ASC',
            'customer_asc' => 's.customer_name ASC',
            'customer_desc' => 's.customer_name DESC'
        ];
        $sortKey = $filters['sort'] ?? 'invoice_date_desc';
        $orderBy = $allowedSorts[$sortKey] ?? $allowedSorts['invoice_date_desc'];

        // Overall summary metrics across all matched invoices (before pagination)
        $summarySql = "
            SELECT 
                COUNT(DISTINCT s.invoice_number) as total_invoices,
                COUNT(DISTINCT s.customer_name) as unique_customers,
                SUM(s.quantity) as grand_total_qty,
                SUM(s.base_value) as grand_base_value,
                SUM(s.vat_component) as grand_total_vat,
                SUM(s.total_amount) as grand_gross_revenue,
                SUM(CASE WHEN s.paid_date IS NOT NULL AND s.paid_date != '' THEN s.total_amount ELSE 0 END) as settled_amount,
                SUM(CASE WHEN s.paid_date IS NULL OR s.paid_date = '' THEN s.total_amount ELSE 0 END) as unpaid_amount,
                COUNT(DISTINCT CASE WHEN s.paid_date IS NOT NULL AND s.paid_date != '' THEN s.invoice_number END) as settled_invoices_count,
                COUNT(DISTINCT CASE WHEN s.paid_date IS NULL OR s.paid_date = '' THEN s.invoice_number END) as unpaid_invoices_count
            FROM sales s
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            $whereSql
        ";
        $summaryData = $this->db->fetch($summarySql, $params) ?: [];

        $totalCount = (int)($summaryData['total_invoices'] ?? 0);
        $page = max(1, (int)$page);
        $limit = ($limit === 'all' || (int)$limit >= 999999 || (int)$limit <= 0) ? 999999 : max(10, min(500, (int)$limit));
        $offset = ($page - 1) * $limit;
        $totalPages = max(1, (int)ceil($totalCount / $limit));

        // Paginated invoice rows
        $dataSql = "
            SELECT 
                s.invoice_number,
                s.invoice_type,
                MIN(s.invoice_date) as invoice_date,
                s.customer_name,
                MAX(s.end_customer) as end_customer,
                s.sales_rep_code,
                COALESCE(m.rep_name, s.sales_rep_code) as rep_name,
                MAX(s.po_number) as po_number,
                MAX(s.paid_date) as paid_date,
                MAX(s.days_to_pay) as days_to_pay,
                p.customer_type,
                COUNT(*) as line_count,
                SUM(s.quantity) as total_quantity,
                SUM(s.base_value) as total_base_value,
                SUM(s.vat_component) as total_vat_component,
                SUM(s.total_amount) as total_gross_amount,
                MAX(CASE WHEN s.item_description LIKE '%S/N%' OR s.item_description LIKE '%SN:%' OR s.item_description LIKE '%Serial%' THEN 1 ELSE 0 END) as has_serials,
                (SELECT COUNT(*) FROM hardware_assets ha WHERE ha.invoice_number = s.invoice_number) as hardware_count,
                (SELECT COUNT(*) FROM hardware_assets ha WHERE ha.invoice_number = s.invoice_number AND ha.serial_number IS NOT NULL AND ha.serial_number != '' AND ha.serial_number != 'UNASSIGNED') as serials_count,
                (SELECT COUNT(*) FROM software_subscriptions ss WHERE ss.invoice_number = s.invoice_number) as subscriptions_count,
                (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number) as items_count,
                COALESCE(
                    (SELECT s2.vat_treatment FROM sales s2 WHERE s2.invoice_number = s.invoice_number AND s2.vat_treatment != 'VAT_EXEMPT' AND s2.total_amount > 0 LIMIT 1),
                    (SELECT ii.vat_treatment FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number AND ii.vat_treatment != 'VAT_EXEMPT' AND ii.total_amount > 0 LIMIT 1),
                    CASE 
                        WHEN SUM(s.vat_component) > 0 THEN 'PLUS_VAT'
                        ELSE 'VAT_EXEMPT'
                    END
                ) as invoice_vat_treatment
            FROM sales s
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            $whereSql
            GROUP BY s.invoice_number
            ORDER BY $orderBy
            LIMIT $limit OFFSET $offset
        ";

        $rows = $this->db->fetchAll($dataSql, $params);

        return [
            'invoices' => $rows,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages,
            'summary' => $summaryData
        ];
    }

    /**
     * Get Complete Invoice Details for Slide-Over / Modal Inspector
     * Fetches invoice header, customer profile CRM data, detailed line items, and payment transactions
     */
    public function getInvoiceDetails($invoiceNumber) {
        $inv = trim($invoiceNumber);
        if (empty($inv)) {
            return ['error' => 'Invoice number required'];
        }

        // 1. Aggregated Header
        $header = $this->db->fetch("
            SELECT 
                s.invoice_number,
                s.invoice_type,
                MIN(s.invoice_date) as invoice_date,
                s.customer_name,
                MAX(s.end_customer) as end_customer,
                s.sales_rep_code,
                COALESCE(m.rep_name, s.sales_rep_code) as rep_name,
                MAX(s.po_number) as po_number,
                MAX(s.paid_date) as paid_date,
                MAX(s.days_to_pay) as days_to_pay,
                MAX(s.memo) as memo,
                COUNT(*) as total_lines,
                SUM(s.quantity) as total_quantity,
                SUM(s.base_value) as total_base_value,
                SUM(s.vat_component) as total_vat,
                SUM(s.total_amount) as total_gross_amount,
                SUM(COALESCE(s.gross_profit, 0)) as total_gross_profit,
                MAX(s.applied_tax_rate) as applied_tax_rate,
                COALESCE((SELECT s2.vat_treatment FROM sales s2 WHERE s2.invoice_number = s.invoice_number AND s2.vat_treatment != 'VAT_EXEMPT' LIMIT 1), (SELECT ii.vat_treatment FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number LIMIT 1), 'PLUS_VAT') as vat_treatment
            FROM sales s
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            WHERE s.invoice_number = ?
            GROUP BY s.invoice_number
        ", [$inv]);

        if (!$header) {
            return ['error' => "Invoice '$inv' not found"];
        }

        // 2. Customer CRM Info
        $customer = $this->db->fetch("
            SELECT customer_name, company_name, contact_name, email, phone, bill_address, bill_city, bill_state, bill_zip, customer_type, credit_limit, terms, current_balance, vat_number, tin_number, is_vat_registered
            FROM customer_profiles
            WHERE customer_name = ?
        ", [$header['customer_name']]) ?: [
            'customer_name' => $header['customer_name'],
            'customer_type' => 'End Customer',
            'is_vat_registered' => 0
        ];

        // 3. Line Items
        $rawLines = $this->db->fetchAll("
            SELECT id, item_description, product_category, quantity, unit_price, unit_cost, gross_profit, base_value, vat_component, applied_tax_rate, total_amount, memo
            FROM sales
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        $lines = [];
        foreach ($rawLines as $l) {
            $desc = $l['item_description'] ?? '';
            $isSerialized = (
                stripos($desc, 'S/N') !== false || 
                stripos($desc, 'SN:') !== false || 
                stripos($desc, 'Serial') !== false
            );
            $l['is_serialized'] = $isSerialized ? 1 : 0;
            $lines[] = $l;
        }

        // 4. Matched Payments
        $payments = $this->db->fetchAll("
            SELECT id, customer_name, invoice_num, payment_date, reference_num, amount, created_at
            FROM payments
            WHERE invoice_num = ? OR invoice_num LIKE ?
            ORDER BY payment_date ASC
        ", [$inv, "%$inv%"]);

        $totalPaid = array_sum(array_column($payments, 'amount'));
        if (empty($totalPaid) && !empty($header['paid_date'])) {
            // Reconciled as settled in QB sales ledger
            $totalPaid = (float)$header['total_gross_amount'];
        }
        $balanceDue = max(0, (float)$header['total_gross_amount'] - $totalPaid);

        // 5. Normalized Extracted Hardware Assets & Warranties (if processed by AI)
        $assets = $this->db->fetchAll("
            SELECT id, product_name, brand, model_sku, serial_number, warranty_type, warranty_months, warranty_start_date, warranty_expiry_date, warranty_status, parent_serial_number, notes, end_customer
            FROM hardware_assets
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        // 6. Normalized Software Subscriptions & SaaS Licenses
        $subscriptions = $this->db->fetchAll("
            SELECT id, software_name, edition_tier, license_seats, period_start_date, period_end_date, term_months, renewal_status, renewal_opportunity_value, end_customer
            FROM software_subscriptions
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        // 7. Normalized Commercial Line Items
        $items = $this->db->fetchAll("
            SELECT id, clean_product_name, product_type, brand_category, brand, category, quantity, unit_price, unit_cost, gross_profit, base_value, vat_component, total_amount, vat_treatment, end_customer
            FROM invoice_items
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        return [
            'success' => true,
            'header' => $header,
            'customer' => $customer,
            'items' => $items,
            'lines' => $lines,
            'payments' => $payments,
            'assets' => $assets,
            'subscriptions' => $subscriptions,
            'reconciliation' => [
                'total_gross' => (float)$header['total_gross_amount'],
                'total_paid' => (float)$totalPaid,
                'balance_due' => (float)$balanceDue,
                'status' => (!empty($header['paid_date']) || $balanceDue <= 0.01) ? 'Settled' : 'Unpaid'
            ]
        ];
    }

    /**
     * Hardware Assets & Discrete Serial Number Warranty Registry
     */
    public function getWarrantyReport($filters = [], $page = 1, $limit = 50) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(h.serial_number LIKE ? OR h.product_name LIKE ? OR h.customer_name LIKE ? OR h.invoice_number LIKE ? OR h.model_sku LIKE ?)";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }

        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            $where[] = "h.brand = ?";
            $params[] = $filters['brand'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $today = date('Y-m-d');
            if ($filters['status'] === 'EXPIRED') {
                $where[] = "h.warranty_expiry_date < ?";
                $params[] = $today;
            } elseif ($filters['status'] === 'EXPIRING_30D') {
                $where[] = "(h.warranty_expiry_date >= ? AND h.warranty_expiry_date <= date(?, '+30 days'))";
                $params[] = $today;
                $params[] = $today;
            } elseif ($filters['status'] === 'EXPIRING_60D') {
                $where[] = "(h.warranty_expiry_date > date(?, '+30 days') AND h.warranty_expiry_date <= date(?, '+60 days'))";
                $params[] = $today;
                $params[] = $today;
            } elseif ($filters['status'] === 'EXPIRING_90D') {
                $where[] = "(h.warranty_expiry_date > date(?, '+60 days') AND h.warranty_expiry_date <= date(?, '+90 days'))";
                $params[] = $today;
                $params[] = $today;
            } elseif ($filters['status'] === 'ACTIVE') {
                $where[] = "h.warranty_expiry_date >= ?";
                $params[] = $today;
            }
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM hardware_assets h WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $assets = $this->db->fetchAll("
            SELECT 
                h.id,
                h.invoice_number,
                h.customer_name,
                h.product_name,
                h.brand,
                h.model_sku,
                h.serial_number,
                h.warranty_type,
                h.warranty_months,
                h.warranty_start_date,
                h.warranty_expiry_date,
                h.parent_serial_number,
                h.notes,
                h.created_at,
                ROUND(julianday(h.warranty_expiry_date) - julianday('now')) as days_remaining,
                CASE 
                    WHEN h.warranty_expiry_date < date('now') THEN 'EXPIRED'
                    WHEN julianday(h.warranty_expiry_date) - julianday('now') <= 30 THEN 'EXPIRING_30D'
                    WHEN julianday(h.warranty_expiry_date) - julianday('now') <= 60 THEN 'EXPIRING_60D'
                    WHEN julianday(h.warranty_expiry_date) - julianday('now') <= 90 THEN 'EXPIRING_90D'
                    ELSE 'ACTIVE'
                END as dynamic_status
            FROM hardware_assets h
            WHERE $whereClause
            ORDER BY h.warranty_expiry_date ASC, h.id DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        $kpis = $this->db->fetch("
            SELECT 
                COUNT(*) as total_assets,
                SUM(CASE WHEN warranty_expiry_date >= date('now') THEN 1 ELSE 0 END) as active_assets,
                SUM(CASE WHEN warranty_expiry_date >= date('now') AND warranty_expiry_date <= date('now', '+30 days') THEN 1 ELSE 0 END) as expiring_30d,
                SUM(CASE WHEN warranty_expiry_date > date('now', '+30 days') AND warranty_expiry_date <= date('now', '+60 days') THEN 1 ELSE 0 END) as expiring_60d,
                SUM(CASE WHEN warranty_expiry_date > date('now', '+60 days') AND warranty_expiry_date <= date('now', '+90 days') THEN 1 ELSE 0 END) as expiring_90d,
                SUM(CASE WHEN warranty_expiry_date < date('now') THEN 1 ELSE 0 END) as expired_assets
            FROM hardware_assets
        ") ?: [
            'total_assets' => 0,
            'active_assets' => 0,
            'expiring_30d' => 0,
            'expiring_60d' => 0,
            'expiring_90d' => 0,
            'expired_assets' => 0
        ];

        return [
            'assets' => $assets,
            'total' => $total,
            'pages' => $pages,
            'kpis' => $kpis
        ];
    }

    /**
     * Software Subscriptions & SaaS License Renewals Pipeline
     */
    public function getRenewalsReport($filters = [], $page = 1, $limit = 50) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(sub.software_name LIKE ? OR sub.customer_name LIKE ? OR sub.invoice_number LIKE ?)";
            $params = array_merge($params, [$s, $s, $s]);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $today = date('Y-m-d');
            if ($filters['status'] === 'EXPIRED') {
                $where[] = "sub.period_end_date < ?";
                $params[] = $today;
            } elseif ($filters['status'] === 'DUE_SOON') {
                $where[] = "(sub.period_end_date >= ? AND sub.period_end_date <= date(?, '+60 days'))";
                $params[] = $today;
                $params[] = $today;
            } elseif ($filters['status'] === 'ACTIVE') {
                $where[] = "sub.period_end_date >= ?";
                $params[] = $today;
            }
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM software_subscriptions sub WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $subs = $this->db->fetchAll("
            SELECT 
                sub.id,
                sub.invoice_number,
                sub.customer_name,
                sub.software_name,
                sub.edition_tier,
                sub.license_seats,
                sub.period_start_date,
                sub.period_end_date,
                sub.term_months,
                sub.renewal_opportunity_value,
                sub.created_at,
                ROUND(julianday(sub.period_end_date) - julianday('now')) as days_remaining,
                CASE 
                    WHEN sub.period_end_date < date('now') THEN 'EXPIRED'
                    WHEN julianday(sub.period_end_date) - julianday('now') <= 60 THEN 'DUE_SOON'
                    ELSE 'ACTIVE'
                END as dynamic_status
            FROM software_subscriptions sub
            WHERE $whereClause
            ORDER BY sub.period_end_date ASC, sub.id DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        $kpis = $this->db->fetch("
            SELECT 
                COUNT(*) as total_subscriptions,
                SUM(license_seats) as total_seats,
                SUM(CASE WHEN period_end_date >= date('now') THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN period_end_date >= date('now') AND period_end_date <= date('now', '+60 days') THEN 1 ELSE 0 END) as due_soon_count,
                SUM(CASE WHEN period_end_date >= date('now') AND period_end_date <= date('now', '+60 days') THEN renewal_opportunity_value ELSE 0 END) as pipeline_value,
                SUM(CASE WHEN period_end_date < date('now') THEN 1 ELSE 0 END) as expired_count
            FROM software_subscriptions
        ") ?: [
            'total_subscriptions' => 0,
            'total_seats' => 0,
            'active_count' => 0,
            'due_soon_count' => 0,
            'pipeline_value' => 0,
            'expired_count' => 0
        ];

        $calendar = $this->db->fetchAll("
            SELECT 
                strftime('%Y-%m', period_end_date) as renewal_month,
                COUNT(*) as count,
                SUM(license_seats) as total_seats,
                SUM(renewal_opportunity_value) as renewal_value
            FROM software_subscriptions
            WHERE period_end_date >= date('now', '-1 month')
            GROUP BY strftime('%Y-%m', period_end_date)
            ORDER BY renewal_month ASC
            LIMIT 12
        ");

        return [
            'subscriptions' => $subs,
            'total' => $total,
            'pages' => $pages,
            'kpis' => $kpis,
            'calendar' => $calendar
        ];
    }

    /**
     * Product Mapping Rules Catalog
     */
    public function getProductMappings($filters = [], $page = 1, $limit = 50) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(pattern LIKE ? OR canonical_name LIKE ? OR master_sku LIKE ? OR brand LIKE ? OR notes LIKE ?)";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }

        if (!empty($filters['commercial_type']) && $filters['commercial_type'] !== 'ALL') {
            $where[] = "commercial_type = ?";
            $params[] = $filters['commercial_type'];
        }

        if (!empty($filters['match_type']) && $filters['match_type'] !== 'ALL') {
            $where[] = "match_type = ?";
            $params[] = $filters['match_type'];
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM product_mappings WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rules = $this->db->fetchAll("
            SELECT *
            FROM product_mappings
            WHERE $whereClause
            ORDER BY priority ASC, id ASC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        $kpis = $this->db->fetch("
            SELECT 
                COUNT(*) as total_rules,
                SUM(CASE WHEN commercial_type = 'RENTAL' THEN 1 ELSE 0 END) as rental_rules,
                SUM(CASE WHEN commercial_type = 'OUTRIGHT_SALE' THEN 1 ELSE 0 END) as sale_rules,
                COUNT(DISTINCT master_sku) as distinct_skus
            FROM product_mappings
        ") ?: [
            'total_rules' => 0,
            'rental_rules' => 0,
            'sale_rules' => 0,
            'distinct_skus' => 0
        ];

        return [
            'rules' => $rules,
            'total' => $total,
            'pages' => $pages,
            'kpis' => $kpis
        ];
    }

    /**
     * Save Product Mapping Rule (Insert or Update)
     */
    public function saveProductMapping(array $data) {
        $this->db->ensureProductMappingColumns();

        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $pattern = trim($data['pattern'] ?? '');
        $matchType = strtoupper(trim($data['match_type'] ?? 'CONTAINS'));
        $masterSku = strtoupper(trim($data['master_sku'] ?? ''));
        $canonicalName = trim($data['canonical_name'] ?? '');
        $brand = trim($data['brand'] ?? '');
        $category = trim($data['category'] ?? '');
        $commercialType = strtoupper(trim($data['commercial_type'] ?? 'OUTRIGHT_SALE'));
        $vatTreatment = strtoupper(trim($data['default_vat_treatment'] ?? 'DEFAULT'));
        $priority = !empty($data['priority']) ? (int)$data['priority'] : 10;
        $notes = trim($data['notes'] ?? '');

        if (empty($pattern) || empty($canonicalName)) {
            throw new Exception('Pattern and Canonical Name are required fields.');
        }

        if ($id > 0) {
            $this->db->execute("
                UPDATE product_mappings SET
                    pattern = ?,
                    match_type = ?,
                    master_sku = ?,
                    canonical_name = ?,
                    brand = ?,
                    category = ?,
                    commercial_type = ?,
                    default_vat_treatment = ?,
                    priority = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [$pattern, $matchType, $masterSku, $canonicalName, $brand, $category, $commercialType, $vatTreatment, $priority, $notes, $id]);
            return $id;
        } else {
            $this->db->execute("
                INSERT INTO product_mappings (
                    pattern, match_type, master_sku, canonical_name, brand, category,
                    commercial_type, default_vat_treatment, priority, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$pattern, $matchType, $masterSku, $canonicalName, $brand, $category, $commercialType, $vatTreatment, $priority, $notes]);
            return (int)$this->db->lastInsertId();
        }
    }

    /**
     * Delete Product Mapping Rule
     */
    public function deleteProductMapping(int $id) {
        return $this->db->execute("DELETE FROM product_mappings WHERE id = ?", [$id]);
    }

    /**
     * Unmapped / High Frequency Raw Descriptions from Sales
     */
    public function getUnmappedDescriptions(int $limit = 50) {
        $rows = $this->db->fetchAll("
            SELECT 
                TRIM(s.item_description) as description,
                COUNT(*) as occ_count,
                SUM(s.total_amount) as total_volume,
                MAX(s.invoice_date) as last_seen,
                MAX(s.customer_name) as sample_customer
            FROM sales s
            WHERE s.total_amount > 0
              AND s.item_description IS NOT NULL
              AND TRIM(s.item_description) != ''
              AND s.item_description NOT IN ('Item', 'Opening balance')
              AND s.item_description NOT LIKE 'Please Remit to%'
              AND s.item_description NOT LIKE 'SSCL%'
            GROUP BY TRIM(s.item_description)
            ORDER BY occ_count DESC, total_volume DESC
            LIMIT ?
        ", [$limit]);

        // Check each description against active mapping rules
        $rules = $this->db->fetchAll("SELECT pattern, match_type, canonical_name, commercial_type FROM product_mappings");

        foreach ($rows as &$r) {
            $matchedRule = null;
            $desc = $r['description'];
            foreach ($rules as $rule) {
                $pat = $rule['pattern'];
                $mType = strtoupper($rule['match_type'] ?? 'CONTAINS');
                if ($mType === 'EXACT' && strcasecmp($desc, $pat) === 0) {
                    $matchedRule = $rule;
                    break;
                } elseif ($mType === 'REGEX' && @preg_match('/' . str_replace('/', '\/', $pat) . '/i', $desc)) {
                    $matchedRule = $rule;
                    break;
                } elseif ($mType === 'CONTAINS' && stripos($desc, $pat) !== false) {
                    $matchedRule = $rule;
                    break;
                }
            }
            $r['mapped_rule'] = $matchedRule;
            $r['is_mapped'] = $matchedRule !== null;
        }

        return $rows;
    }

    /**
     * Rental Fleet & Recurring Billing Tracker
     */
    public function getRentalFleet($filters = [], $page = 1, $limit = 50) {
        $where = ["ii.product_type = 'RENTAL'"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(ii.customer_name LIKE ? OR ii.invoice_number LIKE ? OR ii.clean_product_name LIKE ? OR EXISTS (
                SELECT 1 FROM hardware_assets ha WHERE ha.invoice_item_id = ii.id AND ha.serial_number LIKE ?
            ))";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            if ($filters['status'] === 'ACTIVE') {
                $where[] = "(julianday('now') - julianday(ii.invoice_date)) <= 35";
            } elseif ($filters['status'] === 'OVERDUE') {
                $where[] = "(julianday('now') - julianday(ii.invoice_date)) > 35 AND (julianday('now') - julianday(ii.invoice_date)) <= 60";
            } elseif ($filters['status'] === 'SUSPENDED') {
                $where[] = "(julianday('now') - julianday(ii.invoice_date)) > 60";
            }
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM invoice_items ii WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $items = $this->db->fetchAll("
            SELECT 
                ii.id,
                ii.invoice_number,
                ii.customer_name,
                ii.invoice_date,
                ii.clean_product_name,
                ii.brand_category,
                ii.quantity,
                ii.unit_price,
                ii.base_value,
                ii.vat_component,
                ii.total_amount,
                ii.vat_treatment,
                CAST(ROUND(julianday('now') - julianday(ii.invoice_date)) AS INTEGER) as days_since_billed,
                CASE 
                    WHEN (julianday('now') - julianday(ii.invoice_date)) <= 35 THEN 'ACTIVE'
                    WHEN (julianday('now') - julianday(ii.invoice_date)) <= 60 THEN 'OVERDUE'
                    ELSE 'SUSPENDED'
                END as rental_status,
                (
                    SELECT GROUP_CONCAT(ha.serial_number, ', ') 
                    FROM hardware_assets ha 
                    WHERE ha.invoice_item_id = ii.id AND ha.serial_number != 'UNASSIGNED'
                ) as serial_numbers,
                (
                    SELECT COUNT(*) 
                    FROM hardware_assets ha 
                    WHERE ha.invoice_item_id = ii.id AND ha.serial_number != 'UNASSIGNED'
                ) as serial_count,
                (
                    SELECT ha.notes 
                    FROM hardware_assets ha 
                    WHERE ha.invoice_item_id = ii.id AND ha.notes IS NOT NULL AND ha.notes != ''
                    LIMIT 1
                ) as rental_period_notes
            FROM invoice_items ii
            WHERE $whereClause
            ORDER BY ii.invoice_date DESC, ii.id DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        return [
            'deployments' => $items,
            'total' => $total,
            'pages' => $pages,
            'summary' => $this->getRentalSummary()
        ];
    }

    /**
     * Rental Portfolio KPI Summary & Recurring MRR
     */
    public function getRentalSummary() {
        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as total_rentals,
                COUNT(DISTINCT customer_name) as total_rental_customers,
                SUM(total_amount) as total_rental_volume,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) <= 35 THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) <= 35 THEN base_value ELSE 0 END) as active_mrr,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) > 35 AND (julianday('now') - julianday(invoice_date)) <= 60 THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) > 35 AND (julianday('now') - julianday(invoice_date)) <= 60 THEN base_value ELSE 0 END) as overdue_mrr,
                (SELECT COUNT(*) FROM hardware_assets WHERE is_rental = 1) as rental_hardware_units
            FROM invoice_items
            WHERE product_type = 'RENTAL'
        ") ?: [
            'total_rentals' => 0,
            'total_rental_customers' => 0,
            'total_rental_volume' => 0,
            'active_count' => 0,
            'active_mrr' => 0,
            'overdue_count' => 0,
            'overdue_mrr' => 0,
            'rental_hardware_units' => 0
        ];

        return $summary;
    }

    /**
     * Extracted Invoice Products Management (Brand & Category Assignment)
     */
    public function getExtractedProducts($filters = [], $page = 1, $limit = 50) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(ii.clean_product_name LIKE ? OR ii.invoice_number LIKE ? OR ii.customer_name LIKE ?)";
            $params = array_merge($params, [$s, $s, $s]);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'UNASSIGNED') {
                $where[] = "(ii.brand IS NULL OR ii.brand = '' OR ii.brand = 'Other' OR ii.category IS NULL OR ii.category = '' OR ii.category = 'Other / Unassigned')";
            } elseif ($filters['status'] === 'ASSIGNED') {
                $where[] = "(ii.brand IS NOT NULL AND ii.brand != '' AND ii.brand != 'Other' AND ii.category IS NOT NULL AND ii.category != '' AND ii.category != 'Other / Unassigned')";
            }
        }

        if (!empty($filters['brand']) && $filters['brand'] !== 'ALL') {
            $where[] = "ii.brand = ?";
            $params[] = $filters['brand'];
        }

        if (!empty($filters['category']) && $filters['category'] !== 'ALL') {
            $where[] = "ii.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['product_type']) && $filters['product_type'] !== 'ALL') {
            $where[] = "ii.product_type = ?";
            $params[] = $filters['product_type'];
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM invoice_items ii WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $items = $this->db->fetchAll("
            SELECT 
                ii.id,
                ii.invoice_number,
                ii.customer_name,
                ii.invoice_date,
                ii.product_type,
                ii.clean_product_name,
                COALESCE(NULLIF(ii.brand, ''), 'Other') as brand,
                COALESCE(NULLIF(ii.category, ''), 'Other / Unassigned') as category,
                ii.quantity,
                ii.unit_price,
                ii.base_value,
                ii.vat_component,
                ii.total_amount,
                mb.color as brand_color,
                mc.color as category_color
            FROM invoice_items ii
            LEFT JOIN master_brands mb ON mb.name = ii.brand
            LEFT JOIN master_categories mc ON mc.name = ii.category
            WHERE $whereClause
            ORDER BY ii.invoice_date DESC, ii.id DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        $kpis = $this->db->fetch("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN (brand IS NULL OR brand = '' OR brand = 'Other' OR category IS NULL OR category = '' OR category = 'Other / Unassigned') THEN 1 ELSE 0 END) as unassigned_items,
                SUM(total_amount) as total_gross,
                SUM(CASE WHEN (brand IS NULL OR brand = '' OR brand = 'Other' OR category IS NULL OR category = '' OR category = 'Other / Unassigned') THEN total_amount ELSE 0 END) as unassigned_gross,
                COUNT(DISTINCT brand) as total_brands_count,
                COUNT(DISTINCT category) as total_categories_count
            FROM invoice_items
        ") ?: [
            'total_items' => 0,
            'unassigned_items' => 0,
            'total_gross' => 0,
            'unassigned_gross' => 0,
            'total_brands_count' => 0,
            'total_categories_count' => 0
        ];

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
            'kpis' => $kpis
        ];
    }

    public function assignProductBrandCategory(int $id, ?string $brand, ?string $category) {
        $updates = [];
        $params = [];

        if ($brand !== null) {
            $updates[] = "brand = ?";
            $params[] = trim($brand);
        }
        if ($category !== null) {
            $updates[] = "category = ?";
            $params[] = trim($category);
        }

        if (empty($updates)) return true;

        $params[] = $id;
        return $this->db->execute("UPDATE invoice_items SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    }

    public function bulkAssignProducts(array $ids, ?string $brand, ?string $category) {
        $cleanIds = array_filter(array_map('intval', $ids));
        if (empty($cleanIds)) return 0;

        $updates = [];
        $params = [];

        if ($brand !== null && $brand !== '') {
            $updates[] = "brand = ?";
            $params[] = trim($brand);
        }
        if ($category !== null && $category !== '') {
            $updates[] = "category = ?";
            $params[] = trim($category);
        }

        if (empty($updates)) return 0;

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $params = array_merge($params, $cleanIds);

        return $this->db->execute("
            UPDATE invoice_items 
            SET " . implode(', ', $updates) . " 
            WHERE id IN ($placeholders)
        ", $params);
    }

    public function autoClassifyExtractedProducts() {
        $rules = $this->db->fetchAll("
            SELECT pattern, match_type, canonical_name, brand, category, commercial_type 
            FROM product_mappings 
            ORDER BY priority ASC, id ASC
        ");

        $unassigned = $this->db->fetchAll("
            SELECT id, clean_product_name, product_type 
            FROM invoice_items 
            WHERE brand = 'Other' OR category = 'Other / Unassigned' OR brand IS NULL OR category IS NULL
        ");

        $classifiedCount = 0;
        foreach ($unassigned as $item) {
            $text = $item['clean_product_name'];
            $newBrand = null;
            $newCat = null;

            foreach ($rules as $rule) {
                $pat = $rule['pattern'];
                $mType = strtoupper($rule['match_type'] ?? 'CONTAINS');
                $matched = false;

                if ($mType === 'EXACT' && strcasecmp($text, $pat) === 0) {
                    $matched = true;
                } elseif ($mType === 'REGEX' && @preg_match('/' . str_replace('/', '\/', $pat) . '/i', $text)) {
                    $matched = true;
                } elseif ($mType === 'CONTAINS' && stripos($text, $pat) !== false) {
                    $matched = true;
                }

                if ($matched) {
                    if (!empty($rule['brand'])) $newBrand = $rule['brand'];
                    if (!empty($rule['category'])) $newCat = $rule['category'];
                    break;
                }
            }

            if ($newBrand || $newCat) {
                $this->assignProductBrandCategory((int)$item['id'], $newBrand, $newCat);
                $classifiedCount++;
            }
        }
        return $classifiedCount;
    }

    /**
     * Re-sort all rental-related invoices or a specific invoice list
     */
    public function reSortInvoices(array $invoiceNumbers = []) {
        require_once __DIR__ . '/DataSorter.php';
        $sorter = new DataSorter($this->db);

        if (empty($invoiceNumbers)) {
            // Find all invoices with potential rental lines
            $rows = $this->db->fetchAll("
                SELECT DISTINCT invoice_number 
                FROM sales 
                WHERE total_amount > 0 AND (
                    item_description LIKE '%rent%' 
                    OR item_description LIKE '%hire%' 
                    OR item_description LIKE '%lease%'
                )
            ");
            $invoiceNumbers = array_column($rows, 'invoice_number');
        }

        $processed = 0;
        foreach ($invoiceNumbers as $invNum) {
            try {
                $parsed = $sorter->sortInvoice($invNum);
                $sorter->persistSortedData($parsed);
                $processed++;
            } catch (Exception $e) {
                error_log("reSortInvoices error for $invNum: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /* =========================================================================
       NEW STRATEGIC & REGULATORY BUSINESS REPORTS (2009–2026)
       ========================================================================= */

    /**
     * 1. Customer Lifetime Value (LTV) & Loyalty Matrix
     */
    public function getCustomerLTVReport($filters = [], $page = 1, $limit = 50) {
        $where = ["s.total_amount > 0"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(s.customer_name LIKE ? OR cp.sales_rep LIKE ?)";
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($filters['customer_type'])) {
            $where[] = "cp.customer_type = ?";
            $params[] = $filters['customer_type'];
        }
        if (!empty($filters['rep_code'])) {
            $where[] = "cp.sales_rep = ?";
            $params[] = $filters['rep_code'];
        }
        if (!empty($filters['tier'])) {
            if ($filters['tier'] === 'PLATINUM') {
                $havingTier = "HAVING lifetime_gross >= 20000000";
            } elseif ($filters['tier'] === 'GOLD') {
                $havingTier = "HAVING lifetime_gross >= 5000000 AND lifetime_gross < 20000000";
            } elseif ($filters['tier'] === 'SILVER') {
                $havingTier = "HAVING lifetime_gross >= 1000000 AND lifetime_gross < 5000000";
            } elseif ($filters['tier'] === 'BRONZE') {
                $havingTier = "HAVING lifetime_gross < 1000000";
            }
        }

        $whereClause = implode(' AND ', $where);

        // Sort handling
        $sort = $filters['sort'] ?? 'ltv_desc';
        $orderBy = "lifetime_gross DESC";
        if ($sort === 'ltv_asc') $orderBy = "lifetime_gross ASC";
        elseif ($sort === 'invoices_desc') $orderBy = "total_invoices DESC";
        elseif ($sort === 'tenure_desc') $orderBy = "tenure_years DESC";
        elseif ($sort === 'aov_desc') $orderBy = "avg_order_value DESC";
        elseif ($sort === 'name_asc') $orderBy = "s.customer_name ASC";

        // Aggregate query
        $subQuery = "
            SELECT 
                s.customer_name,
                cp.customer_type,
                cp.sales_rep,
                MIN(s.invoice_date) as first_invoice,
                MAX(s.invoice_date) as last_invoice,
                ROUND((julianday(MAX(s.invoice_date)) - julianday(MIN(s.invoice_date))) / 365.25, 1) as tenure_years,
                COUNT(DISTINCT s.invoice_number) as total_invoices,
                ROUND(SUM(s.base_value), 2) as lifetime_base,
                ROUND(SUM(s.vat_component), 2) as lifetime_vat,
                ROUND(SUM(s.total_amount), 2) as lifetime_gross,
                ROUND(SUM(s.total_amount) / NULLIF(COUNT(DISTINCT s.invoice_number), 0), 2) as avg_order_value,
                CASE 
                    WHEN SUM(s.total_amount) >= 20000000 THEN 'PLATINUM'
                    WHEN SUM(s.total_amount) >= 5000000 THEN 'GOLD'
                    WHEN SUM(s.total_amount) >= 1000000 THEN 'SILVER'
                    ELSE 'BRONZE'
                END as ltv_tier
            FROM sales s
            LEFT JOIN customer_profiles cp ON s.customer_name = cp.customer_name
            WHERE $whereClause
            GROUP BY s.customer_name
            " . ($havingTier ?? "") . "
        ";

        // Count total rows
        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM ($subQuery) t", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->db->fetchAll("$subQuery ORDER BY $orderBy LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

        // Portfolio Summary
        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as total_customers,
                SUM(lifetime_gross) as grand_lifetime_revenue,
                SUM(lifetime_base) as grand_lifetime_base,
                SUM(lifetime_vat) as grand_lifetime_vat,
                AVG(avg_order_value) as grand_aov,
                SUM(CASE WHEN ltv_tier = 'PLATINUM' THEN 1 ELSE 0 END) as platinum_count,
                SUM(CASE WHEN ltv_tier = 'GOLD' THEN 1 ELSE 0 END) as gold_count,
                SUM(CASE WHEN ltv_tier = 'SILVER' THEN 1 ELSE 0 END) as silver_count,
                SUM(CASE WHEN ltv_tier = 'BRONZE' THEN 1 ELSE 0 END) as bronze_count
            FROM ($subQuery) t
        ", $params) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'summary' => $summary
        ];
    }

    /**
     * 2. Account Churn & Reactivation Pipeline
     */
    public function getAccountChurnReport($filters = [], $page = 1, $limit = 50) {
        $where = ["s.total_amount > 0"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(s.customer_name LIKE ? OR cp.sales_rep LIKE ?)";
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($filters['rep_code'])) {
            $where[] = "cp.sales_rep = ?";
            $params[] = $filters['rep_code'];
        }

        $minDays = 90;
        if (!empty($filters['risk'])) {
            if ($filters['risk'] === 'DORMANT') $minDays = 365;
            elseif ($filters['risk'] === 'CRITICAL') $minDays = 180;
            elseif ($filters['risk'] === 'WATCHLIST') $minDays = 90;
        }

        $whereClause = implode(' AND ', $where);

        $subQuery = "
            SELECT 
                s.customer_name,
                cp.customer_type,
                cp.sales_rep,
                MAX(s.invoice_date) as last_order_date,
                CAST((julianday('now') - julianday(MAX(s.invoice_date))) as INTEGER) as days_inactive,
                COUNT(DISTINCT s.invoice_number) as historical_invoices,
                ROUND(SUM(s.base_value), 2) as historical_base,
                ROUND(SUM(s.vat_component), 2) as historical_vat,
                ROUND(SUM(s.total_amount), 2) as historical_gross,
                CASE 
                    WHEN (julianday('now') - julianday(MAX(s.invoice_date))) > 365 THEN 'DORMANT'
                    WHEN (julianday('now') - julianday(MAX(s.invoice_date))) > 180 THEN 'CRITICAL'
                    WHEN (julianday('now') - julianday(MAX(s.invoice_date))) > 90 THEN 'WATCHLIST'
                    ELSE 'ACTIVE'
                END as churn_risk
            FROM sales s
            LEFT JOIN customer_profiles cp ON s.customer_name = cp.customer_name
            WHERE $whereClause
            GROUP BY s.customer_name
            HAVING days_inactive >= $minDays
        ";

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM ($subQuery) t", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->db->fetchAll("$subQuery ORDER BY historical_gross DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as at_risk_accounts,
                SUM(historical_gross) as at_risk_historical_spend,
                SUM(CASE WHEN churn_risk = 'DORMANT' THEN 1 ELSE 0 END) as dormant_count,
                SUM(CASE WHEN churn_risk = 'CRITICAL' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN churn_risk = 'WATCHLIST' THEN 1 ELSE 0 END) as watchlist_count,
                AVG(days_inactive) as avg_inactivity_days
            FROM ($subQuery) t
        ", $params) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'summary' => $summary
        ];
    }

    /**
     * 3. Hardware End-of-Life (EOL) & Refresh Forecast
     */
    public function getHardwareEOLReport($filters = [], $page = 1, $limit = 50) {
        $where = ["h.serial_number IS NOT NULL AND h.serial_number != '' AND h.serial_number != 'UNASSIGNED'"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(h.serial_number LIKE ? OR h.product_name LIKE ? OR h.model_sku LIKE ? OR h.customer_name LIKE ? OR h.invoice_number LIKE ?)";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }
        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            $where[] = "h.brand = ?";
            $params[] = $filters['brand'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'EXPIRED') {
                $where[] = "h.warranty_expiry_date < date('now')";
            } elseif ($filters['status'] === 'EXPIRING_90D') {
                $where[] = "h.warranty_expiry_date >= date('now') AND h.warranty_expiry_date <= date('now', '+90 days')";
            } elseif ($filters['status'] === 'EXPIRING_30D') {
                $where[] = "h.warranty_expiry_date >= date('now') AND h.warranty_expiry_date <= date('now', '+30 days')";
            } elseif ($filters['status'] === 'ACTIVE') {
                $where[] = "h.warranty_expiry_date > date('now', '+90 days')";
            }
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM hardware_assets h WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->db->fetchAll("
            SELECT 
                h.id,
                h.serial_number,
                h.model_sku,
                h.product_name,
                h.brand,
                h.customer_name,
                h.invoice_number,
                h.warranty_start_date,
                h.warranty_expiry_date,
                h.warranty_months,
                CAST(ROUND(julianday(h.warranty_expiry_date) - julianday('now')) AS INTEGER) as days_remaining,
                CASE 
                    WHEN h.warranty_expiry_date < date('now') THEN 'EXPIRED'
                    WHEN julianday(h.warranty_expiry_date) - julianday('now') <= 30 THEN 'EXPIRING_30D'
                    WHEN julianday(h.warranty_expiry_date) - julianday('now') <= 90 THEN 'EXPIRING_90D'
                    ELSE 'ACTIVE'
                END as eol_status,
                (SELECT s.invoice_date FROM sales s WHERE s.invoice_number = h.invoice_number LIMIT 1) as invoice_date,
                (SELECT s.total_amount FROM sales s WHERE s.invoice_number = h.invoice_number LIMIT 1) as original_invoice_val
            FROM hardware_assets h
            WHERE $whereClause
            ORDER BY h.warranty_expiry_date ASC, h.id DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as total_tracked_assets,
                SUM(CASE WHEN warranty_expiry_date < date('now') THEN 1 ELSE 0 END) as total_expired,
                SUM(CASE WHEN warranty_expiry_date >= date('now') AND warranty_expiry_date <= date('now', '+90 days') THEN 1 ELSE 0 END) as expiring_90d,
                SUM(CASE WHEN warranty_expiry_date >= date('now') AND warranty_expiry_date <= date('now', '+30 days') THEN 1 ELSE 0 END) as expiring_30d,
                SUM(CASE WHEN warranty_expiry_date > date('now', '+90 days') THEN 1 ELSE 0 END) as active_assets
            FROM hardware_assets
            WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'
        ") ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'summary' => $summary
        ];
    }

    /**
     * 4. Maintenance Contracts & SLA Renewals
     */
    public function getContractsRenewalReport($filters = [], $page = 1, $limit = 50) {
        $where = ["1=1"];
        $params = [];

        // 1. Expiration Window Range Filter (Default: pm_90d = -90 days to +90 days)
        $range = $filters['range'] ?? 'pm_90d';
        if ($range === 'pm_90d') {
            $where[] = "sub.period_end_date BETWEEN date('now', '-90 days') AND date('now', '+90 days')";
        } elseif ($range === 'due_30d') {
            $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+30 days')";
        } elseif ($range === 'due_60d') {
            $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+60 days')";
        } elseif ($range === 'due_90d') {
            $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+90 days')";
        } elseif ($range === 'due_180d') {
            $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+180 days')";
        } elseif ($range === 'post_30d') {
            $where[] = "sub.period_end_date BETWEEN date('now', '-30 days') AND date('now', '-1 day')";
        } elseif ($range === 'post_90d') {
            $where[] = "sub.period_end_date BETWEEN date('now', '-90 days') AND date('now', '-1 day')";
        } elseif ($range === 'overdue') {
            $where[] = "sub.period_end_date < date('now')";
        } elseif ($range === 'upcoming') {
            $where[] = "sub.period_end_date >= date('now')";
        } elseif ($range === 'custom' && !empty($filters['date_from']) && !empty($filters['date_to'])) {
            $where[] = "sub.period_end_date BETWEEN ? AND ?";
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
        }

        // 2. Service Offering / Category Filter
        $category = $filters['category'] ?? 'all';
        if ($category === 'acronis') {
            $where[] = "(sub.software_name LIKE '%Acronis%' OR sub.edition_tier LIKE '%Acronis%')";
        } elseif ($category === 'ma') {
            $where[] = "(sub.software_name LIKE '%Maintenance%' OR sub.edition_tier LIKE '%Maintenance%' OR sub.software_name LIKE '%SLA%')";
        } elseif ($category === 'hosting') {
            $where[] = "(sub.software_name LIKE '%Hosting%' OR sub.software_name LIKE '%Domain%' OR sub.edition_tier LIKE '%Hosting%')";
        } elseif ($category === 'licenses') {
            $where[] = "(sub.software_name LIKE '%License%' OR sub.software_name LIKE '%ESET%' OR sub.software_name LIKE '%McAfee%' OR sub.software_name LIKE '%DrayTek%' OR sub.software_name LIKE '%Microsoft%' OR sub.software_name LIKE '%Office%')";
        }

        // 3. Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'OVERDUE') {
                $where[] = "sub.period_end_date < date('now')";
            } elseif ($filters['status'] === 'DUE_SOON') {
                $where[] = "sub.period_end_date >= date('now') AND sub.period_end_date <= date('now', '+30 days')";
            } elseif ($filters['status'] === 'ACTIVE') {
                $where[] = "sub.period_end_date > date('now', '+30 days')";
            }
        }

        // 4. Free-text Search
        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(sub.invoice_number LIKE ? OR sub.customer_name LIKE ? OR sub.end_customer LIKE ? OR sub.software_name LIKE ? OR sub.edition_tier LIKE ?)";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }

        $whereClause = implode(' AND ', $where);

        // Sorting
        $sort = $filters['sort'] ?? 'expiry_asc';
        $orderBy = "sub.period_end_date ASC, sub.id DESC";
        if ($sort === 'expiry_desc') {
            $orderBy = "sub.period_end_date DESC, sub.id DESC";
        } elseif ($sort === 'value_desc') {
            $orderBy = "sub.renewal_opportunity_value DESC, sub.period_end_date ASC";
        } elseif ($sort === 'customer_asc') {
            $orderBy = "sub.customer_name ASC, sub.period_end_date ASC";
        }

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM software_subscriptions sub WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->db->fetchAll("
            SELECT 
                sub.id,
                sub.invoice_number,
                sub.customer_name,
                sub.end_customer,
                sub.software_name,
                sub.edition_tier,
                sub.license_seats,
                sub.period_start_date,
                sub.period_end_date,
                sub.term_months,
                sub.renewal_opportunity_value,
                CAST(ROUND(julianday(sub.period_end_date) - julianday('now')) AS INTEGER) as days_remaining,
                CASE 
                    WHEN sub.period_end_date < date('now') THEN 'OVERDUE'
                    WHEN julianday(sub.period_end_date) - julianday('now') <= 30 THEN 'DUE_SOON'
                    ELSE 'ACTIVE'
                END as dynamic_status,
                CASE
                    WHEN sub.software_name LIKE '%Acronis%' OR sub.edition_tier LIKE '%Acronis%' THEN 'Acronis'
                    WHEN sub.software_name LIKE '%Maintenance%' OR sub.edition_tier LIKE '%Maintenance%' OR sub.software_name LIKE '%SLA%' THEN 'MA / SLA'
                    WHEN sub.software_name LIKE '%Hosting%' OR sub.software_name LIKE '%Domain%' OR sub.edition_tier LIKE '%Hosting%' THEN 'Hosting'
                    ELSE 'License'
                END as category_label
            FROM software_subscriptions sub
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        // Calculate KPI Summary for the current filtered window
        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as total_contracts,
                COALESCE(SUM(renewal_opportunity_value), 0) as total_opportunity_value,
                SUM(CASE WHEN period_end_date < date('now') THEN 1 ELSE 0 END) as overdue_count,
                COALESCE(SUM(CASE WHEN period_end_date < date('now') THEN renewal_opportunity_value ELSE 0 END), 0) as overdue_value,
                SUM(CASE WHEN period_end_date >= date('now') AND period_end_date <= date('now', '+30 days') THEN 1 ELSE 0 END) as due_30d_count,
                COALESCE(SUM(CASE WHEN period_end_date >= date('now') AND period_end_date <= date('now', '+30 days') THEN renewal_opportunity_value ELSE 0 END), 0) as due_30d_value,
                SUM(CASE WHEN period_end_date > date('now', '+30 days') AND period_end_date <= date('now', '+90 days') THEN 1 ELSE 0 END) as due_90d_count,
                COALESCE(SUM(CASE WHEN period_end_date > date('now', '+30 days') AND period_end_date <= date('now', '+90 days') THEN renewal_opportunity_value ELSE 0 END), 0) as due_90d_value
            FROM software_subscriptions sub
            WHERE $whereClause
        ", $params) ?: [
            'total_contracts' => 0,
            'total_opportunity_value' => 0,
            'overdue_count' => 0,
            'overdue_value' => 0,
            'due_30d_count' => 0,
            'due_30d_value' => 0,
            'due_90d_count' => 0,
            'due_90d_value' => 0
        ];

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'summary' => $summary
        ];
    }

    /**
     * 5. Rental Fleet Utilization & Commercial Yield
     */
    public function getRentalROIReport($filters = [], $page = 1, $limit = 50) {
        $where = ["ii.product_type = 'RENTAL'"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(ii.invoice_number LIKE ? OR ii.customer_name LIKE ? OR ii.clean_product_name LIKE ?)";
            $params = array_merge($params, [$s, $s, $s]);
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'ACTIVE') {
                $where[] = "(julianday('now') - julianday(ii.invoice_date)) <= 35";
            } elseif ($filters['status'] === 'OVERDUE') {
                $where[] = "(julianday('now') - julianday(ii.invoice_date)) > 35 AND (julianday('now') - julianday(ii.invoice_date)) <= 60";
            } elseif ($filters['status'] === 'SUSPENDED') {
                $where[] = "(julianday('now') - julianday(ii.invoice_date)) > 60";
            }
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM invoice_items ii WHERE $whereClause", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->db->fetchAll("
            SELECT 
                ii.id,
                ii.invoice_number,
                ii.customer_name,
                ii.invoice_date,
                ii.clean_product_name,
                ii.brand_category,
                ii.quantity,
                ii.unit_price,
                ii.base_value,
                ii.vat_component,
                ii.total_amount,
                CAST(ROUND(julianday('now') - julianday(ii.invoice_date)) AS INTEGER) as days_since_billed,
                CASE 
                    WHEN (julianday('now') - julianday(ii.invoice_date)) <= 35 THEN 'ACTIVE'
                    WHEN (julianday('now') - julianday(ii.invoice_date)) <= 60 THEN 'OVERDUE'
                    ELSE 'SUSPENDED'
                END as rental_status,
                (
                    SELECT GROUP_CONCAT(ha.serial_number, ', ') 
                    FROM hardware_assets ha 
                    WHERE ha.invoice_item_id = ii.id AND ha.serial_number != 'UNASSIGNED'
                ) as serial_numbers
            FROM invoice_items ii
            WHERE $whereClause
            ORDER BY ii.invoice_date DESC, ii.id DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as total_deployments,
                COUNT(DISTINCT customer_name) as unique_clients,
                SUM(total_amount) as total_rental_volume,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) <= 35 THEN base_value ELSE 0 END) as active_mrr,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) <= 35 THEN 1 ELSE 0 END) as active_deployments,
                SUM(CASE WHEN (julianday('now') - julianday(invoice_date)) > 35 THEN 1 ELSE 0 END) as delinquent_count,
                (SELECT COUNT(*) FROM hardware_assets WHERE is_rental = 1) as dedicated_fleet_units
            FROM invoice_items
            WHERE product_type = 'RENTAL'
        ") ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'summary' => $summary
        ];
    }

    /**
     * 6. Brand & Category Performance (2009–2026)
     */
    public function getBrandGrowthReport($filters = []) {
        $where = ["ii.total_amount > 0"];
        $params = [];

        if (!empty($filters['brand']) && $filters['brand'] !== 'ALL') {
            $where[] = "ii.brand = ?";
            $params[] = $filters['brand'];
        }

        if (!empty($filters['category']) && $filters['category'] !== 'ALL') {
            $where[] = "ii.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "ii.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "ii.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $rows = $this->db->fetchAll("
            SELECT 
                COALESCE(NULLIF(ii.brand, ''), 'Other') as brand_name,
                mb.code as brand_code,
                COALESCE(mb.color, '#2563eb') as brand_color,
                COUNT(DISTINCT ii.invoice_number) as total_invoices,
                COUNT(DISTINCT ii.customer_name) as client_reach,
                SUM(ii.quantity) as total_units_sold,
                ROUND(SUM(ii.base_value), 2) as lifetime_base_revenue,
                ROUND(SUM(ii.vat_component), 2) as lifetime_vat_revenue,
                ROUND(SUM(ii.total_amount), 2) as lifetime_gross_revenue,
                ROUND(SUM(CASE WHEN ii.invoice_date < '2023-01-01' THEN ii.total_amount ELSE 0 END), 2) as historical_pre_2023,
                ROUND(SUM(CASE WHEN ii.invoice_date >= '2023-01-01' THEN ii.total_amount ELSE 0 END), 2) as modern_post_2023,
                ROUND(AVG(ii.total_amount), 2) as avg_line_yield
            FROM invoice_items ii
            LEFT JOIN master_brands mb ON mb.name = ii.brand
            WHERE $whereClause
            GROUP BY brand_name
            ORDER BY lifetime_gross_revenue DESC
        ", $params);

        $totalRevenue = array_sum(array_column($rows, 'lifetime_gross_revenue'));
        foreach ($rows as &$r) {
            $r['revenue_share_pct'] = $totalRevenue > 0 ? round(($r['lifetime_gross_revenue'] / $totalRevenue) * 100, 1) : 0;
            $pre = (float)$r['historical_pre_2023'];
            $post = (float)$r['modern_post_2023'];
            $r['growth_trajectory'] = $post > $pre ? 'EXPANDING' : 'DECLINING';
        }

        $summary = [
            'total_brands' => count($rows),
            'total_gross_portfolio' => $totalRevenue,
            'total_units_dispatched' => array_sum(array_column($rows, 'total_units_sold')),
            'total_client_reach' => array_sum(array_column($rows, 'client_reach')),
            'modern_sales_volume' => array_sum(array_column($rows, 'modern_post_2023'))
        ];

        return [
            'rows' => $rows,
            'summary' => $summary
        ];
    }

    public function getCategoryPerformanceReport($filters = []) {
        $where = ["ii.total_amount > 0"];
        $params = [];

        if (!empty($filters['category']) && $filters['category'] !== 'ALL') {
            $where[] = "ii.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['brand']) && $filters['brand'] !== 'ALL') {
            $where[] = "ii.brand = ?";
            $params[] = $filters['brand'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "ii.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "ii.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $rows = $this->db->fetchAll("
            SELECT 
                COALESCE(NULLIF(ii.category, ''), 'Other / Unassigned') as category_name,
                mc.code as category_code,
                COALESCE(mc.color, '#059669') as category_color,
                COUNT(DISTINCT ii.invoice_number) as total_invoices,
                COUNT(DISTINCT ii.customer_name) as client_reach,
                SUM(ii.quantity) as total_units_sold,
                ROUND(SUM(ii.base_value), 2) as lifetime_base_revenue,
                ROUND(SUM(ii.vat_component), 2) as lifetime_vat_revenue,
                ROUND(SUM(ii.total_amount), 2) as lifetime_gross_revenue,
                ROUND(SUM(CASE WHEN ii.invoice_date < '2023-01-01' THEN ii.total_amount ELSE 0 END), 2) as historical_pre_2023,
                ROUND(SUM(CASE WHEN ii.invoice_date >= '2023-01-01' THEN ii.total_amount ELSE 0 END), 2) as modern_post_2023,
                ROUND(AVG(ii.total_amount), 2) as avg_line_yield
            FROM invoice_items ii
            LEFT JOIN master_categories mc ON mc.name = ii.category
            WHERE $whereClause
            GROUP BY category_name
            ORDER BY lifetime_gross_revenue DESC
        ", $params);

        $totalRevenue = array_sum(array_column($rows, 'lifetime_gross_revenue'));
        foreach ($rows as &$r) {
            $r['revenue_share_pct'] = $totalRevenue > 0 ? round(($r['lifetime_gross_revenue'] / $totalRevenue) * 100, 1) : 0;
            $pre = (float)$r['historical_pre_2023'];
            $post = (float)$r['modern_post_2023'];
            $r['growth_trajectory'] = $post > $pre ? 'EXPANDING' : 'DECLINING';
        }

        $summary = [
            'total_categories' => count($rows),
            'total_gross_portfolio' => $totalRevenue,
            'total_units_dispatched' => array_sum(array_column($rows, 'total_units_sold')),
            'total_client_reach' => array_sum(array_column($rows, 'client_reach')),
            'modern_sales_volume' => array_sum(array_column($rows, 'modern_post_2023'))
        ];

        return [
            'rows' => $rows,
            'summary' => $summary
        ];
    }

    public function getBrandCategoryMatrixReport($filters = []) {
        $where = ["ii.total_amount > 0"];
        $params = [];

        if (!empty($filters['brand']) && $filters['brand'] !== 'ALL') {
            $where[] = "ii.brand = ?";
            $params[] = $filters['brand'];
        }
        if (!empty($filters['category']) && $filters['category'] !== 'ALL') {
            $where[] = "ii.category = ?";
            $params[] = $filters['category'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll("
            SELECT 
                COALESCE(NULLIF(ii.brand, ''), 'Other') as brand_name,
                COALESCE(NULLIF(ii.category, ''), 'Other / Unassigned') as category_name,
                mb.color as brand_color,
                mc.color as category_color,
                COUNT(DISTINCT ii.invoice_number) as invoice_count,
                SUM(ii.quantity) as units_sold,
                ROUND(SUM(ii.total_amount), 2) as gross_revenue
            FROM invoice_items ii
            LEFT JOIN master_brands mb ON mb.name = ii.brand
            LEFT JOIN master_categories mc ON mc.name = ii.category
            WHERE $whereClause
            GROUP BY brand_name, category_name
            ORDER BY gross_revenue DESC
        ", $params);
    }

    /**
     * 7. Working Capital & DSO Collection Velocity
     */
    public function getDSOTrendsReport($year = 'all', $filters = []) {
        $where = ["s.total_amount > 0"];
        $params = [];

        if ($year !== 'all' && !empty($year)) {
            $where[] = "strftime('%Y', s.invoice_date) = ?";
            $params[] = (string)$year;
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(s.customer_name LIKE ? OR s.sales_rep_code LIKE ?)";
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);

        $rows = $this->db->fetchAll("
            SELECT 
                s.customer_name,
                cp.customer_type,
                COALESCE(m.rep_name, s.sales_rep_code) as sales_rep,
                COUNT(DISTINCT s.invoice_number) as invoice_count,
                SUM(s.total_amount) as gross_billed,
                SUM(CASE WHEN s.paid_date IS NOT NULL AND s.paid_date != '' THEN s.total_amount ELSE 0 END) as collected_amount,
                SUM(CASE WHEN s.paid_date IS NULL OR s.paid_date = '' THEN s.total_amount ELSE 0 END) as outstanding_amount,
                ROUND(AVG(CASE WHEN s.days_to_pay IS NOT NULL THEN s.days_to_pay ELSE (julianday('now') - julianday(s.invoice_date)) END), 0) as avg_dso_days,
                MAX(CASE WHEN s.days_to_pay IS NOT NULL THEN s.days_to_pay ELSE (julianday('now') - julianday(s.invoice_date)) END) as max_dso_days
            FROM sales s
            LEFT JOIN customer_profiles cp ON s.customer_name = cp.customer_name
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            WHERE $whereClause
            GROUP BY s.customer_name
            ORDER BY outstanding_amount DESC, avg_dso_days DESC
        ", $params);

        foreach ($rows as &$r) {
            $gross = (float)$r['gross_billed'];
            $collected = (float)$r['collected_amount'];
            $r['collection_rate_pct'] = $gross > 0 ? round(($collected / $gross) * 100, 1) : 0;
            $dso = (int)$r['avg_dso_days'];
            if ($dso > 90) $r['risk_badge'] = 'CRITICAL';
            elseif ($dso > 60) $r['risk_badge'] = 'DELAYED';
            elseif ($dso > 30) $r['risk_badge'] = 'NORMAL';
            else $r['risk_badge'] = 'EXCELLENT';
        }

        $summary = [
            'total_accounts' => count($rows),
            'grand_gross_billed' => array_sum(array_column($rows, 'gross_billed')),
            'grand_collected' => array_sum(array_column($rows, 'collected_amount')),
            'grand_outstanding' => array_sum(array_column($rows, 'outstanding_amount')),
            'grand_avg_dso' => count($rows) > 0 ? round(array_sum(array_column($rows, 'avg_dso_days')) / count($rows)) : 0
        ];

        return [
            'rows' => $rows,
            'summary' => $summary
        ];
    }

    /**
     * 8. Statutory Tax & IRD Audit Ledger
     */
    public function getTaxAuditReport($year = 'all', $month = 'all', $page = 1, $limit = 50) {
        $where = ["1=1"];
        $params = [];

        if ($year !== 'all' && !empty($year)) {
            $where[] = "strftime('%Y', s.invoice_date) = ?";
            $params[] = (string)$year;
        }
        if ($month !== 'all' && !empty($month)) {
            $mPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
            $where[] = "strftime('%m', s.invoice_date) = ?";
            $params[] = $mPadded;
        }

        $whereClause = implode(' AND ', $where);

        $subQuery = "
            SELECT 
                s.invoice_number,
                s.invoice_type,
                MIN(s.invoice_date) as invoice_date,
                s.customer_name,
                COALESCE(
                    (SELECT s2.vat_treatment FROM sales s2 WHERE s2.invoice_number = s.invoice_number AND s2.vat_treatment != 'VAT_EXEMPT' LIMIT 1),
                    (SELECT ii.vat_treatment FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number LIMIT 1),
                    CASE WHEN SUM(s.vat_component) > 0 THEN 'PLUS_VAT' ELSE 'VAT_EXEMPT' END
                ) as vat_treatment,
                SUM(s.base_value) as taxable_base,
                SUM(s.vat_component) as vat_18_component,
                SUM(s.total_amount) as gross_total,
                MAX(s.paid_date) as paid_date
            FROM sales s
            WHERE $whereClause
            GROUP BY s.invoice_number
        ";

        $countRow = $this->db->fetch("SELECT COUNT(*) as c FROM ($subQuery) t", $params);
        $total = (int)($countRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->db->fetchAll("$subQuery ORDER BY invoice_date DESC, s.invoice_number DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

        $summary = $this->db->fetch("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(taxable_base) as total_taxable_base,
                SUM(vat_18_component) as total_vat_assessed,
                SUM(gross_total) as grand_gross_total,
                SUM(CASE WHEN vat_treatment = 'PLUS_VAT' THEN vat_18_component ELSE 0 END) as statutory_plus_vat,
                SUM(CASE WHEN vat_treatment = 'VAT_INCLUSIVE' THEN gross_total ELSE 0 END) as inclusive_sales_volume
            FROM ($subQuery) t
        ", $params) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'summary' => $summary
        ];
    }

    /**
     * 9. Warranty & Serial Number Lifecycle Lookup
     * Allows lookup by full or partial serial number, returns matching hardware assets,
     * associated invoice history, real-time warranty status/countdown, and linked maintenance agreements.
     */
    public function lookupWarrantySerial($query = '', $statusFilter = 'all', $limit = 50) {
        $cleanQuery = trim($query);
        $cleanLimit = ($limit === 'all' || (int)$limit >= 999999) ? 999999 : max(1, min(100, (int)$limit));
        
        $params = [];
        $whereConditions = ["ha.serial_number IS NOT NULL AND ha.serial_number != '' AND ha.serial_number != 'UNASSIGNED'"];
        
        if (!empty($cleanQuery)) {
            $searchWild = '%' . $cleanQuery . '%';
            $whereConditions[] = "(ha.serial_number LIKE ? OR ha.parent_serial_number LIKE ? OR ha.product_name LIKE ? OR ha.model_sku LIKE ? OR ha.customer_name LIKE ? OR ha.end_customer LIKE ? OR ha.invoice_number LIKE ?)";
            $params = array_merge($params, [$searchWild, $searchWild, $searchWild, $searchWild, $searchWild, $searchWild, $searchWild]);
        }
        
        $whereSql = "WHERE " . implode(" AND ", $whereConditions);
        
        // Group by serial_number to get primary record with latest warranty info
        $sql = "
            SELECT 
                ha.id,
                ha.serial_number,
                MAX(ha.product_name) as product_name,
                MAX(ha.brand) as brand,
                MAX(ha.model_sku) as model_sku,
                MAX(ha.parent_serial_number) as parent_serial_number,
                MAX(ha.warranty_type) as warranty_type,
                MAX(ha.warranty_months) as warranty_months,
                MIN(ha.warranty_start_date) as initial_start_date,
                MAX(ha.warranty_start_date) as latest_start_date,
                MAX(ha.warranty_expiry_date) as warranty_expiry_date,
                MAX(ha.customer_name) as current_customer,
                MAX(ha.end_customer) as end_customer,
                GROUP_CONCAT(DISTINCT ha.customer_name) as all_customers,
                GROUP_CONCAT(DISTINCT ha.invoice_number) as asset_invoices,
                MAX(ha.notes) as notes,
                MAX(ha.is_rental) as is_rental
            FROM hardware_assets ha
            $whereSql
            GROUP BY ha.serial_number
            ORDER BY 
                CASE 
                    WHEN ha.serial_number = ? THEN 0
                    WHEN ha.serial_number LIKE ? THEN 1
                    ELSE 2
                END,
                MAX(ha.warranty_expiry_date) DESC
            LIMIT $cleanLimit
        ";
        
        $orderParams = !empty($cleanQuery) ? [$cleanQuery, $cleanQuery . '%'] : ['', ''];
        $assets = $this->db->fetchAll($sql, array_merge($params, $orderParams));
        
        // If cleanQuery provided and fewer than limit results, also search sales item_description
        $existingSerials = array_flip(array_column($assets, 'serial_number'));
        if (!empty($cleanQuery) && count($assets) < $cleanLimit) {
            $salesMatches = $this->db->fetchAll("
                SELECT 
                    s.id,
                    s.invoice_number,
                    s.customer_name,
                    s.end_customer,
                    s.item_description as product_name,
                    s.product_category as brand,
                    s.invoice_date,
                    s.sales_rep_code,
                    s.total_amount as invoice_item_amount,
                    s.paid_date
                FROM sales s
                WHERE s.item_description LIKE ? 
                  AND s.total_amount > 0 
                  AND s.item_description NOT LIKE 'End Customer%'
                ORDER BY s.invoice_date DESC
                LIMIT 25
            ", ['%' . $cleanQuery . '%']);
            
            foreach ($salesMatches as $sm) {
                $extractedSerial = $cleanQuery;
                if (preg_match('/(?:S\/N|SN|Serial(?: No)?[:\s]+)([A-Za-z0-9\-_]+)/i', $sm['product_name'], $m)) {
                    $extractedSerial = trim($m[1]);
                }
                if (!isset($existingSerials[$extractedSerial])) {
                    $existingSerials[$extractedSerial] = true;
                    $assets[] = [
                        'id' => null,
                        'serial_number' => $extractedSerial,
                        'product_name' => $sm['product_name'],
                        'brand' => $sm['brand'] ?: 'Hardware',
                        'model_sku' => '',
                        'parent_serial_number' => '',
                        'warranty_type' => 'Standard',
                        'warranty_months' => 36,
                        'initial_start_date' => $sm['invoice_date'],
                        'latest_start_date' => $sm['invoice_date'],
                        'warranty_expiry_date' => !empty($sm['invoice_date']) ? date('Y-m-d', strtotime('+36 months', strtotime($sm['invoice_date']))) : null,
                        'current_customer' => $sm['customer_name'],
                        'end_customer' => $sm['end_customer'] ?? '',
                        'all_customers' => $sm['customer_name'],
                        'asset_invoices' => $sm['invoice_number'],
                        'notes' => 'Extracted from invoice description',
                        'is_rental' => 0
                    ];
                }
            }
        }
        
        $today = date('Y-m-d');
        $todayTs = strtotime($today);
        $results = [];
        
        foreach ($assets as $asset) {
            $serial = $asset['serial_number'];
            $startDate = $asset['latest_start_date'] ?: $asset['initial_start_date'];
            $expiryDate = $asset['warranty_expiry_date'];
            
            if (empty($expiryDate) && !empty($startDate) && !empty($asset['warranty_months'])) {
                $expiryDate = date('Y-m-d', strtotime('+' . (int)$asset['warranty_months'] . ' months', strtotime($startDate)));
            }
            
            $status = 'UNKNOWN';
            $daysDiff = null;
            $statusBadgeClass = 'secondary';
            $statusLabel = 'Status Unknown';
            $progressPct = 100;
            
            if (!empty($expiryDate)) {
                $expiryTs = strtotime($expiryDate);
                $daysDiff = (int)ceil(($expiryTs - $todayTs) / 86400);
                
                if ($daysDiff > 60) {
                    $status = 'ACTIVE';
                    $statusBadgeClass = 'success';
                    $statusLabel = 'Active (' . number_format($daysDiff) . ' days left)';
                } elseif ($daysDiff >= 0) {
                    $status = 'EXPIRING_SOON';
                    $statusBadgeClass = 'warning';
                    $statusLabel = 'Expiring Soon (' . number_format($daysDiff) . ' days left)';
                } else {
                    $status = 'EXPIRED';
                    $statusBadgeClass = 'danger';
                    $absDays = abs($daysDiff);
                    $statusLabel = 'Expired (' . number_format($absDays) . ' days ago)';
                }
                
                if (!empty($startDate)) {
                    $startTs = strtotime($startDate);
                    $totalSpan = max(1, $expiryTs - $startTs);
                    $elapsed = max(0, $todayTs - $startTs);
                    $progressPct = max(0, min(100, round(($elapsed / $totalSpan) * 100)));
                }
            }
            
            // Status filter check
            if ($statusFilter !== 'all') {
                if ($statusFilter === 'active' && $status !== 'ACTIVE') continue;
                if ($statusFilter === 'expiring_soon' && $status !== 'EXPIRING_SOON') continue;
                if ($statusFilter === 'expired' && $status !== 'EXPIRED') continue;
            }
            
            // Invoices where this serial number is referenced
            $invoices = $this->getInvoicesForSerial($serial, $asset['asset_invoices']);
            
            // Maintenance contracts for customer(s)
            $customers = array_unique(array_filter(explode(',', (string)$asset['all_customers'])));
            if (empty($customers) && !empty($asset['current_customer'])) {
                $customers = [$asset['current_customer']];
            }
            
            $maintenanceContracts = [];
            foreach ($customers as $cust) {
                $custContracts = $this->getMaintenanceContractsForCustomer(trim($cust));
                foreach ($custContracts as $cc) {
                    $maintenanceContracts[$cc['invoice_number'] . '_' . $cc['contract_title']] = $cc;
                }
            }
            $maintenanceContracts = array_values($maintenanceContracts);
            
            if ($statusFilter === 'has_maintenance' && empty($maintenanceContracts)) {
                continue;
            }
            
            $asset['computed_status'] = $status;
            $asset['days_diff'] = $daysDiff;
            $asset['status_badge_class'] = $statusBadgeClass;
            $asset['status_label'] = $statusLabel;
            $asset['progress_pct'] = $progressPct;
            $asset['computed_expiry_date'] = $expiryDate;
            $asset['invoices'] = $invoices;
            $asset['maintenance_contracts'] = $maintenanceContracts;
            
            $results[] = $asset;
        }
        
        return $results;
    }

    /**
     * Find all invoices where a serial number appears
     */
    public function getInvoicesForSerial($serial, $assetInvoicesStr = '') {
        $invList = array_unique(array_filter(explode(',', (string)$assetInvoicesStr)));
        $serialWild = '%' . trim($serial) . '%';
        
        $where = ["s.item_description LIKE ?"];
        $params = [$serialWild];
        
        if (!empty($invList)) {
            $placeholders = implode(',', array_fill(0, count($invList), '?'));
            $where[] = "s.invoice_number IN ($placeholders)";
            $params = array_merge($params, $invList);
        }
        
        $whereSql = implode(' OR ', $where);
        
        $sql = "
            SELECT 
                s.invoice_number,
                MIN(s.invoice_date) as invoice_date,
                s.customer_name,
                MAX(CASE WHEN s.item_description LIKE ? THEN s.item_description ELSE NULL END) as matching_line_desc,
                SUM(s.total_amount) as total_invoice_amount,
                MAX(s.paid_date) as paid_date,
                MAX(s.days_to_pay) as days_to_pay,
                MAX(s.sales_rep_code) as sales_rep_code,
                COALESCE(MAX(m.rep_name), MAX(s.sales_rep_code)) as rep_name
            FROM sales s
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            WHERE $whereSql
            GROUP BY s.invoice_number
            ORDER BY invoice_date DESC
        ";
        
        return $this->db->fetchAll($sql, array_merge([$serialWild], $params));
    }

    /**
     * Get maintenance agreements and SLAs for a customer
     */
    public function getMaintenanceContractsForCustomer($customerName) {
        $today = date('Y-m-d');
        $todayTs = strtotime($today);
        
        $subs = $this->db->fetchAll("
            SELECT 
                ss.id,
                ss.invoice_number,
                ss.customer_name,
                ss.software_name as contract_title,
                ss.edition_tier,
                ss.license_seats,
                ss.period_start_date,
                ss.period_end_date,
                ss.term_months,
                ss.renewal_status,
                ss.renewal_opportunity_value as contract_value
            FROM software_subscriptions ss
            WHERE ss.customer_name = ?
              AND (
                ss.edition_tier LIKE '%Maintenance%' 
                OR ss.software_name LIKE '%Maintenance%' 
                OR ss.software_name LIKE '%SLA%' 
                OR ss.software_name LIKE '%Support%'
              )
            ORDER BY ss.period_end_date DESC
        ", [$customerName]);
        
        $contracts = [];
        foreach ($subs as $sub) {
            $endDate = $sub['period_end_date'];
            $daysDiff = null;
            $statusLabel = $sub['renewal_status'] ?: 'RECORDED';
            $badgeClass = 'secondary';
            
            if (!empty($endDate)) {
                $endTs = strtotime($endDate);
                $daysDiff = (int)ceil(($endTs - $todayTs) / 86400);
                if ($daysDiff >= 0) {
                    $statusLabel = 'ACTIVE • ' . number_format($daysDiff) . ' days left';
                    $badgeClass = 'success';
                } else {
                    $statusLabel = 'EXPIRED • ' . number_format(abs($daysDiff)) . ' days ago';
                    $badgeClass = 'danger';
                }
            } elseif (strtoupper($sub['renewal_status']) === 'ACTIVE') {
                $statusLabel = 'ACTIVE';
                $badgeClass = 'success';
            }
            
            $sub['status_label'] = $statusLabel;
            $sub['badge_class'] = $badgeClass;
            $sub['days_diff'] = $daysDiff;
            $contracts[] = $sub;
        }
        
        return $contracts;
    }

    /**
     * Warranty & Asset Metrics
     */
    public function getWarrantySummaryMetrics() {
        $today = date('Y-m-d');
        $date60Days = date('Y-m-d', strtotime('+60 days'));
        
        $sql = "
            SELECT
                (SELECT COUNT(DISTINCT serial_number) FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED') as total_serials,
                (SELECT COUNT(DISTINCT serial_number) FROM hardware_assets WHERE warranty_expiry_date > ? AND serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED') as active_count,
                (SELECT COUNT(DISTINCT serial_number) FROM hardware_assets WHERE warranty_expiry_date >= ? AND warranty_expiry_date <= ? AND serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED') as expiring_count,
                (SELECT COUNT(DISTINCT serial_number) FROM hardware_assets WHERE warranty_expiry_date < ? AND serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED') as expired_count,
                (SELECT COUNT(*) FROM software_subscriptions WHERE edition_tier LIKE '%Maintenance%' OR software_name LIKE '%Maintenance%' OR software_name LIKE '%SLA%') as maintenance_count
        ";
        
        return $this->db->fetch($sql, [$date60Days, $today, $date60Days, $today]) ?: [
            'total_serials' => 0,
            'active_count' => 0,
            'expiring_count' => 0,
            'expired_count' => 0,
            'maintenance_count' => 0
        ];
    }

    /**
     * 10. Unpaid Invoices Report Sorted by Customer
     * Aggregates active post-2021 unpaid receivables by customer, sorted by customer name or balance,
     * with aging risk tiers, individual invoice breakdowns, and KPI summaries.
     */
    public function getUnpaidInvoicesByCustomerReport($filters = [], $page = 1, $limit = 50) {
        $today = date('Y-m-d');
        
        $whereConditions = [
            "s.invoice_type = 'Invoice'",
            "(s.paid_date IS NULL OR s.paid_date = '')",
            "s.total_amount > 0",
            "s.invoice_date > '2021-12-31'"
        ];
        $params = [];
        
        // Search filter (customer name or invoice number)
        if (!empty($filters['search'])) {
            $searchWild = '%' . trim($filters['search']) . '%';
            $whereConditions[] = "(s.customer_name LIKE ? OR s.invoice_number LIKE ?)";
            $params[] = $searchWild;
            $params[] = $searchWild;
        }
        
        // Sales rep filter
        if (!empty($filters['rep_code']) && $filters['rep_code'] !== 'all') {
            $whereConditions[] = "s.sales_rep_code = ?";
            $params[] = $filters['rep_code'];
        }
        
        // Customer type / channel filter
        if (!empty($filters['customer_type']) && $filters['customer_type'] !== 'all') {
            $whereConditions[] = "p.customer_type = ?";
            $params[] = $filters['customer_type'];
        }
        
        $whereSql = "WHERE " . implode(" AND ", $whereConditions);
        
        // 1. Group by customer to calculate customer totals and aging
        $customerSummarySql = "
            SELECT 
                s.customer_name,
                COALESCE(p.customer_type, 'End Customer') as customer_type,
                s.sales_rep_code,
                COALESCE(m.rep_name, s.sales_rep_code) as rep_name,
                COUNT(DISTINCT s.invoice_number) as unpaid_invoices_count,
                SUM(s.base_value) as total_base_due,
                SUM(s.vat_component) as total_vat_due,
                SUM(s.total_amount) as total_gross_due,
                MIN(s.invoice_date) as oldest_invoice_date,
                MAX(s.invoice_date) as newest_invoice_date,
                CAST((julianday('$today') - julianday(MIN(s.invoice_date))) AS INT) as max_aging_days,
                ROUND(AVG(CAST((julianday('$today') - julianday(s.invoice_date)) AS INT)), 0) as avg_aging_days
            FROM sales s
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            $whereSql
            GROUP BY s.customer_name
        ";
        
        $customers = $this->db->fetchAll($customerSummarySql, $params);
        
        // Aging bracket filter (applied to max_aging_days)
        $agingBracket = $filters['aging_bracket'] ?? 'all';
        if ($agingBracket !== 'all') {
            $customers = array_filter($customers, function($c) use ($agingBracket) {
                $days = (int)$c['max_aging_days'];
                if ($agingBracket === 'current') return $days <= 30;
                if ($agingBracket === '30_60') return $days > 30 && $days <= 60;
                if ($agingBracket === '60_90') return $days > 60 && $days <= 90;
                if ($agingBracket === 'over_90') return $days > 90;
                return true;
            });
            $customers = array_values($customers);
        }
        
        // Compute Risk Badges
        foreach ($customers as &$c) {
            $days = (int)$c['max_aging_days'];
            if ($days > 90) {
                $c['risk_badge'] = 'CRITICAL';
                $c['badge_class'] = 'danger';
            } elseif ($days > 60) {
                $c['risk_badge'] = 'OVERDUE';
                $c['badge_class'] = 'warning';
            } elseif ($days > 30) {
                $c['risk_badge'] = 'ATTENTION';
                $c['badge_class'] = 'info';
            } else {
                $c['risk_badge'] = 'CURRENT';
                $c['badge_class'] = 'success';
            }
        }
        unset($c);
        
        // Sorting
        $sort = $filters['sort'] ?? 'customer_asc';
        usort($customers, function($a, $b) use ($sort) {
            if ($sort === 'customer_desc') {
                return strcasecmp($b['customer_name'], $a['customer_name']);
            } elseif ($sort === 'balance_desc') {
                return $b['total_gross_due'] <=> $a['total_gross_due'];
            } elseif ($sort === 'balance_asc') {
                return $a['total_gross_due'] <=> $b['total_gross_due'];
            } elseif ($sort === 'aging_desc') {
                return $b['max_aging_days'] <=> $a['max_aging_days'];
            } elseif ($sort === 'count_desc') {
                return $b['unpaid_invoices_count'] <=> $a['unpaid_invoices_count'];
            }
            // default customer_asc
            return strcasecmp($a['customer_name'], $b['customer_name']);
        });
        
        // Overall Summary across all filtered customers
        $summary = [
            'total_customers' => count($customers),
            'total_unpaid_invoices' => array_sum(array_column($customers, 'unpaid_invoices_count')),
            'grand_gross_due' => array_sum(array_column($customers, 'total_gross_due')),
            'grand_base_due' => array_sum(array_column($customers, 'total_base_due')),
            'grand_vat_due' => array_sum(array_column($customers, 'total_vat_due')),
            'critical_amount' => 0,
            'critical_count' => 0,
            'overdue_amount' => 0,
            'overdue_count' => 0,
            'current_amount' => 0,
            'current_count' => 0
        ];
        
        foreach ($customers as $c) {
            $days = (int)$c['max_aging_days'];
            if ($days > 90) {
                $summary['critical_amount'] += $c['total_gross_due'];
                $summary['critical_count'] += $c['unpaid_invoices_count'];
            } elseif ($days > 30) {
                $summary['overdue_amount'] += $c['total_gross_due'];
                $summary['overdue_count'] += $c['unpaid_invoices_count'];
            } else {
                $summary['current_amount'] += $c['total_gross_due'];
                $summary['current_count'] += $c['unpaid_invoices_count'];
            }
        }
        
        // Pagination
        $totalCustomers = count($customers);
        $page = max(1, (int)$page);
        $limit = ($limit === 'all' || (int)$limit >= 999999 || (int)$limit <= 0) ? 999999 : max(10, min(500, (int)$limit));
        $totalPages = max(1, (int)ceil($totalCustomers / $limit));
        $offset = ($page - 1) * $limit;
        
        $pagedCustomers = array_slice($customers, $offset, $limit);
        
        // For each customer on current page, fetch individual unpaid invoices
        foreach ($pagedCustomers as &$cust) {
            $invSql = "
                SELECT 
                    s.invoice_number,
                    MIN(s.invoice_date) as invoice_date,
                    MAX(s.po_number) as po_number,
                    COUNT(*) as line_count,
                    GROUP_CONCAT(DISTINCT s.item_description) as items_summary,
                    SUM(s.base_value) as base_value,
                    SUM(s.vat_component) as vat_component,
                    SUM(s.total_amount) as total_amount,
                    CAST((julianday('$today') - julianday(MIN(s.invoice_date))) AS INT) as aging_days
                FROM sales s
                WHERE s.customer_name = ?
                  AND s.invoice_type = 'Invoice'
                  AND (s.paid_date IS NULL OR s.paid_date = '')
                  AND s.total_amount > 0
                  AND s.invoice_date > '2021-12-31'
                GROUP BY s.invoice_number
                ORDER BY s.invoice_date ASC, s.invoice_number ASC
            ";
            $custInvoices = $this->db->fetchAll($invSql, [$cust['customer_name']]);
            
            foreach ($custInvoices as &$inv) {
                $days = (int)$inv['aging_days'];
                if ($days > 90) {
                    $inv['aging_badge'] = 'danger';
                    $inv['aging_label'] = $days . 'd Overdue';
                } elseif ($days > 60) {
                    $inv['aging_badge'] = 'warning';
                    $inv['aging_label'] = $days . 'd Overdue';
                } elseif ($days > 30) {
                    $inv['aging_badge'] = 'info';
                    $inv['aging_label'] = $days . 'd Overdue';
                } else {
                    $inv['aging_badge'] = 'success';
                    $inv['aging_label'] = $days . 'd (Current)';
                }
            }
            $cust['invoices'] = $custInvoices;
        }
        unset($cust);
        
        return [
            'customers' => $pagedCustomers,
            'total' => $totalCustomers,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages,
            'summary' => $summary
        ];
    }
}

