<?php
/**
 * Exporter Class - Export reports to CSV/Excel
 *
 * Handles exporting data in various formats
 */

class Exporter {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Export report to CSV
     */
    public function exportToCSV($data, $filename, $headers = []) {
        try {
            // Validate data
            if (empty($data)) {
                return ['success' => false, 'message' => 'No data to export'];
            }

            // Set headers for download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');

            // Open PHP output stream
            $output = fopen('php://output', 'w');

            // If no headers provided, use first row keys
            if (empty($headers) && is_array($data[0])) {
                $headers = array_keys($data[0]);
            }

            // Write headers
            if (!empty($headers)) {
                fputcsv($output, $headers);
            }

            // Write data rows
            foreach ($data as $row) {
                if (is_array($row)) {
                    fputcsv($output, array_values($row));
                }
            }

            fclose($output);
            exit();
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Export error: ' . $e->getMessage()];
        }
    }

    /**
     * Export monthly report to CSV
     */
    public function exportMonthlyReport($year, $month, $userId = null) {
        try {
            $dateFrom = "$year-$month-01";
            $dateTo = date('Y-m-t', strtotime($dateFrom));

            $data = $this->db->fetchAll(
                "SELECT
                    invoice_date as 'Date',
                    invoice_number as 'Invoice #',
                    customer_name as 'Customer',
                    item_description as 'Item',
                    tax_code as 'Tax Code',
                    quantity as 'Qty',
                    base_value as 'Base Value',
                    vat_component as '18% VAT',
                    total_amount as 'Total'
                FROM sales
                WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?
                ORDER BY invoice_date DESC",
                [$dateFrom, $dateTo]
            );

            if (empty($data)) {
                return ['success' => false, 'message' => 'No data for this period'];
            }

            // Add summary row
            $summary = $this->db->fetch(
                "SELECT
                    COUNT(*) as invoices,
                    SUM(base_value) as total_base,
                    SUM(vat_component) as total_vat,
                    SUM(total_amount) as total_amount
                FROM sales
                WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?",
                [$dateFrom, $dateTo]
            );

            $filename = "Sales_Report_" . $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".csv";

            $this->exportToCSV($data, $filename);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Export top customers report
     */
    public function exportTopCustomers($limit = 50, $dateFrom = null, $dateTo = null) {
        try {
            $where = "WHERE invoice_type = 'Invoice'";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND invoice_date BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $data = $this->db->fetchAll(
                "SELECT
                    customer_name as 'Customer',
                    COUNT(*) as 'Num Purchases',
                    SUM(base_value) as 'Revenue (Base)',
                    SUM(vat_component) as 'VAT Total',
                    SUM(total_amount) as 'Total Revenue',
                    AVG(total_amount) as 'Avg Invoice',
                    MAX(invoice_date) as 'Last Purchase'
                FROM sales $where
                GROUP BY customer_name
                ORDER BY total_amount DESC
                LIMIT ?", array_merge($params, [$limit])
            );

            $filename = "Top_Customers_" . date('Y-m-d') . ".csv";
            $this->exportToCSV($data, $filename);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Export top products report
     */
    public function exportTopProducts($limit = 50, $dateFrom = null, $dateTo = null) {
        try {
            $where = "WHERE invoice_type = 'Invoice' AND item_description != ''";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND invoice_date BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $data = $this->db->fetchAll(
                "SELECT
                    item_description as 'Product',
                    product_category as 'Category',
                    COUNT(*) as 'Times Sold',
                    SUM(quantity) as 'Total Qty',
                    SUM(base_value) as 'Revenue (Base)',
                    SUM(vat_component) as 'VAT',
                    SUM(total_amount) as 'Total Revenue',
                    AVG(total_amount) as 'Avg Sale Price'
                FROM sales $where
                GROUP BY item_description
                ORDER BY total_amount DESC
                LIMIT ?", array_merge($params, [$limit])
            );

            $filename = "Top_Products_" . date('Y-m-d') . ".csv";
            $this->exportToCSV($data, $filename);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Generate JSON data for charts
     */
    public function getChartData($type, $year = null, $month = null) {
        try {
            if (!$year) $year = date('Y');
            if (!$month) $month = date('m');

            switch ($type) {
                case 'monthly_trend':
                    return $this->getMonthlyTrendData($year);

                case 'revenue_by_category':
                    return $this->getRevenueByCategory($year, $month);

                case 'customer_concentration':
                    return $this->getCustomerConcentration($year, $month);

                case 'daily_sales':
                    return $this->getDailySalesData($year, $month);

                default:
                    return [];
            }
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get monthly trend data for chart
     */
    private function getMonthlyTrendData($year) {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $data = $this->db->fetchAll(
            "SELECT
                strftime('%m', invoice_date) as month,
                SUM(total_amount) as revenue
            FROM sales
            WHERE invoice_type = 'Invoice' AND strftime('%Y', invoice_date) = ?
            GROUP BY strftime('%m', invoice_date)
            ORDER BY month ASC",
            [$year]
        );

        $labels = [];
        $revenues = [];

        foreach (range(1, 12) as $m) {
            $labels[] = $months[$m - 1];
            $found = false;

            foreach ($data as $row) {
                if (intval($row['month']) === $m) {
                    $revenues[] = (float)$row['revenue'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $revenues[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Monthly Revenue',
                    'data' => $revenues,
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                    'fill' => true,
                    'tension' => 0.4
                ]
            ]
        ];
    }

    /**
     * Get revenue by category for chart
     */
    private function getRevenueByCategory($year, $month) {
        $dateFrom = "$year-$month-01";
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $data = $this->db->fetchAll(
            "SELECT product_category, SUM(total_amount) as revenue
            FROM sales
            WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?
            GROUP BY product_category
            ORDER BY revenue DESC",
            [$dateFrom, $dateTo]
        );

        $labels = [];
        $revenues = [];
        $colors = ['#667eea', '#764ba2', '#f44336', '#ff9800', '#4caf50', '#00bcd4', '#9c27b0', '#ffc107'];

        foreach ($data as $index => $row) {
            $labels[] = $row['product_category'] ?? 'Uncategorized';
            $revenues[] = (float)$row['revenue'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue by Category',
                    'data' => $revenues,
                    'backgroundColor' => array_slice($colors, 0, count($labels))
                ]
            ]
        ];
    }

    /**
     * Get customer concentration data for chart
     */
    private function getCustomerConcentration($year, $month) {
        $dateFrom = "$year-$month-01";
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $top5 = $this->db->fetchAll(
            "SELECT customer_name, SUM(total_amount) as revenue
            FROM sales
            WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?
            GROUP BY customer_name
            ORDER BY revenue DESC
            LIMIT 5",
            [$dateFrom, $dateTo]
        );

        $totalRevenue = $this->db->fetch(
            "SELECT SUM(total_amount) as total FROM sales WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );

        $labels = [];
        $revenues = [];

        foreach ($top5 as $row) {
            $labels[] = substr($row['customer_name'], 0, 20);
            $revenues[] = (float)$row['revenue'];
        }

        // Add "Others"
        $top5Total = array_sum($revenues);
        $others = $totalRevenue['total'] - $top5Total;

        if ($others > 0) {
            $labels[] = 'Others';
            $revenues[] = (float)$others;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Customer Revenue Share',
                    'data' => $revenues,
                    'backgroundColor' => ['#667eea', '#764ba2', '#f44336', '#ff9800', '#4caf50', '#cccccc']
                ]
            ]
        ];
    }

    /**
     * Get daily sales data for chart
     */
    private function getDailySalesData($year, $month) {
        $dateFrom = "$year-$month-01";
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $data = $this->db->fetchAll(
            "SELECT invoice_date, SUM(total_amount) as revenue
            FROM sales
            WHERE invoice_type = 'Invoice' AND invoice_date BETWEEN ? AND ?
            GROUP BY invoice_date
            ORDER BY invoice_date ASC",
            [$dateFrom, $dateTo]
        );

        $labels = [];
        $revenues = [];

        foreach ($data as $row) {
            $labels[] = date('d M', strtotime($row['invoice_date']));
            $revenues[] = (float)$row['revenue'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Daily Sales',
                    'data' => $revenues,
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                    'fill' => true
                ]
            ]
        ];
    }
}
?>
