<?php
/**
 * Sales BI Application - Configuration File
 */

// Detect cPanel environment
$is_cpanel = (strpos(__DIR__, 'public_html') !== false);

if ($is_cpanel) {
    // Store in a hidden folder inside the project to bypass open_basedir restrictions
    // while maintaining security via .htaccess block
    $storage_root = __DIR__ . '/.act_private';
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

// Error Display (set to true temporarily to debug 403/500 if they persist)
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

// Ensure storage directory exists
if (!is_dir($storage_root)) {
    mkdir($storage_root, 0755, true);
}

// Ensure subdirectories exist
$subdirs = [DATA_DIR, LOG_DIR, BACKUP_DIR, SESSION_DIR, TMP_DIR];
foreach ($subdirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Secure session
if ($is_cpanel) {
    session_save_path(SESSION_DIR);
}
?>
