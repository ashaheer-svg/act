<?php
/**
 * Database Class - SQLite Connection & Management (ENHANCED)
 *
 * Handles all database operations with prepared statements
 * SECURITY: Uses prepared statements to prevent SQL injection
 */

class Database {
    private $db;
    private $error;

    public function __construct($dbPath) {
        try {
            // Validate database path
            if (empty($dbPath)) {
                throw new Exception('Database path cannot be empty');
            }

            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->exec('PRAGMA foreign_keys = ON');
            $this->db->exec('PRAGMA journal_mode = WAL');
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die('Database Connection Failed: ' . $this->error);
        }
    }

    /**
     * Get underlying PDO connection
     */
    public function getConnection() {
        return $this->db;
    }

    /**
     * Direct query on PDO
     */
    public function query($sql) {
        return $this->db->query($sql);
    }

    /**
     * Initialize database schema
     */
    public function initialize() {
        $this->createTablesIfNotExists();
        $this->syncSchema();
        $this->createDefaultUser();
    }

    private function createTablesIfNotExists() {
        try {
            // Users table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    email TEXT,
                    role TEXT NOT NULL DEFAULT 'viewer',
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login DATETIME
                )
            ");

            // Sales data table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sales (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_type TEXT NOT NULL,
                    invoice_date DATE NOT NULL,
                    invoice_number TEXT NOT NULL,
                    customer_name TEXT NOT NULL,
                    item_description TEXT,
                    tax_code TEXT NOT NULL,
                    quantity REAL DEFAULT 1,
                    qb_amount DECIMAL(12,2) NOT NULL,
                    base_value DECIMAL(12,2) NOT NULL,
                    vat_component DECIMAL(12,2) NOT NULL,
                    applied_tax_rate DECIMAL(5,4),
                    total_amount DECIMAL(12,2) NOT NULL,
                    gross_profit DECIMAL(12,2) DEFAULT 0,
                    product_category TEXT,
                    sales_rep_code TEXT,
                    paid_date DATE,
                    days_to_pay INTEGER,
                    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(invoice_number, customer_name, item_description, qb_amount)
                )
            ");

