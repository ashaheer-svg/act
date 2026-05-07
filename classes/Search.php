<?php
/**
 * Search Class - Search & Advanced Filtering
 *
 * Provides search and filtering capabilities for transactions
 */

class Search {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Search transactions with filters
     */
    public function searchTransactions($filters = [], $page = 1, $limit = 50) {
        try {
            $where = ["invoice_type = 'Invoice'"];
            $params = [];
            $offset = ($page - 1) * $limit;

            // Search by customer name
            if (!empty($filters['customer'])) {
                $where[] = "customer_name LIKE ?";
                $params[] = '%' . $filters['customer'] . '%';
            }

            // Search by product
            if (!empty($filters['product'])) {
                $where[] = "item_description LIKE ?";
                $params[] = '%' . $filters['product'] . '%';
            }

            // Search by invoice number
            if (!empty($filters['invoice'])) {
                $where[] = "invoice_number LIKE ?";
                $params[] = '%' . $filters['invoice'] . '%';
            }

            // Filter by tax code
            if (!empty($filters['tax_code'])) {
                $where[] = "tax_code = ?";
                $params[] = $filters['tax_code'];
            }

            // Filter by category
            if (!empty($filters['category'])) {
                $where[] = "product_category = ?";
                $params[] = $filters['category'];
            }

            // Date range filter
            if (!empty($filters['date_from'])) {
                $where[] = "invoice_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "invoice_date <= ?";
                $params[] = $filters['date_to'];
            }

            // Amount range filter
            if (!empty($filters['amount_from'])) {
                $where[] = "total_amount >= ?";
                $params[] = floatval($filters['amount_from']);
            }

            if (!empty($filters['amount_to'])) {
                $where[] = "total_amount <= ?";
                $params[] = floatval($filters['amount_to']);
            }

            $whereClause = implode(" AND ", $where);

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM sales WHERE " . $whereClause;
            $countResult = $this->db->fetch($countQuery, $params);
            $total = $countResult['total'] ?? 0;

            // Get paginated results
            $query = "SELECT * FROM sales WHERE " . $whereClause . " ORDER BY invoice_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $data = $this->db->fetchAll($query, $params);

            return [
                'success' => true,
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'pages' => 0
            ];
        }
    }

