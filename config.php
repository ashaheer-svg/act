<?php
/**
 * Sales BI Application - Configuration File
 *
 * Store all configuration settings here.
 * For cPanel: Update DATABASE_PATH to writable directory
 */

// Detect cPanel environment
$is_cpanel = (strpos(__DIR__, 'public_html') !== false);

if ($is_cpanel) {
    // We are in /home/user/public_html/act -> Storage in /home/user/act_storage
    $storage_root = dirname(__DIR__, 2) . '/act_storage';
} else {
    // Local development
    $storage_root = __DIR__;
}

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

// Secure session if on cPanel
if ($is_cpanel) {
    if (!is_dir(SESSION_DIR)) mkdir(SESSION_DIR, 0700, true);
    session_save_path(SESSION_DIR);
}
?>
