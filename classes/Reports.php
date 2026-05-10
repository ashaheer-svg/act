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
            WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?
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
                COUNT(*) as total_invoices,
                SUM(total_amount) as total_volume,
                SUM(CASE WHEN paid_date IS NOT NULL THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN paid_date IS NULL THEN 1 ELSE 0 END) as unpaid_count,
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
                SUM(total_amount) as total_value
            FROM sales
            WHERE customer_name = ? AND invoice_type = 'Invoice'
            GROUP BY item_description
            ORDER BY total_value DESC
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
                total_amount,
                (CASE 
                    WHEN paid_date IS NOT NULL THEN days_to_pay 
                    ELSE CAST((julianday(?) - julianday(invoice_date)) AS INT)
                END) as aging_days
            FROM sales
            $where
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
}
?>
