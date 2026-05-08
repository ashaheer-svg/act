<?php
/**
 * Sales BI Application - Configuration File
 */

// All files stored within the web root under the act subdirectory
$storage_root = __DIR__;

// Database Configuration
define('DATA_DIR', $storage_root . '/data');
define('DATABASE_PATH', DATA_DIR . '/sales_bi.db');
define('BACKUP_DIR', $storage_root . '/backups');
define('LOG_DIR', $storage_root . '/logs');
define('SESSION_DIR', $storage_root . '/sessions');
define('TMP_DIR', $storage_root . '/tmp');

// Application Settings
define('APP_NAME', 'Sales BI Dashboard');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'UTC');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['xlsx', 'xls', 'csv']);

// Session Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_NAME', 'sales_bi_session');

// Business Settings
define('VAT_RATE', 0.18); // 18% VAT
define('CURRENCY', '$');

// Error Display (temporarily enabled for debugging)
define('DEBUG_MODE', true);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error handling
if (!DEBUG_MODE) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Ensure required directories exist
$dirs = [DATA_DIR, LOG_DIR, BACKUP_DIR, SESSION_DIR, TMP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// --- EMERGENCY REPAIR TOOL ---
if (isset($_GET['repair_db']) && $_GET['repair_db'] == '1') {
    try {
        $pdo = new PDO('sqlite:' . DATABASE_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $needed = [
            "ALTER TABLE sales ADD COLUMN gross_profit DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE sales ADD COLUMN applied_tax_rate DECIMAL(5,4)",
            "ALTER TABLE sales ADD COLUMN product_category TEXT",
            "CREATE TABLE IF NOT EXISTS tax_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tax_name TEXT DEFAULT 'VAT', tax_rate REAL NOT NULL, effective_from DATE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS customer_profiles (customer_name TEXT PRIMARY KEY, customer_type TEXT DEFAULT 'End Customer', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
        ];
        echo "<h1>Database Repair Log</h1><ul>";
        foreach ($needed as $sql) {
            try { $pdo->exec($sql); echo "<li style='color:green'>Success: $sql</li>"; }
            catch (Exception $e) { echo "<li style='color:orange'>Skipped: " . $e->getMessage() . "</li>"; }
        }
        echo "</ul><p><a href='index.php'>Return to App</a></p>";
        exit;
    } catch (Exception $e) { die("Repair failed: " . $e->getMessage()); }
}
?>
