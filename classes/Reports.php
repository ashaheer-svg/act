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
        $where = "WHERE invoice_type = 'Invoice'";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $summary = $this->db->fetch(
            "SELECT
                COUNT(*) as total_invoices,
                COUNT(DISTINCT customer_name) as unique_customers,
                SUM(base_value) as total_revenue_base,
                SUM(vat_component) as total_vat,
                SUM(total_amount) as total_amount,
                AVG(total_amount) as avg_invoice_value,
                MAX(total_amount) as largest_invoice,
                MIN(total_amount) as smallest_invoice,
                (SELECT SUM(amount) FROM payments) as total_payments_received
            FROM sales $where", $params
        );

        $summary['total_outstanding'] = ($summary['total_amount'] ?? 0) - ($summary['total_payments_received'] ?? 0);

        // Format numbers
        foreach ($summary as $key => $value) {
            if (is_numeric($value) && strpos($value, '.') !== false) {
                $summary[$key] = round($value, 2);
            }
        }

        return $summary;
    }

    /**
     * Monthly sales report
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
                WHERE strftime('%Y', invoice_date) = ? AND invoice_type = 'Invoice'
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
            WHERE strftime('%Y', invoice_date) = ? AND invoice_type = 'Invoice'
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
            WHERE invoice_type = 'Invoice' 
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
                COUNT(*) as invoice_count,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total
            FROM sales
            WHERE invoice_type = 'Invoice' AND strftime('%Y', invoice_date) = ?
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
        $where = "WHERE invoice_type = 'Invoice'";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $customers = $this->db->fetchAll(
            "SELECT
                customer_name,
                COUNT(*) as invoice_count,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_invoice,
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
        $where = "WHERE invoice_type = 'Invoice'";
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
        $where = "WHERE invoice_type = 'Invoice'";
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
        $where = "WHERE invoice_type = 'Invoice'";
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
        $where = "WHERE invoice_type = 'Invoice'";
        $params = [];

        if ($dateFrom && $dateTo) {
            $where .= " AND invoice_date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        return $this->db->fetchAll(
            "SELECT
                invoice_date as date,
                COUNT(*) as invoice_count,
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
                SUM(CASE WHEN s.invoice_type = 'Invoice' THEN s.total_amount ELSE -s.total_amount END) as total_invoiced,
                COALESCE(p.total_paid, 0) as total_paid,
                (SUM(CASE WHEN s.invoice_type = 'Invoice' THEN s.total_amount ELSE -s.total_amount END) - COALESCE(p.total_paid, 0)) as balance,
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
            WHERE s.invoice_type = 'Invoice'
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
        $where = "WHERE s.invoice_type = 'Invoice' AND s.sales_rep_code IS NOT NULL AND s.sales_rep_code != ''";
        $params = [];

        if (!empty($year)) {
            $where .= " AND strftime('%Y', s.invoice_date) = ? ";
            $params[] = $year;
        }

        return $this->db->fetchAll("
            SELECT 
                s.sales_rep_code,
                COALESCE(m.rep_name, 'Sales Rep ' || s.sales_rep_code) as rep_name,
                COUNT(DISTINCT s.invoice_number) as invoice_count,
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
        $limit = max(10, min(200, (int)$limit));
        $offset = ($page - 1) * $limit;
        $totalPages = max(1, (int)ceil($totalCount / $limit));

        // Paginated invoice rows
        $dataSql = "
            SELECT 
                s.invoice_number,
                s.invoice_type,
                MIN(s.invoice_date) as invoice_date,
                s.customer_name,
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
                (SELECT ii.vat_treatment FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number LIMIT 1) as invoice_vat_treatment
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
                s.sales_rep_code,
                COALESCE(m.rep_name, s.sales_rep_code) as rep_name,
                MAX(s.po_number) as po_number,
                MAX(s.paid_date) as paid_date,
                MAX(s.days_to_pay) as days_to_pay,
                COUNT(*) as total_lines,
                SUM(s.quantity) as total_quantity,
                SUM(s.base_value) as total_base_value,
                SUM(s.vat_component) as total_vat,
                SUM(s.total_amount) as total_gross_amount,
                COALESCE((SELECT ii.vat_treatment FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number LIMIT 1), (SELECT s2.vat_treatment FROM sales s2 WHERE s2.invoice_number = s.invoice_number AND s2.vat_treatment != 'VAT_EXEMPT' LIMIT 1), 'VAT_INCLUSIVE') as vat_treatment
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
            SELECT id, item_description, product_category, quantity, base_value, vat_component, applied_tax_rate, total_amount, memo
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
            SELECT id, product_name, brand, model_sku, serial_number, warranty_type, warranty_months, warranty_start_date, warranty_expiry_date, warranty_status, parent_serial_number, notes
            FROM hardware_assets
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        // 6. Normalized Software Subscriptions & SaaS Licenses
        $subscriptions = $this->db->fetchAll("
            SELECT id, software_name, edition_tier, license_seats, period_start_date, period_end_date, term_months, renewal_status, renewal_opportunity_value
            FROM software_subscriptions
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        // 7. Normalized Commercial Line Items
        $items = $this->db->fetchAll("
            SELECT id, clean_product_name, product_type, brand_category, quantity, unit_price, base_value, vat_component, total_amount, vat_treatment
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
                    commercial_type = ?,
                    default_vat_treatment = ?,
                    priority = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [$pattern, $matchType, $masterSku, $canonicalName, $brand, $commercialType, $vatTreatment, $priority, $notes, $id]);
            return $id;
        } else {
            $this->db->execute("
                INSERT INTO product_mappings (
                    pattern, match_type, master_sku, canonical_name, brand,
                    commercial_type, default_vat_treatment, priority, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$pattern, $matchType, $masterSku, $canonicalName, $brand, $commercialType, $vatTreatment, $priority, $notes]);
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
}
?>
