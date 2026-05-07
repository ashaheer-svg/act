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
?>
