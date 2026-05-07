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
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die('Database Connection Failed: ' . $this->error);
        }
    }

    /**
     * Initialize database schema
     */
    public function initialize() {
        $this->createTablesIfNotExists();
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
                    total_amount DECIMAL(12,2) NOT NULL,
                    product_category TEXT,
                    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(invoice_number, customer_name, item_description, qb_amount)
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
            $this->db->beginTransaction();
            
            // Check if tables exist before deleting
            $tables = ['sales', 'import_logs', 'activity_log'];
            foreach ($tables as $table) {
                $this->db->exec("DELETE FROM $table");
            }
            
            // Reset sequences safely
            $this->db->exec("DELETE FROM sqlite_sequence WHERE name IN ('sales', 'import_logs', 'activity_log')");
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            // If the error is just a missing table, we can ignore it during reset
            if (strpos($e->getMessage(), 'no such table') !== false) {
                return true; 
            }
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
     * Initialize default settings
     */
    public function initializeSettings() {
        try {
            $defaults = [
                'vat_rate' => '0.18',
                'currency_symbol' => '$',
                'company_name' => 'My Company',
                'date_format' => 'Y-m-d',
                'backup_enabled' => '1'
            ];

            foreach ($defaults as $key => $value) {
                $existing = $this->fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if (!$existing) {
                    $this->execute("
                        INSERT INTO settings (setting_key, setting_value)
                        VALUES (?, ?)
                    ", [$key, $value]);
                }
            }
        } catch (Exception $e) {
            error_log('Settings initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Get setting value
     */
    public function getSetting($key, $default = '') {
        try {
            $result = $this->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
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
}
?>
