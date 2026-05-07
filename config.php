<?php
/**
 * Sales BI Application - Configuration File
 *
 * Store all configuration settings here.
 * For cPanel: Update DATABASE_PATH to writable directory
 */

// Database Configuration
define('DATABASE_PATH', __DIR__ . '/data/sales_bi.db');
define('DATA_DIR', __DIR__ . '/data');

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

// Error Display (set to false in production)
define('DEBUG_MODE', false);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error handling
if (!DEBUG_MODE) {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
?>
