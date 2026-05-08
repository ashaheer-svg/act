<?php
/**
 * Reports Class - Business Analytics & Reporting
 *
 * Generates reports for sales analysis, metrics, trends, etc.
 */

class Reports {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
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
                MIN(total_amount) as smallest_invoice
            FROM sales $where", $params
        );

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
    public function getCustomerYearlyPivot($year, $brand = null) {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStr = str_pad($m, 2, '0', STR_PAD_LEFT);
            $months[$m] = "SUM(CASE WHEN strftime('%m', invoice_date) = '$monthStr' THEN base_value ELSE 0 END) as month_$m";
        }
        
        $monthSql = implode(", ", $months);
        $params = [$year];
        $brandFilter = "";
        
        if ($brand) {
            $brandFilter = " AND product_category = ? ";
            $params[] = $brand;
        }
        
        return $this->db->fetchAll("
            SELECT 
                sales.customer_name,
                p.customer_type,
                COUNT(*) as total_volume,
                SUM(base_value) as total_revenue,
                SUM(gross_profit) as total_profit,
                (SELECT product_category FROM sales s2 WHERE s2.customer_name = sales.customer_name GROUP BY product_category ORDER BY COUNT(*) DESC LIMIT 1) as top_category,
                $monthSql
            FROM sales
            LEFT JOIN customer_profiles p ON sales.customer_name = p.customer_name
            WHERE strftime('%Y', invoice_date) = ? AND invoice_type = 'Invoice'
            $brandFilter
            GROUP BY sales.customer_name
            ORDER BY total_revenue DESC
        ", $params);
    }

    /**
     * Get unique product categories (brands)
     */
    public function getUniqueBrands() {
        return $this->db->fetchAll("SELECT DISTINCT product_category FROM sales ORDER BY product_category ASC");
    }

    /**
     * Get Brand/Category breakdown for each customer
     */
    public function getCustomerBrandBreakdown($year) {
        return $this->db->fetchAll("
            SELECT 
                customer_name,
                product_category,
                SUM(total_amount) as category_revenue,
                COUNT(*) as purchase_count
            FROM sales
            WHERE strftime('%Y', invoice_date) = ? AND invoice_type = 'Invoice'
            GROUP BY customer_name, product_category
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
                product_category,
                COUNT(*) as transaction_count,
                SUM(base_value) as revenue_base,
                SUM(vat_component) as vat_total,
                SUM(total_amount) as total_revenue,
                ROUND(SUM(total_amount) * 100.0 / NULLIF((SELECT SUM(total_amount) FROM sales $where), 0), 2) as percentage
            FROM sales $where
            GROUP BY product_category
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
        return $this->db->fetchAll(
            "SELECT DISTINCT product_category FROM sales WHERE product_category IS NOT NULL ORDER BY product_category ASC"
        );
    }
}
?>
