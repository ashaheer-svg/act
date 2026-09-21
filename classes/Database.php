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

            // Master Brands Registry
            $this->execute("
                CREATE TABLE IF NOT EXISTS master_brands (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    code TEXT,
                    color TEXT DEFAULT '#2563eb',
                    description TEXT,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Master Categories Registry
            $this->execute("
                CREATE TABLE IF NOT EXISTS master_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    code TEXT,
                    color TEXT DEFAULT '#059669',
                    description TEXT,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
                    end_customer TEXT,
                    invoice_date DATE NOT NULL,
                    product_type TEXT NOT NULL, -- 'HARDWARE', 'SOFTWARE_LICENSE', 'SAAS_SUBSCRIPTION', 'SERVICE_AMC', 'ACCESSORY_OTHER'
                    clean_product_name TEXT NOT NULL,
                    brand_category TEXT,
                    brand TEXT,
                    category TEXT,
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
                    end_customer TEXT,
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
                    end_customer TEXT,
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

            // Contract Periods View (SLA & Maintenance Pipeline)
            $this->execute("
                CREATE VIEW IF NOT EXISTS contract_periods AS
                SELECT 
                    ss.id,
                    ss.software_name as contract_reference,
                    ss.customer_name,
                    ss.invoice_number,
                    COALESCE(ss.edition_tier, 'Annual Maintenance') as service_type,
                    COALESCE(ss.period_start_date, date(ii.invoice_date)) as start_date,
                    COALESCE(ss.period_end_date, date(ii.invoice_date, '+1 year')) as end_date,
                    COALESCE(ss.renewal_opportunity_value, ii.total_amount, 0) as contract_value,
                    'Service / Software Agreement' as notes
                FROM software_subscriptions ss
                LEFT JOIN invoice_items ii ON ss.invoice_item_id = ii.id
                UNION ALL
                SELECT 
                    ii.id + 1000000 as id,
                    ii.clean_product_name as contract_reference,
                    ii.customer_name,
                    ii.invoice_number,
                    'AMC / Support Contract' as service_type,
                    date(ii.invoice_date) as start_date,
                    date(ii.invoice_date, '+1 year') as end_date,
                    ii.total_amount as contract_value,
                    'Annual Service Maintenance' as notes
                FROM invoice_items ii
                WHERE ii.product_type = 'SERVICE_AMC'
                  AND NOT EXISTS (SELECT 1 FROM software_subscriptions ss2 WHERE ss2.invoice_item_id = ii.id)
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
                'category' => "ALTER TABLE product_mappings ADD COLUMN category TEXT",
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

            // Also ensure invoice_items has brand and category columns
            $iiCols = $this->db->query("PRAGMA table_info(invoice_items)")->fetchAll();
            $iiColNames = array_column($iiCols, 'name');
            if (!in_array('brand', $iiColNames)) {
                $this->db->exec("ALTER TABLE invoice_items ADD COLUMN brand TEXT");
            }
            if (!in_array('category', $iiColNames)) {
                $this->db->exec("ALTER TABLE invoice_items ADD COLUMN category TEXT");
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
                
                if (preg_match('/(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{3,4})?|[0-9A-Z\-\/]{7,})/i', $text, $m)) {
                    $vatNum = trim($m[1]);
                    $isVat = 1;
                } elseif (preg_match('/\b([0-9]{9}-7000?)\b/', $text, $m)) {
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
            // If the rule specifies date bounds, ensure the invoice date falls within them
            if (!empty($date)) {
                if (!empty($r['effective_from']) && $date < $r['effective_from']) continue;
                if (!empty($r['effective_to']) && $date > $r['effective_to']) continue;
            }
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
                    ['Legacy 12% VAT (2009-2013)', 0.12, '2009-01-01', '2013-12-31', 'AS000001', 'AS004000', 1, 'Historical statutory 12% VAT (Old Seq AS000001-AS004000)'],
                    ['Legacy 12% VAT', 0.12, null, null, 'AS004001', 'AS005147', 1, 'Historical statutory 12% VAT'],
                    ['Legacy 0% Exempt', 0.00, null, null, 'AS005148', 'AS006560', 0, 'Historical VAT exempt period'],
                    ['Legacy 15% VAT', 0.15, null, null, 'AS006561', 'AS008154', 1, 'Historical statutory 15% VAT'],
                    ['Legacy 8% VAT', 0.08, null, null, 'AS008155', 'AS008211', 1, 'Historical statutory 8% VAT'],
                    ['Exempt 0% VAT', 0.00, null, null, 'AS008212', 'AS010020', 0, 'VAT exempt era (2021-2023)'],
                    ['18% Statutory VAT', 0.18, '2024-01-01', '2026-06-30', 'AS010021', 'AS011260', 1, '18% VAT Regime (Old Seq)'],
                    ['New Seq ASN 18% VAT', 0.18, '2026-07-01', null, 'ASN000001', 'ASN999999', 1, '18% VAT Regime (New Seq ASN)'],
                    ['Historical Date 12% VAT', 0.12, '2009-01-01', '2014-12-31', null, null, 1, 'Statutory 12% VAT date fallback (2009-2014)'],
                    ['Historical Date 0% VAT', 0.00, '2015-01-01', '2016-10-31', null, null, 0, 'Statutory exempt date fallback (2015-2016)'],
                    ['Historical Date 15% VAT', 0.15, '2016-11-01', '2019-11-30', null, null, 1, 'Statutory 15% VAT date fallback (2016-2019)'],
                    ['Historical Date 8% VAT', 0.08, '2019-12-01', '2022-05-31', null, null, 1, 'Statutory 8% VAT date fallback (2019-2022)'],
                    ['Historical Date 12% VAT', 0.12, '2022-06-01', '2022-08-31', null, null, 1, 'Statutory 12% VAT date fallback (mid-2022)'],
                    ['Historical Date 15% VAT', 0.15, '2022-09-01', '2023-12-31', null, null, 1, 'Statutory 15% VAT date fallback (late 2022-2023)'],
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
     * Converts VAT-inclusive invoices into +VAT invoices for system purposes with extracted VAT component
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

        // 2. Fetch all sales lines with tax footer metadata
        try {
            $this->db->exec("ALTER TABLE sales ADD COLUMN manual_vat_override INTEGER DEFAULT 0");
        } catch (Exception $e) {
            // Column already exists
        }
        $sales = $this->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, tax_code, qb_amount, total_amount, item_description, subtotal, sales_tax_total, sales_tax_rate, sales_tax_item, vat_treatment, COALESCE(manual_vat_override, 0) as manual_vat_override FROM sales");
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
                $rawAmt = floatval(($row['qb_amount'] ?? 0) != 0 ? $row['qb_amount'] : $row['total_amount']);
                $invNum = trim($row['invoice_number']);
                $desc = trim($row['item_description'] ?? '');
                $custName = trim($row['customer_name'] ?? '');
                $taxCode = trim($row['tax_code'] ?? 'Taxable Sales');

                $salesTaxTotal = floatval($row['sales_tax_total'] ?? 0);
                $salesTaxRate = floatval($row['sales_tax_rate'] ?? 0);
                $salesTaxItem = trim($row['sales_tax_item'] ?? '');
                $currentTreatment = trim($row['vat_treatment'] ?? '');

                $rule = $this->getTaxRuleForInvoice($invNum, $date);
                $rate = $rule['rate'];

                if ($rawAmt == 0) {
                    // Informational, warranty, or empty line item
                    $appliedRate = 0.00;
                    $base = 0.00;
                    $vat = 0.00;
                    $total = 0.00;
                    $treatment = 'VAT_EXEMPT';
                } elseif ($salesTaxTotal > 0 && strcasecmp($salesTaxItem, 'VAT') === 0) {
                    // Modern Era (2024-2026): VAT is specified in invoice footer (+18% added on pre-tax subtotal)
                    $effRate = ($salesTaxRate > 0) ? ($salesTaxRate / 100) : $rate;
                    $appliedRate = $effRate;
                    $base = $rawAmt;
                    $vat = round($rawAmt * $effRate, 2);
                    $total = round($base + $vat, 2);
                    $treatment = 'PLUS_VAT';
                } elseif ($date >= '2024-01-01') {
                    // Modern Era (2024-2026): Any invoice without an explicit +VAT footer is statutory VAT-INCLUSIVE
                    $appliedRate = ($rate > 0) ? $rate : 0.18;
                    $total = $rawAmt;
                    $base = round($rawAmt / (1 + $appliedRate), 2);
                    $vat = round($total - $base, 2);
                    $treatment = 'VAT_INCLUSIVE';
                } elseif ($rate <= 0) {
                    // Statutory 0% VAT exempt period (e.g. 2015-2016, 2021-2023)
                    $appliedRate = 0.00;
                    $base = $rawAmt;
                    $vat = 0.00;
                    $total = $rawAmt;
                    $treatment = 'VAT_EXEMPT';
                } elseif ($salesTaxItem === 'Non' || ($salesTaxRate <= 0 && !empty($salesTaxItem))) {
                    // Historical non-taxable / export invoice in QuickBooks prior to 2024
                    $appliedRate = 0.00;
                    $base = $rawAmt;
                    $vat = 0.00;
                    $total = $rawAmt;
                    $treatment = 'VAT_EXEMPT';
                } else {
                    $hasSepVatLine = isset($vatLineInvoices[$invNum]);
                    $isVatLineItself = (bool)preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $desc);

                    if ($hasSepVatLine) {
                        $treatment = 'PLUS_VAT';
                        $appliedRate = $rate;
                        if ($isVatLineItself) {
                            $base = 0.00;
                            $vat = $rawAmt;
                            $total = $rawAmt;
                        } else {
                            $base = $rawAmt;
                            $vat = 0.00;
                            $total = $rawAmt;
                        }
                    } elseif (!empty($row['manual_vat_override']) && $row['manual_vat_override'] == 1 && $currentTreatment === 'PLUS_VAT') {
                        // Preserved user manual override to PLUS_VAT
                        $treatment = 'PLUS_VAT';
                        $appliedRate = $rate;
                        $base = $rawAmt;
                        $vat = round($rawAmt * $rate, 2);
                        $total = round($base + $vat, 2);
                    } else {
                        // VAT-INCLUSIVE INVOICE:
                        // Under statutory rule, any invoice without an explicit VAT breakdown shown in a taxable period is VAT-inclusive.
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

            // 3. Synchronize invoice_items with matching invoice VAT treatment
            $treatmentRows = $this->fetchAll("SELECT DISTINCT invoice_number, vat_treatment FROM sales WHERE vat_treatment != 'VAT_EXEMPT'");
            $salesTreatmentMap = [];
            foreach ($treatmentRows as $tr) {
                $salesTreatmentMap[trim($tr['invoice_number'])] = $tr['vat_treatment'];
            }

            $stmtItem = $this->db->prepare("
                UPDATE invoice_items 
                SET base_value = ?, vat_component = ?, vat_treatment = ?
                WHERE id = ?
            ");
            $invItems = $this->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, total_amount, base_value, vat_component FROM invoice_items");
            foreach ($invItems as $item) {
                $invNum = trim($item['invoice_number']);
                $date = $item['invoice_date'];
                $total = floatval($item['total_amount']);

                $rule = $this->getTaxRuleForInvoice($invNum, $date);
                $rate = $rule['rate'];

                if ($total == 0 || $rate <= 0) {
                    $base = $total;
                    $vat = 0.00;
                    $treatment = 'VAT_EXEMPT';
                } else {
                    $invMode = $salesTreatmentMap[$invNum] ?? ($date >= '2024-01-01' ? 'VAT_INCLUSIVE' : 'PLUS_VAT');
                    $treatment = $invMode;
                    $base = round($total / (1 + $rate), 2);
                    $vat = round($total - $base, 2);
                }
                $stmtItem->execute([$base, $vat, $treatment, $item['id']]);
            }

            $this->db->commit();
            return $updated;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * TAX: Manually switch an invoice between VAT_INCLUSIVE, PLUS_VAT, and VAT_EXEMPT
     */
    public function switchInvoiceVatMode(string $invoiceNumber, string $targetMode): array {
        $invoiceNumber = trim($invoiceNumber);
        $targetMode = strtoupper(trim($targetMode));
        if (!in_array($targetMode, ['VAT_INCLUSIVE', 'PLUS_VAT', 'VAT_EXEMPT'])) {
            throw new InvalidArgumentException("Invalid VAT mode: $targetMode");
        }

        $lines = $this->fetchAll("SELECT * FROM sales WHERE invoice_number = ?", [$invoiceNumber]);
        if (empty($lines)) {
            throw new RuntimeException("Invoice '$invoiceNumber' not found.");
        }

        $date = $lines[0]['invoice_date'];
        $rule = $this->getTaxRuleForInvoice($invoiceNumber, $date);
        $rate = $rule['rate'];

        $this->db->beginTransaction();
        try {
            $stmtSales = $this->db->prepare("
                UPDATE sales 
                SET applied_tax_rate = ?, base_value = ?, vat_component = ?, total_amount = ?, vat_treatment = ?, manual_vat_override = 1
                WHERE id = ?
            ");

            $newTotalInvoice = 0.0;
            $newBaseInvoice = 0.0;
            $newVatInvoice = 0.0;

            foreach ($lines as $row) {
                $rawAmt = floatval(($row['qb_amount'] != 0) ? $row['qb_amount'] : $row['total_amount']);

                if ($rawAmt == 0) {
                    $appliedRate = 0.00; $base = 0.00; $vat = 0.00; $total = 0.00; $treatment = 'VAT_EXEMPT';
                } elseif ($targetMode === 'VAT_EXEMPT' || $rate <= 0) {
                    $appliedRate = 0.00; $base = $rawAmt; $vat = 0.00; $total = $rawAmt; $treatment = 'VAT_EXEMPT';
                } elseif ($targetMode === 'PLUS_VAT') {
                    $appliedRate = $rate;
                    $base = $rawAmt;
                    $vat = round($rawAmt * $rate, 2);
                    $total = round($base + $vat, 2);
                    $treatment = 'PLUS_VAT';
                } else { // VAT_INCLUSIVE
                    $appliedRate = $rate;
                    $total = $rawAmt;
                    $base = round($rawAmt / (1 + $rate), 2);
                    $vat = round($total - $base, 2);
                    $treatment = 'VAT_INCLUSIVE';
                }

                $newTotalInvoice += $total;
                $newBaseInvoice += $base;
                $newVatInvoice += $vat;
                $stmtSales->execute([$appliedRate, $base, $vat, $total, $treatment, $row['id']]);
            }

            // Recalculate invoice applied amount and balance remaining
            $pmtTotal = floatval($this->fetch("SELECT SUM(amount) as s FROM payments WHERE invoice_num = ?", [$invoiceNumber])['s'] ?? 0);
            $newBal = max(0.0, round($newTotalInvoice - $pmtTotal, 2));
            $isPaid = ($newBal <= 0.01 && $pmtTotal > 0) ? 1 : 0;
            $this->execute("UPDATE sales SET balance_remaining = ?, is_paid = ? WHERE invoice_number = ?", [$newBal, $isPaid, $invoiceNumber]);

            // Synchronize invoice_items
            $this->execute("UPDATE invoice_items SET base_value = ?, vat_component = ?, vat_treatment = ? WHERE invoice_number = ?", [
                $newBaseInvoice, $newVatInvoice, $targetMode, $invoiceNumber
            ]);

            $this->db->commit();
            return [
                'success' => true,
                'invoice_number' => $invoiceNumber,
                'new_mode' => $targetMode,
                'new_total' => $newTotalInvoice,
                'new_base' => $newBaseInvoice,
                'new_vat' => $newVatInvoice,
                'new_balance' => $newBal
            ];
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
     * PROFIT: Get sales with profit data for entry (Legacy raw-line method)
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
     * Ensure profit columns exist in invoice_items and sales
     */
    public function ensureProfitColumnsExist() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            // Check invoice_items
            $itemCols = array_column($this->db->query("PRAGMA table_info(invoice_items)")->fetchAll(), 'name');
            if (!empty($itemCols)) {
                if (!in_array('unit_cost', $itemCols)) {
                    $this->db->exec("ALTER TABLE invoice_items ADD COLUMN unit_cost DECIMAL(12,2)");
                }
                if (!in_array('gross_profit', $itemCols)) {
                    $this->db->exec("ALTER TABLE invoice_items ADD COLUMN gross_profit DECIMAL(12,2)");
                }
                if (!in_array('brand', $itemCols)) {
                    $this->db->exec("ALTER TABLE invoice_items ADD COLUMN brand TEXT");
                }
                if (!in_array('category', $itemCols)) {
                    $this->db->exec("ALTER TABLE invoice_items ADD COLUMN category TEXT");
                }
            }

            // Check sales
            $salesCols = array_column($this->db->query("PRAGMA table_info(sales)")->fetchAll(), 'name');
            if (!empty($salesCols)) {
                if (!in_array('unit_cost', $salesCols)) {
                    $this->db->exec("ALTER TABLE sales ADD COLUMN unit_cost DECIMAL(12,2)");
                }
                if (!in_array('gross_profit', $salesCols)) {
                    $this->db->exec("ALTER TABLE sales ADD COLUMN gross_profit DECIMAL(12,2) DEFAULT 0");
                }
            }
        } catch (Throwable $e) {
            // Suppress if already exists
        }
    }

    /**
     * PROFIT: Get invoices aggregated for profit entry
     * Returns one row per commercial invoice with net base revenue, current GP, cost, and margin
     */
    public function getInvoicesForProfitEntry($year, $month, array $filters = []) {
        $this->ensureProfitColumnsExist();
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $where = "strftime('%Y', s.invoice_date) = ? AND strftime('%m', s.invoice_date) = ? AND s.invoice_type = 'Invoice'";
        $params = [$year, $monthStr];

        if (!empty($filters['customer_type'])) {
            $where .= " AND p.customer_type = ?";
            $params[] = $filters['customer_type'];
        }

        if (!empty($filters['rep_code'])) {
            $where .= " AND s.sales_rep_code = ?";
            $params[] = $filters['rep_code'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $where .= " AND (s.invoice_number LIKE ? OR s.customer_name LIKE ?)";
            $params[] = $term;
            $params[] = $term;
        }

        $sql = "
            SELECT 
                s.invoice_number,
                MAX(s.invoice_date) as invoice_date,
                s.customer_name,
                COALESCE(p.customer_type, 'End Customer') as customer_type,
                s.sales_rep_code,
                COALESCE(m.rep_name, s.sales_rep_code) as rep_name,
                COUNT(s.id) as raw_lines_count,
                ROUND(SUM(s.base_value), 2) as base_value,
                ROUND(SUM(s.vat_component), 2) as vat_component,
                ROUND(SUM(s.total_amount), 2) as total_amount,
                (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number) as items_count,
                (SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(ii.brand, ''), NULLIF(ii.clean_product_name, ''))) 
                 FROM (SELECT * FROM invoice_items WHERE invoice_number = s.invoice_number LIMIT 2) ii) as brands_preview,
                COALESCE(
                    (SELECT SUM(ii.gross_profit) FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number),
                    SUM(s.gross_profit)
                ) as gross_profit,
                COALESCE(
                    (SELECT SUM(ii.unit_cost * ii.quantity) FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number),
                    SUM(s.unit_cost * s.quantity)
                ) as total_cost,
                (SELECT MAX(CASE WHEN ii.gross_profit IS NOT NULL AND ii.gross_profit != 0 THEN 1 ELSE 0 END) 
                 FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number) as has_item_gp,
                MAX(CASE WHEN s.gross_profit IS NOT NULL AND s.gross_profit != 0 THEN 1 ELSE 0 END) as has_sales_gp
            FROM sales s
            LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
            LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
            WHERE $where
            GROUP BY s.invoice_number
            ORDER BY s.invoice_date DESC, s.invoice_number DESC
        ";

        $rows = $this->fetchAll($sql, $params);

        $results = [];
        $statusFilter = $filters['status'] ?? 'all';

        foreach ($rows as $r) {
            $baseVal = (float)$r['base_value'];
            $hasGp = ($r['has_item_gp'] == 1 || $r['has_sales_gp'] == 1 || ($r['gross_profit'] !== null && $r['gross_profit'] != 0));
            
            $gp = $hasGp ? (float)$r['gross_profit'] : null;
            $cost = ($r['total_cost'] !== null && $r['total_cost'] != 0) 
                ? (float)$r['total_cost'] 
                : ($gp !== null ? ($baseVal - $gp) : null);

            $marginPct = ($baseVal > 0 && $gp !== null) ? round(($gp / $baseVal) * 100, 1) : 0.0;

            $r['is_entered'] = $hasGp ? 1 : 0;
            $r['calc_gp'] = $gp;
            $r['calc_cost'] = $cost;
            $r['margin_pct'] = $marginPct;

            if ($statusFilter === 'pending' && $hasGp) {
                continue;
            }
            if ($statusFilter === 'completed' && !$hasGp) {
                continue;
            }

            $results[] = $r;
        }

        return $results;
    }

    /**
     * PROFIT: Get high-level summary KPIs for selected year and month
     */
    public function getProfitEntrySummary($year, $month) {
        $invoices = $this->getInvoicesForProfitEntry($year, $month, ['status' => 'all']);

        $totalCount = count($invoices);
        $completedCount = 0;
        $pendingCount = 0;
        $totalBase = 0;
        $totalGp = 0;
        $totalCost = 0;

        foreach ($invoices as $inv) {
            $totalBase += (float)$inv['base_value'];
            if ($inv['is_entered']) {
                $completedCount++;
                $totalGp += (float)$inv['calc_gp'];
                $totalCost += (float)$inv['calc_cost'];
            } else {
                $pendingCount++;
            }
        }

        $overallMargin = ($totalBase > 0 && $totalGp > 0) ? round(($totalGp / $totalBase) * 100, 1) : 0.0;

        return [
            'total_invoices' => $totalCount,
            'completed_count' => $completedCount,
            'pending_count' => $pendingCount,
            'total_base_revenue' => $totalBase,
            'total_gross_profit' => $totalGp,
            'total_cost' => $totalCost,
            'overall_margin_pct' => $overallMargin
        ];
    }

    /**
     * PROFIT: Update gross profit and cost for an entire commercial invoice
     * Values are strictly EXCLUDING 18% VAT (Base Net basis).
     * Distributes pro-rata across normalized invoice_items and syncs with sales raw lines.
     */
    public function updateInvoiceGrossProfit($invoiceNumber, $gp, $totalCost = null) {
        $this->ensureProfitColumnsExist();
        $inv = trim($invoiceNumber);
        if (empty($inv)) return ['success' => false, 'error' => 'Invoice number required'];

        // 1. Fetch invoice header totals
        $header = $this->fetch("
            SELECT invoice_number, SUM(base_value) as base_value, SUM(total_amount) as total_amount
            FROM sales
            WHERE invoice_number = ?
            GROUP BY invoice_number
        ", [$inv]);

        if (!$header) return ['success' => false, 'error' => "Invoice $inv not found"];

        $baseVal = (float)$header['base_value'];

        // If both are null/empty, clear GP and Cost
        if (($gp === null || $gp === '') && ($totalCost === null || $totalCost === '')) {
            $this->execute("UPDATE invoice_items SET gross_profit = NULL, unit_cost = NULL WHERE invoice_number = ?", [$inv]);
            $this->execute("UPDATE sales SET gross_profit = 0, unit_cost = NULL WHERE invoice_number = ?", [$inv]);
            return [
                'success' => true,
                'invoice_number' => $inv,
                'base_value' => $baseVal,
                'gross_profit' => null,
                'total_cost' => null,
                'margin_pct' => 0.0
            ];
        }

        // If one is given and the other is null, compute the complement
        if ($gp === null && $totalCost !== null) {
            $gp = $baseVal - (float)$totalCost;
        } elseif ($gp !== null && $totalCost === null) {
            $totalCost = $baseVal - (float)$gp;
        }

        $gp = round((float)$gp, 2);
        $totalCost = round((float)$totalCost, 2);

        // 2. Fetch normalized invoice items
        $items = $this->fetchAll("
            SELECT id, quantity, base_value, raw_line_ids
            FROM invoice_items
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        $totalItemBase = array_sum(array_column($items, 'base_value'));
        $itemCount = count($items);

        if ($itemCount > 0) {
            $runningGp = 0;
            $runningCost = 0;

            for ($i = 0; $i < $itemCount; $i++) {
                $item = $items[$i];
                $itemQty = max(1, (float)($item['quantity'] ?? 1));
                $itemBase = (float)($item['base_value'] ?? 0);

                if ($i === $itemCount - 1) {
                    // Last item takes remaining to prevent 1-cent rounding drift
                    $itemGp = round($gp - $runningGp, 2);
                    $itemCost = round($totalCost - $runningCost, 2);
                } else {
                    $ratio = ($totalItemBase > 0) ? ($itemBase / $totalItemBase) : (1 / $itemCount);
                    $itemGp = round($gp * $ratio, 2);
                    $itemCost = round($totalCost * $ratio, 2);
                    $runningGp += $itemGp;
                    $runningCost += $itemCost;
                }

                $unitCost = round($itemCost / $itemQty, 2);

                $this->execute(
                    "UPDATE invoice_items SET gross_profit = ?, unit_cost = ? WHERE id = ?",
                    [$itemGp, $unitCost, $item['id']]
                );

                // If linked raw line IDs exist in sales table, update them
                if (!empty($item['raw_line_ids'])) {
                    $rawIds = array_filter(array_map('intval', explode(',', $item['raw_line_ids'])));
                    if (!empty($rawIds)) {
                        $rawCount = count($rawIds);
                        $rawGp = round($itemGp / $rawCount, 2);
                        $inPlaceholders = implode(',', array_fill(0, $rawCount, '?'));
                        $execParams = array_merge([$rawGp, $unitCost], $rawIds);
                        $this->execute("UPDATE sales SET gross_profit = ?, unit_cost = ? WHERE id IN ($inPlaceholders)", $execParams);
                    }
                }
            }
        }

        // 3. Also synchronize raw sales table directly for lines of this invoice
        $salesRows = $this->fetchAll("
            SELECT id, base_value, quantity
            FROM sales
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$inv]);

        $salesCount = count($salesRows);
        $totalSalesBase = array_sum(array_column($salesRows, 'base_value'));

        if ($salesCount > 0) {
            $runningSalesGp = 0;
            $runningSalesCost = 0;

            for ($s = 0; $s < $salesCount; $s++) {
                $sRow = $salesRows[$s];
                $sBase = (float)($sRow['base_value'] ?? 0);
                $sQty = max(1, (float)($sRow['quantity'] ?? 1));

                // If row has 0 base value (e.g. memo/header row), set GP = 0 and Cost = 0
                if ($totalSalesBase > 0 && $sBase <= 0) {
                    $this->execute("UPDATE sales SET gross_profit = 0, unit_cost = 0 WHERE id = ?", [$sRow['id']]);
                    continue;
                }

                if ($s === $salesCount - 1) {
                    $sGp = round($gp - $runningSalesGp, 2);
                    $sCost = round($totalCost - $runningSalesCost, 2);
                } else {
                    $ratio = ($totalSalesBase > 0) ? ($sBase / $totalSalesBase) : (1 / $salesCount);
                    $sGp = round($gp * $ratio, 2);
                    $sCost = round($totalCost * $ratio, 2);
                    $runningSalesGp += $sGp;
                    $runningSalesCost += $sCost;
                }

                $sUnitCost = round($sCost / $sQty, 2);
                $this->execute("UPDATE sales SET gross_profit = ?, unit_cost = ? WHERE id = ?", [$sGp, $sUnitCost, $sRow['id']]);
            }
        }

        return [
            'success' => true,
            'invoice_number' => $inv,
            'base_value' => $baseVal,
            'gross_profit' => $gp,
            'total_cost' => $totalCost,
            'margin_pct' => ($baseVal > 0) ? round(($gp / $baseVal) * 100, 1) : 0.0
        ];
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
                'unit_price' => "ALTER TABLE sales ADD COLUMN unit_price REAL DEFAULT 0",
                'end_customer' => "ALTER TABLE sales ADD COLUMN end_customer TEXT"
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
                if (!in_array('end_customer', $itemColNames)) {
                    $this->db->exec("ALTER TABLE invoice_items ADD COLUMN end_customer TEXT");
                }
            } catch (Exception $e) {
                // Table created in createTablesIfNotExists
            }

            // Check for columns in hardware_assets table
            try {
                $hwCols = $this->db->query("PRAGMA table_info(hardware_assets)")->fetchAll();
                $hwColNames = array_column($hwCols, 'name');
                if (!in_array('end_customer', $hwColNames)) {
                    $this->db->exec("ALTER TABLE hardware_assets ADD COLUMN end_customer TEXT");
                }
            } catch (Exception $e) {
                // Table created in createTablesIfNotExists
            }

            // Check for columns in software_subscriptions table
            try {
                $subCols = $this->db->query("PRAGMA table_info(software_subscriptions)")->fetchAll();
                $subColNames = array_column($subCols, 'name');
                if (!in_array('end_customer', $subColNames)) {
                    $this->db->exec("ALTER TABLE software_subscriptions ADD COLUMN end_customer TEXT");
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
                CREATE INDEX IF NOT EXISTS idx_sales_end_customer ON sales(end_customer);
                CREATE INDEX IF NOT EXISTS idx_sales_invoice_type ON sales(invoice_type);
                CREATE INDEX IF NOT EXISTS idx_payments_invoice_num ON payments(invoice_num);
                CREATE INDEX IF NOT EXISTS idx_payments_customer_name ON payments(customer_name);
                CREATE INDEX IF NOT EXISTS idx_payments_payment_date ON payments(payment_date);
                CREATE INDEX IF NOT EXISTS idx_hw_end_customer ON hardware_assets(end_customer);
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

    /**
     * Master Brands Management
     */
    public function getBrands($activeOnly = false) {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return $this->fetchAll("
            SELECT b.*, 
                   COUNT(ii.id) as assigned_product_count,
                   COALESCE(SUM(ii.total_amount), 0) as lifetime_revenue
            FROM master_brands b
            LEFT JOIN invoice_items ii ON ii.brand = b.name
            $where
            GROUP BY b.id
            ORDER BY b.name ASC
        ");
    }

    public function getBrandById($id) {
        return $this->fetch("SELECT * FROM master_brands WHERE id = ?", [$id]);
    }

    public function saveBrand($data) {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $name = trim($data['name'] ?? '');
        $code = strtoupper(trim($data['code'] ?? ''));
        $color = trim($data['color'] ?? '#2563eb');
        $description = trim($data['description'] ?? '');
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if (empty($name)) {
            throw new InvalidArgumentException('Brand name is required');
        }

        if ($id) {
            // Get old name for cascading update
            $old = $this->getBrandById($id);
            $this->execute("
                UPDATE master_brands 
                SET name = ?, code = ?, color = ?, description = ?, is_active = ?
                WHERE id = ?
            ", [$name, $code, $color, $description, $isActive, $id]);

            if ($old && $old['name'] !== $name) {
                // Cascade update to invoice_items and product_mappings
                $this->execute("UPDATE invoice_items SET brand = ? WHERE brand = ?", [$name, $old['name']]);
                $this->execute("UPDATE product_mappings SET brand = ? WHERE brand = ?", [$name, $old['name']]);
            }
            return $id;
        } else {
            $this->execute("
                INSERT INTO master_brands (name, code, color, description, is_active)
                VALUES (?, ?, ?, ?, ?)
            ", [$name, $code, $color, $description, $isActive]);
            return $this->getConnection()->lastInsertId();
        }
    }

    public function deleteBrand($id) {
        $brand = $this->getBrandById($id);
        if (!$brand) return false;
        // Unassign products tagged with this brand
        $this->execute("UPDATE invoice_items SET brand = 'Other' WHERE brand = ?", [$brand['name']]);
        return $this->execute("DELETE FROM master_brands WHERE id = ?", [$id]);
    }

    /**
     * Master Categories Management
     */
    public function getCategories($activeOnly = false) {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return $this->fetchAll("
            SELECT c.*, 
                   COUNT(ii.id) as assigned_product_count,
                   COALESCE(SUM(ii.total_amount), 0) as lifetime_revenue
            FROM master_categories c
            LEFT JOIN invoice_items ii ON ii.category = c.name
            $where
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
    }

    public function getCategoryById($id) {
        return $this->fetch("SELECT * FROM master_categories WHERE id = ?", [$id]);
    }

    public function saveCategory($data) {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $name = trim($data['name'] ?? '');
        $code = strtoupper(trim($data['code'] ?? ''));
        $color = trim($data['color'] ?? '#059669');
        $description = trim($data['description'] ?? '');
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if (empty($name)) {
            throw new InvalidArgumentException('Category name is required');
        }

        if ($id) {
            $old = $this->getCategoryById($id);
            $this->execute("
                UPDATE master_categories 
                SET name = ?, code = ?, color = ?, description = ?, is_active = ?
                WHERE id = ?
            ", [$name, $code, $color, $description, $isActive, $id]);

            if ($old && $old['name'] !== $name) {
                // Cascade update to invoice_items
                $this->execute("UPDATE invoice_items SET category = ? WHERE category = ?", [$name, $old['name']]);
            }
            return $id;
        } else {
            $this->execute("
                INSERT INTO master_categories (name, code, color, description, is_active)
                VALUES (?, ?, ?, ?, ?)
            ", [$name, $code, $color, $description, $isActive]);
            return $this->getConnection()->lastInsertId();
        }
    }

    public function deleteCategory($id) {
        $cat = $this->getCategoryById($id);
        if (!$cat) return false;
        // Unassign products tagged with this category
        $this->execute("UPDATE invoice_items SET category = 'Other / Unassigned' WHERE category = ?", [$cat['name']]);
        return $this->execute("DELETE FROM master_categories WHERE id = ?", [$id]);
    }

    /**
     * Update invoice header details (end_customer, sales_rep_code, po_number, vat_treatment, memo, paid_date)
     */
    public function updateInvoiceHeader($invoiceNumber, array $data) {
        $inv = trim($invoiceNumber);
        if (empty($inv)) return false;

        $fields = [];
        $params = [];

        if (array_key_exists('end_customer', $data)) {
            $fields[] = "end_customer = ?";
            $params[] = trim($data['end_customer']);
        }
        if (array_key_exists('sales_rep_code', $data)) {
            $fields[] = "sales_rep_code = ?";
            $params[] = trim($data['sales_rep_code']);
        }
        if (array_key_exists('po_number', $data)) {
            $fields[] = "po_number = ?";
            $params[] = trim($data['po_number']);
        }
        if (array_key_exists('memo', $data)) {
            $fields[] = "memo = ?";
            $params[] = trim($data['memo']);
        }
        if (array_key_exists('paid_date', $data)) {
            $fields[] = "paid_date = ?";
            $params[] = !empty($data['paid_date']) ? trim($data['paid_date']) : null;
        }

        if (!empty($fields)) {
            $params[] = $inv;
            $this->execute("UPDATE sales SET " . implode(', ', $fields) . " WHERE invoice_number = ?", $params);
        }

        // Also update end_customer on invoice_items and assets if provided
        if (array_key_exists('end_customer', $data)) {
            $endCust = trim($data['end_customer']);
            $this->execute("UPDATE invoice_items SET end_customer = ? WHERE invoice_number = ?", [$endCust, $inv]);
            $this->execute("UPDATE hardware_assets SET end_customer = ? WHERE invoice_number = ?", [$endCust, $inv]);
            $this->execute("UPDATE software_subscriptions SET end_customer = ? WHERE invoice_number = ?", [$endCust, $inv]);
        }

        // Handle VAT treatment changes & automatic recalculations
        if (!empty($data['vat_treatment'])) {
            $vatTreatment = trim($data['vat_treatment']);
            $this->execute("UPDATE sales SET vat_treatment = ? WHERE invoice_number = ?", [$vatTreatment, $inv]);
            $this->execute("UPDATE invoice_items SET vat_treatment = ? WHERE invoice_number = ?", [$vatTreatment, $inv]);

            if (!empty($data['recalc_vat'])) {
                if ($vatTreatment === 'VAT_EXEMPT' || $vatTreatment === 'NON_VAT') {
                    $this->execute("
                        UPDATE sales 
                        SET base_value = total_amount, vat_component = 0, applied_tax_rate = 0
                        WHERE invoice_number = ?
                    ", [$inv]);
                    $this->execute("
                        UPDATE invoice_items 
                        SET base_value = total_amount, vat_component = 0
                        WHERE invoice_number = ?
                    ", [$inv]);
                } elseif ($vatTreatment === 'PLUS_VAT' || $vatTreatment === 'VAT_INCLUSIVE') {
                    $this->execute("
                        UPDATE sales 
                        SET applied_tax_rate = 0.18,
                            base_value = ROUND(total_amount / 1.18, 2),
                            vat_component = ROUND(total_amount - (total_amount / 1.18), 2)
                        WHERE invoice_number = ?
                    ", [$inv]);
                    $this->execute("
                        UPDATE invoice_items 
                        SET base_value = ROUND(total_amount / 1.18, 2),
                            vat_component = ROUND(total_amount - (total_amount / 1.18), 2)
                        WHERE invoice_number = ?
                    ", [$inv]);
                }
            }
        }

        return true;
    }

    /**
     * Update invoice item details (clean_product_name, brand, category, product_type, unit_cost, gross_profit, vat_treatment, end_customer)
     */
    public function updateInvoiceItemDetails($itemId, array $data) {
        $id = (int)$itemId;
        if ($id <= 0) return false;

        $fields = [];
        $params = [];

        $allowed = ['clean_product_name', 'brand', 'category', 'product_type', 'unit_cost', 'gross_profit', 'vat_treatment', 'end_customer'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $this->execute("UPDATE invoice_items SET " . implode(', ', $fields) . " WHERE id = ?", $params);

        // Also if gross_profit is provided, update the corresponding raw line in sales if linked
        if (array_key_exists('gross_profit', $data)) {
            $item = $this->fetch("SELECT invoice_number, raw_line_ids FROM invoice_items WHERE id = ?", [$id]);
            if ($item && !empty($item['raw_line_ids'])) {
                $rawIds = array_filter(array_map('intval', explode(',', $item['raw_line_ids'])));
                if (!empty($rawIds)) {
                    $inPlaceholders = implode(',', array_fill(0, count($rawIds), '?'));
                    $gpPerLine = (float)$data['gross_profit'] / count($rawIds);
                    $unitCost = isset($data['unit_cost']) ? (float)$data['unit_cost'] : null;
                    $execParams = array_merge([$gpPerLine, $unitCost], $rawIds);
                    $this->execute("UPDATE sales SET gross_profit = ?, unit_cost = ? WHERE id IN ($inPlaceholders)", $execParams);
                }
            }
        }

        return true;
    }

    /**
     * Update hardware asset warranty details
     */
    public function updateHardwareAsset($assetId, array $data) {
        $id = (int)$assetId;
        if ($id <= 0) return false;

        $fields = [];
        $params = [];
        $allowed = ['serial_number', 'model_sku', 'brand', 'warranty_type', 'warranty_months', 'warranty_start_date', 'warranty_expiry_date', 'warranty_status', 'parent_serial_number', 'notes', 'end_customer'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $params[] = $id;
        return $this->execute("UPDATE hardware_assets SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    }

    /**
     * Add a hardware asset to an invoice
     */
    public function addHardwareAsset(array $data) {
        return $this->execute("
            INSERT INTO hardware_assets (
                invoice_number, customer_name, product_name, brand, model_sku,
                serial_number, warranty_type, warranty_months, warranty_start_date,
                warranty_expiry_date, warranty_status, parent_serial_number, notes, end_customer
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['invoice_number'] ?? '',
            $data['customer_name'] ?? '',
            $data['product_name'] ?? '',
            $data['brand'] ?? 'Synology',
            $data['model_sku'] ?? '',
            $data['serial_number'] ?? '',
            $data['warranty_type'] ?? 'Standard Hardware Warranty',
            !empty($data['warranty_months']) ? (int)$data['warranty_months'] : 36,
            $data['warranty_start_date'] ?? date('Y-m-d'),
            $data['warranty_expiry_date'] ?? date('Y-m-d', strtotime('+3 years')),
            $data['warranty_status'] ?? 'Active',
            $data['parent_serial_number'] ?? '',
            $data['notes'] ?? '',
            $data['end_customer'] ?? ''
        ]);
    }

    /**
     * Delete hardware asset
     */
    public function deleteHardwareAsset($assetId) {
        return $this->execute("DELETE FROM hardware_assets WHERE id = ?", [(int)$assetId]);
    }

    /**
     * Update software subscription / recurring contract details
     */
    public function updateSoftwareSubscription($subId, array $data) {
        $id = (int)$subId;
        if ($id <= 0) return false;

        $fields = [];
        $params = [];
        $allowed = ['software_name', 'edition_tier', 'license_seats', 'period_start_date', 'period_end_date', 'term_months', 'renewal_status', 'renewal_opportunity_value', 'end_customer'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $params[] = $id;
        return $this->execute("UPDATE software_subscriptions SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    }
}