            // Tax Rules (Date-based & Invoice-range VAT rates)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS tax_rules (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tax_name TEXT NOT NULL,
                    tax_rate REAL NOT NULL,
                    effective_from DATE,
                    effective_to DATE,
                    invoice_range_start TEXT,
                    invoice_range_end TEXT,
                    is_inclusive_default INTEGER DEFAULT 1,
                    notes TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Import logs table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS import_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    filename TEXT NOT NULL,
                    records_imported INTEGER,
                    records_skipped INTEGER,
                    import_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    imported_by INTEGER,
                    status TEXT DEFAULT 'success',
                    error_message TEXT,
                    FOREIGN KEY (imported_by) REFERENCES users(id)
                )
            ");

            // Activity/Audit log table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS activity_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    description TEXT,
                    ip_address TEXT,
                    activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            // Settings table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key TEXT UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Customer Profiles (CRM metadata)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS customer_profiles (
                    customer_name TEXT PRIMARY KEY,
                    customer_type TEXT DEFAULT 'End Customer',
                    is_verified INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Password reset tokens
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token TEXT UNIQUE NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            // Create indexes for performance
            $this->db->exec("
                CREATE INDEX IF NOT EXISTS idx_sales_invoice_date ON sales(invoice_date);
                CREATE INDEX IF NOT EXISTS idx_sales_customer_name ON sales(customer_name);
                CREATE INDEX IF NOT EXISTS idx_sales_tax_code ON sales(tax_code);
                CREATE INDEX IF NOT EXISTS idx_sales_product_category ON sales(product_category);
                CREATE INDEX IF NOT EXISTS idx_activity_user_id ON activity_log(user_id);
                CREATE INDEX IF NOT EXISTS idx_activity_date ON activity_log(activity_date);
                CREATE INDEX IF NOT EXISTS idx_import_logs_date ON import_logs(import_date);
            ");

            // Payments Table
            $this->execute("
                CREATE TABLE IF NOT EXISTS payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    customer_name TEXT,
                    invoice_num TEXT,
                    payment_date DATE,
                    reference_num TEXT,
                    amount DECIMAL(12,2),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Sales Rep Mapping Table
            $this->execute("
                CREATE TABLE IF NOT EXISTS sales_rep_mapping (
                    rep_code TEXT PRIMARY KEY,
                    rep_name TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Product Mappings & Rule Engine table
            $this->execute("
                CREATE TABLE IF NOT EXISTS product_mappings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    pattern TEXT NOT NULL,
                    match_type TEXT DEFAULT 'CONTAINS', -- 'EXACT', 'CONTAINS', 'REGEX'
                    master_sku TEXT,
                    canonical_name TEXT NOT NULL,
                    brand TEXT,
                    commercial_type TEXT NOT NULL DEFAULT 'OUTRIGHT_SALE', -- 'RENTAL', 'OUTRIGHT_SALE', 'MAINTENANCE', 'SOFTWARE', 'SERVICE'
                    default_vat_treatment TEXT DEFAULT 'DEFAULT',
                    priority INTEGER DEFAULT 10,
                    notes TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Invoice Items (Normalized Commercial Products)
            $this->execute("
                CREATE TABLE IF NOT EXISTS invoice_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_number TEXT NOT NULL,
                    customer_name TEXT NOT NULL,
                    invoice_date DATE NOT NULL,
                    product_type TEXT NOT NULL, -- 'HARDWARE', 'SOFTWARE_LICENSE', 'SAAS_SUBSCRIPTION', 'SERVICE_AMC', 'ACCESSORY_OTHER'
                    clean_product_name TEXT NOT NULL,
                    brand_category TEXT,
                    quantity REAL DEFAULT 1,
                    unit_price DECIMAL(12,2),
                    base_value DECIMAL(12,2),
                    vat_component DECIMAL(12,2),
                    total_amount DECIMAL(12,2) NOT NULL,
                    raw_line_ids TEXT, -- JSON array of merged sales line IDs
                    confidence_score INTEGER DEFAULT 100,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Hardware Assets (Unit-Level Serial & Warranty Registry)
            $this->execute("
                CREATE TABLE IF NOT EXISTS hardware_assets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_number TEXT NOT NULL,
                    invoice_item_id INTEGER,
                    customer_name TEXT NOT NULL,
                    product_name TEXT NOT NULL,
                    brand TEXT,
                    model_sku TEXT,
                    serial_number TEXT NOT NULL,
                    warranty_type TEXT DEFAULT 'Standard',
                    warranty_months INTEGER,
                    warranty_start_date DATE,
                    warranty_expiry_date DATE,
                    warranty_status TEXT DEFAULT 'ACTIVE', -- 'ACTIVE', 'EXPIRING_30D', 'EXPIRING_60D', 'EXPIRING_90D', 'EXPIRED'
                    parent_serial_number TEXT, -- Links HDD serial to NAS chassis serial
                    notes TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(id)
                )
            ");

            // Software Subscriptions (Licenses & SaaS Renewal Ledger)
            $this->execute("
                CREATE TABLE IF NOT EXISTS software_subscriptions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_number TEXT NOT NULL,
                    invoice_item_id INTEGER,
                    customer_name TEXT NOT NULL,
                    software_name TEXT NOT NULL,
                    edition_tier TEXT,
                    license_seats INTEGER DEFAULT 1,
                    period_start_date DATE,
                    period_end_date DATE,
                    term_months INTEGER,
                    renewal_status TEXT DEFAULT 'ACTIVE', -- 'ACTIVE', 'DUE_SOON', 'EXPIRED', 'RENEWED'
                    renewal_opportunity_value DECIMAL(12,2),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(id)
                )
            ");

            // AI Extraction Logs (Traceability & Prompt Auditing)
            $this->execute("
                CREATE TABLE IF NOT EXISTS ai_extraction_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_number TEXT NOT NULL,
                    ai_provider TEXT NOT NULL,
                    model_name TEXT NOT NULL,
                    prompt_tokens INTEGER,
                    completion_tokens INTEGER,
                    status TEXT DEFAULT 'SUCCESS', -- 'SUCCESS', 'FAILED', 'VALIDATION_WARNING'
                    confidence_score INTEGER DEFAULT 100,
                    raw_response TEXT,
                    extracted_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Indexes for performance
            $this->execute("
                CREATE INDEX IF NOT EXISTS idx_items_inv ON invoice_items(invoice_number);
                CREATE INDEX IF NOT EXISTS idx_items_prod_type ON invoice_items(product_type);
                CREATE INDEX IF NOT EXISTS idx_hardware_serial ON hardware_assets(serial_number);
                CREATE INDEX IF NOT EXISTS idx_hardware_customer ON hardware_assets(customer_name);
                CREATE INDEX IF NOT EXISTS idx_hardware_expiry ON hardware_assets(warranty_expiry_date);
                CREATE INDEX IF NOT EXISTS idx_sub_end_date ON software_subscriptions(period_end_date);
                CREATE INDEX IF NOT EXISTS idx_sub_customer ON software_subscriptions(customer_name);
                CREATE INDEX IF NOT EXISTS idx_ai_logs_inv ON ai_extraction_logs(invoice_number);
            ");

            return true;
        } catch (PDOException $e) {
            throw new Exception('Failed to create tables: ' . $e->getMessage());
        }
    }

    private function createDefaultUser() {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result['count'] == 0) {
                $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
                $stmt = $this->db->prepare("
                    INSERT INTO users (username, password, email, role, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute(['admin', $adminPass, 'admin@example.com', 'admin', 1]);

                // Log the creation
                $this->logActivity(1, 'DEFAULT_ADMIN_CREATED', 'Default admin user created during installation', '127.0.0.1');
            }
        } catch (PDOException $e) {
            // Silent fail - user might already exist
        }
    }

    /**
     * Execute prepared statement
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Self-healing: if column is missing, try to sync schema and retry once
            if (strpos($e->getMessage(), 'no such column') !== false || strpos($e->getMessage(), 'no such table') !== false) {
                $this->syncSchema();
                try {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                    return $stmt;
                } catch (PDOException $e2) {
                    throw new Exception('Database Error after Sync: ' . $e2->getMessage());
                }
            }
            throw new Exception('Database Error: ' . $e->getMessage());
        }
    }

    /**
     * Fetch all results
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch single row
     */
    /**
     * Resets all sales data and import logs
     * Preserves users and settings
     */
    public function resetSalesData() {
        try {
            // We drop and recreate tables to ensure schema changes (like UNIQUE constraints) are applied
            $this->db->exec("DROP TABLE IF EXISTS sales");
            $this->db->exec("DROP TABLE IF EXISTS import_logs");
            $this->db->exec("DROP TABLE IF EXISTS activity_log");
            
            // Re-initialize the tables with the latest schema from createTablesIfNotExists()
            $this->createTablesIfNotExists();
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }

    /**
     * Get affected rows from statement
     */
    public function rowCount($stmt) {
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollBack() {
        $this->db->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction() {
        return $this->db->inTransaction();
    }

    /**
     * Get setting value by key
     */
    public function getSetting($key, $default = '') {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }

    public function ensureCustomerProfileColumns() {
        try {
            $custCols = $this->db->query("PRAGMA table_info(customer_profiles)")->fetchAll();
            $custColNames = array_column($custCols, 'name');

            $neededCustCols = [
                'company_name' => "ALTER TABLE customer_profiles ADD COLUMN company_name TEXT",
                'contact_name' => "ALTER TABLE customer_profiles ADD COLUMN contact_name TEXT",
                'email' => "ALTER TABLE customer_profiles ADD COLUMN email TEXT",
                'phone' => "ALTER TABLE customer_profiles ADD COLUMN phone TEXT",
                'alt_phone' => "ALTER TABLE customer_profiles ADD COLUMN alt_phone TEXT",
                'fax' => "ALTER TABLE customer_profiles ADD COLUMN fax TEXT",
                'bill_address' => "ALTER TABLE customer_profiles ADD COLUMN bill_address TEXT",
                'bill_city' => "ALTER TABLE customer_profiles ADD COLUMN bill_city TEXT",
                'bill_state' => "ALTER TABLE customer_profiles ADD COLUMN bill_state TEXT",
                'bill_zip' => "ALTER TABLE customer_profiles ADD COLUMN bill_zip TEXT",
                'bill_country' => "ALTER TABLE customer_profiles ADD COLUMN bill_country TEXT",
                'sales_rep' => "ALTER TABLE customer_profiles ADD COLUMN sales_rep TEXT",
                'current_balance' => "ALTER TABLE customer_profiles ADD COLUMN current_balance REAL DEFAULT 0",
                'total_balance' => "ALTER TABLE customer_profiles ADD COLUMN total_balance REAL DEFAULT 0",
                'credit_limit' => "ALTER TABLE customer_profiles ADD COLUMN credit_limit REAL DEFAULT 0",
                'terms' => "ALTER TABLE customer_profiles ADD COLUMN terms TEXT",
                'account_number' => "ALTER TABLE customer_profiles ADD COLUMN account_number TEXT",
                'is_active' => "ALTER TABLE customer_profiles ADD COLUMN is_active INTEGER DEFAULT 1",
                'qb_list_id' => "ALTER TABLE customer_profiles ADD COLUMN qb_list_id TEXT",
                'is_verified' => "ALTER TABLE customer_profiles ADD COLUMN is_verified INTEGER DEFAULT 0",
                'resale_number' => "ALTER TABLE customer_profiles ADD COLUMN resale_number TEXT",
                'vat_number' => "ALTER TABLE customer_profiles ADD COLUMN vat_number TEXT",
                'tin_number' => "ALTER TABLE customer_profiles ADD COLUMN tin_number TEXT",
                'is_vat_registered' => "ALTER TABLE customer_profiles ADD COLUMN is_vat_registered INTEGER DEFAULT 0",
                'tax_item_ref' => "ALTER TABLE customer_profiles ADD COLUMN tax_item_ref TEXT",
                'tax_code_ref' => "ALTER TABLE customer_profiles ADD COLUMN tax_code_ref TEXT",
                'notes' => "ALTER TABLE customer_profiles ADD COLUMN notes TEXT"
            ];

            foreach ($neededCustCols as $col => $sql) {
                if (!in_array($col, $custColNames)) {
                    $this->db->exec($sql);
                }
            }
        } catch (Exception $e) {
            error_log("ensureCustomerProfileColumns error: " . $e->getMessage());
        }
    }

    /**
     * Ensure product_mappings table has all necessary rule columns
     */
    public function ensureProductMappingColumns() {
        try {
            $cols = $this->db->query("PRAGMA table_info(product_mappings)")->fetchAll();
            $colNames = array_column($cols, 'name');

            $neededCols = [
                'pattern' => "ALTER TABLE product_mappings ADD COLUMN pattern TEXT",
                'match_type' => "ALTER TABLE product_mappings ADD COLUMN match_type TEXT DEFAULT 'CONTAINS'",
                'master_sku' => "ALTER TABLE product_mappings ADD COLUMN master_sku TEXT",
                'canonical_name' => "ALTER TABLE product_mappings ADD COLUMN canonical_name TEXT",
                'brand' => "ALTER TABLE product_mappings ADD COLUMN brand TEXT",
                'commercial_type' => "ALTER TABLE product_mappings ADD COLUMN commercial_type TEXT DEFAULT 'OUTRIGHT_SALE'",
                'default_vat_treatment' => "ALTER TABLE product_mappings ADD COLUMN default_vat_treatment TEXT DEFAULT 'DEFAULT'",
                'priority' => "ALTER TABLE product_mappings ADD COLUMN priority INTEGER DEFAULT 10",
                'notes' => "ALTER TABLE product_mappings ADD COLUMN notes TEXT",
                'updated_at' => "ALTER TABLE product_mappings ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP"
            ];

            foreach ($neededCols as $col => $sql) {
                if (!in_array($col, $colNames)) {
                    $this->db->exec($sql);
                }
            }

            // Also ensure hardware_assets has is_rental column
            $hwCols = $this->db->query("PRAGMA table_info(hardware_assets)")->fetchAll();
            $hwColNames = array_column($hwCols, 'name');
            if (!in_array('is_rental', $hwColNames)) {
                $this->db->exec("ALTER TABLE hardware_assets ADD COLUMN is_rental INTEGER DEFAULT 0");
            }
        } catch (Exception $e) {
            error_log("ensureProductMappingColumns error: " . $e->getMessage());
        }
    }

    /**
     * Parses all customer profile text fields to extract and update VAT/TIN numbers and registration status
     */
    public function parseAndPopulateCustomerTaxNumbers() {
        $this->ensureCustomerProfileColumns();
        $customers = $this->fetchAll("SELECT customer_name, bill_address, bill_city, bill_state, bill_zip, company_name, resale_number, notes FROM customer_profiles");
        $stmt = $this->db->prepare("UPDATE customer_profiles SET vat_number = ?, tin_number = ?, is_vat_registered = ? WHERE customer_name = ?");
        
        $this->db->beginTransaction();
        $updated = 0;
        try {
            foreach ($customers as $c) {
                $text = ($c['resale_number'] ?? '') . ' ' . ($c['bill_address'] ?? '') . ' ' . ($c['bill_city'] ?? '') . ' ' . ($c['bill_state'] ?? '') . ' ' . ($c['bill_zip'] ?? '') . ' ' . ($c['company_name'] ?? '') . ' ' . ($c['notes'] ?? '');
                
                $vatNum = '';
                $tinNum = '';
                $isVat = 0;
                
                if (preg_match('/(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{4})?|[0-9A-Z\-\/]{7,})/i', $text, $m)) {
                    $vatNum = trim($m[1]);
                    $isVat = 1;
                } elseif (preg_match('/\b([0-9]{9}-7000)\b/', $text, $m)) {
                    $vatNum = trim($m[1]);
                    $isVat = 1;
                }
                
                if (preg_match('/(?:TIN)\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9]{9}|[0-9A-Z\-\/]{7,})/i', $text, $m)) {
                    $tinNum = trim($m[1]);
                    if (empty($vatNum) && !empty($tinNum)) {
                        $isVat = 1; // Corporate registered tax entity
                    }
                }
                
                $stmt->execute([$vatNum, $tinNum, $isVat, $c['customer_name']]);
                $updated++;
            }
            $this->db->commit();
            return $updated;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("parseAndPopulateCustomerTaxNumbers error: " . $e->getMessage());
            return 0;
        }
    }

    public function syncCustomerProfiles() {
        $this->ensureCustomerProfileColumns();
        $this->db->exec("
            INSERT OR IGNORE INTO customer_profiles (customer_name)
            SELECT DISTINCT customer_name FROM sales
        ");
    }

    /**
     * Ledger / Payment Management
     */
    public function clearPayments() {
        return $this->execute("DELETE FROM payments");
    }

    public function addPayment($customer, $date, $ref, $amount, $invoiceNum = null) {
        $sql = "INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num) VALUES (?, ?, ?, ?, ?)";
        // Convert date from MM/DD/YYYY to YYYY-MM-DD if needed
        if (strpos($date, '/') !== false) {
            $parts = explode('/', $date);
            if (count($parts) == 3) {
                $date = $parts[2] . '-' . $parts[0] . '-' . $parts[1];
            }
        }
        return $this->execute($sql, [$customer, $date, $ref, $amount, $invoiceNum]);
    }

    /**
     * CRM: Get all customer profiles with their types
     */
    public function getCustomerProfiles($limit = null, $offset = 0, $search = '', $sort = 'lifetime_revenue', $dir = 'DESC') {
        $this->syncCustomerProfiles(); // Ensure we have latest names
        
        $params = [];
        $where = "";
        if (!empty($search)) {
            $where = " WHERE p.customer_name LIKE ? ";
            $params[] = "%$search%";
        }

        // Validate sort column to prevent SQL injection
        $allowedSort = ['customer_name', 'customer_type', 'lifetime_invoices', 'lifetime_revenue', 'is_verified'];
        if (!in_array($sort, $allowedSort)) $sort = 'lifetime_revenue';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT p.*, 
                   COUNT(s.id) as lifetime_invoices,
                   SUM(s.base_value) as lifetime_revenue
            FROM customer_profiles p
            LEFT JOIN sales s ON p.customer_name = s.customer_name
            $where
            GROUP BY p.customer_name
            ORDER BY $sort $dir
        ";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        return $this->fetchAll($sql, $params);
    }

    /**
     * CRM: Get total customer count
     */
    public function countCustomers($search = '') {
        $params = [];
        $where = "";
        if (!empty($search)) {
            $where = " WHERE customer_name LIKE ? ";
            $params[] = "%$search%";
        }
        $row = $this->fetch("SELECT COUNT(*) as total FROM customer_profiles $where", $params);
        return $row ? (int)$row['total'] : 0;
    }

    public function getCustomerPayments($customerName) {
        return $this->fetchAll("
            SELECT * FROM payments 
            WHERE customer_name = ? 
            ORDER BY payment_date DESC
        ", [$customerName]);
    }

    /**
     * CRM: Update customer type
     */
    public function updateCustomerType($name, $type) {
        return $this->execute(
            "UPDATE customer_profiles SET customer_type = ?, is_verified = 1, updated_at = CURRENT_TIMESTAMP WHERE customer_name = ?",
            [$type, $name]
        );
    }

    /**
     * CRM: Bulk update customer profiles
     */
    public function bulkUpdateCustomerProfiles($names, $type) {
        if (empty($names)) return false;
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $params = array_merge([$type], $names);
        return $this->execute(
            "UPDATE customer_profiles SET customer_type = ?, is_verified = 1, updated_at = CURRENT_TIMESTAMP WHERE customer_name IN ($placeholders)",
            $params
        );
    }

    /**
     * TAX: Parse invoice number prefix and integer for range matching
     */
    public function parseInvoiceNumber($inv) {
        $inv = trim($inv ?? '');
        if (preg_match('/^([A-Za-z]+)-?(\d+)$/', $inv, $m)) {
            return ['prefix' => strtoupper($m[1]), 'num' => intval($m[2]), 'raw' => $inv];
        }
        return ['prefix' => '', 'num' => 0, 'raw' => $inv];
    }

    /**
     * TAX: Check if invoice number falls within an alphanumeric range
     */
    public function matchesInvoiceRange($inv, $rangeStart, $rangeEnd) {
        if (empty($rangeStart) || empty($rangeEnd)) return false;
        $pInv = $this->parseInvoiceNumber($inv);
        $pStart = $this->parseInvoiceNumber($rangeStart);
        $pEnd = $this->parseInvoiceNumber($rangeEnd);

        if (!empty($pInv['prefix']) && $pInv['prefix'] === $pStart['prefix']) {
            return ($pInv['num'] >= $pStart['num'] && $pInv['num'] <= $pEnd['num']);
        }
        return false;
    }

    /**
     * TAX: Resolve effective tax rule by Invoice Number Range, then Date Range, then Fallback
     */
    public function getTaxRuleForInvoice($invoiceNumber, $date = null) {
        // 1. Check all sequence-based rules first (highest priority)
        $rules = $this->fetchAll("
            SELECT * FROM tax_rules 
            WHERE invoice_range_start IS NOT NULL AND invoice_range_end IS NOT NULL 
            ORDER BY id ASC
        ");
        foreach ($rules as $r) {
            if ($this->matchesInvoiceRange($invoiceNumber, $r['invoice_range_start'], $r['invoice_range_end'])) {
                return [
                    'rate' => floatval($r['tax_rate']),
                    'name' => $r['tax_name'],
                    'is_inclusive_default' => (int)($r['is_inclusive_default'] ?? 1),
                    'rule_id' => $r['id'],
                    'matched_by' => 'INVOICE_RANGE'
                ];
            }
        }

        // 2. Check date-based rules if date provided
        if (!empty($date)) {
            $stmt = $this->db->prepare("
                SELECT * FROM tax_rules 
                WHERE (effective_from IS NULL OR effective_from <= ?)
                  AND (effective_to IS NULL OR effective_to >= ?)
                  AND (invoice_range_start IS NULL OR invoice_range_start = '')
                ORDER BY effective_from DESC 
                LIMIT 1
            ");
            $stmt->execute([$date, $date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'rate' => floatval($row['tax_rate']),
                    'name' => $row['tax_name'],
                    'is_inclusive_default' => (int)($row['is_inclusive_default'] ?? 1),
                    'rule_id' => $row['id'],
                    'matched_by' => 'DATE_RANGE'
                ];
            }
        }

        // 3. Fallback to global setting
        $fallbackRate = floatval($this->getSetting('vat_rate', '0.18'));
        return [
            'rate' => $fallbackRate,
            'name' => 'Default VAT Setting',
            'is_inclusive_default' => 1,
            'rule_id' => null,
            'matched_by' => 'FALLBACK'
        ];
    }

    /**
     * TAX: Get effective tax rate for a specific date (backward compatibility)
     */
    public function getTaxRateForDate($date) {
        $rule = $this->getTaxRuleForInvoice('', $date);
        return $rule['rate'];
    }

    /**
     * TAX: Add or update a tax rule
     */
    public function saveTaxRule($data) {
        $id = !empty($data['id']) ? intval($data['id']) : null;
        $name = trim($data['tax_name'] ?? 'VAT Rule');
        $rate = floatval($data['tax_rate'] ?? 0);
        $from = !empty($data['effective_from']) ? $data['effective_from'] : null;
        $to = !empty($data['effective_to']) ? $data['effective_to'] : null;
        $invStart = !empty($data['invoice_range_start']) ? trim($data['invoice_range_start']) : null;
        $invEnd = !empty($data['invoice_range_end']) ? trim($data['invoice_range_end']) : null;
        $isInclusive = isset($data['is_inclusive_default']) ? intval($data['is_inclusive_default']) : 1;
        $notes = trim($data['notes'] ?? '');

        if ($id) {
            $stmt = $this->db->prepare("
                UPDATE tax_rules 
                SET tax_name = ?, tax_rate = ?, effective_from = ?, effective_to = ?, 
                    invoice_range_start = ?, invoice_range_end = ?, is_inclusive_default = ?, notes = ?
                WHERE id = ?
            ");
            return $stmt->execute([$name, $rate, $from, $to, $invStart, $invEnd, $isInclusive, $notes, $id]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO tax_rules (tax_name, tax_rate, effective_from, effective_to, invoice_range_start, invoice_range_end, is_inclusive_default, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$name, $rate, $from, $to, $invStart, $invEnd, $isInclusive, $notes]);
        }
    }

    /**
     * TAX: Seed historical statutory VAT sequences & regimes
     */
    public function seedDefaultTaxRules() {
        try {
            $count = $this->fetch("SELECT COUNT(*) as c FROM tax_rules")['c'] ?? 0;
            if ($count == 0) {
                $defaultRules = [
                    ['Legacy 12% VAT', 0.12, null, null, 'AS004001', 'AS005147', 1, 'Historical statutory 12% VAT'],
                    ['Legacy 0% Exempt', 0.00, null, null, 'AS005148', 'AS006560', 0, 'Historical VAT exempt period'],
                    ['Legacy 15% VAT', 0.15, null, null, 'AS006561', 'AS008154', 1, 'Historical statutory 15% VAT'],
                    ['Legacy 8% VAT', 0.08, null, null, 'AS008155', 'AS008211', 1, 'Historical statutory 8% VAT'],
                    ['Exempt 0% VAT', 0.00, null, null, 'AS008212', 'AS010020', 0, 'VAT exempt era (2021-2023)'],
                    ['18% Statutory VAT', 0.18, '2024-01-01', '2026-06-30', 'AS010021', 'AS011260', 1, '18% VAT Regime (Old Seq)'],
                    ['New Seq ASN 18% VAT', 0.18, '2026-07-01', null, 'ASN000001', 'ASN000102', 1, '18% VAT Regime (New Seq ASN)'],
                    ['New Seq AS 18% VAT', 0.18, '2026-07-01', null, 'AS000001', 'AS000102', 1, '18% VAT Regime (New Seq AS)'],
                    ['Future 18% Statutory Default', 0.18, '2024-01-01', null, null, null, 1, 'Default fallback rate for recent/future invoices']
                ];

                $stmt = $this->db->prepare("
                    INSERT INTO tax_rules (tax_name, tax_rate, effective_from, effective_to, invoice_range_start, invoice_range_end, is_inclusive_default, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($defaultRules as $r) {
                    $stmt->execute($r);
                }
            }
        } catch (Exception $e) {
            error_log("seedDefaultTaxRules error: " . $e->getMessage());
        }
    }

    /**
     * TAX: Recalculate historical VAT for all sales lines based on sequence rules & inclusivity
     */
    public function recalculateHistoricalVat() {
        $this->syncSchema();

        // 1. Identify invoices that contain an explicit separate VAT line item with amount > 0
        $invoicesWithExplicitVat = $this->fetchAll("
            SELECT DISTINCT invoice_number 
            FROM sales 
            WHERE total_amount > 0 AND (
                item_description LIKE 'VAT%' 
                OR item_description LIKE '%Value Added Tax%'
                OR item_description LIKE '18% VAT%'
                OR item_description LIKE '15% VAT%'
                OR item_description LIKE '12% VAT%'
                OR item_description LIKE '8% VAT%'
            )
        ");
        $vatLineInvoices = array_flip(array_column($invoicesWithExplicitVat, 'invoice_number'));

        // Load customer VAT registrations
        $custProfiles = $this->fetchAll("SELECT customer_name, is_vat_registered, vat_number, tin_number FROM customer_profiles");
        $custVatMap = [];
        foreach ($custProfiles as $cp) {
            $custVatMap[$cp['customer_name']] = (int)($cp['is_vat_registered'] ?? 0);
        }

        // 2. Fetch all sales lines
        $sales = $this->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, tax_code, qb_amount, total_amount, item_description FROM sales");
        $updated = 0;

        $stmt = $this->db->prepare("
            UPDATE sales 
            SET applied_tax_rate = ?, base_value = ?, vat_component = ?, total_amount = ?, vat_treatment = ?
            WHERE id = ?
        ");

        $this->db->beginTransaction();
        try {
            foreach ($sales as $row) {
                $date = $row['invoice_date'];
                $rawAmt = floatval(($row['qb_amount'] ?? 0) > 0 ? $row['qb_amount'] : $row['total_amount']);
                $invNum = trim($row['invoice_number']);
                $desc = trim($row['item_description'] ?? '');
                $custName = trim($row['customer_name'] ?? '');
                $isVatReg = $custVatMap[$custName] ?? 0;
                $taxCode = trim($row['tax_code'] ?? 'Taxable Sales');

                $rule = $this->getTaxRuleForInvoice($invNum, $date);
                $rate = $rule['rate'];

                if ($rate <= 0 || $rawAmt == 0 || stripos($taxCode, 'Non') !== false || stripos($taxCode, 'Zero') !== false || stripos($taxCode, 'Exempt') !== false) {
                    $appliedRate = 0.00;
                    $base = $rawAmt;
                    $vat = 0.00;
                    $total = $rawAmt;
                    $treatment = 'VAT_EXEMPT';
                } else {
                    $hasSepVatLine = isset($vatLineInvoices[$invNum]);
                    $isVatLineItself = (bool)preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $desc);

                    if ($hasSepVatLine) {
                        $treatment = 'VAT_EXCLUSIVE_BREAKUP';
                        if ($isVatLineItself) {
                            $appliedRate = $rate;
                            $base = 0.00;
                            $vat = $rawAmt;
                            $total = $rawAmt;
                        } else {
                            $appliedRate = $rate;
                            $base = $rawAmt;
                            $vat = 0.00;
                            $total = $rawAmt;
                        }
                    } elseif ($isVatReg == 1) {
                        // Customer IS VAT-Registered: Line amount is Net Base, VAT is added on top (+18%)
                        $treatment = 'PLUS_VAT';
                        $appliedRate = $rate;
                        $base = $rawAmt;
                        $vat = round($rawAmt * $rate, 2);
                        $total = round($base + $vat, 2);
                    } else {
                        // Customer is NOT VAT-Registered: Under IRD law, invoice is issued VAT-inclusive (no separate breakdown)
                        $treatment = 'VAT_INCLUSIVE';
                        $appliedRate = $rate;
                        $total = $rawAmt;
                        $base = round($rawAmt / (1 + $rate), 2);
                        $vat = round($total - $base, 2);
                    }
                }

                $stmt->execute([$appliedRate, $base, $vat, $total, $treatment, $row['id']]);
                $updated++;
            }
            $this->db->commit();
            return $updated;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * TAX: Get all tax rules
     */
    public function getTaxRules() {
        return $this->fetchAll("SELECT * FROM tax_rules ORDER BY id ASC");
    }

    /**
     * Save application setting
     */
    public function saveSetting($key, $value) {
        $stmt = $this->db->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }

    /**
     * Initialize default application settings
     */
    public function initializeSettings() {
        $defaults = [
            'vat_rate' => '0.18',
            'currency' => 'LKR ',
            'company_name' => 'Active Solutions',
            'session_timeout' => '3600',
            'qb_sync_interval' => '60',
            'qb_require_running' => '1',
            'qb_include_serials' => '1',
            'qb_batch_size' => '500',
            'qb_api_key' => 'act_live_sync_key_2026'
        ];

        foreach ($defaults as $key => $value) {
            $existing = $this->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            if (!$existing) {
                $this->execute("
                    INSERT INTO settings (setting_key, setting_value)
                    VALUES (?, ?)
                ", [$key, $value]);
            }
        }
    }

    /**
     * TAX: Delete tax rule
     */
    public function deleteTaxRule($id) {
        return $this->execute("DELETE FROM tax_rules WHERE id = ?", [$id]);
    }

    /**
     * PROFIT: Update gross profit for a line item
     */
    public function updateGrossProfit($id, $gp) {
        return $this->execute(
            "UPDATE sales SET gross_profit = ? WHERE id = ?",
            [$gp, $id]
        );
    }

    /**
     * PROFIT: Get sales with profit data for entry
     */
    public function getSalesForProfitEntry($year, $month) {
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        return $this->fetchAll("
            SELECT * FROM sales 
            WHERE strftime('%Y', invoice_date) = ? 
            AND strftime('%m', invoice_date) = ?
            ORDER BY invoice_date DESC, invoice_number DESC
        ", [$year, $monthStr]);
    }

    /**
     * Log user activity
     */
    public function logActivity($userId, $action, $description = '', $ipAddress = '') {
        try {
            if (empty($ipAddress)) {
                $ipAddress = $this->getClientIP();
            }

            $stmt = $this->db->prepare("
                INSERT INTO activity_log (user_id, action, description, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $description, $ipAddress]);
        } catch (Exception $e) {
            // Log errors silently - activity logging shouldn't break main functionality
            error_log('Activity log error: ' . $e->getMessage());
        }
    }

    /**
     * Get client IP address (security)
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Ensure all columns exist in existing tables (Migration)
     */
    public function syncSchema() {
        $results = ['success' => true, 'messages' => []];
        try {
            $this->createTablesIfNotExists();
            $this->seedDefaultTaxRules();
            // Check for columns in sales table
            $cols = $this->db->query("PRAGMA table_info(sales)")->fetchAll();
            $colNames = array_column($cols, 'name');

            $needed = [
                'gross_profit' => "ALTER TABLE sales ADD COLUMN gross_profit DECIMAL(12,2) DEFAULT 0",
                'applied_tax_rate' => "ALTER TABLE sales ADD COLUMN applied_tax_rate DECIMAL(5,4)",
                'vat_treatment' => "ALTER TABLE sales ADD COLUMN vat_treatment TEXT DEFAULT 'VAT_INCLUSIVE'",
                'product_category' => "ALTER TABLE sales ADD COLUMN product_category TEXT",
                'sales_rep_code' => "ALTER TABLE sales ADD COLUMN sales_rep_code TEXT",
                'paid_date' => "ALTER TABLE sales ADD COLUMN paid_date DATE",
                'days_to_pay' => "ALTER TABLE sales ADD COLUMN days_to_pay INTEGER",
                'po_number' => "ALTER TABLE sales ADD COLUMN po_number TEXT",
                'memo' => "ALTER TABLE sales ADD COLUMN memo TEXT",
                'qb_txn_id' => "ALTER TABLE sales ADD COLUMN qb_txn_id TEXT",
                'subtotal' => "ALTER TABLE sales ADD COLUMN subtotal REAL DEFAULT 0",
                'sales_tax_total' => "ALTER TABLE sales ADD COLUMN sales_tax_total REAL DEFAULT 0",
                'sales_tax_rate' => "ALTER TABLE sales ADD COLUMN sales_tax_rate REAL DEFAULT 0",
                'sales_tax_item' => "ALTER TABLE sales ADD COLUMN sales_tax_item TEXT",
                'customer_tax_code' => "ALTER TABLE sales ADD COLUMN customer_tax_code TEXT",
                'applied_amount' => "ALTER TABLE sales ADD COLUMN applied_amount REAL DEFAULT 0",
                'balance_remaining' => "ALTER TABLE sales ADD COLUMN balance_remaining REAL DEFAULT 0",
                'is_paid' => "ALTER TABLE sales ADD COLUMN is_paid INTEGER DEFAULT 0",
                'is_pending' => "ALTER TABLE sales ADD COLUMN is_pending INTEGER DEFAULT 0",
                'due_date' => "ALTER TABLE sales ADD COLUMN due_date DATE",
                'ship_date' => "ALTER TABLE sales ADD COLUMN ship_date DATE",
                'terms' => "ALTER TABLE sales ADD COLUMN terms TEXT",
                'unit_price' => "ALTER TABLE sales ADD COLUMN unit_price REAL DEFAULT 0"
            ];

            foreach ($needed as $col => $sql) {
                if (!in_array($col, $colNames)) {
                    $this->db->exec($sql);
                    $results['messages'][] = "Added column '$col' to sales table.";
                }
            }

            // Check for columns in payments table
            $payCols = $this->db->query("PRAGMA table_info(payments)")->fetchAll();
            $payColNames = array_column($payCols, 'name');
            $payNeeded = [
                'payment_method' => "ALTER TABLE payments ADD COLUMN payment_method TEXT",
                'deposit_account' => "ALTER TABLE payments ADD COLUMN deposit_account TEXT",
                'memo' => "ALTER TABLE payments ADD COLUMN memo TEXT",
                'unused_payment' => "ALTER TABLE payments ADD COLUMN unused_payment REAL DEFAULT 0"
            ];
            foreach ($payNeeded as $col => $sql) {
                if (!in_array($col, $payColNames)) {
                    $this->db->exec($sql);
                    $results['messages'][] = "Added column '$col' to payments table.";
                }
            }

            // Check for columns in tax_rules table
            $taxCols = $this->db->query("PRAGMA table_info(tax_rules)")->fetchAll();
            $taxColNames = array_column($taxCols, 'name');
            $taxNeeded = [
                'effective_to' => "ALTER TABLE tax_rules ADD COLUMN effective_to DATE",
                'invoice_range_start' => "ALTER TABLE tax_rules ADD COLUMN invoice_range_start TEXT",
                'invoice_range_end' => "ALTER TABLE tax_rules ADD COLUMN invoice_range_end TEXT",
                'is_inclusive_default' => "ALTER TABLE tax_rules ADD COLUMN is_inclusive_default INTEGER DEFAULT 1",
                'notes' => "ALTER TABLE tax_rules ADD COLUMN notes TEXT"
            ];
            foreach ($taxNeeded as $col => $sql) {
                if (!in_array($col, $taxColNames)) {
                    $this->db->exec($sql);
                    $results['messages'][] = "Added column '$col' to tax_rules table.";
                }
            }

            // Check for columns in invoice_items table
            try {
                $itemCols = $this->db->query("PRAGMA table_info(invoice_items)")->fetchAll();
                $itemColNames = array_column($itemCols, 'name');
                if (!in_array('vat_treatment', $itemColNames)) {
                    $this->db->exec("ALTER TABLE invoice_items ADD COLUMN vat_treatment TEXT DEFAULT 'VAT_INCLUSIVE'");
                }
            } catch (Exception $e) {
                // Table created in createTablesIfNotExists
            }

            // Check and update customer_profiles columns
            $this->ensureCustomerProfileColumns();

            // Check and update product_mappings & rental columns
            $this->ensureProductMappingColumns();

            // Performance indexes
            $this->db->exec("
                CREATE INDEX IF NOT EXISTS idx_sales_invoice_date ON sales(invoice_date);
                CREATE INDEX IF NOT EXISTS idx_sales_invoice_num ON sales(invoice_number);
                CREATE INDEX IF NOT EXISTS idx_sales_customer_name ON sales(customer_name);
                CREATE INDEX IF NOT EXISTS idx_sales_tax_code ON sales(tax_code);
                CREATE INDEX IF NOT EXISTS idx_sales_product_category ON sales(product_category);
                CREATE INDEX IF NOT EXISTS idx_sales_rep_code ON sales(sales_rep_code);
                CREATE INDEX IF NOT EXISTS idx_payments_invoice_num ON payments(invoice_num);
                CREATE INDEX IF NOT EXISTS idx_payments_customer_name ON payments(customer_name);
                CREATE INDEX IF NOT EXISTS idx_payments_payment_date ON payments(payment_date);
            ");
            
            // Check for customer_profiles table
            $this->createTablesIfNotExists();
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }
        return $results;
    }

    /**
     * Update setting
     */
    public function setSetting($key, $value) {
        try {
            $existing = $this->fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);

            if ($existing) {
                $this->execute("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?", [$value, $key]);
            } else {
                $this->execute("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            }

            return true;
        } catch (Exception $e) {
            throw new Exception('Error updating setting: ' . $e->getMessage());
        }
    }

    /**
     * Get database size in MB
     */
    public function getDatabaseSize() {
        try {
            $result = $this->fetch("SELECT page_count * page_size as size FROM pragma_page_count(), pragma_page_size()");
            return $result ? round($result['size'] / 1024 / 1024, 2) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get error message
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Close database connection
     */
    public function __destruct() {
        $this->db = null;
    }
    /**
     * Sales Rep Mapping Methods
     */
    public function getSalesReps() {
        try {
            return $this->fetchAll("SELECT * FROM sales_rep_mapping ORDER BY rep_name ASC");
        } catch (Exception $e) {
            // Self-healing: if table is missing, try to sync schema and retry
            if (strpos($e->getMessage(), 'no such table') !== false) {
                $this->syncSchema();
                return $this->fetchAll("SELECT * FROM sales_rep_mapping ORDER BY rep_name ASC");
            }
            throw $e;
        }
    }

    public function addSalesRep($code, $name) {
        return $this->execute(
            "INSERT OR REPLACE INTO sales_rep_mapping (rep_code, rep_name, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)",
            [$code, $name]
        );
    }

    public function deleteSalesRep($code) {
        return $this->execute("DELETE FROM sales_rep_mapping WHERE rep_code = ?", [$code]);
    }

    /**
     * RESET: Clear all payment and settlement data
     * Affects: 
     * - payments table: All records deleted
     * - sales table: paid_date and days_to_pay reset to NULL
     * - import_logs: ledger/payment related logs cleared
     */
    public function resetPaymentData() {
        try {
            $this->beginTransaction();
            
            // 1. Clear payments table
            $this->execute("DELETE FROM payments");
            
            // 2. Reset settlement columns in sales
            $this->execute("UPDATE sales SET paid_date = NULL, days_to_pay = NULL");
            
            // 3. Clear logs related to ledger imports
            $this->execute("DELETE FROM import_logs WHERE filename LIKE '%payment%' OR filename LIKE '%ledger%'");
            
            $this->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->rollBack();
            throw new Exception('Reset Error: ' . $e->getMessage());
        }
    }
    /**
     * Rationalization: Get items missing categories
     */
    public function getUncategorizedItems() {
        return $this->fetchAll("
            SELECT DISTINCT s.item_description, COUNT(*) as occurrence_count
            FROM sales s
            LEFT JOIN product_mappings pm ON s.item_description = pm.item_description
            WHERE (s.product_category IS NULL OR s.product_category = '' OR s.product_category = s.item_description)
              AND pm.item_description IS NULL
            GROUP BY s.item_description
            ORDER BY occurrence_count DESC
            LIMIT 50
        ");
    }

    public function saveProductMapping($item, $category) {
        $category = trim($category);
        if (empty($category)) return false;

        // 1. Save rule
        $this->execute("INSERT OR REPLACE INTO product_mappings (item_description, product_category) VALUES (?, ?)", [$item, $category]);

        // 2. Propagate to ALL historical records
        return $this->execute("
            UPDATE sales 
            SET product_category = ? 
            WHERE item_description = ?
        ", [$category, $item]);
    }

    public function getAllMappings() {
        return $this->fetchAll("SELECT * FROM product_mappings ORDER BY item_description ASC");
    }

    public function deleteProductMapping($id) {
        return $this->execute("DELETE FROM product_mappings WHERE id = ?", [$id]);
    }
}
?>