    /**
     * Search customers by name
     */
    public function searchCustomers($query, $limit = 10) {
        try {
            if (empty($query)) {
                return [];
            }

            $data = $this->db->fetchAll(
                "SELECT DISTINCT customer_name
                FROM sales
                WHERE customer_name LIKE ? AND invoice_type = 'Invoice'
                ORDER BY customer_name ASC
                LIMIT ?",
                ['%' . $query . '%', $limit]
            );

            return $data;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Search products by name
     */
    public function searchProducts($query, $limit = 10) {
        try {
            if (empty($query)) {
                return [];
            }

            $data = $this->db->fetchAll(
                "SELECT DISTINCT item_description
                FROM sales
                WHERE item_description LIKE ? AND invoice_type = 'Invoice'
                ORDER BY item_description ASC
                LIMIT ?",
                ['%' . $query . '%', $limit]
            );

            return $data;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get advanced filter options
     */
    public function getFilterOptions() {
        try {
            $taxCodes = $this->db->fetchAll(
                "SELECT DISTINCT tax_code FROM sales WHERE tax_code IS NOT NULL ORDER BY tax_code"
            );

            $categories = $this->db->fetchAll(
                "SELECT DISTINCT product_category FROM sales WHERE product_category IS NOT NULL ORDER BY product_category"
            );

            return [
                'tax_codes' => $taxCodes,
                'categories' => $categories
            ];
        } catch (Exception $e) {
            return [
                'tax_codes' => [],
                'categories' => []
            ];
        }
    }

    /**
     * Get customer info
     */
    public function getCustomerInfo($customerName) {
        try {
            $info = $this->db->fetch(
                "SELECT
                    customer_name,
                    COUNT(*) as total_purchases,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_purchase,
                    MIN(invoice_date) as first_purchase,
                    MAX(invoice_date) as last_purchase
                FROM sales
                WHERE customer_name = ? AND invoice_type = 'Invoice'
                GROUP BY customer_name",
                [$customerName]
            );

            if ($info) {
                // Get recent purchases
                $recent = $this->db->fetchAll(
                    "SELECT invoice_date, invoice_number, total_amount FROM sales
                    WHERE customer_name = ? AND invoice_type = 'Invoice'
                    ORDER BY invoice_date DESC
                    LIMIT 10",
                    [$customerName]
                );

                $info['recent_purchases'] = $recent;
            }

            return $info;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get product info
     */
    public function getProductInfo($productName) {
        try {
            $info = $this->db->fetch(
                "SELECT
                    item_description,
                    product_category,
                    COUNT(*) as times_sold,
                    SUM(quantity) as total_qty,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_price,
                    MIN(invoice_date) as first_sold,
                    MAX(invoice_date) as last_sold
                FROM sales
                WHERE item_description = ? AND invoice_type = 'Invoice'
                GROUP BY item_description",
                [$productName]
            );

            if ($info) {
                // Get top customers for this product
                $topCustomers = $this->db->fetchAll(
                    "SELECT customer_name, COUNT(*) as qty, SUM(total_amount) as revenue
                    FROM sales
                    WHERE item_description = ? AND invoice_type = 'Invoice'
                    GROUP BY customer_name
                    ORDER BY revenue DESC
                    LIMIT 5",
                    [$productName]
                );

                $info['top_customers'] = $topCustomers;
            }

            return $info;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Data reconciliation - Find potential duplicates
     */
    public function findPotentialDuplicates($threshold = 95) {
        try {
            // Find records with very similar invoice numbers and amounts on same date
            $data = $this->db->fetchAll(
                "SELECT a.id as id1, b.id as id2, a.invoice_number, a.customer_name, a.total_amount
                FROM sales a
                JOIN sales b ON
                    a.invoice_date = b.invoice_date AND
                    a.customer_name = b.customer_name AND
                    ABS(a.total_amount - b.total_amount) < 1 AND
                    a.id < b.id
                WHERE a.invoice_type = 'Invoice' AND b.invoice_type = 'Invoice'
                ORDER BY a.invoice_date DESC
                LIMIT 50"
            );

            return $data;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get missing value warnings
     */
    public function findMissingValues() {
        try {
            $warnings = [];

            // Missing customer names
            $noCustomer = $this->db->fetch(
                "SELECT COUNT(*) as count FROM sales WHERE (customer_name IS NULL OR customer_name = '') AND invoice_type = 'Invoice'"
            );

            if ($noCustomer['count'] > 0) {
                $warnings[] = [
                    'type' => 'missing_customer',
                    'count' => $noCustomer['count'],
                    'message' => $noCustomer['count'] . ' record(s) missing customer name'
                ];
            }

            // Missing product descriptions
            $noProduct = $this->db->fetch(
                "SELECT COUNT(*) as count FROM sales WHERE (item_description IS NULL OR item_description = '') AND invoice_type = 'Invoice'"
            );

            if ($noProduct['count'] > 0) {
                $warnings[] = [
                    'type' => 'missing_product',
                    'count' => $noProduct['count'],
                    'message' => $noProduct['count'] . ' record(s) missing product description'
                ];
            }

            // Missing category
            $noCategory = $this->db->fetch(
                "SELECT COUNT(*) as count FROM sales WHERE (product_category IS NULL OR product_category = '') AND invoice_type = 'Invoice'"
            );

            if ($noCategory['count'] > 0) {
                $warnings[] = [
                    'type' => 'missing_category',
                    'count' => $noCategory['count'],
                    'message' => $noCategory['count'] . ' record(s) missing product category'
                ];
            }

            return $warnings;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
